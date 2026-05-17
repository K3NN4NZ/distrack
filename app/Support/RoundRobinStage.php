<?php

namespace App\Support;

use App\Models\Tournament;
use App\Models\TournamentMatch;
use Illuminate\Support\Collection;

final class RoundRobinStage
{
    /**
     * Whether a match stage string represents Round Robin pool play (not bracket/placement games).
     */
    public static function isRoundRobinStage(?string $stage): bool
    {
        $normalized = strtolower(trim((string) $stage));
        $normalized = (string) preg_replace('/_+/', '_', str_replace(['-', ' '], '_', $normalized));

        if ($normalized === '') {
            return false;
        }

        if (in_array($normalized, ['round_robin'], true)) {
            return true;
        }

        if (str_replace('_', '', $normalized) === 'roundrobin') {
            return true;
        }

        if (str_contains($normalized, 'round_robin')) {
            return true;
        }

        return str_contains($normalized, 'round') && str_contains($normalized, 'robin');
    }

    /**
     * @return Collection<int, TournamentMatch>
     */
    public static function completedMatchesForTournament(Tournament $tournament): Collection
    {
        return $tournament->matches
            ->filter(fn (TournamentMatch $match): bool => self::isRoundRobinStage($match->stage)
                && $match->status === 'completed')
            ->values();
    }
}
