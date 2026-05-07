<?php

namespace Database\Seeders;

use App\Models\MatchPlayerStat;
use App\Models\TournamentMatch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MatchPlayerStatsSeeder extends Seeder
{
    /**
     * Seed plausible per-player goal/assist/block tallies for every completed match.
     *
     * - Reuses the match's existing scoreline when available; otherwise generates a
     *   realistic ultimate frisbee total (8-15 per side) and writes it back to the match.
     * - Team total goals and team total assists are equal; goals and assists are spread
     *   across different players when the roster has at least two members (disjoint pools).
     * - Block counts are independent noise.
     * - Idempotent: re-running refreshes the stats and keeps match scores in sync.
     */
    public function run(): void
    {
        $matches = TournamentMatch::query()
            ->where('status', 'completed')
            ->with([
                'homeRegistration.team.members',
                'awayRegistration.team.members',
            ])
            ->get();

        if ($matches->isEmpty()) {
            $this->command?->warn('MatchPlayerStatsSeeder: no completed matches found, nothing to seed.');

            return;
        }

        DB::transaction(function () use ($matches): void {
            foreach ($matches as $match) {
                $this->seedMatchStats($match);
            }
        });

        $this->command?->info(sprintf(
            'MatchPlayerStatsSeeder: populated player stats for %d completed match(es).',
            $matches->count(),
        ));
    }

    protected function seedMatchStats(TournamentMatch $match): void
    {
        $homeMembers = $match->homeRegistration?->team?->members ?? collect();
        $awayMembers = $match->awayRegistration?->team?->members ?? collect();

        if ($homeMembers->isEmpty() || $awayMembers->isEmpty()) {
            return;
        }

        $homeTotal = $match->home_score !== null && $match->home_score > 0
            ? (int) $match->home_score
            : random_int(10, 15);

        $awayTotal = $match->away_score !== null && $match->away_score > 0
            ? (int) $match->away_score
            : random_int(8, 14);

        if ($homeTotal === $awayTotal) {
            $homeTotal++;
        }

        if ((int) ($match->home_score ?? 0) !== $homeTotal || (int) ($match->away_score ?? 0) !== $awayTotal) {
            $match->forceFill([
                'home_score' => $homeTotal,
                'away_score' => $awayTotal,
            ])->save();
        }

        MatchPlayerStat::query()
            ->where('match_id', $match->id)
            ->delete();

        $this->writeTeamStats($match->id, $homeMembers, $homeTotal);
        $this->writeTeamStats($match->id, $awayMembers, $awayTotal);
    }

    /**
     * @param  Collection<int, \App\Models\TeamMember>  $members
     */
    protected function writeTeamStats(int $matchId, Collection $members, int $totalGoals): void
    {
        if ($members->isEmpty() || $totalGoals < 1) {
            return;
        }

        $orderedIds = $members->shuffle()->pluck('id')->values()->all();
        $n = count($orderedIds);

        if ($n === 1) {
            $goalsByMember = $this->distributeCounts([$orderedIds[0]], $totalGoals);
            $assistsByMember = $this->distributeCounts([$orderedIds[0]], $totalGoals);
        } else {
            $cut = random_int(1, $n - 1);
            $scorerIds = array_slice($orderedIds, 0, $cut);
            $assisterIds = array_slice($orderedIds, $cut);
            $goalsByMember = $this->distributeCounts($scorerIds, $totalGoals);
            $assistsByMember = $this->distributeCounts($assisterIds, $totalGoals);
        }

        $rows = [];
        $now = now();

        foreach ($members as $member) {
            $goals = (int) ($goalsByMember[$member->id] ?? 0);
            $assists = (int) ($assistsByMember[$member->id] ?? 0);
            $blocks = $this->randomBlockCount();

            if ($goals === 0 && $assists === 0 && $blocks === 0) {
                continue;
            }

            $rows[] = [
                'match_id' => $matchId,
                'team_member_id' => $member->id,
                'goals' => $goals,
                'assists' => $assists,
                'blocks' => $blocks,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            MatchPlayerStat::query()->insert($rows);
        }
    }

    /**
     * Randomly distribute a total count across the given member ids.
     *
     * @param  list<int>  $memberIds
     * @return array<int, int>
     */
    protected function distributeCounts(array $memberIds, int $total): array
    {
        $allocation = array_fill_keys($memberIds, 0);

        if ($memberIds === [] || $total < 1) {
            return $allocation;
        }

        for ($i = 0; $i < $total; $i++) {
            $pick = $memberIds[array_rand($memberIds)];
            $allocation[$pick]++;
        }

        return $allocation;
    }

    protected function randomBlockCount(): int
    {
        $roll = random_int(0, 100);

        return match (true) {
            $roll < 55 => 0,
            $roll < 85 => 1,
            $roll < 96 => 2,
            default => 3,
        };
    }
}
