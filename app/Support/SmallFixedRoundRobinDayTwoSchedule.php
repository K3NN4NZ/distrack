<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Controllers\Admin\TournamentController;
use App\Models\Pitch;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentRegistration;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Fixed Day 2 round-robin grid for tournaments below {@see TournamentController::MINIMUM_BRACKET_TEAM_COUNT}.
 *
 * Game numbers follow Day 1's Berger pairing queue: Day 1 persists pair indices 0–23; Day 2 continues with 24+
 * up to the full round-robin (typically games 25–36 for nine teams). Rounds and pitches derive from slot order.
 */
final class SmallFixedRoundRobinDayTwoSchedule
{
    public const MARKER_PREFIX = '[[small-day2:';

    public const SCHEDULE_DATE_LABEL = 'May 17, 2026';

    public const SCHEDULE_DATE_ISO = '2026-05-17';

    /**
     * First Day 2 round continues numbering from Day 1's twelve rounds.
     */
    public const FIRST_ROUND_NUMBER = 13;

    /**
     * Game numbers continue from Day 1's twenty-four games.
     */
    public const FIRST_GAME_NUMBER = 25;

    public const ROUND_COUNT = 6;

    public const SLOT_COUNT = self::ROUND_COUNT;

    public const GAME_COUNT = self::ROUND_COUNT * 2;

    /**
     * Berger indices for pair slots consumed by Day 1 ({@see SmallFixedRoundRobinDayOneSchedule::firstTwentyFourPairSlots}).
     */
    private const BERGER_DAY_ONE_PAIR_SLOTS = 24;

    /**
     * Remaining Berger pairs for Day 2, keyed by match_number, resolved against {@see SmallFixedRoundRobinDayOneSchedule::sortedRegistrations}.
     *
     * @return array<int, array{
     *     home: TournamentRegistration,
     *     away: TournamentRegistration,
     *     round: int,
     *     pitch_slot: int,
     *     home_label: string,
     *     away_label: string,
     * }>
     */
    public static function complementScheduleEntries(Tournament $tournament): array
    {
        $sorted = SmallFixedRoundRobinDayOneSchedule::sortedRegistrations($tournament)->values();
        $berger = SmallFixedRoundRobinDayOneSchedule::bergerOrderedPairs($sorted->count());

        $slotDescriptors = self::timeSlotDescriptors();
        $maxPitchPairs = count($slotDescriptors) * 2;

        $out = [];
        $gameNumber = self::FIRST_GAME_NUMBER;

        for ($idx = self::BERGER_DAY_ONE_PAIR_SLOTS; $idx < count($berger); $idx++) {
            [$ia, $ib] = $berger[$idx];
            $homeReg = $sorted->get($ia);
            $awayReg = $sorted->get($ib);

            if ($homeReg === null || $awayReg === null) {
                continue;
            }

            $offset = $gameNumber - self::FIRST_GAME_NUMBER;
            if ($offset >= $maxPitchPairs) {
                break;
            }

            $slotIndex = intdiv($offset, 2);
            $pitchSlot = ($offset % 2 === 0) ? 1 : 2;
            $round = self::FIRST_ROUND_NUMBER + $slotIndex;

            $out[$gameNumber] = [
                'home' => $homeReg,
                'away' => $awayReg,
                'round' => $round,
                'pitch_slot' => $pitchSlot,
                'home_label' => $homeReg->team?->name ?? (string) __('Registration :id', ['id' => $homeReg->id]),
                'away_label' => $awayReg->team?->name ?? (string) __('Registration :id', ['id' => $awayReg->id]),
            ];

            $gameNumber++;
        }

        return $out;
    }

    /**
     * Number of Day 2 time-slot rows (each row has Pitch 1 + Pitch 2 games).
     */
    public static function dayTwoRoundSlotCount(Tournament $tournament): int
    {
        $pairCount = count(self::complementScheduleEntries($tournament));

        return $pairCount === 0 ? 0 : (int) ceil($pairCount / 2);
    }

