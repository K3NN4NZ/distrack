<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tournament;
use App\Models\TournamentMatch;

/**
 * Non-blocking warnings for admin-edited small-tournament fixed RR grids (pre-sync forms).
 */
final class SmallRoundRobinUnsyncedFormWarnings
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    public static function dayOneWarnings(array $rows): array
    {
        $warnings = [];

        foreach ($rows as $index => $row) {
            $round = (int) ($row['round'] ?? $index + 1);
            $m1h = (int) ($row['match1_home_registration_id'] ?? 0);
            $m1a = (int) ($row['match1_away_registration_id'] ?? 0);
            $m2h = (int) ($row['match2_home_registration_id'] ?? 0);
            $m2a = (int) ($row['match2_away_registration_id'] ?? 0);
            $slotRegs = array_filter([$m1h, $m1a, $m2h, $m2a]);

            if (count($slotRegs) !== count(array_unique($slotRegs))) {
                $warnings[] = __('Round :round: the same registration appears more than once in this time slot (a team may be scheduled twice at the same time).', ['round' => $round]);
            }
        }

        $pairCounts = [];
        foreach ($rows as $row) {
            foreach (
                [
                    ['match1_home_registration_id', 'match1_away_registration_id'],
                    ['match2_home_registration_id', 'match2_away_registration_id'],
                ] as [$hk, $ak]
            ) {
                $h = (int) ($row[$hk] ?? 0);
                $a = (int) ($row[$ak] ?? 0);
                if ($h === 0 || $a === 0 || $h === $a) {
                    continue;
                }
                $k = min($h, $a).':'.max($h, $a);
                $pairCounts[$k] = ($pairCounts[$k] ?? 0) + 1;
            }
        }

        foreach ($pairCounts as $count) {
            if ($count > 1) {
                $warnings[] = __('The same team pairing appears more than once across the schedule (round robin usually has each pair once).');

                break;
            }
        }

        return array_values(array_unique($warnings));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    public static function dayTwoWarnings(array $rows, ?Tournament $tournament = null): array
    {
        $warnings = self::dayOneWarnings($rows);

        if ($tournament === null) {
            return $warnings;
        }

        $warnings = array_merge($warnings, self::dayTwoDuplicatePairWarningsAgainstDatabase($rows, $tournament));

        return array_values(array_unique($warnings));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    private static function dayTwoDuplicatePairWarningsAgainstDatabase(array $rows, Tournament $tournament): array
    {
        $tournamentId = (int) $tournament->id;
        $knockoutMarker = SmallDayTwoKnockoutBracket::MARKER_PREFIX;
        $warnings = [];

        foreach ($rows as $row) {
            foreach (
                [
                    [(int) ($row['match1_match_number'] ?? 0), (int) ($row['match1_home_registration_id'] ?? 0), (int) ($row['match1_away_registration_id'] ?? 0)],
                    [(int) ($row['match2_match_number'] ?? 0), (int) ($row['match2_home_registration_id'] ?? 0), (int) ($row['match2_away_registration_id'] ?? 0)],
                ] as [$num, $h, $a]
            ) {
                if ($num < 1 || $h <= 0 || $a <= 0 || $h === $a) {
                    continue;
                }

                $exists = TournamentMatch::query()
                    ->where('tournament_id', $tournamentId)
                    ->where('stage', 'round_robin')
                    ->where('notes', 'not like', '%'.$knockoutMarker.'%')
                    ->where('match_number', '!=', $num)
                    ->where(static function ($q) use ($h, $a): void {
                        $q->where(static function ($q2) use ($h, $a): void {
                            $q2->where('home_registration_id', $h)->where('away_registration_id', $a);
                        })->orWhere(static function ($q2) use ($h, $a): void {
                            $q2->where('home_registration_id', $a)->where('away_registration_id', $h);
                        });
                    })
                    ->exists();

                if ($exists) {
                    $warnings[] = __('This schedule includes a pairing that already exists in another round robin match.');

                    return array_values(array_unique($warnings));
                }
            }
        }

        return $warnings;
    }
}
