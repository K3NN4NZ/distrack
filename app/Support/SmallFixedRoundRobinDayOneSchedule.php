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
     * @return list<array{label: string, start: string}>
     */
    public static function timeSlotDescriptors(): array
    {
        return [
            ['label' => '07:00am – 07:40am', 'start' => '07:00'],
            ['label' => '07:45am – 08:35am', 'start' => '07:45'],
            ['label' => '08:40am – 09:25am', 'start' => '08:40'],
            ['label' => '09:30am – 10:15am', 'start' => '09:30'],
            ['label' => '10:20am – 11:05am', 'start' => '10:20'],
            ['label' => '11:10am – 11:55am', 'start' => '11:10'],
            ['label' => '12:00pm – 12:45pm', 'start' => '12:00'],
            ['label' => '12:50pm – 01:35pm', 'start' => '12:50'],
            ['label' => '01:40pm – 02:25pm', 'start' => '13:40'],
            ['label' => '02:30pm – 03:15pm', 'start' => '14:30'],
            ['label' => '03:20pm – 04:05pm', 'start' => '15:20'],
            ['label' => '04:10pm – 04:55pm', 'start' => '16:10'],
        ];
    }

    public static function tournamentTimezone(Tournament $tournament): string
    {
        $tz = trim((string) ($tournament->timezone ?? ''));

        return $tz !== '' ? $tz : 'Asia/Manila';
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
     * Replace tracked Day 1 matches and recreate them from the fixed slot grid + Berger pair order.
     *
     * @throws InvalidArgumentException
     */
    public static function sync(Tournament $tournament): void
    {
        $registrationCount = $tournament->registrations()->count();

        if ($registrationCount >= TournamentController::MINIMUM_BRACKET_TEAM_COUNT) {
            throw new InvalidArgumentException(__('Fixed Day 1 schedule applies only to tournaments with fewer than :count registered teams.', ['count' => TournamentController::MINIMUM_BRACKET_TEAM_COUNT]));
        }

        $sortedRegs = self::sortedRegistrations($tournament);

        if ($sortedRegs->isEmpty()) {
            throw new InvalidArgumentException(__('Register at least one team before syncing the Day 1 schedule.'));
        }

        DB::transaction(function () use ($tournament, $sortedRegs): void {
            TournamentMatch::query()
                ->where('tournament_id', $tournament->id)
                ->where('stage', 'round_robin')
                ->where('notes', 'like', '%'.self::MARKER_PREFIX.'%')
                ->delete();

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
                throw new InvalidArgumentException(__('Two pitches are required for the Day 1 grid.'));
            }

            $tz = self::tournamentTimezone($tournament);
            $slots = self::timeSlotDescriptors();
            $pairSlots = self::firstTwentyFourPairSlots($sortedRegs->count());

            foreach ($pairSlots as $index => $pair) {
                $gameNumber = $index + 1;
                $slotIndex = intdiv($index, 2);
                $pitchSlot = ($index % 2 === 0) ? 1 : 2;
                $pitch = $pitchSlot === 1 ? $pitch1 : $pitch2;

                $descriptor = $slots[$slotIndex] ?? null;

                if ($descriptor === null) {
                    break;
                }

                $scheduledAt = CarbonImmutable::parse(self::SCHEDULE_DATE_ISO.' '.$descriptor['start'].':00', $tz);

                if ($pair === null) {
                    continue;
                }

                [$ia, $ib] = $pair;
                $homeRegistration = $sortedRegs->get($ia);
                $awayRegistration = $sortedRegs->get($ib);

                if ($homeRegistration === null || $awayRegistration === null) {
                    continue;
                }

                TournamentMatch::query()->create([
                    'tournament_id' => $tournament->id,
                    'pitch_id' => $pitch->id,
                    'pitch_assigned_by' => null,
                    'home_registration_id' => $homeRegistration->id,
                    'away_registration_id' => $awayRegistration->id,
                    'stage' => 'round_robin',
                    'round_label' => __('Round :round', ['round' => $slotIndex + 1]),
                    'match_number' => $gameNumber,
                    'scheduled_at' => $scheduledAt,
                    'status' => 'scheduled',
                    'home_score' => null,
                    'away_score' => null,
                    'notes' => self::marker($gameNumber, $pitchSlot),
                ]);
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

        return collect(range(0, 11))->map(function (int $slotIndex) use ($slots, $pairSlots, $labelPair, $byGame): array {
            $descriptor = $slots[$slotIndex];
            $g1 = $slotIndex * 2 + 1;
            $g2 = $slotIndex * 2 + 2;

            return [
                'time_label' => $descriptor['label'],
                'round' => $slotIndex + 1,
                'pitch1_game_no' => $g1,
                'pitch2_game_no' => $g2,
                'pitch1_matchup' => $labelPair($pairSlots[$g1 - 1] ?? null),
                'pitch2_matchup' => $labelPair($pairSlots[$g2 - 1] ?? null),
                'pitch1_match' => $byGame[$g1] ?? null,
                'pitch2_match' => $byGame[$g2] ?? null,
            ];
        });
    }
}
