<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsStaticTeamRoster;
use Illuminate\Database\Seeder;

class YooyTeamSeeder extends Seeder
{
    use SeedsStaticTeamRoster;

    public function run(): void
    {
        $this->seedTeamWithRoster('YOOY', [
            // Male
            ['name' => 'Jewison "Jewi" Rodriguez', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Michael Genes "King" Endrina', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Apolinar "Inar" Bagayna', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Rosthel "Tetel" Banaag', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Jules Viernes', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Sean Elijah Amora', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Adrian Ray "Pasyo" Millan', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Elijah Job "Ejay" Carolino', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Jorg Leonel Hortelano', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Christian Nel "Yanyan" Tejero', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Antonio "Bolantoy" Francisco', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Arjay "Sarge" Duculan', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Laurence "Lau2x" Capili', 'gender' => 'Male', 'status' => 'Lite', 'role' => 'Player'],
            // Female
            ['name' => 'Stacey Shairick "Staxang" Amora', 'gender' => 'Female', 'status' => 'Lite', 'role' => 'Captain'],
            ['name' => 'Kyla Mariz Jote', 'gender' => 'Female', 'status' => 'Lite', 'role' => 'Spirit Captain'],
            ['name' => 'Karmylle Raia Caingcoy', 'gender' => 'Female', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Joei Immaculate "Owe" Agcopra', 'gender' => 'Female', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Princess Andrea "Andeng" Yosores', 'gender' => 'Female', 'status' => 'Lite', 'role' => 'Player'],
            ['name' => 'Trixie Febb Marco', 'gender' => 'Female', 'status' => 'Lite', 'role' => 'Player'],
        ]);
    }
}
