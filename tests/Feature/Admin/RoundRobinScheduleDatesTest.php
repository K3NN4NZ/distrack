<?php

use App\Models\Pitch;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentRegistration;
use App\Models\User;

test('round robin tab shows fixed day one and day two dates when tournament dates are unset', function (): void {
    $admin = User::factory()->admin()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Round Robin Date Cup',
        'slug' => 'round-robin-date-cup',
        'venue' => 'North Grounds',
        'status' => 'draft',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => false,
        'timezone' => 'Asia/Manila',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.tournaments.index', [
            'tournament' => $tournament->id,
            'tab' => 'round-robin',
        ]))
        ->assertOk()
        ->assertSee('DAY 1')
        ->assertSee('May 16, 2026')
        ->assertSee('DAY 2')
        ->assertSee('May 17, 2026');
});

test('manual round robin schedule row save uses the submitted day header date', function (): void {
    $admin = User::factory()->admin()->create();
    $teamOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Round Robin Save Date Cup',
        'slug' => 'round-robin-save-date-cup',
        'venue' => 'Central Field',
        'status' => 'draft',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => false,
        'timezone' => 'Asia/Manila',
    ]);

    $pitchA = Pitch::query()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Pitch 1',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $pitchB = Pitch::query()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Pitch 2',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $registrations = collect();

    foreach (range(1, 4) as $number) {
        $team = Team::query()->create([
            'owner_user_id' => $teamOwner->id,
            'name' => 'Schedule Team '.$number,
            'address' => 'Bukidnon',
            'status' => 'active',
        ]);

        $registrations->push(TournamentRegistration::query()->create([
            'tournament_id' => $tournament->id,
            'team_id' => $team->id,
            'status' => 'approved',
        ]));
    }

    $this->actingAs($admin)
        ->post(route('admin.tournaments.round-robin.schedules.store', $tournament), [
            'day' => '1',
            'rr_slot' => [
                'date' => '2026-05-16',
                'round' => 1,
                'start_time' => '07:00',
                'end_time' => '07:40',
                'pitch1_pitch_id' => $pitchA->id,
                'pitch2_pitch_id' => $pitchB->id,
                'match1_match_number' => 1,
                'match2_match_number' => 2,
                'match1_home_registration_id' => $registrations[0]->id,
                'match1_away_registration_id' => $registrations[1]->id,
                'match2_home_registration_id' => $registrations[2]->id,
                'match2_away_registration_id' => $registrations[3]->id,
                'status' => 'upcoming',
            ],
            'redirect_route' => 'admin.tournaments.index',
            'redirect_tab' => 'round-robin',
        ])
        ->assertRedirect(route('admin.tournaments.index', [
            'tournament' => $tournament->id,
            'tab' => 'round-robin',
        ]));

    $match = TournamentMatch::query()
        ->where('tournament_id', $tournament->id)
        ->where('match_number', 1)
        ->firstOrFail();

    expect($match->scheduled_at?->timezone('Asia/Manila')->format('Y-m-d'))->toBe('2026-05-16')
        ->and($match->scheduled_ends_at?->timezone('Asia/Manila')->format('Y-m-d'))->toBe('2026-05-16');
});
