<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsStaticTeamRoster;
use Illuminate\Database\Seeder;

class CpuTeamSeeder extends Seeder
{
    use SeedsStaticTeamRoster;

    public function run(): void
    {
        $this->seedTeamWithRoster('CPU', [
            // Male
            ['name' => 'Ivan Frank G. Acobo', 'gender' => 'Male', 'status' => 'Organizer', 'role' => 'Captain'],
            ['name' => 'Joshua Jofame Braña', 'gender' => 'Male', 'status' => 'Organizer', 'role' => 'Spirit Captain'],
            ['name' => 'Karl Ythann Sicat', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'John David Mahistrado', 'gender' => 'Male', 'status' => 'Organizer', 'role' => 'Player'],
            ['name' => 'Sean Richner', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Kaiser Basil', 'gender' => 'Male', 'status' => 'Organizer', 'role' => 'Player'],
            ['name' => 'Sheram Rodriguez', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Calvin Andre Gamolo', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Kevin Tacandong', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Jam-Jam Andoy', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Jorgen Gil F. Fosgate', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Zanal-Abdin C. Khan', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Player'],
            // Female
            ['name' => 'Carmella Niña Quirog', 'gender' => 'Female', 'status' => 'Organizer', 'role' => 'Player'],
            ['name' => 'Gabrielle Martleah Mejares', 'gender' => 'Female', 'status' => 'Organizer', 'role' => 'Player'],
            ['name' => 'Gwendolyn Casalan', 'gender' => 'Female', 'status' => 'Organizer', 'role' => 'Player'],
            ['name' => 'Kim Laina', 'gender' => 'Female', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Alysa Balles', 'gender' => 'Female', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Minette Patron Pimentel', 'gender' => 'Female', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Jaerah Emano', 'gender' => 'Female', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Daryl Castañeda', 'gender' => 'Female', 'status' => 'Full', 'role' => 'Player'],
        ]);
    }
}
