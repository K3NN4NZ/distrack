<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsStaticTeamRoster;
use Illuminate\Database\Seeder;

class SigbinTeamSeeder extends Seeder
{
    use SeedsStaticTeamRoster;

    public function run(): void
    {
        $this->seedTeamWithRoster('SIGBIN', [
            // Male
            ['name' => 'Joash Rautraut', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Captain'],
            ['name' => 'Joseph Baguio', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Spirit Captain'],
            ['name' => 'Lester Dalde', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Amiel James Aranas', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Kenan Olivar', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Macky Dapanas', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Mj Magsalos', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Meiji James Reciña Ricafort', 'gender' => 'Male', 'status' => 'Walk in (special)', 'role' => 'Player'],
            ['name' => 'Vince Alcantara', 'gender' => 'Male', 'status' => 'Walk in (special)', 'role' => 'Player'],
            ['name' => 'Russ Cody Javien', 'gender' => 'Male', 'status' => 'Walk in (special)', 'role' => 'Player'],
            ['name' => 'Johnrobert Sy', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Mark Jasper Hamlag', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Cyril Dominguez', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Mark Valcueba', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Donce', 'gender' => 'Male', 'status' => 'Walk in (special)', 'role' => 'Player'],
            // Female
            ['name' => 'Shahoney', 'gender' => 'Female', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'claudine quirante', 'gender' => 'Female', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Fresian Redgette', 'gender' => 'Female', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Justine Kyle Remulta', 'gender' => 'Female', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Erica Pauline Martinez', 'gender' => 'Female', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Hyacinth Ipanag', 'gender' => 'Female', 'status' => 'Full', 'role' => 'Player'],
        ]);
    }
}
