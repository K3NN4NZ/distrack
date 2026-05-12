<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use Illuminate\Database\Seeder;
use RuntimeException;

class TournamentOneRegistrationSeedingSeeder extends Seeder
{
    /**
     * Register tournament #1 teams with tournament-wide seeds A–I (stored as seed_number 1–9).
     *
     * Display names in brackets align with live brackets as EMPOX ULTIMATE, etc.;
     * persisted {@see Team::$name} values match the static roster seeders.
     */
    public function run(): void
    {
        $tournamentId = 1;

        if (! Tournament::query()->whereKey($tournamentId)->exists()) {
            if ($this->command !== null) {
                $this->command->warn("TournamentOneRegistrationSeedingSeeder skipped: tournament id {$tournamentId} does not exist.");
            }

            return;
        }

        $order = [
            [1, 'EMPOX ULTI'],
            [2, 'BOMBANA'],
            [3, 'ATSU'],
            [4, 'YOOY'],
            [5, 'SIGBIN'],
            [6, 'CPU'],
            [7, 'TOOTHLESS ULTI'],
            [8, 'RINGER'],
            [9, 'UTI'],
        ];

        foreach ($order as [$seedNumber, $teamName]) {
            $teamId = Team::query()->where('name', $teamName)->value('id');

            if ($teamId === null) {
                throw new RuntimeException("Team \"{$teamName}\" not found. Seed roster teams before running this seeder.");
            }

            $registration = TournamentRegistration::query()->firstOrNew([
                'tournament_id' => $tournamentId,
                'team_id' => (int) $teamId,
            ]);

            if (! $registration->exists) {
                $registration->status = 'pending';
            }

            $registration->seed_number = $seedNumber;
            $registration->save();
        }
    }
}
