<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserAccountSeeder::class,
            CpuTeamSeeder::class,
            SigbinTeamSeeder::class,
            UtiTeamSeeder::class,
            YooyTeamSeeder::class,
            ToothlessUltiTeamSeeder::class,
            EmpoxUltiTeamSeeder::class,
            AtsuTeamSeeder::class,
            BombanaTeamSeeder::class,
            RingerTeamSeeder::class,
            TournamentOneRegistrationSeedingSeeder::class,
            // Manual only: official tournament #1 schedule (Day 1 + Day 2 + bracket, games 1–48).
            // php artisan db:seed --class=ExactTournamentOneScheduleSeeder
            // Manual only: complete all games 1–48 with scores, player stats, and spirit (requires schedule seeder first).
            // php artisan db:seed --class=TournamentOneAllGamesCompletedScoreSeeder
            // @deprecated RoundRobinDay1ScheduleSeeder / RoundRobinDayTwoScheduleSeeder — superseded by ExactTournamentOneScheduleSeeder.
            // php artisan db:seed --class=RoundRobinDay1ScheduleSeeder
            // Manual only: seeds MatchPlayerStat rows for round robin, quarter-finals, and Ranking Path (games 41–42 placement).
            // php artisan db:seed --class=TournamentGameRosterScoreSeeder
            // Manual only: creates/updates Ranking Path match rows 41–42 (registrations / schedule).
            // $this->call(RankingPathSeeder::class);
        ]);
    }
}
