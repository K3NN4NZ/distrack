<?php

namespace App\Support;

use App\Models\MatchPlayerStat;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TournamentMatch;
use App\Models\TournamentRegistration;

final class MatchMvp
{
    /**
     * Best player MVP for one match registration (team roster), or null when no stats exist.
     *
     * @return array{player: TeamMember, team: Team, scores: int, assists: int, blocks: int, total: int}|null
     */
    public static function forRegistration(TournamentMatch $match, TournamentRegistration $registration): ?array
    {
        $team = $registration->team;

        if ($team === null) {
            return null;
        }

        $stat = $match->playerStats
            ->filter(fn (MatchPlayerStat $playerStat): bool => (int) $playerStat->teamMember?->team_id === (int) $team->id)
            ->sortBy(fn (MatchPlayerStat $playerStat): array => [
                -self::totalScore($playerStat),
                -self::scores($playerStat),
                -self::assists($playerStat),
                -self::blocks($playerStat),
                strtolower((string) ($playerStat->teamMember?->name ?? '')),
            ])
            ->first();

        if ($stat === null || $stat->teamMember === null) {
            return null;
        }

        return self::fromStat($stat, $team);
    }

    public static function presentationForMatch(TournamentMatch $match): MatchMvpPresentation
    {
        if (! $match->isCompletedMatchStatus()) {
            return new MatchMvpPresentation(MatchMvpPresentation::STATUS_NOT_COMPLETED);
        }

        $homeScore = $match->home_score;
        $awayScore = $match->away_score;

        if ($homeScore === null || $awayScore === null || (int) $homeScore === (int) $awayScore) {
            return new MatchMvpPresentation(MatchMvpPresentation::STATUS_PENDING);
        }

        $homeWon = (int) $homeScore > (int) $awayScore;
        $winningRegistration = $homeWon ? $match->homeRegistration : $match->awayRegistration;
        $losingRegistration = $homeWon ? $match->awayRegistration : $match->homeRegistration;

        $winningMvp = $winningRegistration
            ? self::forRegistration($match, $winningRegistration)
            : null;
        $losingMvp = $losingRegistration
            ? self::forRegistration($match, $losingRegistration)
            : null;

        if ($winningMvp === null && $losingMvp === null) {
            return new MatchMvpPresentation(MatchMvpPresentation::STATUS_NO_DATA);
        }

        return new MatchMvpPresentation(
            MatchMvpPresentation::STATUS_READY,
            $winningMvp,
            $losingMvp,
        );
    }

    /**
     * @return array{player: TeamMember, team: Team, scores: int, assists: int, blocks: int, total: int}
     */
    private static function fromStat(MatchPlayerStat $stat, Team $team): array
    {
        $scores = self::scores($stat);
        $assists = self::assists($stat);
        $blocks = self::blocks($stat);

        return [
            'player' => $stat->teamMember,
            'team' => $team,
            'scores' => $scores,
            'assists' => $assists,
            'blocks' => $blocks,
            'total' => $scores + $assists + $blocks,
        ];
    }

    private static function scores(MatchPlayerStat $stat): int
    {
        $goals = $stat->getAttribute('scores') ?? $stat->goals;

        return (int) $goals;
    }

    private static function assists(MatchPlayerStat $stat): int
    {
        return (int) $stat->assists;
    }

    private static function blocks(MatchPlayerStat $stat): int
    {
        return (int) $stat->blocks;
    }

    private static function totalScore(MatchPlayerStat $stat): int
    {
        return self::scores($stat) + self::assists($stat) + self::blocks($stat);
    }
}
