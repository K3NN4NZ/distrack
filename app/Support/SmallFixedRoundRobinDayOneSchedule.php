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
 * Fixed Day 1 round-robin grid for tournaments below {@see TournamentController::MINIMUM_BRACKET_TEAM_COUNT}.
 *
 * Persists rows on the existing {@see TournamentMatch} table using a marker in {@see TournamentMatch::$notes}.
 */
final class SmallFixedRoundRobinDayOneSchedule
{
    public const MARKER_PREFIX = '[[small-day1:';

    public const SCHEDULE_DATE_LABEL = 'May 16, 2026';

    public const SCHEDULE_DATE_ISO = '2026-05-16';

    /**
     * @return list<array{label: string, start: string, end: string}>
     */
    public static function timeSlotDescriptors(): array
    {
        return [
            ['label' => '07:00am – 07:40am', 'start' => '07:00', 'end' => '07:40'],
            ['label' => '07:45am – 08:35am', 'start' => '07:45', 'end' => '08:35'],
            ['label' => '08:40am – 09:25am', 'start' => '08:40', 'end' => '09:25'],
            ['label' => '09:30am – 10:15am', 'start' => '09:30', 'end' => '10:15'],
            ['label' => '10:20am – 11:05am', 'start' => '10:20', 'end' => '11:05'],
            ['label' => '11:10am – 11:55am', 'start' => '11:10', 'end' => '11:55'],
            ['label' => '12:00pm – 12:45pm', 'start' => '12:00', 'end' => '12:45'],
            ['label' => '12:50pm – 01:35pm', 'start' => '12:50', 'end' => '13:35'],
            ['label' => '01:40pm – 02:25pm', 'start' => '13:40', 'end' => '14:25'],
            ['label' => '02:30pm – 03:15pm', 'start' => '14:30', 'end' => '15:15'],
            ['label' => '03:20pm – 04:05pm', 'start' => '15:20', 'end' => '16:05'],
            ['label' => '04:10pm – 04:55pm', 'start' => '16:10', 'end' => '16:55'],
        ];
    }

    /**
     * Map legacy or informal timezone strings to a valid IANA identifier for Carbon.
     */
    public static function normalizeTimezone(?string $timezone): string
    {
        $timezone = trim((string) $timezone);

        if ($timezone === '' || $timezone === 'UTC+8' || $timezone === 'GMT+8') {
            return 'Asia/Manila';
        }

        if (preg_match('/^UTC\+?8(?::00)?$/i', $timezone) === 1) {
            return 'Asia/Manila';
        }

        if (in_array($timezone, timezone_identifiers_list(), true)) {
            return $timezone;
        }

        return 'Asia/Manila';
    }

    public static function tournamentTimezone(Tournament $tournament): string
    {
        $raw = trim((string) ($tournament->timezone ?? ''));

        if ($raw === '') {
            return 'Asia/Manila';
        }

        return self::normalizeTimezone($raw);
    }

    public static function marker(int $gameNumber, int $pitchSlot): string
    {
        return self::MARKER_PREFIX.'g='.$gameNumber.':p='.$pitchSlot.']]';
    }

    public static function isTrackedMatch(TournamentMatch $match): bool
    {
        return str_contains((string) ($match->notes ?? ''), self::MARKER_PREFIX);
    }

    /**
     * Read the pitch column (1 = Pitch 1 / left, 2 = Pitch 2 / right) from a fixed-schedule marker in {@see TournamentMatch::$notes}.
     */
    public static function markerPitchSlotFromNotes(?string $notes): ?int
    {
        if ($notes === null || $notes === '') {
            return null;
        }

        foreach ([self::MARKER_PREFIX, SmallFixedRoundRobinDayTwoSchedule::MARKER_PREFIX] as $prefix) {
            if (! str_contains($notes, $prefix)) {
                continue;
            }

            $quoted = preg_quote($prefix, '/');

            if (preg_match('/'.$quoted.'g=\d+:p=(\d+)\]\]/', $notes, $m)) {
                $n = (int) $m[1];

                return ($n === 1 || $n === 2) ? $n : null;
            }
        }

        return null;
    }

