<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsStaticTeamRoster;
use Illuminate\Database\Seeder;

class RingerTeamSeeder extends Seeder
{
    use SeedsStaticTeamRoster;

    public function run(): void
    {
        $this->seedTeamWithRoster('RINGER', [
            // Male
            ['name' => 'Sunder Rajput', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Christian Joeros Hamlag', 'gender' => 'Male', 'status' => 'Walk In (special)', 'role' => 'Player'],
            ['name' => 'Kerwin Thabor', 'gender' => 'Male', 'status' => 'Walk In', 'role' => 'Player'],
            ['name' => 'Sandie Bautista', 'gender' => 'Male', 'status' => 'Walk In (special)', 'role' => 'Player'],
            ['name' => 'Jayson Zabala', 'gender' => 'Male', 'status' => 'Walk In (special)', 'role' => 'Player'],
            ['name' => 'Dirk Angelo Celocia', 'gender' => 'Male', 'status' => 'Walk In (special)', 'role' => 'Player'],
            ['name' => 'Adrian Butanas', 'gender' => 'Male', 'status' => 'Walk In (special)', 'role' => 'Player'],
            ['name' => 'Andres Ariz', 'gender' => 'Male', 'status' => 'Walk In (special)', 'role' => 'Player'],
            ['name' => 'Edison Villa', 'gender' => 'Male', 'status' => 'Walk In (special)', 'role' => 'Player'],
            ['name' => 'John Michael Celades', 'gender' => 'Male', 'status' => 'Walk In', 'role' => 'Player'],
            ['name' => 'Joie', 'gender' => 'Male', 'status' => 'Walk In', 'role' => 'Player'],
            ['name' => 'Nike Doldolia', 'gender' => 'Male', 'status' => 'Walk In', 'role' => 'Player'],
            // Female
            ['name' => 'Maria Sophia', 'gender' => 'Female', 'status' => 'Walk In (special)', 'role' => 'Player'],
            ['name' => 'Ash Sagarias', 'gender' => 'Female', 'status' => 'Walk In (special)', 'role' => 'Player'],
        ]);
    }
}
