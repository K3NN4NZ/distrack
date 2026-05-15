<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Controllers\Admin\TournamentController;
use App\Models\MatchPlayerStat;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Generic Day 2 knockout bracket sync (games 37–48) for any small tournament.
 */
final class TournamentBracketSyncer
{
    private const SLOT_PREFIX = '[[bracket-slot-';

    /** @var array<int, array{0: int, 1: int}> */
    private const QUARTER_FINAL_RANK_PAIRS = [
        37 => [1, 8],
        38 => [2, 7],
        39 => [3, 6],
        40 => [4, 5],
    ];

    public function syncAll(Tournament $tournament): void
    {
        if (! $this->appliesToTournament($tournament)) {
            return;
        }

        DB::transaction(function () use ($tournament): void {
            $this->syncQuarterFinalsFromStandings($tournament);
            $this->syncRanking21FromQuarterFinals($tournament);
            $this->syncSemiFinalsFromQuarterFinals($tournament);
            $this->syncPlacementFromRankingPath($tournament);
            $this->syncRanking34FromSemiFinals($tournament);
            $this->syncChampionshipFromSemiFinals($tournament);
        });
    }

    /**
     * @return array{
     *     updated: int,
     *     skipped_locked: int,
     *     skipped_missing_match: int,
     *     skipped_incomplete_standings: bool,
     *     skipped_insufficient_teams: bool,
     *     top_eight: list<array{rank: int, registration_id: int, team_name: string}>,
     * }
     */
    public function syncQuarterFinalsFromStandings(Tournament $tournament): array
    {
        if (! $this->appliesToTournament($tournament)) {
            return $this->emptyQuarterFinalResult();
        }

        $bundle = SmallTournamentTeamStanding::roundRobinTeamStanding($tournament);
        $standingsFinal = ($bundle['meta']['standings_status'] ?? '') === 'final';

        if (! $standingsFinal) {
            $this->debugLog($tournament, 'Quarter finals sync skipped: standings not final.', [
                'standings_status' => $bundle['meta']['standings_status'] ?? null,
            ]);

            return $this->emptyQuarterFinalResult(skippedIncompleteStandings: true);
        }

        $byRank = [];
        $topEightSummary = [];

        foreach ($bundle['rows']->values() as $row) {
            $rank = $row['rank'] ?? null;

            if (! is_int($rank) || $rank < 1 || $rank > 8) {
                continue;
            }

            $registrationId = (int) ($row['registration_id'] ?? 0);

            if ($registrationId < 1) {
                continue;
            }

            $byRank[$rank] = $registrationId;
            $topEightSummary[] = [
                'rank' => $rank,
                'registration_id' => $registrationId,
                'team_name' => (string) ($row['team_name'] ?? '—'),
            ];
        }

        usort($topEightSummary, static fn (array $a, array $b): int => $a['rank'] <=> $b['rank']);

        if (count($byRank) < 8) {
            $this->debugLog($tournament, 'Quarter finals sync skipped: fewer than 8 ranked teams.', [
                'ranks_found' => array_keys($byRank),
            ]);

            return $this->emptyQuarterFinalResult(skippedInsufficientTeams: true, topEight: $topEightSummary);
        }

        $updated = 0;
        $skippedLocked = 0;
        $skippedMissingMatch = 0;
        $defs = SmallDayTwoKnockoutBracket::gameDefinitions();

        foreach (self::QUARTER_FINAL_RANK_PAIRS as $gameNumber => [$homeRank, $awayRank]) {
            $homeId = $byRank[$homeRank] ?? null;
            $awayId = $byRank[$awayRank] ?? null;

            if (! $homeId || ! $awayId) {
                continue;
            }

            $stage = $defs[$gameNumber]['stage'] ?? 'quarterfinal';
            $assigned = $this->assignMatchPair(
                $tournament,
                $gameNumber,
                $homeId,
                $awayId,
                $stage,
                "standings:{$homeRank}v{$awayRank}",
            );

            if ($assigned === 'updated') {
                $updated++;
            } elseif ($assigned === 'locked') {
                $skippedLocked++;
            } else {
                $skippedMissingMatch++;
            }
        }

        $this->debugLog($tournament, 'Quarter finals sync finished.', [
            'updated' => $updated,
            'skipped_locked' => $skippedLocked,
            'skipped_missing_match' => $skippedMissingMatch,
            'top_eight' => $topEightSummary,
        ]);

        return [
            'updated' => $updated,
            'skipped_locked' => $skippedLocked,
            'skipped_missing_match' => $skippedMissingMatch,
            'skipped_incomplete_standings' => false,
            'skipped_insufficient_teams' => false,
            'top_eight' => $topEightSummary,
        ];
    }

