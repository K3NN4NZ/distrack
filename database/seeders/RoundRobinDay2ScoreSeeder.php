<?php

namespace Database\Seeders;

use App\Models\MatchPlayerStat;
use App\Models\MatchScoreLog;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Support\SmallFixedRoundRobinDayTwoSchedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Fills per-player stats for Round Robin "Day 2" matches (games 25–36) on tournament 1, then aligns match totals.
 *
 * Run: php artisan db:seed --class=RoundRobinDay2ScoreSeeder
 *
 * Day 2 rows are identified by {@see SmallFixedRoundRobinDayTwoSchedule::MARKER_PREFIX} in `matches.notes`
 * and `match_number` 25–36. If tracked rows are missing, this seeder calls {@see SmallFixedRoundRobinDayTwoSchedule::sync()}
 * once (same upsert logic as the admin scheduler) so rows exist before scoring.
 *
 * Scoring mirrors {@see RoundRobinDay1ScoreSeeder}: {@see MatchPlayerStat} drives goals; match totals are sums of goals.
 */
class RoundRobinDay2ScoreSeeder extends Seeder
{
    public const TOURNAMENT_ID = 1;

    private const MAX_GOALS_PER_PLAYER = 5;

    private const MAX_ASSISTS_PER_PLAYER = 2;

    private const MAX_BLOCKS_PER_PLAYER = 2;

    /**
     * Per-game team goal totals [home, away] — all decisive (no draws). Matchups follow Day 2 Berger complement ({@see SmallFixedRoundRobinDayTwoSchedule::complementScheduleEntries}).
     *
     * @var array<int, array{0: int, 1: int}>
     */
    private const GAME_SCORES = [
        25 => [11, 7],
        26 => [10, 8],
        27 => [9, 6],
        28 => [12, 5],
        29 => [13, 9],
        30 => [8, 7],
        31 => [10, 9],
        32 => [11, 6],
        33 => [9, 5],
        34 => [14, 8],
        35 => [10, 8],
        36 => [9, 8],
    ];

    public function run(): void
    {
        $tournament = Tournament::query()->find(self::TOURNAMENT_ID);

        if ($tournament === null) {
            $this->command?->warn('Tournament ID '.self::TOURNAMENT_ID.' not found; skipping.');

            return;
        }

        $marker = SmallFixedRoundRobinDayTwoSchedule::MARKER_PREFIX;

        $countTracked = static fn () => TournamentMatch::query()
            ->where('tournament_id', self::TOURNAMENT_ID)
            ->where('stage', 'round_robin')
            ->where('notes', 'like', '%'.$marker.'%')
            ->count();

        $beforeTracked = $countTracked();

        try {
            SmallFixedRoundRobinDayTwoSchedule::sync($tournament);
        } catch (InvalidArgumentException $e) {
            $this->command?->warn('Day 2 schedule sync skipped: '.$e->getMessage());
        } catch (Throwable $e) {
            $this->command?->warn('Day 2 schedule sync failed: '.$e->getMessage());
        }

        $afterTracked = $countTracked();
        $createdApprox = max(0, $afterTracked - $beforeTracked);

        $matches = TournamentMatch::query()
            ->where('tournament_id', self::TOURNAMENT_ID)
            ->where('stage', 'round_robin')
            ->where('notes', 'like', '%'.$marker.'%')
            ->whereNotNull('home_registration_id')
            ->whereNotNull('away_registration_id')
            ->whereBetween('match_number', [
                SmallFixedRoundRobinDayTwoSchedule::FIRST_GAME_NUMBER,
                SmallFixedRoundRobinDayTwoSchedule::FIRST_GAME_NUMBER + SmallFixedRoundRobinDayTwoSchedule::GAME_COUNT - 1,
            ])
            ->orderBy('match_number')
            ->orderBy('id')
            ->get();

        if ($matches->isEmpty()) {
            $this->command?->warn('No Day 2 round robin matches found for tournament '.self::TOURNAMENT_ID.'.');
            $this->command?->line('  • Ensure the tournament is below the bracket threshold and teams match Day 2 pairings (see SmallFixedRoundRobinDayTwoSchedule), or run the admin flow that calls Day 2 sync first.');

            $this->printSummary($afterTracked, 0, 0, 0, [], $createdApprox, []);

            return;
        }

        $warnings = [];
        $scoredGameNumbers = [];
        $playerStatTouches = 0;

        foreach ($matches as $match) {
            $gameNo = (int) $match->match_number;
            $totals = self::GAME_SCORES[$gameNo] ?? null;

            if ($totals === null) {
                $warnings[] = "Game {$gameNo} (match id {$match->id}): no predefined score row in GAME_SCORES; skipped.";

                continue;
            }

            [$homeGoals, $awayGoals] = $totals;

            self::wipeMatchScoresOnly($match);

            $salt = 50_000 + $gameNo * 97;
            $touched = self::seedMatchFromHomeAwayTotals($match, $homeGoals, $awayGoals, $salt);

            if ($touched === null) {
                $warnings[] = "Game {$gameNo} (match id {$match->id}): missing teams or empty roster; not scored.";

                continue;
            }

            $playerStatTouches += $touched;
            $scoredGameNumbers[] = $gameNo;
        }

        $scoredCount = count($scoredGameNumbers);

        $this->command?->newLine();
        $this->command?->info('── Round Robin Day 2 score seeding ('.self::TOURNAMENT_ID.') ──');
        $this->printSummary($afterTracked, $matches->count(), $scoredCount, $playerStatTouches, $scoredGameNumbers, $createdApprox, $warnings);

        foreach ($warnings as $line) {
            $this->command?->warn($line);
        }
    }

