<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsStaticTeamRoster;
use Illuminate\Database\Seeder;

class BombanaTeamSeeder extends Seeder
{
    use SeedsStaticTeamRoster;

    public function run(): void
    {
        $this->seedTeamWithRoster('BOMBANA', [
            // Male
            ['name' => 'John Alain Agnes', 'gender' => 'Male', 'status' => 'Walk In (special)', 'role' => 'Player'],
            ['name' => 'Remo Rafisura', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Melson Itmos Cailing', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Kyle Villano', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Spirit Captain'],
            ['name' => 'Earl Rowlan Abrogar', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Mark Gil Mantecajon', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Captain'],
            ['name' => 'Windell Lariosa', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'John Luigi Caculba', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Nico Bergado', 'gender' => 'Male', 'status' => 'Walk In (special)', 'role' => 'Player'],
            ['name' => 'Kyle Venice Maghanoy', 'gender' => 'Male', 'status' => 'Walk In (special)', 'role' => 'Player'],
            // Female
            ['name' => 'Angelica Nomio', 'gender' => 'Female', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Esjay Nomio', 'gender' => 'Female', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Marianne Lagutin', 'gender' => 'Female', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Rosen Pagaling', 'gender' => 'Female', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Cresia Daming', 'gender' => 'Female', 'status' => 'Lite', 'role' => 'Player'],
        ]);
    }
}