    /**
     * Order the two games in a schedule row so index 0 is always Pitch 1 (left) and index 1 is Pitch 2 (right).
     *
     * @return array{0: TournamentMatch|null, 1: TournamentMatch|null}
     */
    public static function orderMatchesForFixedScheduleColumns(
        ?TournamentMatch $primaryGame,
        ?TournamentMatch $secondaryGame,
        Pitch $pitch1,
        Pitch $pitch2,
    ): array {
        $candidates = array_values(array_filter(
            [$primaryGame, $secondaryGame],
            static fn ($m): bool => $m instanceof TournamentMatch,
        ));

        if ($candidates === []) {
            return [null, null];
        }

        $pitch1Id = (int) $pitch1->id;
        $pitch2Id = (int) $pitch2->id;

        $columnFor = static function (TournamentMatch $m) use ($pitch1Id, $pitch2Id): ?int {
            $slot = self::markerPitchSlotFromNotes((string) ($m->notes ?? ''));

            if ($slot === 1 || $slot === 2) {
                return $slot;
            }

            $pid = (int) ($m->pitch_id ?? 0);

            if ($pid === $pitch1Id) {
                return 1;
            }

            if ($pid === $pitch2Id) {
                return 2;
            }

            return null;
        };

        if (count($candidates) === 1) {
            $m = $candidates[0];
            $col = $columnFor($m);

            if ($col === 1) {
                return [$m, null];
            }

            if ($col === 2) {
                return [null, $m];
            }

            $n = (int) ($m->match_number ?? 0);

            return (($n % 2) === 1) ? [$m, null] : [null, $m];
        }

        [$a, $b] = $candidates;
        $colA = $columnFor($a);
        $colB = $columnFor($b);

        if ($colA === 1 && $colB === 2) {
            return [$a, $b];
        }

        if ($colA === 2 && $colB === 1) {
            return [$b, $a];
        }

        if ($colA === 1) {
            return [$a, $b];
        }

        if ($colB === 1) {
            return [$b, $a];
        }

        $aOn1 = (int) $a->pitch_id === $pitch1Id;
        $bOn1 = (int) $b->pitch_id === $pitch1Id;

        if ($aOn1 && ! $bOn1) {
            return [$a, $b];
        }

        if ($bOn1 && ! $aOn1) {
            return [$b, $a];
        }

        $na = (int) ($a->match_number ?? 0);
        $nb = (int) ($b->match_number ?? 0);

        return $na <= $nb ? [$a, $b] : [$b, $a];
    }

    /**
     * Explain why scoring endpoints stay closed until {@see TournamentMatch::$status} is {@code completed}.
     * Day 1 rows share one status across Pitch 1 and Pitch 2; both matches receive that status together.
     * Day 2 rows share the same UX, so its marker is treated the same here without forcing a circular import.
     */
    public static function scoringRequiresCompletedScheduleMessage(TournamentMatch $match): string
    {
        $notes = (string) ($match->notes ?? '');

        if (self::isTrackedMatch($match) || str_contains($notes, SmallFixedRoundRobinDayTwoSchedule::MARKER_PREFIX)) {
            return __('Scoring is only available after this Round Robin row is marked Completed on the schedule.');
        }

        if (str_contains($notes, SmallDayTwoKnockoutBracket::MARKER_PREFIX)) {
            return __('Scoring is only available after this bracket match is marked Completed on the schedule.');
        }

        return __('Scoring is only available after this match is marked Completed on the tournament schedule.');
    }

    /**
     * Berger round-robin pairings for $teamCount teams (indices 0..n-1), ordered round-by-round.
     *
     * @return list<array{0: int, 1: int}>
     */
    public static function bergerOrderedPairs(int $teamCount): array
    {
        if ($teamCount < 2) {
            return [];
        }

        $teams = range(0, $teamCount - 1);

        if ($teamCount % 2 === 1) {
            $teams[] = -1;
        }

        $n = count($teams);
        $schedule = $teams;
        $pairs = [];

        for ($round = 0; $round < $n - 1; $round++) {
            for ($i = 0; $i < intdiv($n, 2); $i++) {
                $home = $schedule[$i];
                $away = $schedule[$n - 1 - $i];

                if ($home === -1 || $away === -1) {
                    continue;
                }

                $pairs[] = [$home, $away];
            }

            $fixed = $schedule[0];
            $rotating = array_slice($schedule, 1);
            $last = array_pop($rotating);
            array_unshift($rotating, $last);
            $schedule = array_merge([$fixed], $rotating);
        }

        return $pairs;
    }