    /**
     * @param  list<int>  $scoredGameNumbers
     * @param  list<string>  $warnings
     */
    private function printSummary(
        int $trackedAfterSync,
        int $matchesInRun,
        int $matchesScored,
        int $playerStatTouches,
        array $scoredGameNumbers,
        int $createdApprox,
        array $warnings,
    ): void {
        $this->command?->table(
            ['Metric', 'Value'],
            [
                ['Tracked Day 2 matches in DB (after sync)', (string) $trackedAfterSync],
                ['Matches in this run (query set)', (string) $matchesInRun],
                ['Approx. new tracked rows from sync (count delta)', (string) $createdApprox],
                ['Matches scored successfully', (string) $matchesScored],
                ['Player stat rows written (updateOrCreate)', (string) $playerStatTouches],
                ['Game numbers scored', $scoredGameNumbers === [] ? '—' : implode(', ', $scoredGameNumbers)],
                ['Warnings', (string) count($warnings)],
            ],
        );
    }

    /**
     * @return int|null number of player stat rows touched, or null if rosters missing
     */
    private static function seedMatchFromHomeAwayTotals(TournamentMatch $match, int $homeTotal, int $awayTotal, int $salt): ?int
    {
        $match->loadMissing([
            'homeRegistration.team.members',
            'awayRegistration.team.members',
        ]);

        $homeMembers = self::membersInSheetOrder($match->homeRegistration?->team);
        $awayMembers = self::membersInSheetOrder($match->awayRegistration?->team);

        if ($homeMembers->isEmpty() || $awayMembers->isEmpty()) {
            return null;
        }

        $homeRegId = (int) $match->home_registration_id;
        $awayRegId = (int) $match->away_registration_id;

        if ($homeRegId < 1 || $awayRegId < 1) {
            return null;
        }

        $homeCap = $homeMembers->count() * self::MAX_GOALS_PER_PLAYER;
        $awayCap = $awayMembers->count() * self::MAX_GOALS_PER_PLAYER;
        $homeTotal = max(0, min($homeTotal, $homeCap));
        $awayTotal = max(0, min($awayTotal, $awayCap));

        if ($homeTotal === $awayTotal) {
            if ($homeTotal < $homeCap) {
                $homeTotal++;
            } elseif ($awayTotal < $awayCap) {
                $awayTotal++;
            }
        }

        $touched = 0;

        DB::transaction(function () use ($match, $homeMembers, $awayMembers, $homeTotal, $awayTotal, $salt, &$touched): void {
            MatchScoreLog::query()->where('match_id', $match->id)->delete();

            $homeGoals = self::distributeTotalDeterministicSalted(
                $homeMembers->count(),
                $homeTotal,
                self::MAX_GOALS_PER_PLAYER,
                $salt,
            );
            $awayGoals = self::distributeTotalDeterministicSalted(
                $awayMembers->count(),
                $awayTotal,
                self::MAX_GOALS_PER_PLAYER,
                $salt + 17_011,
            );

            foreach ($homeMembers as $index => $member) {
                $goals = $homeGoals[$index] ?? 0;
                $a = ($salt + (int) $member->id + $index) % (self::MAX_ASSISTS_PER_PLAYER + 1);
                $b = ($salt * 2 + (int) $member->id + $index) % (self::MAX_BLOCKS_PER_PLAYER + 1);
                self::upsertPlayerStat($match->id, (int) $member->id, $goals, $a, $b);
                $touched++;
            }

            foreach ($awayMembers as $index => $member) {
                $goals = $awayGoals[$index] ?? 0;
                $a = ($salt + (int) $member->id * 3 + $index) % (self::MAX_ASSISTS_PER_PLAYER + 1);
                $b = ($salt * 3 + (int) $member->id + $index * 2) % (self::MAX_BLOCKS_PER_PLAYER + 1);
                self::upsertPlayerStat($match->id, (int) $member->id, $goals, $a, $b);
                $touched++;
            }

            $homeScore = (int) array_sum($homeGoals);
            $awayScore = (int) array_sum($awayGoals);

            $match->forceFill([
                'home_score' => $homeScore,
                'away_score' => $awayScore,
                'status' => 'completed',
            ])->save();
        });

        return $touched;
    }

