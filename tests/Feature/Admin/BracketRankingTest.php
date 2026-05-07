<?php

use App\Models\Pitch;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentRegistration;
use App\Models\User;

test('bracket ranking applies A1 A2 from completed round robin in same bracket', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Ranking Cup',
        'slug' => 'ranking-cup-'.uniqid(),
        'venue' => 'Field',
        'status' => 'registration',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => false,
    ]);

    $alpha = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Alpha Squad',
        'address' => 'X',
        'status' => 'active',
    ]);
    $beta = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Beta Crew',
        'address' => 'Y',
        'status' => 'active',
    ]);

    $regA = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $alpha->id,
        'status' => 'approved',
        'seed_number' => 1,
        'bracket_code' => 'Bracket A',
    ]);
    $regB = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $beta->id,
        'status' => 'approved',
        'seed_number' => 2,
        'bracket_code' => 'Bracket A',
    ]);

    $pitch = Pitch::query()->create([
        'tournament_id' => $tournament->id,
        'name' => 'P1',
        'location' => 'North',
        'sort_order' => 1,
    ]);

    TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'pitch_id' => $pitch->id,
        'home_registration_id' => $regA->id,
        'away_registration_id' => $regB->id,
        'stage' => 'round_robin',
        'round_label' => 'RR-A',
        'match_number' => 1,
        'scheduled_at' => now()->addDay(),
        'status' => 'completed',
        'home_score' => 12,
        'away_score' => 10,
    ]);

    $this->actingAs($admin);

    $this->post(route('admin.tournaments.bracket-ranking.apply', $tournament), [
        'redirect_route' => 'admin.tournaments.index',
        'redirect_tab' => 'teams',
    ])
        ->assertSessionHas('status', 'bracket-ranking-applied')
        ->assertRedirect(route('admin.tournaments.index', [
            'tournament' => $tournament->id,
            'tab' => 'teams',
        ]));

    expect($regA->fresh()->bracket_rank)->toBe('A1');
    expect($regB->fresh()->bracket_rank)->toBe('A2');
});

test('non admin cannot apply bracket ranking', function () {
    $user = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'X',
        'slug' => 'x-'.uniqid(),
        'venue' => 'V',
        'status' => 'draft',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => false,
    ]);

    $this->actingAs($user);

    $this->post(route('admin.tournaments.bracket-ranking.apply', $tournament))
        ->assertForbidden();
});
