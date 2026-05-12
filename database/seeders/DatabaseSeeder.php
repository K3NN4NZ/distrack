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
        ]);
    }
}