    /**
     * First up to 24 pair slots; missing slots are null when fewer unique Berger pairs exist.
     *
     * @return list<?array{0: int, 1: int}>
     */
    public static function firstTwentyFourPairSlots(int $teamCount): array
    {
        $berger = self::bergerOrderedPairs($teamCount);
        $out = [];

        for ($i = 0; $i < 24; $i++) {
            $out[] = $berger[$i] ?? null;
        }

        return $out;
    }

    /**
     * @return Collection<int, TournamentRegistration>
     */
    public static function sortedRegistrations(Tournament $tournament): Collection
    {
        return $tournament->registrations
            ->sort(static fn ($left, $right) => [
                $left->seed_number ?? PHP_INT_MAX,
                $left->team?->name ?? '',
                $left->id,
            ] <=> [
                $right->seed_number ?? PHP_INT_MAX,
                $right->team?->name ?? '',
                $right->id,
            ])
            ->values();
    }

    /**
     * Human-readable range for a slot (same timezone as stored instants).
     */
    public static function formatSlotRange(?CarbonImmutable $start, ?CarbonImmutable $end): string
    {
        if ($start === null) {
            return '—';
        }

        $tz = $start->timezone;
        $left = $start->timezone($tz)->format('M j, Y g:iA');

        if ($end === null) {
            return $left;
        }

        return $left.' – '.$end->timezone($tz)->format('g:iA');
    }

    /**
     * Map Round Robin row UI values to persisted {@see TournamentMatch::$status}.
     */
    public static function mapRowUiStatusToMatchStatus(string $ui): string
    {
        return match (strtolower(trim($ui))) {
            'live' => TournamentMatch::STATUS_LIVE,
            'completed' => TournamentMatch::STATUS_COMPLETED,
            default => TournamentMatch::STATUS_SCHEDULED,
        };
    }

    /**
     * Default 12 slot rows for pre-sync admin forms (Berger pairings + default pitches/times).
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
        $tournament->loadMissing(['pitches', 'registrations.team']);
        $pitches = $tournament->pitches->sortBy(['sort_order', 'id'])->values();
        $pitch1Id = $pitches->get(0)?->id;
        $pitch2Id = ($pitches->get(1) ?? $pitches->get(0))?->id;
        $slots = self::timeSlotDescriptors();
        $sortedRegs = self::sortedRegistrations($tournament);
        $pairSlots = self::firstTwentyFourPairSlots($sortedRegs->count());
        $rows = [];

        foreach ($slots as $index => $slot) {
            $p1 = $pairSlots[$index * 2] ?? null;
            $p2 = $pairSlots[$index * 2 + 1] ?? null;
            $m1h = $p1 !== null ? $sortedRegs->get($p1[0])?->id : null;
            $m1a = $p1 !== null ? $sortedRegs->get($p1[1])?->id : null;
            $m2h = $p2 !== null ? $sortedRegs->get($p2[0])?->id : null;
            $m2a = $p2 !== null ? $sortedRegs->get($p2[1])?->id : null;

            $rows[] = [
                'round' => $index + 1,
                'date' => self::SCHEDULE_DATE_ISO,
                'start_time' => $slot['start'],
                'end_time' => $slot['end'],
                'pitch1_pitch_id' => $pitch1Id,
                'pitch2_pitch_id' => $pitch2Id,
                'match1_home_registration_id' => $m1h,
                'match1_away_registration_id' => $m1a,
                'match2_home_registration_id' => $m2h,
                'match2_away_registration_id' => $m2a,
                'status' => 'upcoming',
                'match1_match_number' => $index * 2 + 1,
                'match2_match_number' => $index * 2 + 2,
            ];
        }

        return $rows;
    }

    /**
     * Resolve home/away registrations for one game in a slot row (admin form) or Berger fallback.
     *
     * @param  array<string, mixed>|null  $slotRow
     * @param  array{0: int, 1: int}|null  $bergerPair
     * @return array{0: TournamentRegistration, 1: TournamentRegistration}|null Null when no matchup to persist
     */
    public static function resolveRegistrationsForGridGame(
        ?array $slotRow,
        bool $isFirstGameInSlot,
        ?array $bergerPair,
        Collection $sortedRegs,
        int $tournamentId,
    ): ?array {
        if ($slotRow !== null) {
            if ($isFirstGameInSlot) {
                $hid = (int) ($slotRow['match1_home_registration_id'] ?? 0);
                $aid = (int) ($slotRow['match1_away_registration_id'] ?? 0);
            } else {
                $hid = (int) ($slotRow['match2_home_registration_id'] ?? 0);
                $aid = (int) ($slotRow['match2_away_registration_id'] ?? 0);
            }

            if ($hid > 0 && $aid > 0) {
                $home = $sortedRegs->firstWhere('id', $hid)
                    ?? TournamentRegistration::query()->where('tournament_id', $tournamentId)->whereKey($hid)->first();
                $away = $sortedRegs->firstWhere('id', $aid)
                    ?? TournamentRegistration::query()->where('tournament_id', $tournamentId)->whereKey($aid)->first();

                if ($home instanceof TournamentRegistration && $away instanceof TournamentRegistration) {
                    return [$home, $away];
                }

                throw new InvalidArgumentException(__('Invalid team selection for this schedule row.'));
            }
        }

        if ($bergerPair === null) {
            return null;
        }

        [$ia, $ib] = $bergerPair;
        $home = $sortedRegs->get($ia);
        $away = $sortedRegs->get($ib);

        if ($home === null || $away === null) {
            return null;
        }

        return [$home, $away];
    }