    public function syncRanking21FromQuarterFinals(Tournament $tournament): void
    {
        $this->syncFromSources($tournament, 41, 37, 'loser', 40, 'loser');
        $this->syncFromSources($tournament, 42, 38, 'loser', 39, 'loser');
    }

    public function syncSemiFinalsFromQuarterFinals(Tournament $tournament): void
    {
        $this->syncFromSources($tournament, 43, 37, 'winner', 40, 'winner');
        $this->syncFromSources($tournament, 44, 38, 'winner', 39, 'winner');
    }

    public function syncPlacementFromRankingPath(Tournament $tournament): void
    {
        $this->syncFromSources($tournament, 45, 41, 'winner', 42, 'winner');
        $this->syncFromSources($tournament, 46, 41, 'loser', 42, 'loser');
    }

    public function syncRanking34FromSemiFinals(Tournament $tournament): void
    {
        $this->syncFromSources($tournament, 47, 43, 'loser', 44, 'loser');
    }

    public function syncChampionshipFromSemiFinals(Tournament $tournament): void
    {
        $this->syncFromSources($tournament, 48, 43, 'winner', 44, 'winner');
    }

    public function winnerRegistrationId(TournamentMatch $match): ?int
    {
        $match = $this->matchWithResolvedScoreline($match);

        if ($match->home_registration_id === null || $match->away_registration_id === null) {
            return null;
        }

        if (! $this->matchHasDecisiveScoreline($match)) {
            return null;
        }

        if ((int) $match->home_score > (int) $match->away_score) {
            return (int) $match->home_registration_id;
        }

        if ((int) $match->away_score > (int) $match->home_score) {
            return (int) $match->away_registration_id;
        }

        return null;
    }

    public function loserRegistrationId(TournamentMatch $match): ?int
    {
        $match = $this->matchWithResolvedScoreline($match);

        if ($match->home_registration_id === null || $match->away_registration_id === null) {
            return null;
        }

        if (! $this->matchHasDecisiveScoreline($match)) {
            return null;
        }

        if ((int) $match->home_score > (int) $match->away_score) {
            return (int) $match->away_registration_id;
        }

        if ((int) $match->away_score > (int) $match->home_score) {
            return (int) $match->home_registration_id;
        }

        return null;
    }

    public function resolveMatch(Tournament $tournament, int $gameNumber): ?TournamentMatch
    {
        $defs = SmallDayTwoKnockoutBracket::gameDefinitions();
        $canonicalStage = $defs[$gameNumber]['stage'] ?? null;
        $marker = SmallDayTwoKnockoutBracket::marker($gameNumber);

        $candidates = TournamentMatch::query()
            ->where('tournament_id', $tournament->id)
            ->where('match_number', $gameNumber)
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates
            ->sortBy(fn (TournamentMatch $match): array => [
                $this->matchHasDecisiveScoreline($match) ? 0 : 1,
                str_contains((string) ($match->notes ?? ''), $marker) ? 0 : 1,
                $canonicalStage !== null && (string) $match->stage === $canonicalStage ? 0 : 1,
                $match->id,
            ])
            ->first();
    }

    public function matchHasDecisiveScoreline(TournamentMatch $match): bool
    {
        if ($match->status !== TournamentMatch::STATUS_COMPLETED && $match->status !== TournamentMatch::STATUS_LIVE) {
            return false;
        }

        if ($match->home_score === null || $match->away_score === null) {
            return false;
        }

        return (int) $match->home_score !== (int) $match->away_score;
    }