    /**
     * Highest round number used for Day 2 (complement plan or persisted matches).
     */
    public static function lastScheduledRoundNumber(Tournament $tournament): int
    {
        $entries = self::complementScheduleEntries($tournament);
        $max = self::FIRST_ROUND_NUMBER - 1;
        foreach ($entries as $row) {
            $max = max($max, $row['round']);
        }

        $tournament->loadMissing('matches');
        foreach ($tournament->matches ?? [] as $match) {
            if ($match->stage !== 'round_robin' || ! self::isTrackedMatch($match)) {
                continue;
            }
            $label = (string) ($match->round_label ?? '');
            if (preg_match('/(\d+)\s*$/', $label, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return max(self::FIRST_ROUND_NUMBER - 1, $max);
    }

    private static function isStructurallyLockedDayTwoMatch(TournamentMatch $match): bool
    {
        if (in_array($match->status, [TournamentMatch::STATUS_LIVE, TournamentMatch::STATUS_COMPLETED], true)) {
            return true;
        }

        if ($match->home_score !== null || $match->away_score !== null) {
            return true;
        }

        return $match->scoreLogs()->exists()
            || $match->playerStats()->exists()
            || $match->spiritScores()->exists();
    }

    private static function deletableDayTwoTrackedMatch(TournamentMatch $match): bool
    {
        return self::isTrackedMatch($match) && ! self::isStructurallyLockedDayTwoMatch($match);
    }

    /**
     * @param  list<array<string, mixed>>  $slotRows
     */
    private static function syncFromPostedSlotRows(
        Tournament $tournament,
        array $slotRows,
        $pitchById,
        Pitch $pitch1,
        Pitch $pitch2,
    ): void {
        $sortedRegs = SmallFixedRoundRobinDayOneSchedule::sortedRegistrations($tournament);
        $tz = SmallFixedRoundRobinDayOneSchedule::tournamentTimezone($tournament);
        $tournamentId = (int) $tournament->id;

        $postedNumbers = [];
        foreach ($slotRows as $row) {
            $postedNumbers[] = (int) ($row['match1_match_number'] ?? 0);
            $postedNumbers[] = (int) ($row['match2_match_number'] ?? 0);
        }

        $postedNumberSet = [];
        foreach ($postedNumbers as $n) {
            if ($n > 0) {
                $postedNumberSet[$n] = true;
            }
        }

        $tracked = TournamentMatch::query()
            ->where('tournament_id', $tournamentId)
            ->where('stage', 'round_robin')
            ->where('notes', 'like', '%'.self::MARKER_PREFIX.'%')
            ->get();

        foreach ($tracked as $match) {
            $n = (int) ($match->match_number ?? 0);
            if ($n === 0 || isset($postedNumberSet[$n])) {
                continue;
            }

            if (! self::deletableDayTwoTrackedMatch($match)) {
                throw new InvalidArgumentException(__('Cannot sync the Day 2 schedule because a game removed from the grid (game :num) already has scores or is in progress.', ['num' => $n]));
            }

            $match->forceDelete();
        }

        foreach ($slotRows as $slotIndex => $slotRow) {
            $rowUlid = (string) Str::ulid();
            $roundLabelNum = (int) ($slotRow['round'] ?? (self::FIRST_ROUND_NUMBER + $slotIndex));
            $match1Num = (int) ($slotRow['match1_match_number'] ?? 0);
            $match2Num = (int) ($slotRow['match2_match_number'] ?? 0);

            for ($pitchSlot = 1; $pitchSlot <= 2; $pitchSlot++) {
                $gameNumber = $pitchSlot === 1 ? $match1Num : $match2Num;

                $scheduledAt = CarbonImmutable::parse($slotRow['date'].' '.$slotRow['start_time'].':00', $tz);
                $scheduledEnd = CarbonImmutable::parse($slotRow['date'].' '.$slotRow['end_time'].':00', $tz);

                if (! $scheduledEnd->greaterThan($scheduledAt)) {
                    throw new InvalidArgumentException(__('Each slot end time must be after its start time (round :round).', ['round' => $roundLabelNum]));
                }

                $targetPitchId = (int) (
                    $pitchSlot === 1
                        ? ($slotRow['pitch1_pitch_id'] ?? 0)
                        : ($slotRow['pitch2_pitch_id'] ?? 0)
                );

                $resolvedPitch = $pitchById->get($targetPitchId) ?? ($pitchSlot === 1 ? $pitch1 : $pitch2);

                if (! $resolvedPitch instanceof Pitch) {
                    throw new InvalidArgumentException(__('Could not resolve a pitch for round :round.', ['round' => $roundLabelNum]));
                }

                $resolvedRegs = SmallFixedRoundRobinDayOneSchedule::resolveRegistrationsForGridGame(
                    $slotRow,
                    $pitchSlot === 1,
                    null,
                    $sortedRegs,
                    $tournamentId,
                );

                if ($resolvedRegs === null) {
                    throw new InvalidArgumentException(__('Each Day 2 row must include home and away teams for both games (round :round).', ['round' => $roundLabelNum]));
                }

                [$home, $away] = $resolvedRegs;

                $existing = TournamentMatch::query()
                    ->where('tournament_id', $tournamentId)
                    ->where('stage', 'round_robin')
                    ->where('match_number', $gameNumber)
                    ->where('notes', 'like', '%'.self::MARKER_PREFIX.'%')
                    ->first();

                $locked = $existing !== null && self::isStructurallyLockedDayTwoMatch($existing);

                $scheduledForRow = ($locked && $existing?->scheduled_at) ? $existing->scheduled_at : $scheduledAt;
                $scheduledEndForRow = ($locked && $existing?->scheduled_ends_at) ? $existing->scheduled_ends_at : $scheduledEnd;

                $initialStatus = $existing?->status ?? TournamentMatch::STATUS_SCHEDULED;

                if (! $locked) {
                    $initialStatus = SmallFixedRoundRobinDayOneSchedule::mapRowUiStatusToMatchStatus((string) ($slotRow['status'] ?? 'upcoming'));
                }

                $pitchForRow = $locked
                    ? (Pitch::query()->where('tournament_id', $tournamentId)->whereKey((int) $existing->pitch_id)->first() ?? $resolvedPitch)
                    : $resolvedPitch;

                $attributes = [
                    'tournament_id' => $tournamentId,
                    'pitch_id' => $pitchForRow->id,
                    'pitch_assigned_by' => $existing?->pitch_assigned_by,
                    'home_registration_id' => ($locked && $existing?->home_registration_id)
                        ? (int) $existing->home_registration_id
                        : $home->id,
                    'away_registration_id' => ($locked && $existing?->away_registration_id)
                        ? (int) $existing->away_registration_id
                        : $away->id,
                    'stage' => 'round_robin',
                    'round_label' => __('Round :round', ['round' => $roundLabelNum]),
                    'match_number' => $gameNumber,
                    'scheduled_at' => $scheduledForRow,
                    'scheduled_ends_at' => $scheduledEndForRow,
                    'status' => $initialStatus,
                    'home_score' => $existing?->home_score,
                    'away_score' => $existing?->away_score,
                    'notes' => self::marker($gameNumber, $pitchSlot),
                    'schedule_slot_ulid' => $existing?->schedule_slot_ulid ?? $rowUlid,
                ];

                if ($existing !== null) {
                    $existing->forceFill($attributes)->save();
                } else {
                    TournamentMatch::query()->create($attributes);
                }
            }
        }
    }

    /**
     * @return list<array{label: string, start: string, end: string}>
     */
    public static function timeSlotDescriptors(): array
    {
        return [
            ['label' => '07:00am – 07:40am', 'start' => '07:00', 'end' => '07:40'],
            ['label' => '07:45am – 08:25am', 'start' => '07:45', 'end' => '08:25'],
            ['label' => '08:30am – 09:10am', 'start' => '08:30', 'end' => '09:10'],
            ['label' => '09:15am – 09:55am', 'start' => '09:15', 'end' => '09:55'],
            ['label' => '10:00am – 10:40am', 'start' => '10:00', 'end' => '10:40'],
            ['label' => '10:45am – 11:25am', 'start' => '10:45', 'end' => '11:25'],
        ];
    }

    public static function marker(int $gameNumber, int $pitchSlot): string
    {
        return self::MARKER_PREFIX.'g='.$gameNumber.':p='.$pitchSlot.']]';
    }

    /**
     * Default slot rows for pre-sync Day 2 admin forms (length matches {@see self::dayTwoRoundSlotCount()}).
     *
     * @return list<array{
     *     round: int,
     *     date: string,
     *     start_time: string,
     *     end_time: string,
     *     pitch1_pitch_id: int|null,
     *     pitch2_pitch_id: int|null,
     *     match1_home_registration_id: int|null,
     *     match1_away_registration_id: int|null,
     *     match2_home_registration_id: int|null,
     *     match2_away_registration_id: int|null,
     *     status: string
     * }>
     */
    public static function defaultSlotFormRows(Tournament $tournament): array
    {
        $slotCount = self::dayTwoRoundSlotCount($tournament);
        $slots = self::timeSlotDescriptors();
        $tournament->loadMissing(['pitches', 'registrations.team']);
        $pitches = $tournament->pitches->sortBy(['sort_order', 'id'])->values();
        $pitch1Id = $pitches->get(0)?->id;
        $pitch2Id = ($pitches->get(1) ?? $pitches->get(0))?->id;
        $entries = self::complementScheduleEntries($tournament);
        $rows = [];

        for ($i = 0; $i < $slotCount; $i++) {
            $slot = $slots[$i] ?? ['start' => '07:00', 'end' => '07:40', 'label' => ''];
            $g1 = self::FIRST_GAME_NUMBER + ($i * 2);
            $g2 = $g1 + 1;
            $e1 = $entries[$g1] ?? null;
            $e2 = $entries[$g2] ?? null;

            $rows[] = [
                'round' => self::FIRST_ROUND_NUMBER + $i,
                'date' => self::SCHEDULE_DATE_ISO,
                'start_time' => $slot['start'],
                'end_time' => $slot['end'],
                'pitch1_pitch_id' => $pitch1Id,
                'pitch2_pitch_id' => $pitch2Id,
                'match1_match_number' => $g1,
                'match2_match_number' => $g2,
                'match1_home_registration_id' => $e1 !== null ? $e1['home']->id : null,
                'match1_away_registration_id' => $e1 !== null ? $e1['away']->id : null,
                'match2_home_registration_id' => $e2 !== null ? $e2['home']->id : null,
                'match2_away_registration_id' => $e2 !== null ? $e2['away']->id : null,
                'status' => 'upcoming',
            ];
        }

        return $rows;
    }

    public static function isTrackedMatch(TournamentMatch $match): bool
    {
        return str_contains((string) ($match->notes ?? ''), self::MARKER_PREFIX);
    }

    /**
     * Day 2 rows share Pitch 1 / Pitch 2 statuses, mirroring Day 1's combined-row UX.
     */
    public static function scoringRequiresCompletedScheduleMessage(TournamentMatch $match): string
    {
        if (self::isTrackedMatch($match)) {
            return __('Scoring is only available after this Round Robin row is marked Completed on the schedule.');
        }

        return __('Scoring is only available after this match is marked Completed on the tournament schedule.');
    }

    /**
     * Build the Day 2 grid (1 row per round) for display alongside Day 1.
     *
     * @return Collection<int, array{
     *     time_label: string,
     *     round: int,
     *     pitch1_game_no: int,
     *     pitch2_game_no: int,
     *     pitch1_matchup: string,
     *     pitch2_matchup: string,
     *     pitch1_match: TournamentMatch|null,
     *     pitch2_match: TournamentMatch|null,
     * }>
     */
    public static function buildGridRows(Tournament $tournament): Collection
    {
        $slots = self::timeSlotDescriptors();
        $entries = self::complementScheduleEntries($tournament);

        $tracked = ($tournament->matches ?? collect())
            ->filter(static fn (TournamentMatch $m): bool => $m->stage === 'round_robin' && self::isTrackedMatch($m));

        /** @var array<int, TournamentMatch> $byGame */
        $byGame = [];
        foreach ($tracked as $match) {
            $num = $match->match_number;
            if ($num !== null) {
                $byGame[(int) $num] = $match;
            }
        }

        $baseSlots = self::dayTwoRoundSlotCount($tournament);
        $maxGameFromDb = 0;
        foreach ($tracked as $match) {
            $n = (int) ($match->match_number ?? 0);
            if ($n >= self::FIRST_GAME_NUMBER) {
                $maxGameFromDb = max($maxGameFromDb, $n);
            }
        }
        $slotFromGames = $maxGameFromDb >= self::FIRST_GAME_NUMBER
            ? (int) ceil(($maxGameFromDb - self::FIRST_GAME_NUMBER + 1) / 2)
            : 0;
        $slotCount = max($baseSlots, $slotFromGames);

        $tournament->loadMissing(['pitches' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')]);
        $pitches = $tournament->pitches->values();
        $pitch1 = $pitches->first();
        $pitch2 = $pitches->skip(1)->first() ?? $pitch1;

        return collect(range(0, max(0, $slotCount - 1)))->map(function (int $slotIndex) use ($slots, $entries, $byGame, $pitch1, $pitch2): array {
            $descriptor = $slots[$slotIndex] ?? ['label' => '', 'start' => '07:00', 'end' => '07:40'];
            $round = self::FIRST_ROUND_NUMBER + $slotIndex;
            $g1 = self::FIRST_GAME_NUMBER + ($slotIndex * 2);
            $g2 = $g1 + 1;

            $m1 = $byGame[$g1] ?? null;
            $m2 = $byGame[$g2] ?? null;

            if ($pitch1 instanceof Pitch && $pitch2 instanceof Pitch) {
                [$left, $right] = SmallFixedRoundRobinDayOneSchedule::orderMatchesForFixedScheduleColumns($m1, $m2, $pitch1, $pitch2);
            } else {
                [$left, $right] = [$m1, $m2];
            }

            $leftNo = (int) ($left?->match_number ?? $g1);
            $rightNo = (int) ($right?->match_number ?? $g2);

            foreach ([$left, $right] as $rm) {
                $label = (string) ($rm?->round_label ?? '');
                if ($label !== '' && preg_match('/(\d+)\s*$/', $label, $m)) {
                    $round = (int) $m[1];
                    break;
                }
            }

            $entryFor = static function (int $gameNo) use ($entries): ?array {
                return $entries[$gameNo] ?? null;
            };

            $labelFromEntry = static function (?array $entry): string {
                if ($entry === null) {
                    return '—';
                }

                return $entry['home_label'].' vs '.$entry['away_label'];
            };

            $timeLabel = $descriptor['label'];

            if ($left?->scheduled_at) {
                $timeLabel = SmallFixedRoundRobinDayOneSchedule::formatSlotRange($left->scheduled_at, $left->scheduled_ends_at);
            } elseif ($right?->scheduled_at) {
                $timeLabel = SmallFixedRoundRobinDayOneSchedule::formatSlotRange($right->scheduled_at, $right->scheduled_ends_at);
            }

            return [
                'time_label' => $timeLabel,
                'round' => $round,
                'pitch1_game_no' => $leftNo,
                'pitch2_game_no' => $rightNo,
                'pitch1_matchup' => $labelFromEntry($entryFor($leftNo)),
                'pitch2_matchup' => $labelFromEntry($entryFor($rightNo)),
                'pitch1_match' => $left,
                'pitch2_match' => $right,
            ];
        });
    }

    /**
     * Persist Day 2 rows on the `matches` table using {@see updateOrCreate}-style upserts so the seeder
     * stays idempotent. Existing tracked rows for the same game number are updated in place instead of
     * being duplicated.
     *
     * @param  list<array{
     *     round: int,
     *     date: string,
     *     start_time: string,
     *     end_time: string,
     *     pitch1_pitch_id: int,
     *     pitch2_pitch_id: int,
     *     match1_home_registration_id?: int,
     *     match1_away_registration_id?: int,
     *     match2_home_registration_id?: int,
     *     match2_away_registration_id?: int,
     *     status?: string
     * }>|null  $slotRows
     *
     * @throws InvalidArgumentException
     */
    public static function sync(Tournament $tournament, ?array $slotRows = null): void
    {
        $registrationCount = $tournament->registrations()->count();

        if ($registrationCount >= TournamentController::MINIMUM_BRACKET_TEAM_COUNT) {
            throw new InvalidArgumentException(__('Fixed Day 2 schedule applies only to tournaments with fewer than :count registered teams.', ['count' => TournamentController::MINIMUM_BRACKET_TEAM_COUNT]));
        }

        $tournament->loadMissing(['registrations.team', 'pitches']);

        if ($tournament->registrations->isEmpty()) {
            throw new InvalidArgumentException(__('Register at least one team before syncing the Day 2 schedule.'));
        }

        DB::transaction(function () use ($tournament, $slotRows): void {
            if ($tournament->pitches()->count() === 0) {
                foreach (range(1, 2) as $order) {
                    Pitch::query()->create([
                        'tournament_id' => $tournament->id,
                        'name' => 'Pitch '.$order,
                        'sort_order' => $order,
                        'scorekeeper_user_id' => null,
                        'is_active' => true,
                    ]);
                }
            }

            $tournament->unsetRelation('pitches');
            $pitchesOrdered = $tournament->pitches()->orderBy('sort_order')->orderBy('id')->get();
            $pitchById = $pitchesOrdered->keyBy('id');
            $pitch1 = $pitchesOrdered->first();
            $pitch2 = $pitchesOrdered->skip(1)->first() ?? $pitch1;

            if (! $pitch1 instanceof Pitch) {
                throw new InvalidArgumentException(__('At least one tournament pitch is required for the Day 2 grid.'));
            }

            if ($slotRows !== null) {
                self::syncFromPostedSlotRows($tournament, $slotRows, $pitchById, $pitch1, $pitch2);

                return;
            }

            $tz = SmallFixedRoundRobinDayOneSchedule::tournamentTimezone($tournament);
            $slots = self::timeSlotDescriptors();

            $entries = self::complementScheduleEntries($tournament);

            $scheduledGameNumbers = array_keys($entries);

            if ($scheduledGameNumbers !== []) {
                TournamentMatch::withoutGlobalScopes()
                    ->where('tournament_id', $tournament->id)
                    ->where('stage', 'round_robin')
                    ->where('notes', 'like', '%'.self::MARKER_PREFIX.'%')
                    ->where('match_number', '>=', self::FIRST_GAME_NUMBER)
                    ->whereNotIn('match_number', $scheduledGameNumbers)
                    ->get()
                    ->each(static function (TournamentMatch $match): void {
                        $match->forceDelete();
                    });
            }

            $sortedRegs = SmallFixedRoundRobinDayOneSchedule::sortedRegistrations($tournament);

            $slotUlidByRound = [];

            foreach ($entries as $gameNumber => $pairing) {
                $slotIndex = $pairing['round'] - self::FIRST_ROUND_NUMBER;
                $descriptor = $slots[$slotIndex] ?? null;

                if ($descriptor === null) {
                    continue;
                }

                $defaultPitch = $pairing['pitch_slot'] === 1 ? $pitch1 : $pitch2;
                $slotRow = $slotRows !== null ? ($slotRows[$slotIndex] ?? null) : null;

                if ($slotRow !== null) {
                    $scheduledAt = CarbonImmutable::parse($slotRow['date'].' '.$slotRow['start_time'].':00', $tz);
                    $scheduledEnd = CarbonImmutable::parse($slotRow['date'].' '.$slotRow['end_time'].':00', $tz);

                    if (! $scheduledEnd->greaterThan($scheduledAt)) {
                        throw new InvalidArgumentException(__('Each slot end time must be after its start time (round :round).', ['round' => $pairing['round']]));
                    }

                    $targetPitchId = (int) (
                        $pairing['pitch_slot'] === 1
                            ? ($slotRow['pitch1_pitch_id'] ?? 0)
                            : ($slotRow['pitch2_pitch_id'] ?? 0)
                    );

                    $resolvedPitch = $pitchById->get($targetPitchId) ?? ($pairing['pitch_slot'] === 1 ? $pitch1 : $pitch2);

                    if (! $resolvedPitch instanceof Pitch) {
                        throw new InvalidArgumentException(__('Could not resolve a pitch for round :round.', ['round' => $pairing['round']]));
                    }
                } else {
                    $scheduledAt = CarbonImmutable::parse(self::SCHEDULE_DATE_ISO.' '.$descriptor['start'].':00', $tz);
                    $scheduledEnd = CarbonImmutable::parse(self::SCHEDULE_DATE_ISO.' '.$descriptor['end'].':00', $tz);
                    $resolvedPitch = $defaultPitch;
                }

                $offset = $gameNumber - self::FIRST_GAME_NUMBER;
                $isFirstInSlot = ($offset % 2 === 0);

                if ($slotRow !== null) {
                    $resolvedRegs = SmallFixedRoundRobinDayOneSchedule::resolveRegistrationsForGridGame(
                        $slotRow,
                        $isFirstInSlot,
                        null,
                        $sortedRegs,
                        (int) $tournament->id,
                    );

                    if ($resolvedRegs !== null) {
                        [$home, $away] = $resolvedRegs;
                    } else {
                        $home = $pairing['home'];
                        $away = $pairing['away'];
                    }
                } else {
                    $home = $pairing['home'];
                    $away = $pairing['away'];
                }

                $existing = TournamentMatch::query()
                    ->where('tournament_id', $tournament->id)
                    ->where('stage', 'round_robin')
                    ->where('match_number', $gameNumber)
                    ->where('notes', 'like', '%'.self::MARKER_PREFIX.'%')
                    ->first();

                $rk = (int) $pairing['round'];
                if (! array_key_exists($rk, $slotUlidByRound)) {
                    $slotUlidByRound[$rk] = (string) Str::ulid();
                }
                $rowPairUlid = $slotUlidByRound[$rk];

                $locked = $existing !== null && (
                    $existing->status !== TournamentMatch::STATUS_SCHEDULED
                    || $existing->home_score !== null
                    || $existing->away_score !== null
                );

                $scheduledForRow = ($locked && $existing?->scheduled_at) ? $existing->scheduled_at : $scheduledAt;
                $scheduledEndForRow = ($locked && $existing?->scheduled_ends_at) ? $existing->scheduled_ends_at : $scheduledEnd;

                $initialStatus = $existing?->status ?? TournamentMatch::STATUS_SCHEDULED;

                if ($slotRow !== null && ! $locked) {
                    $initialStatus = SmallFixedRoundRobinDayOneSchedule::mapRowUiStatusToMatchStatus((string) ($slotRow['status'] ?? 'upcoming'));
                }

                $pitchForRow = $locked
                    ? (Pitch::query()->where('tournament_id', $tournament->id)->whereKey((int) $existing->pitch_id)->first() ?? $defaultPitch)
                    : $resolvedPitch;

                $attributes = [
                    'tournament_id' => $tournament->id,
                    'pitch_id' => $pitchForRow->id,
                    'pitch_assigned_by' => $existing?->pitch_assigned_by,
                    'home_registration_id' => ($locked && $existing?->home_registration_id)
                        ? (int) $existing->home_registration_id
                        : $home->id,
                    'away_registration_id' => ($locked && $existing?->away_registration_id)
                        ? (int) $existing->away_registration_id
                        : $away->id,
                    'stage' => 'round_robin',
                    'round_label' => __('Round :round', ['round' => $pairing['round']]),
                    'match_number' => $gameNumber,
                    'scheduled_at' => $scheduledForRow,
                    'scheduled_ends_at' => $scheduledEndForRow,
                    'status' => $initialStatus,
                    'home_score' => $existing?->home_score,
                    'away_score' => $existing?->away_score,
                    'notes' => self::marker($gameNumber, $pairing['pitch_slot']),
                    'schedule_slot_ulid' => $existing?->schedule_slot_ulid ?? $rowPairUlid,
                ];

                if ($existing !== null) {
                    $existing->forceFill($attributes)->save();
                } else {
                    TournamentMatch::query()->create($attributes);
                }
            }
        });

        $tournament->unsetRelation('matches');
    }

    /**
     * @param  Collection<int, TournamentMatch>  $matches
     * @return Collection<int, Collection<int, TournamentMatch>>
     */
    public static function bucketSmallDayTwoTrackedMatches(Collection $matches): Collection
    {
        $out = collect();
        $usedIds = [];

        foreach ($matches->whereNotNull('schedule_slot_ulid')->groupBy('schedule_slot_ulid') as $bucket) {
            $sorted = $bucket->sortBy('match_number')->values();
            foreach ($sorted as $m) {
                $usedIds[(int) $m->id] = true;
            }
            if ($sorted->isNotEmpty()) {
                $out->push($sorted);
            }
        }

        $remaining = $matches
            ->filter(fn (TournamentMatch $m): bool => ! isset($usedIds[(int) $m->id]))
            ->sortBy(fn (TournamentMatch $m): array => [(int) ($m->match_number ?? 0), (int) $m->id])
            ->values();

        $i = 0;
        while ($i < $remaining->count()) {
            $m1 = $remaining->get($i);
            $m2 = $remaining->get($i + 1);

            if ($m1 instanceof TournamentMatch && $m2 instanceof TournamentMatch
                && (int) $m2->match_number === (int) $m1->match_number + 1) {
                $out->push(collect([$m1, $m2]));
                $i += 2;

                continue;
            }

            if ($m1 instanceof TournamentMatch) {
                $out->push(collect([$m1]));
            }

            $i += 1;
        }

        return $out->values();
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<int>|null  $excludeMatchIds
     *
     * @throws InvalidArgumentException
     */
    public static function assertAdminDayTwoSlotRowDoesNotViolatePeerRows(Tournament $tournament, array $row, ?array $excludeMatchIds = null): void
    {
        $tz = SmallFixedRoundRobinDayOneSchedule::tournamentTimezone($tournament);
        $candidateRound = (int) ($row['round'] ?? 0);
        $m1n = (int) ($row['match1_match_number'] ?? 0);
        $m2n = (int) ($row['match2_match_number'] ?? 0);

        $regs = [
            (int) ($row['match1_home_registration_id'] ?? 0),
            (int) ($row['match1_away_registration_id'] ?? 0),
            (int) ($row['match2_home_registration_id'] ?? 0),
            (int) ($row['match2_away_registration_id'] ?? 0),
        ];

        if (in_array(0, $regs, true)) {
            throw new InvalidArgumentException(__('Each game in the row must include home and away teams.'));
        }

        if (count(array_unique($regs)) !== count($regs)) {
            throw new InvalidArgumentException(__('The same team cannot appear twice in one schedule time slot.'));
        }

        $candStart = CarbonImmutable::parse((string) $row['date'].' '.(string) $row['start_time'].':00', $tz);
        $candEnd = CarbonImmutable::parse((string) $row['date'].' '.(string) $row['end_time'].':00', $tz);

        $active = TournamentMatch::query()
            ->where('tournament_id', $tournament->id)
            ->where('stage', 'round_robin')
            ->where('notes', 'like', '%'.self::MARKER_PREFIX.'%')
            ->orderBy('match_number')
            ->get();

        $excludeSorted = null;
        if ($excludeMatchIds !== null && count($excludeMatchIds) > 0) {
            $excludeSorted = collect($excludeMatchIds)->map(fn ($id): int => (int) $id)->sort()->values()->all();
        }

        foreach (self::bucketSmallDayTwoTrackedMatches($active) as $bucket) {
            $ids = $bucket->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
            if ($excludeSorted !== null && $ids === $excludeSorted) {
                continue;
            }

            $first = $bucket->first();
            if ($first instanceof TournamentMatch) {
                $peerRound = self::parseRoundNumberFromRoundLabel((string) ($first->round_label ?? ''));
                if ($peerRound > 0 && $peerRound === $candidateRound) {
                    throw new InvalidArgumentException(__('Each schedule row must have a unique round number.'));
                }
            }

            foreach ($bucket as $peer) {
                $n = (int) ($peer->match_number ?? 0);
                if ($n === $m1n || $n === $m2n) {
                    throw new InvalidArgumentException(__('Those game numbers are already used on the Day 2 schedule.'));
                }
            }

            $peerRegs = [];
            $peerStart = null;
            $peerEnd = null;

            foreach ($bucket as $peer) {
                if ($peer->home_registration_id) {
                    $peerRegs[] = (int) $peer->home_registration_id;
                }
                if ($peer->away_registration_id) {
                    $peerRegs[] = (int) $peer->away_registration_id;
                }
                if ($peer->scheduled_at instanceof CarbonImmutable) {
                    $s = $peer->scheduled_at->timezone($tz);
                    $peerStart = $peerStart === null ? $s : ($s->lt($peerStart) ? $s : $peerStart);
                }
                if ($peer->scheduled_ends_at instanceof CarbonImmutable) {
                    $e = $peer->scheduled_ends_at->timezone($tz);
                    $peerEnd = $peerEnd === null ? $e : ($e->gt($peerEnd) ? $e : $peerEnd);
                }
            }

            if ($peerStart === null || $peerEnd === null) {
                continue;
            }

            if (! $candStart->lt($peerEnd) || ! $peerStart->lt($candEnd)) {
                continue;
            }

            $peerRegSet = array_flip($peerRegs);
            foreach ($regs as $rid) {
                if (isset($peerRegSet[$rid])) {
                    $r2 = self::parseRoundNumberFromRoundLabel((string) ($first?->round_label ?? ''));
                    $r2label = $r2 > 0 ? (string) $r2 : __('another row');

                    throw new InvalidArgumentException(__('The same team is scheduled in overlapping time slots (round :r1 and round :r2).', [
                        'r1' => (string) $candidateRound,
                        'r2' => $r2label,
                    ]));
                }
            }
        }
    }

    private static function parseRoundNumberFromRoundLabel(string $label): int
    {
        if ($label === '') {
            return 0;
        }

        if (preg_match('/(\d+)\s*$/', $label, $m)) {
            return (int) $m[1];
        }

        return 0;
    }

    /**
     * Trashed Day 2 tracked slot rows for the "Removed schedules" admin UI.
     *
     * @return Collection<int, array{
     *     slot_ulid: ?string,
     *     match_numbers: list<int>,
     *     time_label: string,
     *     round_label: string,
     *     pitch1_matchup: string,
     *     pitch2_matchup: string,
     * }>
     */
    public static function removedDayTwoScheduleGroups(Tournament $tournament): Collection
    {
        $sortedRegs = SmallFixedRoundRobinDayOneSchedule::sortedRegistrations($tournament);
        $marker = self::MARKER_PREFIX;

        $trashed = TournamentMatch::onlyTrashed()
            ->where('tournament_id', $tournament->id)
            ->where('stage', 'round_robin')
            ->where('notes', 'like', '%'.$marker.'%')
            ->orderBy('match_number')
            ->get();

        $out = collect();

        foreach (self::bucketSmallDayTwoTrackedMatches($trashed) as $bucket) {
            $ulid = $bucket->first()?->schedule_slot_ulid;

            $out->push(self::removedDayTwoRowPayloadFromMatches(
                $bucket,
                $sortedRegs,
                filled($ulid) ? (string) $ulid : null,
            ));
        }

        return $out->values();
    }

    /**
     * @param  Collection<int, TournamentMatch>  $matches
     * @return array{
     *     slot_ulid: ?string,
     *     match_numbers: list<int>,
     *     time_label: string,
     *     round_label: string,
     *     pitch1_matchup: string,
     *     pitch2_matchup: string,
     * }
     */
    private static function removedDayTwoRowPayloadFromMatches(Collection $matches, Collection $sortedRegs, ?string $slotUlid): array
    {
        $m1 = $matches->get(0);
        $m2 = $matches->get(1);

        $labelOne = static function (?TournamentMatch $match) use ($sortedRegs): string {
            if (! $match instanceof TournamentMatch) {
                return '—';
            }

            $home = $match->home_registration_id
                ? $sortedRegs->firstWhere('id', (int) $match->home_registration_id)
                : null;
            $away = $match->away_registration_id
                ? $sortedRegs->firstWhere('id', (int) $match->away_registration_id)
                : null;

            if ($home === null || $away === null) {
                return '—';
            }

            $homeName = $home->team?->name ?? __('Team').' '.$home->id;
            $awayName = $away->team?->name ?? __('Team').' '.$away->id;

            return $homeName.' vs '.$awayName;
        };

        $timeLabel = '—';
        if ($m1 instanceof TournamentMatch && $m1->scheduled_at) {
            $timeLabel = SmallFixedRoundRobinDayOneSchedule::formatSlotRange($m1->scheduled_at, $m1->scheduled_ends_at);
        } elseif ($m2 instanceof TournamentMatch && $m2->scheduled_at) {
            $timeLabel = SmallFixedRoundRobinDayOneSchedule::formatSlotRange($m2->scheduled_at, $m2->scheduled_ends_at);
        }

        $nums = $matches->map(fn (TournamentMatch $m): int => (int) $m->match_number)->filter(fn (int $n): bool => $n > 0)->values()->all();

        return [
            'slot_ulid' => $slotUlid,
            'match_numbers' => $nums,
            'time_label' => $timeLabel,
            'round_label' => (string) ($m1?->round_label ?? $m2?->round_label ?? '—'),
            'pitch1_matchup' => $labelOne($m1),
            'pitch2_matchup' => $labelOne($m2),
        ];
    }

    /**
     * Insert one synced Day 2 slot row (two tracked matches).
     *
     * @param  array<string, mixed>  $slotRow
     */
    public static function persistTrackedDayTwoSlotRowFromAdmin(Tournament $tournament, array $slotRow): void
    {
        $registrationCount = $tournament->registrations()->count();

        if ($registrationCount >= TournamentController::MINIMUM_BRACKET_TEAM_COUNT) {
            throw new InvalidArgumentException(__('Fixed Day 2 schedule applies only to tournaments with fewer than :count registered teams.', ['count' => TournamentController::MINIMUM_BRACKET_TEAM_COUNT]));
        }

        if (self::dayTwoRoundSlotCount($tournament) === 0) {
            throw new InvalidArgumentException(__('This tournament does not use the fixed Day 2 round robin grid.'));
        }

        $sortedRegs = SmallFixedRoundRobinDayOneSchedule::sortedRegistrations($tournament);

        if ($sortedRegs->isEmpty()) {
            throw new InvalidArgumentException(__('Register at least one team before adding a schedule row.'));
        }

        DB::transaction(function () use ($tournament, $slotRow, $sortedRegs): void {
            $tournament->loadMissing(['pitches' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')]);
            $pitchesOrdered = $tournament->pitches;
            $pitchById = $pitchesOrdered->keyBy('id');
            $pitch1 = $pitchesOrdered->first();
            $pitch2 = $pitchesOrdered->skip(1)->first() ?? $pitch1;

            if (! $pitch1 instanceof Pitch) {
                throw new InvalidArgumentException(__('At least one tournament pitch is required for the Day 2 grid.'));
            }

            $tz = SmallFixedRoundRobinDayOneSchedule::tournamentTimezone($tournament);
            $roundLabelNum = (int) ($slotRow['round'] ?? 1);
            $match1Num = (int) ($slotRow['match1_match_number'] ?? 0);
            $match2Num = (int) ($slotRow['match2_match_number'] ?? 0);

            if ($match1Num < self::FIRST_GAME_NUMBER || $match2Num < self::FIRST_GAME_NUMBER || $match2Num !== $match1Num + 1) {
                throw new InvalidArgumentException(__('Game numbers in each Day 2 row must be consecutive (game B = game A + 1) and at least :min.', ['min' => self::FIRST_GAME_NUMBER]));
            }

            $busy = TournamentMatch::query()
                ->where('tournament_id', $tournament->id)
                ->where('stage', 'round_robin')
                ->where('notes', 'like', '%'.self::MARKER_PREFIX.'%')
                ->whereIn('match_number', [$match1Num, $match2Num])
                ->exists();

            if ($busy) {
                throw new InvalidArgumentException(__('Those game numbers are already used on the Day 2 schedule.'));
            }

            $rowUlid = (string) Str::ulid();

            for ($pitchSlot = 1; $pitchSlot <= 2; $pitchSlot++) {
                $gameNumber = $pitchSlot === 1 ? $match1Num : $match2Num;

                $scheduledAt = CarbonImmutable::parse((string) $slotRow['date'].' '.(string) $slotRow['start_time'].':00', $tz);
                $scheduledEnd = CarbonImmutable::parse((string) $slotRow['date'].' '.(string) $slotRow['end_time'].':00', $tz);

                if (! $scheduledEnd->greaterThan($scheduledAt)) {
                    throw new InvalidArgumentException(__('Each slot end time must be after its start time (round :round).', ['round' => $roundLabelNum]));
                }

                $targetPitchId = (int) (
                    $pitchSlot === 1
                        ? ($slotRow['pitch1_pitch_id'] ?? 0)
                        : ($slotRow['pitch2_pitch_id'] ?? 0)
                );

                $resolvedPitch = $pitchById->get($targetPitchId) ?? ($pitchSlot === 1 ? $pitch1 : $pitch2);

                if (! $resolvedPitch instanceof Pitch) {
                    throw new InvalidArgumentException(__('Could not resolve a pitch for round :round.', ['round' => $roundLabelNum]));
                }

                $matchStatus = SmallFixedRoundRobinDayOneSchedule::mapRowUiStatusToMatchStatus((string) ($slotRow['status'] ?? 'upcoming'));

                $resolvedRegs = SmallFixedRoundRobinDayOneSchedule::resolveRegistrationsForGridGame(
                    $slotRow,
                    $pitchSlot === 1,
                    null,
                    $sortedRegs,
                    (int) $tournament->id,
                );

                if ($resolvedRegs === null) {
                    throw new InvalidArgumentException(__('Each game in a new row must include home and away teams.'));
                }

                [$homeRegistration, $awayRegistration] = $resolvedRegs;

                TournamentMatch::query()->create([
                    'tournament_id' => $tournament->id,
                    'pitch_id' => $resolvedPitch->id,
                    'pitch_assigned_by' => null,
                    'home_registration_id' => $homeRegistration->id,
                    'away_registration_id' => $awayRegistration->id,
                    'stage' => 'round_robin',
                    'round_label' => __('Round :round', ['round' => $roundLabelNum]),
                    'match_number' => $gameNumber,
                    'scheduled_at' => $scheduledAt->utc(),
                    'scheduled_ends_at' => $scheduledEnd->utc(),
                    'status' => $matchStatus,
                    'home_score' => null,
                    'away_score' => null,
                    'notes' => self::marker($gameNumber, $pitchSlot),
                    'schedule_slot_ulid' => $rowUlid,
                ]);
            }
        });

        $tournament->unsetRelation('matches');
    }

    public static function restoreTrackedDayTwoSlot(Tournament $tournament, ?string $slotUlid, array $matchNumbers): void
    {
        $query = TournamentMatch::onlyTrashed()
            ->where('tournament_id', $tournament->id)
            ->where('stage', 'round_robin')
            ->where('notes', 'like', '%'.self::MARKER_PREFIX.'%');

        if ($slotUlid !== null && $slotUlid !== '') {
            $query->where('schedule_slot_ulid', $slotUlid);
        } else {
            $nums = array_values(array_filter(array_map('intval', $matchNumbers)));
            if (count($nums) < 1) {
                throw new InvalidArgumentException(__('Select a removed schedule row to restore.'));
            }

            $query->whereIn('match_number', $nums);
        }

        $matches = $query->orderBy('match_number')->get();

        if ($matches->isEmpty()) {
            throw new InvalidArgumentException(__('Could not find removed Day 2 games to restore.'));
        }

        foreach ($matches as $match) {
            $match->restore();
        }

        $tournament->unsetRelation('matches');
    }

    /**
     * @param  array<string, mixed>  $slotRow
     */
    public static function updateTrackedDayTwoSlotRowFromAdmin(
        Tournament $tournament,
        TournamentMatch $pitch1Match,
        TournamentMatch $pitch2Match,
        array $slotRow,
    ): void {
        $registrationCount = $tournament->registrations()->count();

        if ($registrationCount >= TournamentController::MINIMUM_BRACKET_TEAM_COUNT) {
            throw new InvalidArgumentException(__('Fixed Day 2 schedule applies only to tournaments with fewer than :count registered teams.', ['count' => TournamentController::MINIMUM_BRACKET_TEAM_COUNT]));
        }

        if (self::dayTwoRoundSlotCount($tournament) === 0) {
            throw new InvalidArgumentException(__('This tournament does not use the fixed Day 2 round robin grid.'));
        }

        $sortedRegs = SmallFixedRoundRobinDayOneSchedule::sortedRegistrations($tournament);

        DB::transaction(function () use ($tournament, $pitch1Match, $pitch2Match, $slotRow, $sortedRegs): void {
            $tournament->loadMissing(['pitches' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')]);
            $pitchById = $tournament->pitches->keyBy('id');
            $pitch1 = $tournament->pitches->first();
            $pitch2 = $tournament->pitches->skip(1)->first() ?? $pitch1;

            if (! $pitch1 instanceof Pitch) {
                throw new InvalidArgumentException(__('At least one tournament pitch is required for the Day 2 grid.'));
            }

            $tz = SmallFixedRoundRobinDayOneSchedule::tournamentTimezone($tournament);
            $roundLabelNum = (int) ($slotRow['round'] ?? 1);
            $match1Num = (int) ($slotRow['match1_match_number'] ?? 0);
            $match2Num = (int) ($slotRow['match2_match_number'] ?? 0);

            if ($match1Num < self::FIRST_GAME_NUMBER || $match2Num < self::FIRST_GAME_NUMBER || $match2Num !== $match1Num + 1) {
                throw new InvalidArgumentException(__('Game numbers in each Day 2 row must be consecutive (game B = game A + 1) and at least :min.', ['min' => self::FIRST_GAME_NUMBER]));
            }

            $conflict = TournamentMatch::query()
                ->where('tournament_id', $tournament->id)
                ->where('stage', 'round_robin')
                ->where('notes', 'like', '%'.self::MARKER_PREFIX.'%')
                ->whereIn('match_number', [$match1Num, $match2Num])
                ->whereNotIn('id', [(int) $pitch1Match->id, (int) $pitch2Match->id])
                ->exists();

            if ($conflict) {
                throw new InvalidArgumentException(__('Those game numbers are already used by another Day 2 schedule row.'));
            }

            $scheduledAt = CarbonImmutable::parse((string) $slotRow['date'].' '.(string) $slotRow['start_time'].':00', $tz);
            $scheduledEnd = CarbonImmutable::parse((string) $slotRow['date'].' '.(string) $slotRow['end_time'].':00', $tz);

            if (! $scheduledEnd->greaterThan($scheduledAt)) {
                throw new InvalidArgumentException(__('Each slot end time must be after its start time (round :round).', ['round' => $roundLabelNum]));
            }

            $matchStatus = SmallFixedRoundRobinDayOneSchedule::mapRowUiStatusToMatchStatus((string) ($slotRow['status'] ?? 'upcoming'));

            $pairs = [
                1 => [$pitch1Match, $match1Num],
                2 => [$pitch2Match, $match2Num],
            ];

            foreach ($pairs as $pitchSlot => [$match, $gameNumber]) {
                $targetPitchId = (int) (
                    $pitchSlot === 1
                        ? ($slotRow['pitch1_pitch_id'] ?? 0)
                        : ($slotRow['pitch2_pitch_id'] ?? 0)
                );

                $resolvedPitch = $pitchById->get($targetPitchId) ?? ($pitchSlot === 1 ? $pitch1 : $pitch2);

                if (! $resolvedPitch instanceof Pitch) {
                    throw new InvalidArgumentException(__('Could not resolve a pitch for round :round.', ['round' => $roundLabelNum]));
                }

                $resolvedRegs = SmallFixedRoundRobinDayOneSchedule::resolveRegistrationsForGridGame(
                    $slotRow,
                    $pitchSlot === 1,
                    null,
                    $sortedRegs,
                    (int) $tournament->id,
                );

                if ($resolvedRegs === null) {
                    throw new InvalidArgumentException(__('Each game in the row must include home and away teams.'));
                }

                [$homeRegistration, $awayRegistration] = $resolvedRegs;

                $match->forceFill([
                    'pitch_id' => $resolvedPitch->id,
                    'home_registration_id' => $homeRegistration->id,
                    'away_registration_id' => $awayRegistration->id,
                    'round_label' => __('Round :round', ['round' => $roundLabelNum]),
                    'match_number' => $gameNumber,
                    'scheduled_at' => $scheduledAt->utc(),
                    'scheduled_ends_at' => $scheduledEnd->utc(),
                    'status' => $matchStatus,
                    'notes' => self::marker($gameNumber, $pitchSlot),
                ])->save();
            }
        });

        $tournament->unsetRelation('matches');
    }
}
