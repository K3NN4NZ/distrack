<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tournament;
use App\Models\TournamentMatch;

/**
 * @deprecated Use {@see TournamentBracketSyncer} — retained for call-site compatibility.
 */
final class TournamentBracketAdvancer
{
    public static function syncFromCompletedMatches(Tournament $tournament): void
    {
        app(TournamentBracketSyncer::class)->syncAll($tournament);
    }

    public static function resolveBracketMatchForGame(Tournament $tournament, int $gameNumber): ?TournamentMatch
    {
        return app(TournamentBracketSyncer::class)->resolveMatch($tournament, $gameNumber);
    }

    public static function winnerRegistrationId(TournamentMatch $match): ?int
    {
        return app(TournamentBracketSyncer::class)->winnerRegistrationId($match);
    }

    public static function loserRegistrationId(TournamentMatch $match): ?int
    {
        return app(TournamentBracketSyncer::class)->loserRegistrationId($match);
    }

    public static function matchHasDecisiveScoreline(TournamentMatch $match): bool
    {
        return app(TournamentBracketSyncer::class)->matchHasDecisiveScoreline($match);
    }
}
