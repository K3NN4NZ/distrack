<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\TournamentMatch;

/**
 * Shared Tailwind classes for home/away team boxes on admin match cards when a result exists.
 */
final class MatchTeamBoxResultPresentation
{
    private const BASE = 'rounded-lg border-2 px-3 py-2.5 min-w-0';

    private const NEUTRAL = 'border-neutral-200 bg-white text-zinc-900 dark:border-neutral-700 dark:bg-zinc-900 dark:text-white';

    private const WINNER = 'border-green-600 bg-[#86efac] text-zinc-900 shadow-sm dark:border-green-500 dark:bg-green-800/90 dark:text-white';

    private const LOSER = 'border-red-600 bg-red-200 text-zinc-900 shadow-sm dark:border-red-500 dark:bg-red-950/75 dark:text-red-50';

    private const TIE = 'border-amber-400 bg-amber-50 text-zinc-900 dark:border-amber-600 dark:bg-amber-950/45 dark:text-amber-50';

    /**
     * Tailwind class string for the home or away team panel on a match card.
     *
     * @param  'home'|'away'  $side
     */
    public static function teamBoxClasses(TournamentMatch $match, string $side): string
    {
        if ($match->status !== 'completed' || $match->home_score === null || $match->away_score === null) {
            return self::BASE.' '.self::NEUTRAL;
        }

        $home = (int) $match->home_score;
        $away = (int) $match->away_score;

        if ($home === $away) {
            return self::BASE.' '.self::TIE;
        }

        $homeWon = $home > $away;

        if ($side === 'home') {
            return self::BASE.' '.($homeWon ? self::WINNER : self::LOSER);
        }

        return self::BASE.' '.($homeWon ? self::LOSER : self::WINNER);
    }
}
