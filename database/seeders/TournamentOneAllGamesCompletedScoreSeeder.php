<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MatchPlayerStat;
use App\Models\MatchScoreLog;
use App\Models\MatchSpiritScore;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Support\SmallDayTwoKnockoutBracket;
use App\Support\TournamentBracketAdvancer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Completes tournament #1 (games 1–48) with match scorelines, roster player stats, and spirit scores.
 *
 * Prerequisite: {@see ExactTournamentOneScheduleSeeder}
 *
 * Run: php artisan db:seed --class=TournamentOneAllGamesCompletedScoreSeeder
 *
 * WARNING: Local/dev/demo only. Overwrites scores for tournament 1 when re-run.
 */
final class TournamentOneAllGamesCompletedScoreSeeder extends Seeder
{
    public const TOURNAMENT_ID = 1;

    private const NOTES_SPIRIT = 'Seeded by TournamentOneAllGamesCompletedScoreSeeder';

    /** @var array<int, array{home: int, away: int}> */
    private const PRESET_TOTALS = [
        37 => ['home' => 27, 'away' => 24],
        38 => ['home' => 21, 'away' => 7],
        39 => ['home' => 26, 'away' => 14],
        40 => ['home' => 22, 'away' => 17],
    ];

    private int $matchesCompleted = 0;

    private int $playerStatRows = 0;

    private int $spiritRows = 0;

    private int $matchesSkipped = 0;

    public function run(): void
    {
        if (app()->environment('production')) {
            if ($this->command === null || ! $this->command->confirm(
                'This will overwrite scores for tournament '.self::TOURNAMENT_ID.'. Continue?',
                false,
            )) {
                $this->command?->warn('Aborted (production guard).');

                return;
            }
        }

        $tournament = Tournament::query()->find(self::TOURNAMENT_ID);

        if ($tournament === null) {
            $this->command?->error('Tournament '.self::TOURNAMENT_ID.' not found. Run ExactTournamentOneScheduleSeeder first.');

            return;
        }

        DB::transaction(function () use ($tournament): void {
            $this->seedRoundRobinPhase($tournament);
            SmallDayTwoKnockoutBracket::sync($tournament);
            $this->seedMatchNumbers($tournament, [37, 38, 39, 40]);
            TournamentBracketAdvancer::syncFromCompletedMatches($tournament);
            $this->seedMatchNumbers($tournament, [41, 42]);
            TournamentBracketAdvancer::syncFromCompletedMatches($tournament);
            $this->seedMatchNumbers($tournament, [43, 44]);
            TournamentBracketAdvancer::syncFromCompletedMatches($tournament);
            $this->seedMatchNumbers($tournament, [45, 46, 47, 48]);
            TournamentBracketAdvancer::syncFromCompletedMatches($tournament);
        });

        $this->command?->info('TournamentOneAllGamesCompletedScoreSeeder finished.');
        $this->command?->table(
            ['Metric', 'Count'],
            [
                ['Matches completed', $this->matchesCompleted],
                ['Matches skipped (no teams / placeholder)', $this->matchesSkipped],
                ['MatchPlayerStat rows touched', $this->playerStatRows],
                ['MatchSpiritScore rows touched', $this->spiritRows],
            ],
        );
    }

    private function seedRoundRobinPhase(Tournament $tournament): void
    {
        $matches = TournamentMatch::query()
            ->where('tournament_id', $tournament->id)
            ->where('stage', 'round_robin')
            ->whereBetween('match_number', [1, 36])
            ->orderBy('match_number')
            ->orderBy('id')
            ->get();

        foreach ($matches as $match) {
            $this->completeMatchWithGeneratedTotals($tournament, $match, $this->randomRoundRobinTotals());
        }
    }

    /**
     * @param  list<int>  $gameNumbers
     */
    private function seedMatchNumbers(Tournament $tournament, array $gameNumbers): void
    {
        foreach ($gameNumbers as $gameNumber) {
            $match = $this->resolveMatch($tournament, $gameNumber);

            if ($match === null) {
                $this->command?->warn("Game {$gameNumber}: match row not found — skip.");
                $this->matchesSkipped++;

                continue;
            }

            $totals = self::PRESET_TOTALS[$gameNumber] ?? $this->randomKnockoutTotals();

            $this->completeMatchWithGeneratedTotals($tournament, $match, $totals);
        }
    }

