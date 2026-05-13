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
            // Manual only: official Day 1 round-robin fixtures (games 1–24) for tournament 1 — registration IDs + markers.
            // php artisan db:seed --class=RoundRobinDay1ScheduleSeeder
            // Manual only: seeds MatchPlayerStat rows for round robin, quarter-finals, and Ranking Path (games 41–42 placement).
            // php artisan db:seed --class=TournamentGameRosterScoreSeeder
            // Manual only: creates/updates Ranking Path match rows 41–42 (registrations / schedule).
            // $this->call(RankingPathSeeder::class);
        ]);
    }
}
