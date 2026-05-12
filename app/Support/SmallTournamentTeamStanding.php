<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tournament;
use App\Models\TournamentRegistration;
use Illuminate\Support\Collection;

final class SmallTournamentTeamStanding
{
    /**
     * Build rows for the admin Team Standing tab from completed round-robin matches.
     * Rows are ordered by seed number (nulls last), then team name, then registration id.
     *
     * @return Collection<int, array{
     *     seed_display: string,
     *     team_name: string,
     *     wins: int,
     *     losses: int,
     *     point_differential: int,
     *     accumulated_score: int,
     *     rank_display: string,
     * }>
     */
    public static function forRoundRobin(Tournament $tournament): Collection
    {
        $registrations = $tournament->registrations
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

        $registrationIds = $registrations->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $allowed = array_flip($registrationIds);

        $stats = [];
        foreach ($registrationIds as $id) {
            $stats[$id] = [
                'wins' => 0,
                'losses' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
            ];
        }

        $completedRoundRobinMatches = 0;

        foreach ($tournament->matches ?? [] as $match) {
            if ($match->stage !== 'round_robin') {
                continue;
            }

            if ($match->status !== 'completed' || $match->home_score === null || $match->away_score === null) {
                continue;
            }

            $homeId = (int) $match->home_registration_id;
            $awayId = (int) $match->away_registration_id;

            if (! isset($allowed[$homeId], $allowed[$awayId])) {
                continue;
            }

            $completedRoundRobinMatches++;

            $stats[$homeId]['goals_for'] += (int) $match->home_score;
            $stats[$homeId]['goals_against'] += (int) $match->away_score;
            $stats[$awayId]['goals_for'] += (int) $match->away_score;
            $stats[$awayId]['goals_against'] += (int) $match->home_score;

            if ($match->home_score > $match->away_score) {
                $stats[$homeId]['wins']++;
                $stats[$awayId]['losses']++;
            } elseif ($match->home_score < $match->away_score) {
                $stats[$awayId]['wins']++;
                $stats[$homeId]['losses']++;
            }
        }

        $baseRows = $registrations->map(static function (TournamentRegistration $registration) use ($stats): array {
            $id = (int) $registration->id;
            $s = $stats[$id];

            return [
                'registration_id' => $id,
                'seed_sort_key' => $registration->seed_number ?? PHP_INT_MAX,
                'seed_display' => $registration->seed_number !== null ? (string) $registration->seed_number : '—',
                'team_name' => $registration->team?->name ?? '—',
                'wins' => $s['wins'],
                'losses' => $s['losses'],
                'point_differential' => $s['goals_for'] - $s['goals_against'],
                'accumulated_score' => $s['goals_for'],
            ];
        });

        $rankByRegistrationId = [];

        if ($completedRoundRobinMatches > 0) {
            $ordered = $baseRows->sort(static function (array $a, array $b): int {
                foreach ([
                    $b['wins'] <=> $a['wins'],
                    $b['point_differential'] <=> $a['point_differential'],
                    $b['accumulated_score'] <=> $a['accumulated_score'],
                    $a['seed_sort_key'] <=> $b['seed_sort_key'],
                    $a['registration_id'] <=> $b['registration_id'],
                ] as $cmp) {
                    if ($cmp !== 0) {
                        return $cmp;
                    }
                }

                return 0;
            })->values();

            foreach ($ordered as $index => $row) {
                $rankByRegistrationId[$row['registration_id']] = (string) ($index + 1);
            }
        }

        return $baseRows->map(static function (array $row) use ($rankByRegistrationId, $completedRoundRobinMatches): array {
            $id = $row['registration_id'];

            return [
                'seed_display' => $row['seed_display'],
                'team_name' => $row['team_name'],
                'wins' => $row['wins'],
                'losses' => $row['losses'],
                'point_differential' => $row['point_differential'],
                'accumulated_score' => $row['accumulated_score'],
                'rank_display' => $completedRoundRobinMatches > 0 && isset($rankByRegistrationId[$id])
                    ? $rankByRegistrationId[$id]
                    : '—',
            ];
        })->values();
    }
}