    private static function wipeMatchScoresOnly(TournamentMatch $match): void
    {
        DB::transaction(static function () use ($match): void {
            MatchScoreLog::query()->where('match_id', $match->id)->delete();
            MatchPlayerStat::query()->where('match_id', $match->id)->delete();

            $match->forceFill([
                'home_score' => null,
                'away_score' => null,
                'status' => 'scheduled',
            ])->save();
        });
    }

    /**
     * @return Collection<int, TeamMember>
     */
    private static function membersInSheetOrder(?Team $team): Collection
    {
        if ($team === null) {
            return collect();
        }

        $members = $team->relationLoaded('members')
            ? $team->getRelation('members')
            : $team->members()->orderBy('id')->get();

        if ($members->isEmpty()) {
            return collect();
        }

        $male = $members->filter(fn (TeamMember $m): bool => strtolower((string) $m->gender) === 'male')->values();
        $female = $members->filter(fn (TeamMember $m): bool => strtolower((string) $m->gender) === 'female')->values();
        $other = $members->filter(fn (TeamMember $m): bool => ! in_array(strtolower((string) $m->gender), ['male', 'female'], true))->values();

        $sortRoster = static function (Collection $group): Collection {
            return $group->sort(static function (TeamMember $a, TeamMember $b): int {
                $rank = static fn (TeamMember $m): int => match (strtolower((string) $m->role)) {
                    'captain' => 0,
                    'spirit_captain' => 1,
                    default => 2,
                };

                return [$rank($a), $a->name ?? '', $a->id] <=> [$rank($b), $b->name ?? '', $b->id];
            })->values();
        };

        return $sortRoster($male)
            ->concat($sortRoster($female))
            ->concat($sortRoster($other))
            ->values();
    }

    /**
     * @return list<int>
     */
    private static function distributeTotalDeterministic(int $slots, int $total, int $maxPerSlot): array
    {
        if ($slots <= 0) {
            return [];
        }

        $maxAchievable = $slots * $maxPerSlot;
        $total = max(0, min($total, $maxAchievable));

        $amounts = array_fill(0, $slots, 0);
        $remaining = $total;
        $cursor = 0;
        $guard = 0;

        while ($remaining > 0 && $guard < 100_000) {
            $guard++;
            if ($amounts[$cursor % $slots] < $maxPerSlot) {
                $amounts[$cursor % $slots]++;
                $remaining--;
            }
            $cursor++;
        }

        return $amounts;
    }

    /**
     * @return list<int>
     */
    private static function distributeTotalDeterministicSalted(int $slots, int $total, int $maxPerSlot, int $salt): array
    {
        $amounts = self::distributeTotalDeterministic($slots, $total, $maxPerSlot);
        if ($slots <= 1) {
            return $amounts;
        }

        $rotate = abs($salt) % $slots;

        return array_values(array_merge(array_slice($amounts, $rotate), array_slice($amounts, 0, $rotate)));
    }

    private static function upsertPlayerStat(int $matchId, int $teamMemberId, int $goals, int $assists, int $blocks): void
    {
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
}
