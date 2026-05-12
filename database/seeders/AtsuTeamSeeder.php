<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsStaticTeamRoster;
use Illuminate\Database\Seeder;

class AtsuTeamSeeder extends Seeder
{
    use SeedsStaticTeamRoster;

    public function run(): void
    {
        $this->seedTeamWithRoster('ATSU', [
            // Male
            ['name' => 'Philip Cortes', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Dunn Whales', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Rey Buray', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Captain'],
            ['name' => 'Jake Lloyd Quejada', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Ronald Pogado', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Spirit Captain'],
            ['name' => 'Zyronne Penuela', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Carlos Colminas', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Eric Bernasor', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Ian Dexter', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Ethan Valerio', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Sheramy Viloro', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Zack Bedolido', 'gender' => 'Male', 'status' => 'Full', 'role' => 'Player'],
            // Female
            ['name' => 'Earthaea Skye Belono', 'gender' => 'Female', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Mikkaela Isabelle Revelo', 'gender' => 'Female', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Reggie Keanna Palasan', 'gender' => 'Female', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Angel Dancalan', 'gender' => 'Female', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Martina Colminas', 'gender' => 'Female', 'status' => 'Organizer', 'role' => 'Player'],
            ['name' => 'Maydilou Magaro', 'gender' => 'Female', 'status' => 'Full', 'role' => 'Player'],
            ['name' => 'Imma Pabillore', 'gender' => 'Female', 'status' => 'Full', 'role' => 'Player'],
        ]);
    }
}