    /**
     * @param  array{home: int, away: int}  $totals
     */
    private function completeMatchWithGeneratedTotals(Tournament $tournament, TournamentMatch $match, array $totals): void
    {
        $match->refresh();
        $match->loadMissing([
            'homeRegistration.team.members',
            'awayRegistration.team.members',
        ]);

        if ($match->home_registration_id === null || $match->away_registration_id === null) {
            $this->command?->warn("Game {$match->match_number}: teams not assigned — skip.");
            $this->matchesSkipped++;

            return;
        }

        $homeGoals = max(0, (int) $totals['home']);
        $awayGoals = max(0, (int) $totals['away']);

        if ($homeGoals === $awayGoals) {
            $homeGoals++;
        }

        MatchScoreLog::query()->where('match_id', $match->id)->delete();

        $this->seedPlayerStatsForMatch($match, $homeGoals, $awayGoals);
        $this->recomputeMatchScorelineFromPlayerGoals($match);
        $this->seedSpiritScoresForMatch($tournament, $match);

        $match->refresh();

        if ($match->home_score === null || $match->away_score === null) {
            $match->forceFill([
                'home_score' => $homeGoals,
                'away_score' => $awayGoals,
            ])->save();
        }

        $match->forceFill(['status' => TournamentMatch::STATUS_COMPLETED])->save();

        $this->matchesCompleted++;
    }

    private function seedPlayerStatsForMatch(TournamentMatch $match, int $homeGoals, int $awayGoals): void
    {
        $homeMembers = $match->homeRegistration?->team?->members ?? collect();
        $awayMembers = $match->awayRegistration?->team?->members ?? collect();

        if ($homeMembers->isNotEmpty()) {
            $homeDistribution = $this->distributeGoalsAcrossRoster($homeGoals, $homeMembers->values());
            foreach ($homeDistribution as $memberId => $goals) {
                $this->writePlayerStatRow($match->id, (int) $memberId, (int) $goals);
            }
        }

        if ($awayMembers->isNotEmpty()) {
            $awayDistribution = $this->distributeGoalsAcrossRoster($awayGoals, $awayMembers->values());
            foreach ($awayDistribution as $memberId => $goals) {
                $this->writePlayerStatRow($match->id, (int) $memberId, (int) $goals);
            }
        }

        $allowedIds = $homeMembers->pluck('id')->merge($awayMembers->pluck('id'))->all();

        if ($allowedIds !== []) {
            MatchPlayerStat::query()
                ->where('match_id', $match->id)
                ->whereNotIn('team_member_id', $allowedIds)
                ->delete();
        }
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

        $this->playerStatRows++;
    }

    private function recomputeMatchScorelineFromPlayerGoals(TournamentMatch $match): void
    {
        $match->loadMissing(['homeRegistration', 'awayRegistration']);

        $homeTeamId = $match->homeRegistration?->team_id;
        $awayTeamId = $match->awayRegistration?->team_id;

        $stats = MatchPlayerStat::query()
            ->with('teamMember:id,team_id')
            ->where('match_id', $match->id)
            ->get();

        $homeScore = (int) $stats
            ->filter(fn (MatchPlayerStat $stat): bool => $stat->teamMember?->team_id === $homeTeamId)
            ->sum('goals');

        $awayScore = (int) $stats
            ->filter(fn (MatchPlayerStat $stat): bool => $stat->teamMember?->team_id === $awayTeamId)
            ->sum('goals');

        if ($homeScore === $awayScore && ($homeScore > 0 || $awayScore > 0)) {
            $homeScore++;
        }

        $match->forceFill([
            'home_score' => $homeScore,
            'away_score' => $awayScore,
        ])->save();
    }

