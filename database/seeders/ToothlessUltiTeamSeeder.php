<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsStaticTeamRoster;
use Illuminate\Database\Seeder;

class ToothlessUltiTeamSeeder extends Seeder
{
    use SeedsStaticTeamRoster;

    public function run(): void
    {
        $this->seedTeamWithRoster('TOOTHLESS ULTI', [
            // Male
            ['name' => 'JOHN DAVE DELA CRUZ', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'JERSON AGUINID', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Spirit Captain'],
            ['name' => 'FRANCO ANTONIO TAN', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Captain'],
            ['name' => 'LUCAS TAN', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'JOHN LLOYD AGUINID', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'JERECK AGUINID', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'ROY AGUDO', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'RYAN CARUZ', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'JUSTIN RASHID GONZALES', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'BEBE LAUGO', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'JIAN TANGARA', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Player'],
            // Female
            ['name' => 'PEARL PALMARES', 'gender' => 'Female', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'JIANNA FAYE OBEÑITA', 'gender' => 'Female', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'SHANDIE CATERA', 'gender' => 'Female', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'ILA SANDAYAN', 'gender' => 'Female', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'SHYNE BAUL', 'gender' => 'Female', 'status' => 'Full', 'role' => 'Player'],
        ]);
    }
}
