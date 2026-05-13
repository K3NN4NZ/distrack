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
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Seeds roster-based {@see MatchPlayerStat} rows for the **Quarterfinals 20** band only:
 * {@see SmallDayTwoKnockoutBracket} games **39–40** ({@code stage = quarterfinal}, 12:05pm – 12:45pm).
 *
 * To seed **all** quarter-finals (37–40), use {@see TournamentGameRosterScoreSeeder} instead.
 *
 * Run: {@code php artisan db:seed --class=Quarterfinals20ScoreSeeder}
 */
class Quarterfinals20ScoreSeeder extends Seeder
{
    public int $tournamentId = 1;

    private const STAGE_QUARTERFINAL = 'quarterfinal';

    /** @var list<int> */
    private const GAME_NUMBERS = [39, 40];

    /**
     * Demo team goal totals per game (distinct margins).
     *
     * @var array<int, array{home: int, away: int}>
     */
    private const GAME_TEAM_GOALS = [
        39 => ['home' => 21, 'away' => 10],
        40 => ['home' => 16, 'away' => 18],
    ];

    public function run(): void
    {
        $tournament = Tournament::query()->find($this->tournamentId);

        if ($tournament === null) {
            $this->command?->error("Tournament {$this->tournamentId} not found.");

            return;
        }

        foreach (self::GAME_NUMBERS as $gameNumber) {
            DB::transaction(function () use ($tournament, $gameNumber): void {
                $this->seedGame($tournament, $gameNumber);
            });
        }

        $this->command?->info('Quarterfinals20ScoreSeeder finished (games '.implode(', ', self::GAME_NUMBERS).').');
    }

    private function seedGame(Tournament $tournament, int $gameNumber): void
    {
        $match = TournamentMatch::query()
            ->where('tournament_id', $tournament->id)
            ->where('match_number', $gameNumber)
            ->where('stage', self::STAGE_QUARTERFINAL)
            ->first();

        if ($match === null) {
            $this->command?->warn("Game {$gameNumber}: no quarterfinal match found — skipping.");

            return;
        }

        $match->loadMissing([
            'homeRegistration.team.members',
            'awayRegistration.team.members',
        ]);

        $homeRegistration = $match->homeRegistration;
        $awayRegistration = $match->awayRegistration;

        if ($homeRegistration === null || $awayRegistration === null) {
            $this->command?->warn("Game {$gameNumber}: missing home or away registration — skipping.");

            return;
        }

        $homeTeam = $homeRegistration->team;
        $awayTeam = $awayRegistration->team;

        if ($homeTeam === null || $awayTeam === null) {
            $this->command?->warn("Game {$gameNumber}: missing home or away team — skipping.");

            return;
        }

        $homeMembers = $homeTeam->members()->orderBy('id')->get();
        $awayMembers = $awayTeam->members()->orderBy('id')->get();

        if ($homeMembers->isEmpty() || $awayMembers->isEmpty()) {
            $this->command?->warn("Game {$gameNumber}: home or away roster is empty — skipping.");

            return;
        }

        $homeTeamId = (int) $homeRegistration->team_id;
        $awayTeamId = (int) $awayRegistration->team_id;

        $this->assertMembersBelongToTeam($homeMembers, $homeTeamId);
        $this->assertMembersBelongToTeam($awayMembers, $awayTeamId);

        MatchScoreLog::query()->where('match_id', $match->id)->delete();

        $targets = self::GAME_TEAM_GOALS[$gameNumber] ?? ['home' => 15, 'away' => 12];
        $homeGoalsByMember = $this->splitGoalsAcrossMembers($homeMembers, max(0, (int) $targets['home']));
        $awayGoalsByMember = $this->splitGoalsAcrossMembers($awayMembers, max(0, (int) $targets['away']));

        $allowedIds = $homeMembers->pluck('id')->merge($awayMembers->pluck('id'))->all();

        MatchPlayerStat::query()
            ->where('match_id', $match->id)
            ->whereNotIn('team_member_id', $allowedIds)
            ->delete();

        $rowsTouched = 0;

        foreach ($homeMembers->values() as $idx => $member) {
            $second = $this->demoAssistsBlocks($idx, $gameNumber, 'home');
            $this->persistStatRow($match->id, $member->id, $homeGoalsByMember[$member->id] ?? 0, $second);
            $rowsTouched++;
        }

        foreach ($awayMembers->values() as $idx => $member) {
            $second = $this->demoAssistsBlocks($idx, $gameNumber, 'away');
            $this->persistStatRow($match->id, $member->id, $awayGoalsByMember[$member->id] ?? 0, $second);
            $rowsTouched++;
        }

        $this->syncMatchTotalsFromPlayerStats($match);

        $match->refresh();

        $match->forceFill(['status' => 'completed'])->save();

        if ($tournament->registrations()->count() < TournamentController::MINIMUM_BRACKET_TEAM_COUNT) {
            SmallDayTwoKnockoutBracket::syncAfterResultChange($tournament->fresh(), $match->fresh());
        }

        $match->refresh();

        $this->command?->info(sprintf(
            'Quarterfinals 20 · Game %d | %s vs %s | %d – %d | player stat rows written: %d | status=%s',
            $gameNumber,
            $homeTeam->name,
            $awayTeam->name,
            (int) $match->home_score,
            (int) $match->away_score,
            $rowsTouched,
            $match->status,
        ));
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

    /**
     * @return array<int, int> team_member_id => goals
     */
    private function splitGoalsAcrossMembers(Collection $members, int $teamGoals): array
    {
        $members = $members->values();
        $count = max(1, $members->count());
        $base = intdiv($teamGoals, $count);
        $remainder = $teamGoals % $count;

        $map = [];
        foreach ($members as $idx => $member) {
            $map[$member->id] = $base + ($idx < $remainder ? 1 : 0);
        }

        return $map;
    }

    /**
     * @return array{assists: int, blocks: int}
     */
    private function demoAssistsBlocks(int $memberIndex, int $gameNumber, string $side): array
    {
        $salt = $side === 'home' ? 2 : 5;

        return [
            'assists' => ($memberIndex * 5 + $gameNumber + $salt) % 3,
            'blocks' => ($memberIndex * 7 + $gameNumber + $salt * 2) % 3,
        ];
    }

    /**
     * @param  array{assists: int, blocks: int}  $second
     */
    private function persistStatRow(int $matchId, int $teamMemberId, int $goals, array $second): void
    {
        if ($goals === 0 && $second['assists'] === 0 && $second['blocks'] === 0) {
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
                'assists' => $second['assists'],
                'blocks' => $second['blocks'],
            ],
        );
    }

    /**
     * Same aggregation as {@see TournamentController::syncMatchScoreFromPlayerStats}.
     */
    private function syncMatchTotalsFromPlayerStats(TournamentMatch $match): void
    {
        $match->loadMissing(['homeRegistration.team', 'awayRegistration.team']);

        $homeTeamId = $match->homeRegistration?->team_id;
        $awayTeamId = $match->awayRegistration?->team_id;

        $stats = MatchPlayerStat::query()
            ->with('teamMember:id,team_id')
            ->where('match_id', $match->id)
            ->get();

        $homeScore = $stats
            ->filter(fn ($stat) => $stat->teamMember?->team_id === $homeTeamId)
            ->sum('goals');

        $awayScore = $stats
            ->filter(fn ($stat) => $stat->teamMember?->team_id === $awayTeamId)
            ->sum('goals');

        $match->forceFill([
            'home_score' => (int) $homeScore,
            'away_score' => (int) $awayScore,
        ])->save();
    }
}