    private function seedSpiritScoresForMatch(Tournament $tournament, TournamentMatch $match): void
    {
        $homeTeam = $match->homeRegistration?->team;
        $awayTeam = $match->awayRegistration?->team;

        if ($homeTeam === null || $awayTeam === null) {
            return;
        }

        $homeBundle = $this->randomSpiritBundle();
        $awayBundle = $this->randomSpiritBundle();

        MatchSpiritScore::query()->updateOrCreate(
            [
                'match_id' => $match->id,
                'scored_team_id' => $homeTeam->id,
            ],
            [
                'tournament_id' => $tournament->id,
                'scoring_team_id' => $awayTeam->id,
                'spirit_captain_id' => $this->spiritCaptainMemberId($homeTeam),
                ...$homeBundle,
                'notes' => self::NOTES_SPIRIT,
            ],
        );
        $this->spiritRows++;

        MatchSpiritScore::query()->updateOrCreate(
            [
                'match_id' => $match->id,
                'scored_team_id' => $awayTeam->id,
            ],
            [
                'tournament_id' => $tournament->id,
                'scoring_team_id' => $homeTeam->id,
                'spirit_captain_id' => $this->spiritCaptainMemberId($awayTeam),
                ...$awayBundle,
                'notes' => self::NOTES_SPIRIT,
            ],
        );
        $this->spiritRows++;
    }

    /**
     * @return array{
     *     knowledge_rules_score: int,
     *     fouls_body_contact_score: int,
     *     fair_mindedness_score: int,
     *     positive_attitude_score: int,
     *     communication_respect_score: int,
     *     total_score: int,
     * }
     */
    private function randomSpiritBundle(): array
    {
        $knowledge = $this->randomSpiritCriterion();
        $fouls = $this->randomSpiritCriterion();
        $fair = $this->randomSpiritCriterion();
        $attitude = $this->randomSpiritCriterion();
        $communication = $this->randomSpiritCriterion();

        return [
            'knowledge_rules_score' => $knowledge,
            'fouls_body_contact_score' => $fouls,
            'fair_mindedness_score' => $fair,
            'positive_attitude_score' => $attitude,
            'communication_respect_score' => $communication,
            'total_score' => $knowledge + $fouls + $fair + $attitude + $communication,
        ];
    }

    private function randomSpiritCriterion(): int
    {
        $roll = random_int(1, 100);

        if ($roll <= 35) {
            return 3;
        }

        if ($roll <= 88) {
            return 2;
        }

        return 1;
    }

    private function spiritCaptainMemberId(?Team $team): ?int
    {
        $team?->loadMissing('members');

        return $team?->members?->firstWhere('role', 'spirit_captain')?->id;
    }

    /**
     * @return array{home: int, away: int}
     */
    private function randomRoundRobinTotals(): array
    {
        $winner = random_int(8, 15);
        $loser = random_int(3, max(3, $winner - 1));

        if (random_int(0, 1) === 1) {
            return ['home' => $winner, 'away' => $loser];
        }

        return ['home' => $loser, 'away' => $winner];
    }

    /**
     * @return array{home: int, away: int}
     */
    private function randomKnockoutTotals(): array
    {
        $winner = random_int(12, 21);
        $loser = random_int(6, max(6, $winner - 2));

        if (random_int(0, 1) === 1) {
            return ['home' => $winner, 'away' => $loser];
        }

        return ['home' => $loser, 'away' => $winner];
    }

    /**
     * @param  Collection<int, TeamMember>  $members
     * @return array<int, int>
     */
    private function distributeGoalsAcrossRoster(int $totalGoals, Collection $members): array
    {
        $count = $members->count();

        if ($count === 0) {
            return [];
        }

        if ($totalGoals === 0) {
            return $members->mapWithKeys(fn (TeamMember $member): array => [$member->id => 0])->all();
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

    private function resolveMatch(Tournament $tournament, int $gameNumber): ?TournamentMatch
    {
        $resolved = TournamentBracketAdvancer::resolveBracketMatchForGame($tournament, $gameNumber);

        if ($resolved !== null) {
            return $resolved->loadMissing([
                'homeRegistration.team.members',
                'awayRegistration.team.members',
            ]);
        }

        return TournamentMatch::query()
            ->where('tournament_id', $tournament->id)
            ->where('match_number', $gameNumber)
            ->with([
                'homeRegistration.team.members',
                'awayRegistration.team.members',
            ])
            ->orderBy('id')
            ->first();
    }
}
