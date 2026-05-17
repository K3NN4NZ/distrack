<?php

namespace App\Support;

use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentRegistration;
use Illuminate\Support\Collection;

final class TournamentSpiritLeaderboard
{
    /**
     * Build the public tournament spirit ranking from scores each team received.
     *
     * @return Collection<int, array{
     *     registration: TournamentRegistration,
     *     team: Team,
     *     total_spirit_score: int,
     *     total_games_played: int,
     *     completed_games_played: int,
     *     average_spirit_score: float|null,
     * }>
     */
    public static function getTournamentSpiritLeaderboard(Tournament $tournament): Collection
    {
        $tournament->loadMissing([
            'registrations.team',
            'matches.spiritScores',
            'matches.homeRegistration.team',
            'matches.awayRegistration.team',
        ]);

        $registrationByTeamId = $tournament->registrations->keyBy('team_id');
        $completedMatches = $tournament->matches
            ->filter(fn (TournamentMatch $match): bool => $match->isCompletedMatchStatus())
            ->values();

        /** @var array<int, array{total_spirit_score: int, total_games_played: int, completed_games_played: int}> $aggregates */
        $aggregates = [];

        foreach ($tournament->registrations as $registration) {
            $teamId = (int) $registration->team_id;

            if ($teamId <= 0) {
                continue;
            }

            $aggregates[$teamId] = [
                'total_spirit_score' => 0,
                'total_games_played' => 0,
                'completed_games_played' => 0,
            ];
        }

        foreach ($completedMatches as $match) {
            foreach ([true, false] as $isHome) {
                $teamId = $isHome
                    ? (int) ($match->homeRegistration?->team_id ?? 0)
                    : (int) ($match->awayRegistration?->team_id ?? 0);

                if ($teamId <= 0 || ! isset($aggregates[$teamId])) {
                    continue;
                }

                $aggregates[$teamId]['completed_games_played']++;

                $breakdown = MatchSpiritScores::receivedBreakdownForMatchSide($match, $isHome);

                if ($breakdown === null) {
                    continue;
                }

                $aggregates[$teamId]['total_spirit_score'] += $breakdown['total'];
                $aggregates[$teamId]['total_games_played']++;
            }
        }

        return collect($aggregates)
            ->map(function (array $stats, int $teamId) use ($registrationByTeamId): ?array {
                if ($stats['total_games_played'] <= 0) {
                    return null;
                }

                $registration = $registrationByTeamId->get($teamId);

                if ($registration === null) {
                    return null;
                }

                $gamesPlayed = $stats['total_games_played'];
                $totalSpirit = $stats['total_spirit_score'];

                return [
                    'registration' => $registration,
                    'team' => $registration->team,
                    'total_spirit_score' => $totalSpirit,
                    'total_games_played' => $gamesPlayed,
                    'completed_games_played' => $stats['completed_games_played'],
                    'average_spirit_score' => $gamesPlayed > 0
                        ? round($totalSpirit / $gamesPlayed, 2)
                        : null,
                ];
            })
            ->filter()
            ->sort(self::defaultSortComparator())
            ->values();
    }

    /**
     * @return callable(array, array): int
     */
    private static function defaultSortComparator(): callable
    {
        return function (array $left, array $right): int {
            $comparison = self::compareDescending(
                $left['average_spirit_score'] ?? 0.0,
                $right['average_spirit_score'] ?? 0.0,
            );

            if ($comparison !== 0) {
                return $comparison;
            }

            $comparison = self::compareDescending(
                $left['total_spirit_score'],
                $right['total_spirit_score'],
            );

            if ($comparison !== 0) {
                return $comparison;
            }

            $comparison = self::compareDescending(
                $left['total_games_played'],
                $right['total_games_played'],
            );

            if ($comparison !== 0) {
                return $comparison;
            }

            return strcasecmp($left['team']->name ?? '', $right['team']->name ?? '');
        };
    }

    private static function compareDescending(float|int $left, float|int $right): int
    {
        if ($left === $right) {
            return 0;
        }

        return $left < $right ? 1 : -1;
    }
}