    /**
     * Replace tracked Day 1 matches and recreate them from the fixed slot grid + Berger pair order.
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
     *        When non-null, one entry per submitted schedule row. Each row may include explicit
     *        {@code match1_match_number} / {@code match2_match_number} (defaults: consecutive from row order).
     *
     * @throws InvalidArgumentException
     */
    public static function sync(Tournament $tournament, ?array $slotRows = null): void
    {
        $registrationCount = $tournament->registrations()->count();

        if ($registrationCount >= TournamentController::MINIMUM_BRACKET_TEAM_COUNT) {
            throw new InvalidArgumentException(__('Fixed Day 1 schedule applies only to tournaments with fewer than :count registered teams.', ['count' => TournamentController::MINIMUM_BRACKET_TEAM_COUNT]));
        }

        $sortedRegs = self::sortedRegistrations($tournament);

        if ($sortedRegs->isEmpty()) {
            throw new InvalidArgumentException(__('Register at least one team before syncing the Day 1 schedule.'));
        }

        DB::transaction(function () use ($tournament, $sortedRegs, $slotRows): void {
            TournamentMatch::withoutGlobalScopes()
                ->where('tournament_id', $tournament->id)
                ->where('stage', 'round_robin')
                ->where('notes', 'like', '%'.self::MARKER_PREFIX.'%')
                ->get()
                ->each(static function (TournamentMatch $match): void {
                    $match->forceDelete();
                });

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
                throw new InvalidArgumentException(__('At least one tournament pitch is required for the Day 1 grid.'));
            }

            $tz = self::tournamentTimezone($tournament);
            $slots = self::timeSlotDescriptors();
            $pairSlots = self::firstTwentyFourPairSlots($sortedRegs->count());

            if ($slotRows !== null) {
                foreach ($slotRows as $slotIndex => $slotRow) {
                    $rowUlid = (string) Str::ulid();
                    $roundLabelNum = (int) ($slotRow['round'] ?? ($slotIndex + 1));
                    $match1Num = (int) ($slotRow['match1_match_number'] ?? (($slotIndex * 2) + 1));
                    $match2Num = (int) ($slotRow['match2_match_number'] ?? (($slotIndex * 2) + 2));

                    for ($pitchSlot = 1; $pitchSlot <= 2; $pitchSlot++) {
                        $gameNumber = $pitchSlot === 1 ? $match1Num : $match2Num;
                        $pairIndex = $gameNumber - 1;
                        $pair = ($pairIndex >= 0 && $pairIndex < count($pairSlots))
                            ? ($pairSlots[$pairIndex] ?? null)
                            : null;

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

                        $matchStatus = self::mapRowUiStatusToMatchStatus((string) ($slotRow['status'] ?? 'upcoming'));

                        $resolvedRegs = self::resolveRegistrationsForGridGame(
                            $slotRow,
                            $pitchSlot === 1,
                            $pair,
                            $sortedRegs,
                            (int) $tournament->id,
                        );

                        if ($resolvedRegs === null) {
                            continue;
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
                            'scheduled_at' => $scheduledAt,
                            'scheduled_ends_at' => $scheduledEnd,
                            'status' => $matchStatus,
                            'home_score' => null,
                            'away_score' => null,
                            'notes' => self::marker($gameNumber, $pitchSlot),
                            'schedule_slot_ulid' => $rowUlid,
                        ]);
                    }
                }
            } else {
                $rowUlidBySlotIndex = [];

                foreach ($pairSlots as $index => $pair) {
                    $gameNumber = $index + 1;
                    $slotIndex = intdiv($index, 2);

                    if (! array_key_exists($slotIndex, $rowUlidBySlotIndex)) {
                        $rowUlidBySlotIndex[$slotIndex] = (string) Str::ulid();
                    }

                    $rowUlid = $rowUlidBySlotIndex[$slotIndex];
                    $pitchSlot = ($index % 2 === 0) ? 1 : 2;
                    $defaultPitch = $pitchSlot === 1 ? $pitch1 : $pitch2;

                    $descriptor = $slots[$slotIndex] ?? null;

                    if ($descriptor === null) {
                        break;
                    }

                    $slotRow = null;

                    $scheduledAt = CarbonImmutable::parse(self::SCHEDULE_DATE_ISO.' '.$descriptor['start'].':00', $tz);
                    $scheduledEnd = CarbonImmutable::parse(self::SCHEDULE_DATE_ISO.' '.$descriptor['end'].':00', $tz);
                    $resolvedPitch = $defaultPitch;
                    $matchStatus = TournamentMatch::STATUS_SCHEDULED;

                    $resolvedRegs = self::resolveRegistrationsForGridGame(
                        $slotRow,
                        $pitchSlot === 1,
                        $pair,
                        $sortedRegs,
                        (int) $tournament->id,
                    );

                    if ($resolvedRegs === null) {
                        continue;
                    }

                    [$homeRegistration, $awayRegistration] = $resolvedRegs;

                    TournamentMatch::query()->create([
                        'tournament_id' => $tournament->id,
                        'pitch_id' => $resolvedPitch->id,
                        'pitch_assigned_by' => null,
                        'home_registration_id' => $homeRegistration->id,
                        'away_registration_id' => $awayRegistration->id,
                        'stage' => 'round_robin',
                        'round_label' => __('Round :round', ['round' => $slotIndex + 1]),
                        'match_number' => $gameNumber,
                        'scheduled_at' => $scheduledAt,
                        'scheduled_ends_at' => $scheduledEnd,
                        'status' => $matchStatus,
                        'home_score' => null,
                        'away_score' => null,
                        'notes' => self::marker($gameNumber, $pitchSlot),
                        'schedule_slot_ulid' => $rowUlid,
                    ]);
                }
            }
        });

        $tournament->unsetRelation('matches');
    }

    /**
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
        $sortedRegs = self::sortedRegistrations($tournament);
        $pairSlots = self::firstTwentyFourPairSlots($sortedRegs->count());
        $slots = self::timeSlotDescriptors();

        $tracked = ($tournament->matches ?? collect())
            ->filter(static fn (TournamentMatch $m): bool => $m->stage === 'round_robin' && self::isTrackedMatch($m));

        $maxGameFromDb = 0;
        foreach ($tracked as $match) {
            $n = (int) ($match->match_number ?? 0);
            if ($n > 0) {
                $maxGameFromDb = max($maxGameFromDb, $n);
            }
        }

        $slotCount = max(12, (int) ceil(max(24, $maxGameFromDb) / 2));

        /** @var array<int, TournamentMatch> $byGame */
        $byGame = [];

        foreach ($tracked as $match) {
            $num = $match->match_number;

            if ($num !== null) {
                $byGame[(int) $num] = $match;
            }
        }

        $labelPair = static function (?array $pair) use ($sortedRegs): string {
            if ($pair === null) {
                return '—';
            }

            [$ia, $ib] = $pair;
            $homeRegistration = $sortedRegs->get($ia);
            $awayRegistration = $sortedRegs->get($ib);

            if ($homeRegistration === null || $awayRegistration === null) {
                return '—';

            }

            $homeName = $homeRegistration->team?->name ?? __('Team').' '.$homeRegistration->id;
            $awayName = $awayRegistration->team?->name ?? __('Team').' '.$awayRegistration->id;

            return $homeName.' vs '.$awayName;
        };

        $tournament->loadMissing(['pitches' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')]);
        $pitches = $tournament->pitches->values();
        $pitch1 = $pitches->first();
        $pitch2 = $pitches->skip(1)->first() ?? $pitch1;

        return collect(range(0, $slotCount - 1))->map(function (int $slotIndex) use ($slots, $pairSlots, $labelPair, $byGame, $sortedRegs, $pitch1, $pitch2): array {
            $descriptor = $slots[$slotIndex] ?? ($slots[count($slots) - 1] ?? ['label' => '—', 'start' => '07:00', 'end' => '07:40']);
            $g1 = $slotIndex * 2 + 1;
            $g2 = $slotIndex * 2 + 2;
            $m1 = $byGame[$g1] ?? null;
            $m2 = $byGame[$g2] ?? null;

            if ($pitch1 instanceof Pitch && $pitch2 instanceof Pitch) {
                [$left, $right] = self::orderMatchesForFixedScheduleColumns($m1, $m2, $pitch1, $pitch2);
            } else {
                [$left, $right] = [$m1, $m2];
            }

            $leftNo = (int) ($left?->match_number ?? $g1);
            $rightNo = (int) ($right?->match_number ?? $g2);

            $matchupFromRegs = static function (?TournamentMatch $match) use ($sortedRegs): string {
                if ($match === null) {
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

            $timeLabel = $descriptor['label'];

            if ($left?->scheduled_at) {
                $timeLabel = self::formatSlotRange($left->scheduled_at, $left->scheduled_ends_at);
            } elseif ($right?->scheduled_at) {
                $timeLabel = self::formatSlotRange($right->scheduled_at, $right->scheduled_ends_at);
            }

            return [
                'time_label' => $timeLabel,
                'round' => $slotIndex + 1,
                'pitch1_game_no' => $leftNo,
                'pitch2_game_no' => $rightNo,
                'pitch1_matchup' => $left !== null ? $matchupFromRegs($left) : $labelPair($pairSlots[$leftNo - 1] ?? null),
                'pitch2_matchup' => $right !== null ? $matchupFromRegs($right) : $labelPair($pairSlots[$rightNo - 1] ?? null),
                'pitch1_match' => $left,
                'pitch2_match' => $right,
            ];
        });
    }

    /**
     * Group tracked Day 1 matches into slot rows (two games per bucket when possible).
     *
     * @param  Collection<int, TournamentMatch>  $matches
     * @return Collection<int, Collection<int, TournamentMatch>>
     */
    public static function bucketSmallDayOneTrackedMatches(Collection $matches): Collection
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
     * Validate a single admin Day 1 slot row against other active tracked rows (round, numbers, overlap, teams).
     *
     * @param  array<string, mixed>  $row  Normalized row including {@code date}, times, registrations, game numbers, {@code round}.
     * @param  list<int>|null  $excludeMatchIds  Two match ids when updating an existing row (skip self-bucket).
     *
     * @throws InvalidArgumentException
     */
    public static function assertAdminDayOneSlotRowDoesNotViolatePeerRows(Tournament $tournament, array $row, ?array $excludeMatchIds = null): void
    {
        $tz = self::tournamentTimezone($tournament);
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

        foreach (self::bucketSmallDayOneTrackedMatches($active) as $bucket) {
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
                    throw new InvalidArgumentException(__('Those game numbers are already used on the Day 1 schedule.'));
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
     * Trashed Day 1 tracked slot rows for the "Removed schedules" admin UI.
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
    public static function removedDayOneScheduleGroups(Tournament $tournament): Collection
    {
        $sortedRegs = self::sortedRegistrations($tournament);
        $marker = self::MARKER_PREFIX;

        $trashed = TournamentMatch::onlyTrashed()
            ->where('tournament_id', $tournament->id)
            ->where('stage', 'round_robin')
            ->where('notes', 'like', '%'.$marker.'%')
            ->orderBy('match_number')
            ->get();

        $out = collect();

        foreach (self::bucketSmallDayOneTrackedMatches($trashed) as $bucket) {
            $ulid = $bucket->first()?->schedule_slot_ulid;

            $out->push(self::removedRowPayloadFromMatches(
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
    private static function removedRowPayloadFromMatches(Collection $matches, Collection $sortedRegs, ?string $slotUlid): array
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
            $timeLabel = self::formatSlotRange($m1->scheduled_at, $m1->scheduled_ends_at);
        } elseif ($m2 instanceof TournamentMatch && $m2->scheduled_at) {
            $timeLabel = self::formatSlotRange($m2->scheduled_at, $m2->scheduled_ends_at);
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
     * Insert one synced Day 1 slot row (two tracked matches). Does not run full grid sync.
     *
     * @param  array<string, mixed>  $slotRow  Normalized like {@see self::sync()} posted rows (includes {@code date}).
     */
    public static function persistTrackedSlotRowFromAdmin(Tournament $tournament, array $slotRow): void
    {
        $registrationCount = $tournament->registrations()->count();

        if ($registrationCount >= TournamentController::MINIMUM_BRACKET_TEAM_COUNT) {
            throw new InvalidArgumentException(__('Fixed Day 1 schedule applies only to tournaments with fewer than :count registered teams.', ['count' => TournamentController::MINIMUM_BRACKET_TEAM_COUNT]));
        }

        $sortedRegs = self::sortedRegistrations($tournament);

        if ($sortedRegs->isEmpty()) {
            throw new InvalidArgumentException(__('Register at least one team before adding a schedule row.'));
        }

        $pairSlots = self::firstTwentyFourPairSlots($sortedRegs->count());

        DB::transaction(function () use ($tournament, $slotRow, $sortedRegs, $pairSlots): void {
            $tournament->loadMissing(['pitches' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')]);
            $pitchesOrdered = $tournament->pitches;
            $pitchById = $pitchesOrdered->keyBy('id');
            $pitch1 = $pitchesOrdered->first();
            $pitch2 = $pitchesOrdered->skip(1)->first() ?? $pitch1;

            if (! $pitch1 instanceof Pitch) {
                throw new InvalidArgumentException(__('At least one tournament pitch is required for the Day 1 grid.'));
            }

            $tz = self::tournamentTimezone($tournament);
            $roundLabelNum = (int) ($slotRow['round'] ?? 1);
            $match1Num = (int) ($slotRow['match1_match_number'] ?? 0);
            $match2Num = (int) ($slotRow['match2_match_number'] ?? 0);

            if ($match1Num < 1 || $match2Num < 1 || $match2Num !== $match1Num + 1) {
                throw new InvalidArgumentException(__('Game numbers in each row must be consecutive (game B = game A + 1).'));
            }

            $busy = TournamentMatch::query()
                ->where('tournament_id', $tournament->id)
                ->where('stage', 'round_robin')
                ->where('notes', 'like', '%'.self::MARKER_PREFIX.'%')
                ->whereIn('match_number', [$match1Num, $match2Num])
                ->exists();

            if ($busy) {
                throw new InvalidArgumentException(__('Those game numbers are already used on the Day 1 schedule.'));
            }

            $rowUlid = (string) Str::ulid();

            for ($pitchSlot = 1; $pitchSlot <= 2; $pitchSlot++) {
                $gameNumber = $pitchSlot === 1 ? $match1Num : $match2Num;
                $pairIndex = $gameNumber - 1;
                $pair = ($pairIndex >= 0 && $pairIndex < count($pairSlots))
                    ? ($pairSlots[$pairIndex] ?? null)
                    : null;

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

                $matchStatus = self::mapRowUiStatusToMatchStatus((string) ($slotRow['status'] ?? 'upcoming'));

                $resolvedRegs = self::resolveRegistrationsForGridGame(
                    $slotRow,
                    $pitchSlot === 1,
                    $pair,
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

    public static function restoreTrackedDayOneSlot(Tournament $tournament, ?string $slotUlid, array $matchNumbers): void
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
            throw new InvalidArgumentException(__('Could not find removed Day 1 games to restore.'));
        }

        foreach ($matches as $match) {
            $match->restore();
        }

        $tournament->unsetRelation('matches');
    }

    /**
     * Update an existing synced Day 1 slot row (two tracked matches). Preserves scores and scoring rows.
     *
     * @param  array<string, mixed>  $slotRow
     */
    public static function updateTrackedSlotRowFromAdmin(
        Tournament $tournament,
        TournamentMatch $pitch1Match,
        TournamentMatch $pitch2Match,
        array $slotRow,
    ): void {
        $registrationCount = $tournament->registrations()->count();

        if ($registrationCount >= TournamentController::MINIMUM_BRACKET_TEAM_COUNT) {
            throw new InvalidArgumentException(__('Fixed Day 1 schedule applies only to tournaments with fewer than :count registered teams.', ['count' => TournamentController::MINIMUM_BRACKET_TEAM_COUNT]));
        }

        $sortedRegs = self::sortedRegistrations($tournament);
        $pairSlots = self::firstTwentyFourPairSlots($sortedRegs->count());

        DB::transaction(function () use ($tournament, $pitch1Match, $pitch2Match, $slotRow, $sortedRegs, $pairSlots): void {
            $tournament->loadMissing(['pitches' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')]);
            $pitchById = $tournament->pitches->keyBy('id');
            $pitch1 = $tournament->pitches->first();
            $pitch2 = $tournament->pitches->skip(1)->first() ?? $pitch1;

            if (! $pitch1 instanceof Pitch) {
                throw new InvalidArgumentException(__('At least one tournament pitch is required for the Day 1 grid.'));
            }

            $tz = self::tournamentTimezone($tournament);
            $roundLabelNum = (int) ($slotRow['round'] ?? 1);
            $match1Num = (int) ($slotRow['match1_match_number'] ?? 0);
            $match2Num = (int) ($slotRow['match2_match_number'] ?? 0);

            if ($match1Num < 1 || $match2Num < 1 || $match2Num !== $match1Num + 1) {
                throw new InvalidArgumentException(__('Game numbers in each row must be consecutive (game B = game A + 1).'));
            }

            $conflict = TournamentMatch::query()
                ->where('tournament_id', $tournament->id)
                ->where('stage', 'round_robin')
                ->where('notes', 'like', '%'.self::MARKER_PREFIX.'%')
                ->whereIn('match_number', [$match1Num, $match2Num])
                ->whereNotIn('id', [(int) $pitch1Match->id, (int) $pitch2Match->id])
                ->exists();

            if ($conflict) {
                throw new InvalidArgumentException(__('Those game numbers are already used by another Day 1 schedule row.'));
            }

            $scheduledAt = CarbonImmutable::parse((string) $slotRow['date'].' '.(string) $slotRow['start_time'].':00', $tz);
            $scheduledEnd = CarbonImmutable::parse((string) $slotRow['date'].' '.(string) $slotRow['end_time'].':00', $tz);

            if (! $scheduledEnd->greaterThan($scheduledAt)) {
                throw new InvalidArgumentException(__('Each slot end time must be after its start time (round :round).', ['round' => $roundLabelNum]));
            }

            $matchStatus = self::mapRowUiStatusToMatchStatus((string) ($slotRow['status'] ?? 'upcoming'));

            $pairs = [
                1 => [$pitch1Match, $match1Num],
                2 => [$pitch2Match, $match2Num],
            ];

            foreach ($pairs as $pitchSlot => [$match, $gameNumber]) {
                $pairIndex = $gameNumber - 1;
                $pair = ($pairIndex >= 0 && $pairIndex < count($pairSlots))
                    ? ($pairSlots[$pairIndex] ?? null)
                    : null;

                $targetPitchId = (int) (
                    $pitchSlot === 1
                        ? ($slotRow['pitch1_pitch_id'] ?? 0)
                        : ($slotRow['pitch2_pitch_id'] ?? 0)
                );

                $resolvedPitch = $pitchById->get($targetPitchId) ?? ($pitchSlot === 1 ? $pitch1 : $pitch2);

                if (! $resolvedPitch instanceof Pitch) {
                    throw new InvalidArgumentException(__('Could not resolve a pitch for round :round.', ['round' => $roundLabelNum]));
                }

                $resolvedRegs = self::resolveRegistrationsForGridGame(
                    $slotRow,
                    $pitchSlot === 1,
                    $pair,
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