    public function matchTeamSlotsAreLocked(TournamentMatch $match): bool
    {
        if ($match->home_registration_id === null || $match->away_registration_id === null) {
            return false;
        }

        return $match->status === TournamentMatch::STATUS_COMPLETED
            || $match->home_score !== null
            || $match->away_score !== null;
    }

    private function appliesToTournament(Tournament $tournament): bool
    {
        return $tournament->registrations()->count() < TournamentController::MINIMUM_BRACKET_TEAM_COUNT;
    }

    private function syncFromSources(
        Tournament $tournament,
        int $targetGame,
        int $homeSourceGame,
        string $homeRole,
        int $awaySourceGame,
        string $awayRole,
    ): void {
        if (! $this->appliesToTournament($tournament)) {
            return;
        }

        $homeSource = $this->resolveMatch($tournament, $homeSourceGame);
        $awaySource = $this->resolveMatch($tournament, $awaySourceGame);

        if ($homeSource === null || $awaySource === null) {
            $this->debugLog($tournament, 'Bracket sync skipped: source match missing.', [
                'target_game' => $targetGame,
                'home_source' => $homeSourceGame,
                'away_source' => $awaySourceGame,
            ]);

            return;
        }

        $homeId = $homeRole === 'winner'
            ? $this->winnerRegistrationId($homeSource)
            : $this->loserRegistrationId($homeSource);

        $awayId = $awayRole === 'winner'
            ? $this->winnerRegistrationId($awaySource)
            : $this->loserRegistrationId($awaySource);

        $this->debugLog($tournament, 'Bracket sync resolved source results.', [
            'target_game' => $targetGame,
            'home_source_game' => $homeSourceGame,
            'home_source_status' => $homeSource->status,
            'home_source_score' => "{$homeSource->home_score}-{$homeSource->away_score}",
            'home_role' => $homeRole,
            'home_registration_id' => $homeId,
            'away_source_game' => $awaySourceGame,
            'away_source_status' => $awaySource->status,
            'away_source_score' => "{$awaySource->home_score}-{$awaySource->away_score}",
            'away_role' => $awayRole,
            'away_registration_id' => $awayId,
        ]);

        if (! $homeId || ! $awayId || $homeId === $awayId) {
            return;
        }

        $defs = SmallDayTwoKnockoutBracket::gameDefinitions();
        $stage = $defs[$targetGame]['stage'] ?? null;

        $this->assignMatchPair(
            $tournament,
            $targetGame,
            $homeId,
            $awayId,
            $stage,
            ($homeRole === 'winner' ? 'W' : 'L').$homeSourceGame.'/'.($awayRole === 'winner' ? 'W' : 'L').$awaySourceGame,
            stampSlots: true,
            homeSourceKey: ($homeRole === 'winner' ? 'W' : 'L').$homeSourceGame,
            awaySourceKey: ($awayRole === 'winner' ? 'W' : 'L').$awaySourceGame,
        );
    }

    /**
     * @return 'updated'|'locked'|'missing'
     */
    private function assignMatchPair(
        Tournament $tournament,
        int $gameNumber,
        int $homeRegistrationId,
        int $awayRegistrationId,
        ?string $stage,
        string $reason,
        bool $stampSlots = false,
        ?string $homeSourceKey = null,
        ?string $awaySourceKey = null,
    ): string {
        $target = $this->resolveMatch($tournament, $gameNumber);

        if ($target === null) {
            $this->debugLog($tournament, 'Bracket sync skipped: target match missing.', [
                'target_game' => $gameNumber,
                'reason' => $reason,
            ]);

            return 'missing';
        }

        if ($this->matchTeamSlotsAreLocked($target)) {
            $this->debugLog($tournament, 'Bracket sync skipped: target locked.', [
                'target_game' => $gameNumber,
                'match_id' => $target->id,
                'reason' => $reason,
            ]);

            return 'locked';
        }

        $payload = [
            'home_registration_id' => $homeRegistrationId,
            'away_registration_id' => $awayRegistrationId,
        ];

        if ($stage !== null && $stage !== '') {
            $payload['stage'] = $stage;
        }

        if ($stampSlots && $homeSourceKey !== null && $awaySourceKey !== null) {
            $notes = (string) ($target->notes ?? '');
            $notes = $this->stampSlotSourceNotes($notes, 'home', $homeSourceKey);
            $notes = $this->stampSlotSourceNotes($notes, 'away', $awaySourceKey);
            $payload['notes'] = $notes;
        }

        $target->forceFill($payload)->save();

        $this->debugLog($tournament, 'Bracket sync updated target match.', [
            'target_game' => $gameNumber,
            'match_id' => $target->id,
            'home_registration_id' => $homeRegistrationId,
            'away_registration_id' => $awayRegistrationId,
            'reason' => $reason,
        ]);

        return 'updated';
    }

