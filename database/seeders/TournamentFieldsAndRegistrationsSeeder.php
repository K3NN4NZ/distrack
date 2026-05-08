<?php

namespace Database\Seeders;

use App\Models\Pitch;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ensures every tournament has FIELD 1 + FIELD 2 pitches and registers all
 * participant teams created by {@see ParticipantTeamsSeeder}.
 */
class TournamentFieldsAndRegistrationsSeeder extends Seeder
{
    /**
     * Team names produced by ParticipantTeamsSeeder::teamProfiles().
     *
     * @return list<string>
     */
    protected function participantTeamNames(): array
    {
        return [
            'Seeded Northstars',
            'Seeded Skybreakers',
            'Seeded Riptide',
            'Seeded Emberhawks',
            'Seeded Stonewind',
            'Seeded Tidebound',
            'Seeded Voltstream',
            'Seeded Daybreak',
            'Seeded Ironwood',
            'Seeded Stormcallers',
        ];
    }

    public function run(): void
    {
        DB::transaction(function (): void {
            $admin = User::query()->where('role', User::ROLE_ADMIN)->orderBy('id')->first();

            if ($admin === null) {
                $this->command?->warn('TournamentFieldsAndRegistrationsSeeder: no admin user found; run UserAccountSeeder first.');

                return;
            }

            $tournaments = Tournament::query()->orderBy('id')->get();

            if ($tournaments->isEmpty()) {
                $demo = Tournament::query()->create([
                    'created_by' => $admin->id,
                    'name' => 'Seeded Demo Open',
                    'slug' => 'seeded-demo-open-'.Str::lower(Str::random(6)),
                    'venue' => 'Demo Complex',
                    'description' => 'Demo tournament created when none existed during seeding.',
                    'status' => 'registration',
                    'country_name' => 'Philippines',
                    'surface' => 'Outdoor',
                    'division' => 'Open',
                    'is_public' => true,
                ]);
                $tournaments = Tournament::query()->whereKey($demo->id)->get();
                $this->command?->info('TournamentFieldsAndRegistrationsSeeder: created demo tournament.');
            }

            $teamIds = Team::query()
                ->whereIn('name', $this->participantTeamNames())
                ->orderBy('name')
                ->pluck('id');

            if ($teamIds->isEmpty()) {
                $this->command?->warn('TournamentFieldsAndRegistrationsSeeder: no participant teams found; run ParticipantTeamsSeeder first.');

                return;
            }

            foreach ($tournaments as $tournament) {
                $this->ensureFields($tournament);
                $this->ensureRegistrations($tournament, $teamIds);
            }

            $this->command?->info(sprintf(
                'TournamentFieldsAndRegistrationsSeeder: updated %d tournament(s), %d team registration slot(s) considered.',
                $tournaments->count(),
                $teamIds->count(),
            ));
        });
    }

    protected function ensureFields(Tournament $tournament): void
    {
        Pitch::query()->updateOrCreate(
            ['tournament_id' => $tournament->id, 'name' => 'FIELD 1'],
            ['location' => null, 'sort_order' => 1, 'is_active' => true],
        );

        Pitch::query()->updateOrCreate(
            ['tournament_id' => $tournament->id, 'name' => 'FIELD 2'],
            ['location' => null, 'sort_order' => 2, 'is_active' => true],
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>  $teamIds
     */
    protected function ensureRegistrations(Tournament $tournament, $teamIds): void
    {
        foreach ($teamIds as $teamId) {
            TournamentRegistration::query()->updateOrCreate(
                ['tournament_id' => $tournament->id, 'team_id' => $teamId],
                ['status' => 'approved'],
            );
        }
    }
}
