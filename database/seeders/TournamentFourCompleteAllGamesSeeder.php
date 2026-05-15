<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Support\TournamentBracketAdvancer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Marks every match in tournament #4 as completed (optional random scorelines when missing).
 *
 * Run: php artisan db:seed --class=TournamentFourCompleteAllGamesSeeder
 */
final class TournamentFourCompleteAllGamesSeeder extends Seeder
{
    public const TOURNAMENT_ID = 1;

    public function run(): void
    {
        $tournament = Tournament::query()->find(self::TOURNAMENT_ID);

        if ($tournament === null) {
            $this->command?->error('Tournament '.self::TOURNAMENT_ID.' not found.');

            return;
        }

        $scoresGenerated = 0;
        $statusOnly = 0;

        DB::transaction(function () use ($tournament, &$scoresGenerated, &$statusOnly): void {
            $matches = TournamentMatch::query()
                ->where('tournament_id', $tournament->id)
                ->orderBy('match_number')
                ->orderBy('id')
                ->get();

            foreach ($matches as $match) {
                $homeScore = $match->home_score;
                $awayScore = $match->away_score;
                $hasBothTeams = $match->home_registration_id !== null
                    && $match->away_registration_id !== null;

                if ($hasBothTeams && ($homeScore === null || $awayScore === null)) {
                    $homeScore = random_int(8, 18);
                    $awayScore = random_int(5, 16);

                    if ($homeScore === $awayScore) {
                        $homeScore++;
                    }

                    $scoresGenerated++;
                } elseif ($hasBothTeams) {
                    $statusOnly++;
                }

                $match->forceFill([
                    'home_score' => $homeScore,
                    'away_score' => $awayScore,
                    'status' => TournamentMatch::STATUS_COMPLETED,
                ])->save();
            }

            for ($pass = 0; $pass < 4; $pass++) {
                TournamentBracketAdvancer::syncFromCompletedMatches($tournament);
            }
        });

        $total = TournamentMatch::query()
            ->where('tournament_id', $tournament->id)
            ->where('status', TournamentMatch::STATUS_COMPLETED)
            ->count();

        $this->command?->info("Completed {$total} matches for tournament #{$tournament->id}.");
        $this->command?->table(
            ['Metric', 'Count'],
            [
                ['Matches touched (scores generated)', $scoresGenerated],
                ['Matches touched (status / existing scores only)', $statusOnly],
                ['Matches now completed', $total],
            ],
        );
    }
}
