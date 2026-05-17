<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Http\Controllers\Admin\TournamentController;
use App\Models\MatchPlayerStat;
use App\Models\MatchScoreLog;
use App\Models\TeamMember;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Support\SmallDayTwoKnockoutBracket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Seeds per-roster {@see MatchPlayerStat} rows (same source as the admin scoring form) for:
 *
 * - {@code stage = round_robin} matches on the tournament
 * - Quarter finals ({@code stage = quarterfinal})
 * - Ranking Path games **41–42** when {@code stage} uses {@see SmallDayTwoKnockoutBracket::rankingPathStageAliases}
 * - Semi-finals **43–44** ({@see SmallDayTwoKnockoutBracket::semiFinalStageAliases})
 * - Placement ladder **45–47** (same ranking-path / placement alias stages as other Day 2 ladder rows)
 * - Championship **48** ({@see SmallDayTwoKnockoutBracket::championshipStageAliases})
 *
 * Admin UI: {@code /admin/tournaments?tournament=1&tab=quarter-final} (knockout cards read {@code matches.home_score}/{@code away_score},
 * derived here from summed player {@code goals}).
 *
 * Player goals on each side sum to that side's match total when finals exist; otherwise totals
 * are chosen randomly up to {@see self::MAX_SIDE_GOALS} and split across roster members only.
 *
 * Existing point-by-point {@see MatchScoreLog} rows for each seeded match are removed
 * so the scoreline stays consistent with summed player goals (the app supports manual scorelines
 * without logs, or log-driven timelines — we pick the player-stat path used by
 * {@see TournamentController::syncMatchScoreFromPlayerStats}).
 *
 * Run: {@code php artisan db:seed --class=TournamentGameRosterScoreSeeder}
 */
class TournamentGameRosterScoreSeeder extends Seeder
{
    /**
     * Tournament to seed (default {@code 1} per project convention).
     */
    public int $tournamentId = 5;

    /**
     * Maximum goals per side when the match has no final {@code home_score}/{@code away_score} yet.
     */
    private const MAX_SIDE_GOALS = 30;

    public function run(): void
    {
        $tournament = Tournament::query()->find($this->tournamentId);

        if ($tournament === null) {
            $this->command?->warn("Tournament {$this->tournamentId} not found — skipping.");

            return;
        }

        $rankingPathStages = SmallDayTwoKnockoutBracket::rankingPathStageAliases();
        $semiFinalStages = SmallDayTwoKnockoutBracket::semiFinalStageAliases();
        $championshipStages = SmallDayTwoKnockoutBracket::championshipStageAliases();

        $matches = TournamentMatch::query()
            ->where('tournament_id', $tournament->id)
            ->where(function (Builder $query) use ($rankingPathStages, $semiFinalStages, $championshipStages): void {
                $query->where('stage', 'quarterfinal')
                    ->orWhere('stage', 'round_robin')
                    ->orWhere(function (Builder $q) use ($rankingPathStages): void {
                        $q->whereIn('match_number', [41, 42])
                            ->whereIn('stage', $rankingPathStages);
                    })
                    ->orWhere(function (Builder $q) use ($semiFinalStages): void {
                        $q->whereIn('match_number', [43, 44])
                            ->whereIn('stage', $semiFinalStages);
                    })
                    ->orWhere(function (Builder $q) use ($rankingPathStages): void {
                        $q->whereIn('match_number', [45, 46, 47])
                            ->whereIn('stage', $rankingPathStages);
                    })
                    ->orWhere(function (Builder $q) use ($championshipStages): void {
                        $q->where('match_number', 48)
                            ->whereIn('stage', $championshipStages);
                    });
            })
            ->with([
                'homeRegistration.team.members',
                'awayRegistration.team.members',
            ])
            ->orderByRaw("case stage when 'round_robin' then 0 when 'quarterfinal' then 1 else 2 end")
            ->orderBy('match_number')
            ->orderBy('id')
            ->get();

        if ($matches->isEmpty()) {
            $this->command?->warn('No seedable matches found (round robin, quarter-final, Ranking Path 41–42, semis 43–44, placement 45–47, or championship 48) — skipping.');
            $this->reportChampionshipGame48SeedSummary($tournament);

            return;
        }

        foreach ($matches as $match) {
            try {
                DB::transaction(function () use ($tournament, $match): void {
                    $this->seedMatchRosterStats($tournament, $match);
                });
            } catch (\Throwable $e) {
                $this->command?->error("Match {$match->id}: {$e->getMessage()}");
            }
        }

        $this->command?->info(sprintf(
            'TournamentGameRosterScoreSeeder finished for tournament %d (%d matches: round robin + quarter-finals + Day 2 ladder 41–48 where present).',
            $tournament->id,
            $matches->count(),
        ));

        $this->reportChampionshipGame48SeedSummary($tournament);
    }