    private function matchWithResolvedScoreline(TournamentMatch $match): TournamentMatch
    {
        if ($match->home_score !== null && $match->away_score !== null) {
            return $match;
        }

        if ($match->home_registration_id === null || $match->away_registration_id === null) {
            return $match;
        }

        $match->loadMissing(['homeRegistration.team', 'awayRegistration.team']);

        $homeTeamId = $match->homeRegistration?->team_id;
        $awayTeamId = $match->awayRegistration?->team_id;

        if ($homeTeamId === null || $awayTeamId === null) {
            return $match;
        }

        $stats = MatchPlayerStat::query()
            ->with('teamMember:id,team_id')
            ->where('match_id', $match->id)
            ->get();

        if ($stats->isEmpty()) {
            return $match;
        }

        $homeScore = (int) $stats
            ->filter(fn (MatchPlayerStat $stat): bool => (int) ($stat->teamMember?->team_id ?? 0) === (int) $homeTeamId)
            ->sum('goals');

        $awayScore = (int) $stats
            ->filter(fn (MatchPlayerStat $stat): bool => (int) ($stat->teamMember?->team_id ?? 0) === (int) $awayTeamId)
            ->sum('goals');

        if ($homeScore === (int) ($match->home_score ?? -1) && $awayScore === (int) ($match->away_score ?? -1)) {
            return $match;
        }

        $match->forceFill([
            'home_score' => $homeScore,
            'away_score' => $awayScore,
        ])->save();

        $tournament = $match->relationLoaded('tournament')
            ? $match->tournament
            : Tournament::query()->find($match->tournament_id);

        $this->debugLog($tournament, 'Recomputed match scoreline from player stats.', [
            'match_id' => $match->id,
            'match_number' => $match->match_number,
            'home_score' => $homeScore,
            'away_score' => $awayScore,
        ]);

        return $match->fresh() ?? $match;
    }

    private function stampSlotSourceNotes(string $notes, string $side, string $sourceKey): string
    {
        $token = self::SLOT_PREFIX.$side.'='.$sourceKey.']]';

        if (str_contains($notes, $token)) {
            return $notes;
        }

        return rtrim($notes).$token;
    }

    /**
     * @param  list<array{rank: int, registration_id: int, team_name: string}>  $topEight
     * @return array{
     *     updated: int,
     *     skipped_locked: int,
     *     skipped_missing_match: int,
     *     skipped_incomplete_standings: bool,
     *     skipped_insufficient_teams: bool,
     *     top_eight: list<array{rank: int, registration_id: int, team_name: string}>,
     * }
     */
    private function emptyQuarterFinalResult(
        bool $skippedIncompleteStandings = false,
        bool $skippedInsufficientTeams = false,
        array $topEight = [],
    ): array {
        return [
            'updated' => 0,
            'skipped_locked' => 0,
            'skipped_missing_match' => 0,
            'skipped_incomplete_standings' => $skippedIncompleteStandings,
            'skipped_insufficient_teams' => $skippedInsufficientTeams,
            'top_eight' => $topEight,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function debugLog(?Tournament $tournament, string $message, array $context = []): void
    {
        if (! config('app.debug')) {
            return;
        }

        $payload = $context;

        if ($tournament !== null) {
            $payload['tournament_id'] = $tournament->id;
        }

        Log::debug($message, $payload);
    }
}
