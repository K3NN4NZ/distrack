<?php

namespace App\Http\Middleware;

use App\Models\TournamentMatch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureScorekeeperOwnsAssignedMatchPitch
{
    /**
     * Restrict scorekeepers to matches on pitches they manage, or on pitches with no
     * assigned scorekeeper (any scorekeeper may score those until an admin assigns one).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isScorekeeper()) {
            abort(403);
        }

        $match = $request->route('match');

        if (! $match instanceof TournamentMatch) {
            abort(403);
        }

        $match->loadMissing([
            'pitch:id,tournament_id,scorekeeper_user_id',
        ]);

        $tournament = $request->route('tournament');

        if ($tournament !== null && (int) $match->tournament_id !== (int) $tournament->getKey()) {
            abort(404);
        }

        if ($match->pitch_id === null) {
            abort(403);
        }

        $pitch = $match->pitch;

        if ($pitch === null || ! $pitch->allowsScorekeeperUserId((int) $user->id)) {
            abort(403);
        }

        return $next($request);
    }
}