    private function reportChampionshipGame48SeedSummary(Tournament $tournament): void
    {
        $aliases = SmallDayTwoKnockoutBracket::championshipStageAliases();

        $match = TournamentMatch::query()
            ->with(['homeRegistration.team', 'awayRegistration.team'])
            ->where('tournament_id', $tournament->id)
            ->where('match_number', 48)
            ->first([
                'id',
                'match_number',
                'stage',
                'status',
                'home_registration_id',
                'away_registration_id',
                'home_score',
                'away_score',
                'round_label',
                'scheduled_at',
            ]);

        $this->command?->newLine();
        $this->command?->info('── Championship Game 48 (after roster seed pass) ──');

        if ($match === null) {
            $this->command?->line('Found: no');

            return;
        }

        $match->refresh();

        $this->command?->line('Found: yes (id='.$match->id.')');
        $this->command?->line('stage: '.$match->stage.(in_array((string) $match->stage, $aliases, true) ? '' : ' (not in alias list: '.implode(', ', $aliases).')'));

        $homeAssigned = $match->home_registration_id !== null;
        $awayAssigned = $match->away_registration_id !== null;
        $this->command?->line('teams assigned: '.($homeAssigned && $awayAssigned ? 'yes' : 'no').
            ' (home_registration_id='.($match->home_registration_id ?? 'null').
            ', away_registration_id='.($match->away_registration_id ?? 'null').')');

        if ($homeAssigned && $awayAssigned) {
            $this->command?->line('home team: '.($match->homeRegistration?->team?->name ?? '?'));
            $this->command?->line('away team: '.($match->awayRegistration?->team?->name ?? '?'));
        }

        $statCount = MatchPlayerStat::query()->where('match_id', $match->id)->count();
        $this->command?->line('MatchPlayerStat rows for this match: '.$statCount);

        $hs = $match->home_score;
        $as = $match->away_score;
        $this->command?->line('home_score: '.($hs === null ? 'null' : (string) $hs).' | away_score: '.($as === null ? 'null' : (string) $as));
        $this->command?->line('status: '.$match->status);

        if ($homeAssigned && $awayAssigned && ($hs === null || $as === null)) {
            $this->command?->warn('Game 48 has registrations but null scoreline — open roster scoring for this match or ensure Games 43–44 are completed so bracket sync can assign winners before seeding.');
        }
    }

    private function seedMatchRosterStats(Tournament $tournament, TournamentMatch $match): void
    {
        if ($match->home_registration_id === null || $match->away_registration_id === null) {
            $this->command?->warn("Match {$match->id}: missing home or away registration — skip.");

            return;
        }

        $homeMembers = $match->homeRegistration?->team?->members ?? collect();
        $awayMembers = $match->awayRegistration?->team?->members ?? collect();

        if ($homeMembers->isEmpty() || $awayMembers->isEmpty()) {
            $this->command?->warn("Match {$match->id}: missing roster members on one or both teams — skip.");

            return;
        }

        /** @var Collection<int, TeamMember> $homeMembers */
        /** @var Collection<int, TeamMember> $awayMembers */
        $homeMembers = $homeMembers->values();
        $awayMembers = $awayMembers->values();

        $homeTeamId = (int) $match->homeRegistration->team_id;
        $awayTeamId = (int) $match->awayRegistration->team_id;

        $this->assertMembersBelongToTeam($homeMembers, $homeTeamId);
        $this->assertMembersBelongToTeam($awayMembers, $awayTeamId);

        if (in_array((int) ($match->match_number ?? 0), [41, 42], true)
            && in_array((string) $match->stage, SmallDayTwoKnockoutBracket::rankingPathStageAliases(), true)
        ) {
            $roundLabel = trim((string) ($match->round_label ?? ''));
            if ($roundLabel !== '' && ! str_contains(strtolower($roundLabel), 'ranking')) {
                $this->command?->warn("Match {$match->id} (game {$match->match_number}): round_label «{$roundLabel}» — expected Ranking Path / Ranking 21 wording for admin cards.");
            }
        }

        MatchScoreLog::query()->where('match_id', $match->id)->delete();

        $homeTarget = $this->resolveSideGoalTotal($match->home_score, self::MAX_SIDE_GOALS);
        $awayTarget = $this->resolveSideGoalTotal($match->away_score, self::MAX_SIDE_GOALS);

        $homeGoalsByMemberId = $this->distributeGoalsAcrossRoster($homeTarget, $homeMembers);
        $awayGoalsByMemberId = $this->distributeGoalsAcrossRoster($awayTarget, $awayMembers);

        $allowedIds = $homeMembers->pluck('id')->merge($awayMembers->pluck('id'))->all();

        MatchPlayerStat::query()
            ->where('match_id', $match->id)
            ->whereNotIn('team_member_id', $allowedIds)
            ->delete();

        foreach ($homeGoalsByMemberId as $memberId => $goals) {
            $this->writePlayerStatRow($match->id, (int) $memberId, (int) $goals);
        }

        foreach ($awayGoalsByMemberId as $memberId => $goals) {
            $this->writePlayerStatRow($match->id, (int) $memberId, (int) $goals);
        }

        $this->recomputeMatchScorelineFromPlayerGoals($match);

        $match->refresh();

        if ($match->home_registration_id !== null && $match->away_registration_id !== null) {
            $match->forceFill(['status' => 'completed'])->save();
        }

        if ($tournament->registrations()->count() < TournamentController::MINIMUM_BRACKET_TEAM_COUNT) {
            SmallDayTwoKnockoutBracket::syncAfterResultChange($tournament, $match->fresh());
        }
    }

