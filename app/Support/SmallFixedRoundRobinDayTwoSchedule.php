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
     * Highest {@code round_label} round number that still has at least one scheduled Day 2 game.
     */
    public static function lastScheduledRoundNumber(Tournament $tournament): int
    {
        $entries = self::complementScheduleEntries($tournament);
        if ($entries === []) {
            return self::FIRST_ROUND_NUMBER - 1;
        }

        $max = self::FIRST_ROUND_NUMBER;
        foreach ($entries as $row) {
            $max = max($max, $row['round']);
        }

        return $max;
    }

    /**
     * @return list<array{label: string, start: string}>
     */
    public static function timeSlotDescriptors(): array
    {
        return [
            ['label' => '07:00am – 07:40am', 'start' => '07:00'],
            ['label' => '07:45am – 08:25am', 'start' => '07:45'],
            ['label' => '08:30am – 09:10am', 'start' => '08:30'],
            ['label' => '09:15am – 09:55am', 'start' => '09:15'],
            ['label' => '10:00am – 10:40am', 'start' => '10:00'],
            ['label' => '10:45am – 11:25am', 'start' => '10:45'],
        ];
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

        $slotCount = self::dayTwoRoundSlotCount($tournament);

        return collect(range(0, max(0, $slotCount - 1)))->map(static function (int $slotIndex) use ($slots, $entries, $byGame): array {
            $descriptor = $slots[$slotIndex];
            $round = self::FIRST_ROUND_NUMBER + $slotIndex;
            $g1 = self::FIRST_GAME_NUMBER + ($slotIndex * 2);
            $g2 = $g1 + 1;

            $e1 = $entries[$g1] ?? null;
            $e2 = $entries[$g2] ?? null;

            return [
                'time_label' => $descriptor['label'],
                'round' => $round,
                'pitch1_game_no' => $g1,
                'pitch2_game_no' => $g2,
                'pitch1_matchup' => $e1 !== null ? $e1['home_label'].' vs '.$e1['away_label'] : '—',
                'pitch2_matchup' => $e2 !== null ? $e2['home_label'].' vs '.$e2['away_label'] : '—',
                'pitch1_match' => $byGame[$g1] ?? null,
                'pitch2_match' => $byGame[$g2] ?? null,
            ];
        });
    }

    /**
     * Persist Day 2 rows on the `matches` table using {@see updateOrCreate}-style upserts so the seeder
     * stays idempotent. Existing tracked rows for the same game number are updated in place instead of
     * being duplicated.
     *
     * @throws InvalidArgumentException
     */
    public static function sync(Tournament $tournament): void
    {
        $registrationCount = $tournament->registrations()->count();

        if ($registrationCount >= TournamentController::MINIMUM_BRACKET_TEAM_COUNT) {
            throw new InvalidArgumentException(__('Fixed Day 2 schedule applies only to tournaments with fewer than :count registered teams.', ['count' => TournamentController::MINIMUM_BRACKET_TEAM_COUNT]));
        }

        $tournament->loadMissing(['registrations.team', 'pitches']);

        if ($tournament->registrations->isEmpty()) {
            throw new InvalidArgumentException(__('Register at least one team before syncing the Day 2 schedule.'));
        }

        DB::transaction(function () use ($tournament): void {
            while ($tournament->pitches()->count() < 2) {
                $nextOrder = (int) ($tournament->pitches()->max('sort_order') ?? 0) + 1;

                Pitch::query()->create([
                    'tournament_id' => $tournament->id,
                    'name' => 'Pitch '.$nextOrder,
                    'sort_order' => $nextOrder,
                    'scorekeeper_user_id' => null,
                    'is_active' => true,
                ]);
            }

            $tournament->unsetRelation('pitches');
            $pitches = $tournament->pitches()->orderBy('sort_order')->orderBy('id')->get();
            $pitch1 = $pitches->get(0);
            $pitch2 = $pitches->get(1);

            if ($pitch1 === null || $pitch2 === null) {
                throw new InvalidArgumentException(__('Two pitches are required for the Day 2 grid.'));
            }

            $tz = SmallFixedRoundRobinDayOneSchedule::tournamentTimezone($tournament);
            $slots = self::timeSlotDescriptors();

            $entries = self::complementScheduleEntries($tournament);

            $scheduledGameNumbers = array_keys($entries);

            if ($scheduledGameNumbers !== []) {
                TournamentMatch::query()
                    ->where('tournament_id', $tournament->id)
                    ->where('stage', 'round_robin')
                    ->where('notes', 'like', '%'.self::MARKER_PREFIX.'%')
                    ->where('match_number', '>=', self::FIRST_GAME_NUMBER)
                    ->whereNotIn('match_number', $scheduledGameNumbers)
                    ->delete();
            }

            foreach ($entries as $gameNumber => $pairing) {
                $slotIndex = $pairing['round'] - self::FIRST_ROUND_NUMBER;
                $descriptor = $slots[$slotIndex] ?? null;

                if ($descriptor === null) {
                    continue;
                }

                $scheduledAt = CarbonImmutable::parse(self::SCHEDULE_DATE_ISO.' '.$descriptor['start'].':00', $tz);
                $pitch = $pairing['pitch_slot'] === 1 ? $pitch1 : $pitch2;

                $home = $pairing['home'];
                $away = $pairing['away'];

                $existing = TournamentMatch::query()
                    ->where('tournament_id', $tournament->id)
                    ->where('stage', 'round_robin')
                    ->where('match_number', $gameNumber)
                    ->where('notes', 'like', '%'.self::MARKER_PREFIX.'%')
                    ->first();

                $attributes = [
                    'tournament_id' => $tournament->id,
                    'pitch_id' => $pitch->id,
                    'pitch_assigned_by' => $existing?->pitch_assigned_by,
                    'home_registration_id' => $home->id,
                    'away_registration_id' => $away->id,
                    'stage' => 'round_robin',
                    'round_label' => __('Round :round', ['round' => $pairing['round']]),
                    'match_number' => $gameNumber,
                    'scheduled_at' => $scheduledAt,
                    'status' => $existing?->status ?? 'scheduled',
                    'home_score' => $existing?->home_score,
                    'away_score' => $existing?->away_score,
                    'notes' => self::marker($gameNumber, $pairing['pitch_slot']),
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
}
