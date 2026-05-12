<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsStaticTeamRoster;
use Illuminate\Database\Seeder;

class UtiTeamSeeder extends Seeder
{
    use SeedsStaticTeamRoster;

    private const DEFAULT_STATUS = 'Full';

    public function run(): void
    {
        $this->seedTeamWithRoster('UTI', [
            // Male
            ['name' => 'Edmar Perez', 'gender' => 'Male', 'status' => self::DEFAULT_STATUS, 'role' => 'Player'],
            ['name' => 'Jan Lee Abing', 'gender' => 'Male', 'status' => self::DEFAULT_STATUS, 'role' => 'Captain'],
            ['name' => 'Kerr Tagolimot', 'gender' => 'Male', 'status' => self::DEFAULT_STATUS, 'role' => 'Player'],
            ['name' => 'Dredd Ogdol', 'gender' => 'Male', 'status' => self::DEFAULT_STATUS, 'role' => 'Player'],
            ['name' => 'Clark Paradela', 'gender' => 'Male', 'status' => self::DEFAULT_STATUS, 'role' => 'Player'],
            ['name' => 'Rupert Gabrielle Velez', 'gender' => 'Male', 'status' => self::DEFAULT_STATUS, 'role' => 'Player'],
            ['name' => 'Meljon Navales', 'gender' => 'Male', 'status' => self::DEFAULT_STATUS, 'role' => 'Player'],
            ['name' => 'Jan Philip Ecat', 'gender' => 'Male', 'status' => self::DEFAULT_STATUS, 'role' => 'Player'],
            ['name' => 'Christopher Benabaye', 'gender' => 'Male', 'status' => self::DEFAULT_STATUS, 'role' => 'Player'],
            ['name' => 'Robert James Yare', 'gender' => 'Male', 'status' => self::DEFAULT_STATUS, 'role' => 'Player'],
            // Female
            ['name' => 'Eileen Piquero', 'gender' => 'Female', 'status' => self::DEFAULT_STATUS, 'role' => 'Spirit Captain'],
            ['name' => 'Regine Capor', 'gender' => 'Female', 'status' => self::DEFAULT_STATUS, 'role' => 'Player'],
            ['name' => 'Sandy Tabuclin', 'gender' => 'Female', 'status' => self::DEFAULT_STATUS, 'role' => 'Player'],
            ['name' => 'Jeicy Abuzo', 'gender' => 'Female', 'status' => self::DEFAULT_STATUS, 'role' => 'Player'],
            ['name' => 'Khay Sacabin', 'gender' => 'Female', 'status' => self::DEFAULT_STATUS, 'role' => 'Player'],
            ['name' => 'Rogelyn Sacal', 'gender' => 'Female', 'status' => self::DEFAULT_STATUS, 'role' => 'Player'],
            ['name' => 'Cyra Labadan', 'gender' => 'Female', 'status' => self::DEFAULT_STATUS, 'role' => 'Player'],
        ]);
    }
}