    /**
     * @param  Collection<int, TeamMember>  $members
     */
    private function assertMembersBelongToTeam(Collection $members, int $teamId): void
    {
        foreach ($members as $member) {
            if ((int) $member->team_id !== $teamId) {
                throw new \RuntimeException("Team member {$member->id} does not belong to team {$teamId}.");
            }
        }
    }

    private function resolveSideGoalTotal(?int $existing, int $maxRandom): int
    {
        if ($existing !== null && $existing >= 0) {
            return $existing;
        }

        return random_int(0, $maxRandom);
    }

    /**
     * Split {@code $totalGoals} across roster players with random-ish weights; sums exactly to total.
     *
     * @param  Collection<int, TeamMember>  $members
     * @return array<int, int> team_member_id => goals
     */
    private function distributeGoalsAcrossRoster(int $totalGoals, Collection $members): array
    {
        $count = $members->count();
        if ($count === 0) {
            return [];
        }

        if ($totalGoals === 0) {
            return $members->mapWithKeys(fn (TeamMember $m): array => [$m->id => 0])->all();
        }

        $weights = [];
        for ($i = 0; $i < $count; $i++) {
            $weights[$i] = random_int(1, 1000);
        }
        $weightSum = array_sum($weights);
        $amounts = array_fill(0, $count, 0);
        $allocated = 0;

        foreach (range(0, $count - 1) as $i) {
            if ($i === $count - 1) {
                $amounts[$i] = max(0, $totalGoals - $allocated);
            } else {
                $share = (int) floor($totalGoals * $weights[$i] / $weightSum);
                $amounts[$i] = $share;
                $allocated += $share;
            }
        }

        $drift = $totalGoals - array_sum($amounts);
        $idx = 0;
        while ($drift !== 0 && $count > 0) {
            if ($drift > 0) {
                $amounts[$idx % $count]++;
                $drift--;
            } elseif ($amounts[$idx % $count] > 0) {
                $amounts[$idx % $count]--;
                $drift++;
            }
            $idx++;
            if ($idx > $count * 1000) {
                break;
            }
        }

        $out = [];
        foreach ($members->values() as $i => $member) {
            $out[$member->id] = $amounts[$i] ?? 0;
        }

        return $out;
    }

    private function writePlayerStatRow(int $matchId, int $teamMemberId, int $goals): void
    {
        $assists = random_int(0, min(5, max(0, $goals + 2)));
        $blocks = random_int(0, 3);

        if ($goals === 0 && $assists === 0 && $blocks === 0) {
            MatchPlayerStat::query()
                ->where('match_id', $matchId)
                ->where('team_member_id', $teamMemberId)
                ->delete();

            return;
        }

        MatchPlayerStat::query()->updateOrCreate(
            [
                'match_id' => $matchId,
                'team_member_id' => $teamMemberId,
            ],
            [
                'goals' => $goals,
                'assists' => $assists,
                'blocks' => $blocks,
            ],
        );
    }

    /**
     * Same rules as {@see TournamentController::syncMatchScoreFromPlayerStats} (goals-only scoreline).
     */
    private function recomputeMatchScorelineFromPlayerGoals(TournamentMatch $match): void
    {
        $match->loadMissing(['homeRegistration', 'awayRegistration']);

        $homeTeamId = $match->homeRegistration?->team_id;
        $awayTeamId = $match->awayRegistration?->team_id;

        $stats = MatchPlayerStat::query()
            ->with('teamMember:id,team_id')
            ->where('match_id', $match->id)
            ->get();

        $homeScore = $stats
            ->filter(fn (MatchPlayerStat $stat): bool => $stat->teamMember?->team_id === $homeTeamId)
            ->sum('goals');

        $awayScore = $stats
            ->filter(fn (MatchPlayerStat $stat): bool => $stat->teamMember?->team_id === $awayTeamId)
            ->sum('goals');

        $match->forceFill([
            'home_score' => (int) $homeScore,
            'away_score' => (int) $awayScore,
        ])->save();
    }
}
