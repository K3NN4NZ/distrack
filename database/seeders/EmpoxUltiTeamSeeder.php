<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsStaticTeamRoster;
use Illuminate\Database\Seeder;

class EmpoxUltiTeamSeeder extends Seeder
{
    use SeedsStaticTeamRoster;

    public function run(): void
    {
        $this->seedTeamWithRoster('EMPOX ULTI', [
            // Male
            ['name' => 'Adrian "Ad" Nazareno', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Niño Rodriguez', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Marc "AG" Grain', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Spirit Captain'],
            ['name' => 'Phol "Devin" Luranza', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Darius "Daido" Cagadas', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Dymnpaul "Impox" Sajonia', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Captain'],
            ['name' => 'Zio Timbal', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Justine "Jajan" Batingal', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Michael "Maki" Calaor', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Josh Reyes', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Nikko Bullecer', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Joshua Sean "Bambam" Mercado', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Marky Chan', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Kenneth Clark "Tanjiro" Rivera', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Player'],
            // Female
            ['name' => 'Jasmin Flores', 'gender' => 'Female', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Bonyang Lamigo', 'gender' => 'Female', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Angela Garcia', 'gender' => 'Female', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Mich Vicente', 'gender' => 'Female', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Cherub Cainglet', 'gender' => 'Female', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Shania "Shalo" Yulo', 'gender' => 'Female', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Sandy Jean Iwayan', 'gender' => 'Female', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Mia Bautista', 'gender' => 'Female', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Francis "Kim" Milar', 'gender' => 'Female', 'status' => 'Lite', 'role' => 'Player'],
        ]);
    }
}
