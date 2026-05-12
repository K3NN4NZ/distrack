<?php

use App\Http\Controllers\Admin\TournamentController as AdminTournamentController;
use App\Models\MatchPlayerStat;
use App\Models\MatchScoreLog;
use App\Models\Pitch;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Tournament;
use App\Models\TournamentCrew;
use App\Models\TournamentMatch;
use App\Models\TournamentRegistration;
use App\Models\User;
use App\Support\SmallFixedRoundRobinDayOneSchedule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Ensures enough registrations exist for Bracket Ranking / Crossover / Pooling tabs to appear (threshold matches {@see AdminTournamentController::MINIMUM_BRACKET_TEAM_COUNT}).
 */
function distrackPadRegistrationsForBracketWorkflowTabs(Tournament $tournament, User $owner, int $currentRegistrationCount): void
{
    $minimum = AdminTournamentController::MINIMUM_BRACKET_TEAM_COUNT;
    $needed = max(0, $minimum - $currentRegistrationCount);

    foreach (range(1, $needed) as $index) {
        $team = Team::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Bracket Tab Pad '.$tournament->id.'-'.$index,
            'address' => 'Padding',
            'status' => 'active',
        ]);

        TournamentRegistration::query()->create([
            'tournament_id' => $tournament->id,
            'team_id' => $team->id,
            'status' => 'approved',
            'seed_number' => null,
            'bracket_code' => null,
            'bracket_rank' => null,
            'pool_name' => null,
        ]);
    }
}

test('non admin users cannot visit tournament setup', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->get(route('admin.index'))->assertForbidden();
    $this->get(route('admin.tournaments.index'))->assertForbidden();
    $this->get(route('admin.tournaments.list'))->assertForbidden();
});

test('admin users are redirected from the admin root to tournament setup', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    $this->get(route('admin.index'))
        ->assertRedirect(route('admin.tournaments.index'));
});

test('scorekeepers are redirected from the admin root to the tournament directory', function () {
    $scorekeeper = User::factory()->scorekeeper()->create();

    $this->actingAs($scorekeeper);

    $this->get(route('admin.index'))
        ->assertRedirect(route('admin.tournaments.list'));
});

test('admin users can visit tournament setup', function () {
    $admin = User::factory()->admin()->create();

    Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Setup Target',
        'slug' => 'setup-target',
        'venue' => 'Main Grounds',
        'status' => 'draft',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    $this->actingAs($admin);

    $this->get(route('admin.tournaments.index'))
        ->assertOk()
        ->assertSee('Create Tournament')
        ->assertSee('No tournament selected for setup.')
        ->assertSee('Open the Tournaments tab, then click Setup on the tournament you want to manage here.')
        ->assertDontSee('Search name, venue, division, surface, or status');
});

test('admin users can visit the admin tournaments directory', function () {
    $admin = User::factory()->admin()->create();

    Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Summer Showcase',
        'slug' => 'summer-showcase',
        'venue' => 'City Oval',
        'status' => 'draft',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    $this->actingAs($admin);

    $this->get(route('admin.tournaments.list'))
        ->assertOk()
        ->assertSee('Create Tournament')
        ->assertSee('Tournaments')
        ->assertSee('Register Team')
        ->assertSee('tab=overview', false)
        ->assertDontSee('All surfaces')
        ->assertDontSee('All visibility')
        ->assertDontSee('Apply')
        ->assertDontSee('Add Pitch');
});

test('admin setup opens a tabbed workspace for the selected tournament', function () {
    $admin = User::factory()->admin()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Tabbed Setup Cup',
        'slug' => 'tabbed-setup-cup',
        'venue' => 'Downtown Arena',
        'status' => 'draft',
        'country_name' => 'Philippines',
        'province' => 'Bukidnon',
        'city' => 'Valencia City',
        'barangay' => 'Poblacion',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => false,
    ]);

    $this->actingAs($admin);

    $this->get(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'overview']))
        ->assertOk()
        ->assertSee('Tournament Setup Workspace')
        ->assertSee('Seeding')
        ->assertSee('Round Robin')
        ->assertDontSee('tab=bracket-ranking', false)
        ->assertDontSee('tab=crossover', false)
        ->assertDontSee('tab=pooling', false)
        ->assertSee('tab=team-standing', false)
        ->assertSee('Quarter Final')
        ->assertSee('Semi Finals')
        ->assertSee('Championship')
        ->assertSee('Auto Seed')
        ->assertDontSee('Save Manual Seeding')
        ->assertDontSee('Manual Team Assignment')
        ->assertDontSee('Quick Actions')
        ->assertDontSee('Readiness Checklist')
        ->assertDontSee('Tournament Summary');
});

test('team standing tab shows seeded rows for tournaments below bracket workflow threshold', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Standing Tab Cup',
        'slug' => 'standing-tab-cup',
        'venue' => 'North Oval',
        'status' => 'draft',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => false,
    ]);

    $alpha = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Alpha Line',
        'address' => 'CDO',
        'status' => 'active',
    ]);
    $beta = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Beta Line',
        'address' => 'Iligan',
        'status' => 'active',
    ]);

    TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $beta->id,
        'status' => 'approved',
        'seed_number' => 2,
    ]);
    TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $alpha->id,
        'status' => 'approved',
        'seed_number' => 1,
    ]);

    $this->actingAs($admin);

    $this->get(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'team-standing']))
        ->assertOk()
        ->assertSee('Team Standing')
        ->assertSeeInOrder(['Alpha Line', 'Beta Line']);
});

test('team standing tab redirects to seeding when bracket workflow threshold is met', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'No Standing Tab Cup',
        'slug' => 'no-standing-tab-cup',
        'venue' => 'Arena',
        'status' => 'draft',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => false,
    ]);

    distrackPadRegistrationsForBracketWorkflowTabs($tournament, $owner, 0);

    $this->actingAs($admin);

    $this->get(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'team-standing']))
        ->assertRedirect(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'overview']));
});

test('admin setup reveals bracket workflow tabs after registration count reaches threshold', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Bracket Tabs Threshold Cup',
        'slug' => 'bracket-tabs-threshold-cup',
        'venue' => 'Downtown Arena',
        'status' => 'draft',
        'country_name' => 'Philippines',
        'province' => 'Bukidnon',
        'city' => 'Valencia City',
        'barangay' => 'Poblacion',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => false,
    ]);

    distrackPadRegistrationsForBracketWorkflowTabs($tournament, $owner, 0);

    $this->actingAs($admin);

    $this->get(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'overview']))
        ->assertOk()
        ->assertSee('tab=bracket-ranking', false)
        ->assertSee('tab=crossover', false)
        ->assertSee('tab=pooling', false)
        ->assertDontSee('tab=team-standing', false);
});

test('round robin tab no longer shows the tournament profile form', function () {
    $admin = User::factory()->admin()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Round Robin Only Cup',
        'slug' => 'round-robin-only-cup',
        'venue' => 'Main Grounds',
        'status' => 'draft',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => false,
    ]);

    $this->actingAs($admin);

    $this->get(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'round-robin']))
        ->assertOk()
        ->assertSee('Round Robin')
        ->assertSee('Generate Bracket Round Robin')
        ->assertSee('No pitches yet')
        ->assertSee('Add a pitch to start building the Day 1 board.')
        ->assertDontSee('Tournament Profile')
        ->assertDontSee('Save Tournament Changes');
});

test('round robin tab shows per-pitch schedule actions for assigned matches', function () {
    $admin = User::factory()->admin()->create();
    $teamOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Pitch Schedule Cup',
        'slug' => 'pitch-schedule-cup',
        'venue' => 'Main Grounds',
        'status' => 'draft',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => false,
    ]);

    $pitch = Pitch::query()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Pitch Alpha',
        'location' => 'North Field',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $homeTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Sky Riders',
        'address' => 'Valencia City',
        'status' => 'active',
    ]);

    $awayTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Flight Paths',
        'address' => 'Valencia City',
        'status' => 'active',
    ]);

    $homeRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $homeTeam->id,
        'status' => 'approved',
        'seed_number' => 1,
        'bracket_code' => 'Bracket A',
    ]);

    $awayRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $awayTeam->id,
        'status' => 'approved',
        'seed_number' => 2,
        'bracket_code' => 'Bracket A',
    ]);

    TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'pitch_id' => $pitch->id,
        'home_registration_id' => $homeRegistration->id,
        'away_registration_id' => $awayRegistration->id,
        'stage' => 'round_robin',
        'round_label' => 'Bracket A - Round 1',
        'match_number' => 1,
        'scheduled_at' => null,
        'status' => 'scheduled',
    ]);

    $this->actingAs($admin);

    $this->get(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'round-robin']))
        ->assertOk()
        ->assertSee('Add Match')
        ->assertSee('Pitch Schedule')
        ->assertSee('Sky Riders')
        ->assertSee('Flight Paths');
});

test('legacy basic info tab links redirect to the round robin tab', function () {
    $admin = User::factory()->admin()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Legacy Tab Alias Cup',
        'slug' => 'legacy-tab-alias-cup',
        'venue' => 'Main Grounds',
        'status' => 'draft',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => false,
    ]);

    $this->actingAs($admin);

    $this->get(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'basic-info']))
        ->assertRedirect(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'round-robin']));
});

test('overview shows seeded team names inside current bracket cards', function () {
    $admin = User::factory()->admin()->create();
    $teamOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Bracket Summary Cup',
        'slug' => 'bracket-summary-cup',
        'venue' => 'Main Grounds',
        'status' => 'registration',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => false,
    ]);

    foreach (range(1, AdminTournamentController::BRACKET_TEAM_LIMIT * 2) as $number) {
        $team = Team::query()->create([
            'owner_user_id' => $teamOwner->id,
            'name' => 'Bracket Summary Team '.$number,
            'address' => 'Valencia City',
            'status' => 'active',
        ]);

        TournamentRegistration::query()->create([
            'tournament_id' => $tournament->id,
            'team_id' => $team->id,
            'status' => 'pending',
            'seed_number' => $number,
            'bracket_code' => $number <= AdminTournamentController::BRACKET_TEAM_LIMIT ? 'Bracket A' : 'Bracket B',
        ]);
    }

    $this->actingAs($admin);

    $this->get(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'overview']))
        ->assertOk()
        ->assertSee('Current Brackets')
        ->assertSee('Randomize current brackets')
        ->assertSee('A — Bracket Summary Team 1')
        ->assertSee('E — Bracket Summary Team 5')
        ->assertSee('F — Bracket Summary Team 6')
        ->assertSee('J — Bracket Summary Team 10');
});

test('admin users can auto seed 8 teams without creating brackets', function () {
    $admin = User::factory()->admin()->create();
    $teamOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Bracket Composer Cup',
        'slug' => 'bracket-composer-cup',
        'venue' => 'City Complex',
        'status' => 'registration',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => false,
    ]);

    foreach (range(1, 8) as $number) {
        $team = Team::query()->create([
            'owner_user_id' => $teamOwner->id,
            'name' => 'Seed Team '.$number,
            'address' => 'Valencia City',
            'status' => 'active',
        ]);

        TournamentRegistration::query()->create([
            'tournament_id' => $tournament->id,
            'team_id' => $team->id,
            'status' => 'pending',
        ]);
    }

    $this->actingAs($admin);

    $this->post(route('admin.tournaments.registrations.seed'), [
        'tournament_id' => $tournament->id,
        'redirect_route' => 'admin.tournaments.index',
        'redirect_tab' => 'overview',
    ])
        ->assertRedirect(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'overview']))
        ->assertSessionHas('status', 'registrations-seeded');

    $registrations = TournamentRegistration::query()
        ->where('tournament_id', $tournament->id)
        ->orderBy('seed_number')
        ->get();

    expect($registrations->pluck('seed_number')->all())->toBe([1, 2, 3, 4, 5, 6, 7, 8]);
    expect($registrations->pluck('bracket_code')->filter()->all())->toBe([]);
    expect($registrations->pluck('bracket_rank')->filter()->all())->toBe([]);
    expect($registrations->pluck('pool_name')->filter()->all())->toBe([]);
});

test('admin users can auto seed 10 teams into two random 5-team brackets', function () {
    $admin = User::factory()->admin()->create();
    $teamOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Overflow Seeding Cup',
        'slug' => 'overflow-seeding-cup',
        'venue' => 'City Complex',
        'status' => 'registration',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => false,
    ]);

    foreach (range(1, 10) as $number) {
        $team = Team::query()->create([
            'owner_user_id' => $teamOwner->id,
            'name' => 'Overflow Team '.$number,
            'address' => 'Valencia City',
            'status' => 'active',
        ]);

        TournamentRegistration::query()->create([
            'tournament_id' => $tournament->id,
            'team_id' => $team->id,
            'status' => 'pending',
        ]);
    }

    $this->actingAs($admin);

    $this->post(route('admin.tournaments.registrations.seed'), [
        'tournament_id' => $tournament->id,
        'redirect_route' => 'admin.tournaments.index',
        'redirect_tab' => 'overview',
    ])
        ->assertRedirect(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'overview']))
        ->assertSessionHas('status', 'registrations-seeded');

    $registrations = TournamentRegistration::query()
        ->where('tournament_id', $tournament->id)
        ->orderBy('seed_number')
        ->get();

    expect($registrations->pluck('seed_number')->all())->toBe([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
    expect($registrations->pluck('bracket_code')->filter()->count())->toBe(10);
    expect($registrations->where('bracket_code', 'Bracket A')->count())->toBe(5);
    expect($registrations->where('bracket_code', 'Bracket B')->count())->toBe(5);
});

test('admin users can auto seed teams through json for in-place bracket refreshes', function () {
    $admin = User::factory()->admin()->create();
    $teamOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Async Seeding Cup',
        'slug' => 'async-seeding-cup',
        'venue' => 'City Complex',
        'status' => 'registration',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => false,
    ]);

    foreach (range(1, 10) as $number) {
        $team = Team::query()->create([
            'owner_user_id' => $teamOwner->id,
            'name' => 'Async Team '.$number,
            'address' => 'Valencia City',
            'status' => 'active',
        ]);

        TournamentRegistration::query()->create([
            'tournament_id' => $tournament->id,
            'team_id' => $team->id,
            'status' => 'pending',
        ]);
    }

    $this->actingAs($admin);

    $response = $this->postJson(route('admin.tournaments.registrations.seed'), [
        'tournament_id' => $tournament->id,
        'redirect_route' => 'admin.tournaments.index',
        'redirect_tab' => 'overview',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', 'registrations-seeded')
        ->assertJsonPath('message', 'Teams seeded successfully. Brackets now use 5 teams each, and extra teams remain unassigned.')
        ->assertJsonStructure(['overview_html']);

    expect($response->json('overview_html'))->toContain('Current Brackets');
    expect($response->json('overview_html'))->toContain('Randomize current brackets');
});

test('legacy round robin generator endpoint no longer creates matches even when brackets and pitches exist', function () {
    $admin = User::factory()->admin()->create();
    $teamOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Round Robin Sample Cup',
        'slug' => 'round-robin-sample-cup',
        'venue' => 'City Complex',
        'status' => 'registration',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => false,
    ]);

    $pitch1 = Pitch::query()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Pitch 1',
        'location' => 'North Field',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $pitch2 = Pitch::query()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Pitch 2',
        'location' => 'South Field',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    collect(range(1, AdminTournamentController::BRACKET_TEAM_LIMIT * 2))
        ->map(function (int $number) use ($teamOwner, $tournament) {
            $team = Team::query()->create([
                'owner_user_id' => $teamOwner->id,
                'name' => 'Round Robin Team '.$number,
                'address' => 'Valencia City',
                'status' => 'active',
            ]);

            return TournamentRegistration::query()->create([
                'tournament_id' => $tournament->id,
                'team_id' => $team->id,
                'status' => 'pending',
                'seed_number' => $number,
                'bracket_code' => $number <= AdminTournamentController::BRACKET_TEAM_LIMIT ? 'Bracket A' : 'Bracket B',
            ]);
        });

    $this->actingAs($admin);

    $this->post(route('admin.tournaments.matches.round-robin.generate'), [
        'tournament_id' => $tournament->id,
        'redirect_route' => 'admin.tournaments.index',
        'redirect_tab' => 'round-robin',
    ])
        ->assertRedirect(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'round-robin']))
        ->assertSessionHas('status', 'round-robin-generated');

    $matches = TournamentMatch::query()
        ->where('tournament_id', $tournament->id)
        ->where('stage', 'round_robin')
        ->orderBy('match_number')
        ->get();

    expect($matches)->toHaveCount(20);
    expect($matches->where('pitch_id', $pitch1->id))->toHaveCount(10);
    expect($matches->where('pitch_id', $pitch2->id))->toHaveCount(10);
    expect($matches->where('status', 'completed'))->toHaveCount(20);
    expect($matches->where('home_score', 0))->toHaveCount(20);
    expect($matches->where('away_score', 0))->toHaveCount(20);
});

test('admin users cannot create a duplicate round robin matchup for the same two teams', function () {
    $admin = User::factory()->admin()->create();
    $teamOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Duplicate Pairing Cup',
        'slug' => 'duplicate-pairing-cup',
        'venue' => 'City Complex',
        'status' => 'registration',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => false,
    ]);

    $pitch = Pitch::query()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Pitch 1',
        'location' => 'North Field',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $homeTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Seeded Daybreak',
        'address' => 'Valencia City',
        'status' => 'active',
    ]);

    $awayTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Seeded Voltstream',
        'address' => 'Valencia City',
        'status' => 'active',
    ]);

    $homeRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $homeTeam->id,
        'status' => 'approved',
        'seed_number' => 1,
        'bracket_code' => 'Bracket A',
    ]);

    $awayRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $awayTeam->id,
        'status' => 'approved',
        'seed_number' => 2,
        'bracket_code' => 'Bracket A',
    ]);

    TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'pitch_id' => $pitch->id,
        'home_registration_id' => $homeRegistration->id,
        'away_registration_id' => $awayRegistration->id,
        'stage' => 'round_robin',
        'round_label' => 'Bracket A - Round 1',
        'match_number' => 1,
        'scheduled_at' => now()->addDay(),
        'status' => 'scheduled',
    ]);

    $this->actingAs($admin);

    $this->from(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'round-robin']))
        ->post(route('admin.tournaments.matches.store'), [
            'tournament_id' => $tournament->id,
            'pitch_id' => $pitch->id,
            'home_registration_id' => $awayRegistration->id,
            'away_registration_id' => $homeRegistration->id,
            'stage' => 'round_robin',
            'round_robin_bracket_code' => 'Bracket A',
            'round_label' => 'Bracket A - Round 2',
            'match_number' => 2,
            'status' => 'scheduled',
            'redirect_route' => 'admin.tournaments.index',
            'redirect_tab' => 'round-robin',
            'match_tournament_id' => $tournament->id,
            'match_form_intent' => 'round_robin_add',
            'round_robin_pitch_context' => 'pitch-'.$pitch->id,
        ])
        ->assertRedirect(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'round-robin']))
        ->assertSessionHasErrors(['away_registration_id']);

    expect(TournamentMatch::query()
        ->where('tournament_id', $tournament->id)
        ->where('stage', 'round_robin')
        ->count())->toBe(1);
});

test('admin users can reuse a team on the same pitch against a different round robin opponent', function () {
    $admin = User::factory()->admin()->create();
    $teamOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Pitch Lock Cup',
        'slug' => 'pitch-lock-cup',
        'venue' => 'City Complex',
        'status' => 'registration',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => false,
    ]);

    $pitch = Pitch::query()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Pitch 1',
        'location' => 'North Field',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $teamNames = ['Seeded Daybreak', 'Seeded Voltstream', 'Seeded Riptide'];

    $registrations = collect($teamNames)->values()->map(function (string $teamName, int $index) use ($teamOwner, $tournament) {
        $team = Team::query()->create([
            'owner_user_id' => $teamOwner->id,
            'name' => $teamName,
            'address' => 'Valencia City',
            'status' => 'active',
        ]);

        return TournamentRegistration::query()->create([
            'tournament_id' => $tournament->id,
            'team_id' => $team->id,
            'status' => 'approved',
            'seed_number' => $index + 1,
            'bracket_code' => 'Bracket A',
        ]);
    });

    TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'pitch_id' => $pitch->id,
        'home_registration_id' => $registrations[0]->id,
        'away_registration_id' => $registrations[1]->id,
        'stage' => 'round_robin',
        'round_label' => 'Bracket A - Round 1',
        'match_number' => 1,
        'scheduled_at' => now()->addDay(),
        'status' => 'scheduled',
    ]);

    $this->actingAs($admin);

    $this->from(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'round-robin']))
        ->post(route('admin.tournaments.matches.store'), [
            'tournament_id' => $tournament->id,
            'pitch_id' => $pitch->id,
            'home_registration_id' => $registrations[0]->id,
            'away_registration_id' => $registrations[2]->id,
            'stage' => 'round_robin',
            'round_robin_bracket_code' => 'Bracket A',
            'round_label' => 'Bracket A - Round 2',
            'match_number' => 2,
            'status' => 'scheduled',
            'redirect_route' => 'admin.tournaments.index',
            'redirect_tab' => 'round-robin',
            'match_tournament_id' => $tournament->id,
            'match_form_intent' => 'round_robin_add',
            'round_robin_pitch_context' => 'pitch-'.$pitch->id,
        ])
        ->assertRedirect(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'round-robin']))
        ->assertSessionHasNoErrors();

    expect(TournamentMatch::query()
        ->where('tournament_id', $tournament->id)
        ->where('stage', 'round_robin')
        ->count())->toBe(2);
});

test('admin auto seeding leaves extra teams unassigned after full 5-team brackets are formed', function () {
    $admin = User::factory()->admin()->create();
    $teamOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Overflow Seeding Cup',
        'slug' => 'overflow-seeding-cup',
        'venue' => 'City Complex',
        'status' => 'registration',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => false,
    ]);

    foreach (range(1, 11) as $number) {
        $team = Team::query()->create([
            'owner_user_id' => $teamOwner->id,
            'name' => 'Overflow Team '.$number,
            'address' => 'Valencia City',
            'status' => 'active',
        ]);

        TournamentRegistration::query()->create([
            'tournament_id' => $tournament->id,
            'team_id' => $team->id,
            'status' => 'pending',
        ]);
    }

    $this->actingAs($admin);

    $this->post(route('admin.tournaments.registrations.seed'), [
        'tournament_id' => $tournament->id,
        'redirect_route' => 'admin.tournaments.index',
        'redirect_tab' => 'overview',
    ])
        ->assertRedirect(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'overview']))
        ->assertSessionHas('status', 'registrations-seeded');

    $registrations = TournamentRegistration::query()
        ->where('tournament_id', $tournament->id)
        ->orderBy('seed_number')
        ->get();

    expect($registrations->pluck('seed_number')->all())->toBe([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11]);
    expect($registrations->take(10)->where('bracket_code', 'Bracket A')->count())->toBe(5);
    expect($registrations->take(10)->where('bracket_code', 'Bracket B')->count())->toBe(5);
    expect($registrations->last()->bracket_code)->toBeNull();
});

test('admin users can manually update seeding metadata per team', function () {
    $admin = User::factory()->admin()->create();
    $teamOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Manual Seeding Cup',
        'slug' => 'manual-seeding-cup',
        'venue' => 'Field House',
        'status' => 'registration',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => false,
    ]);

    $registrations = collect(range(1, AdminTournamentController::BRACKET_TEAM_LIMIT * 2))
        ->map(function (int $number) use ($teamOwner, $tournament) {
            $team = Team::query()->create([
                'owner_user_id' => $teamOwner->id,
                'name' => 'Manual Team '.$number,
                'address' => 'Valencia City',
                'status' => 'active',
            ]);

            return TournamentRegistration::query()->create([
                'tournament_id' => $tournament->id,
                'team_id' => $team->id,
                'status' => 'pending',
            ]);
        });

    $this->actingAs($admin);

    $this->patch(route('admin.tournaments.registrations.seeding.update'), [
        'tournament_id' => $tournament->id,
        'redirect_route' => 'admin.tournaments.index',
        'redirect_tab' => 'overview',
        'registrations' => $registrations->values()->map(function (TournamentRegistration $registration, int $index): array {
            return [
                'id' => $registration->id,
                'seed_number' => $index + 1,
                'bracket_code' => $index < AdminTournamentController::BRACKET_TEAM_LIMIT ? 'A' : 'Bracket B',
            ];
        })->all(),
    ])
        ->assertRedirect(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'overview']))
        ->assertSessionHas('status', 'registrations-seeding-updated');

    $firstRegistration = $registrations->first()->fresh();
    $lastRegistration = $registrations->last()->fresh();

    expect($firstRegistration->seed_number)->toBe(1);
    expect($firstRegistration->bracket_code)->toBe('Bracket A');
    expect($firstRegistration->bracket_rank)->toBeNull();

    expect($lastRegistration->seed_number)->toBe(AdminTournamentController::BRACKET_TEAM_LIMIT * 2);
    expect($lastRegistration->bracket_code)->toBe('Bracket B');
    expect($lastRegistration->bracket_rank)->toBeNull();
});

test('admin users can update seed order for one bracket without resubmitting every team', function () {
    $admin = User::factory()->admin()->create();
    $teamOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Bracket Modal Edit Cup',
        'slug' => 'bracket-modal-edit-cup',
        'venue' => 'Field House',
        'status' => 'registration',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => false,
    ]);

    $registrations = collect(range(1, AdminTournamentController::BRACKET_TEAM_LIMIT * 2))
        ->map(function (int $number) use ($teamOwner, $tournament) {
            $team = Team::query()->create([
                'owner_user_id' => $teamOwner->id,
                'name' => 'Bracket Modal Team '.$number,
                'address' => 'Valencia City',
                'status' => 'active',
            ]);

            return TournamentRegistration::query()->create([
                'tournament_id' => $tournament->id,
                'team_id' => $team->id,
                'status' => 'pending',
                'seed_number' => $number,
                'bracket_code' => $number <= AdminTournamentController::BRACKET_TEAM_LIMIT ? 'Bracket A' : 'Bracket B',
            ]);
        });

    $this->actingAs($admin);

    $bracketARegistrations = $registrations->take(AdminTournamentController::BRACKET_TEAM_LIMIT)->values();

    $this->patch(route('admin.tournaments.registrations.seeding.update'), [
        'tournament_id' => $tournament->id,
        'redirect_route' => 'admin.tournaments.index',
        'redirect_tab' => 'overview',
        'seed_order_bracket_code' => 'Bracket A',
        'registrations' => $bracketARegistrations->map(function (TournamentRegistration $registration, int $index): array {
            return [
                'id' => $registration->id,
                'seed_number' => AdminTournamentController::BRACKET_TEAM_LIMIT - $index,
                'bracket_code' => 'Bracket A',
            ];
        })->all(),
    ])
        ->assertRedirect(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'overview']))
        ->assertSessionHas('status', 'registrations-seeding-updated');

    expect($bracketARegistrations->map(fn (TournamentRegistration $registration) => $registration->fresh()->seed_number)->all())
        ->toBe([5, 4, 3, 2, 1]);

    expect($registrations->slice(AdminTournamentController::BRACKET_TEAM_LIMIT)->values()->map(fn (TournamentRegistration $registration) => $registration->fresh()->seed_number)->all())
        ->toBe([6, 7, 8, 9, 10]);
});

test('admin users cannot reuse a seed number that already belongs to another unsubmitted team', function () {
    $admin = User::factory()->admin()->create();
    $teamOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Bracket Seed Conflict Cup',
        'slug' => 'bracket-seed-conflict-cup',
        'venue' => 'Field House',
        'status' => 'registration',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => false,
    ]);

    $registrations = collect(range(1, AdminTournamentController::BRACKET_TEAM_LIMIT * 2))
        ->map(function (int $number) use ($teamOwner, $tournament) {
            $team = Team::query()->create([
                'owner_user_id' => $teamOwner->id,
                'name' => 'Bracket Conflict Team '.$number,
                'address' => 'Valencia City',
                'status' => 'active',
            ]);

            return TournamentRegistration::query()->create([
                'tournament_id' => $tournament->id,
                'team_id' => $team->id,
                'status' => 'pending',
                'seed_number' => $number,
                'bracket_code' => $number <= AdminTournamentController::BRACKET_TEAM_LIMIT ? 'Bracket A' : 'Bracket B',
            ]);
        });

    $this->actingAs($admin);

    $response = $this->from(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'overview']))
        ->patch(route('admin.tournaments.registrations.seeding.update'), [
            'tournament_id' => $tournament->id,
            'redirect_route' => 'admin.tournaments.index',
            'redirect_tab' => 'overview',
            'seed_order_bracket_code' => 'Bracket A',
            'registrations' => $registrations->take(AdminTournamentController::BRACKET_TEAM_LIMIT)->values()->map(function (TournamentRegistration $registration, int $index): array {
                return [
                    'id' => $registration->id,
                    'seed_number' => $index === 0 ? 6 : $registration->seed_number,
                    'bracket_code' => 'Bracket A',
                ];
            })->all(),
        ]);

    $response
        ->assertRedirect(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'overview']))
        ->assertSessionHasErrors([
            'registrations',
            'registrations.0.seed_number',
        ]);

    expect($registrations->first()->fresh()->seed_number)->toBe(1);
});

test('admin users cannot assign more than 5 teams to one bracket during manual seeding', function () {
    $admin = User::factory()->admin()->create();
    $teamOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Bracket Limit Cup',
        'slug' => 'bracket-limit-cup',
        'venue' => 'City Oval',
        'status' => 'registration',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => false,
    ]);

    $registrations = collect(range(1, 10))->map(function (int $number) use ($teamOwner, $tournament) {
        $team = Team::query()->create([
            'owner_user_id' => $teamOwner->id,
            'name' => 'Limit Team '.$number,
            'address' => 'Valencia City',
            'status' => 'active',
        ]);

        return TournamentRegistration::query()->create([
            'tournament_id' => $tournament->id,
            'team_id' => $team->id,
            'status' => 'pending',
        ]);
    });

    $this->actingAs($admin);

    $response = $this->from(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'overview']))
        ->patch(route('admin.tournaments.registrations.seeding.update'), [
            'tournament_id' => $tournament->id,
            'redirect_route' => 'admin.tournaments.index',
            'redirect_tab' => 'overview',
            'registrations' => $registrations->values()->map(fn (TournamentRegistration $registration, int $index): array => [
                'id' => $registration->id,
                'seed_number' => $index + 1,
                'bracket_code' => $index < (AdminTournamentController::BRACKET_TEAM_LIMIT + 1) ? 'A' : 'B',
            ])->all(),
        ]);

    $response
        ->assertRedirect(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'overview']))
        ->assertSessionHasErrors([
            'registrations',
            'registrations.0.bracket_code',
            'registrations.6.bracket_code',
        ]);

    expect(TournamentRegistration::query()
        ->where('tournament_id', $tournament->id)
        ->whereNotNull('bracket_code')
        ->count())->toBe(0);
});

test('admin users cannot save incomplete manual brackets', function () {
    $admin = User::factory()->admin()->create();
    $teamOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Incomplete Bracket Cup',
        'slug' => 'incomplete-bracket-cup',
        'venue' => 'City Oval',
        'status' => 'registration',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => false,
    ]);

    $registrations = collect(range(1, 10))->map(function (int $number) use ($teamOwner, $tournament) {
        $team = Team::query()->create([
            'owner_user_id' => $teamOwner->id,
            'name' => 'Incomplete Team '.$number,
            'address' => 'Valencia City',
            'status' => 'active',
        ]);

        return TournamentRegistration::query()->create([
            'tournament_id' => $tournament->id,
            'team_id' => $team->id,
            'status' => 'pending',
        ]);
    });

    $this->actingAs($admin);

    $response = $this->from(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'overview']))
        ->patch(route('admin.tournaments.registrations.seeding.update'), [
            'tournament_id' => $tournament->id,
            'redirect_route' => 'admin.tournaments.index',
            'redirect_tab' => 'overview',
            'registrations' => $registrations->values()->map(fn (TournamentRegistration $registration, int $index): array => [
                'id' => $registration->id,
                'seed_number' => $index + 1,
                'bracket_code' => $index < AdminTournamentController::BRACKET_TEAM_LIMIT
                    ? 'A'
                    : ($index < ((AdminTournamentController::BRACKET_TEAM_LIMIT * 2) - 1) ? 'B' : null),
            ])->all(),
        ]);

    $response
        ->assertRedirect(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'overview']))
        ->assertSessionHasErrors([
            'registrations',
            'registrations.5.bracket_code',
        ]);

    expect(TournamentRegistration::query()
        ->where('tournament_id', $tournament->id)
        ->whereNotNull('bracket_code')
        ->count())->toBe(0);
});

test('admin users cannot manually create bracket play before 10 teams are available', function () {
    $admin = User::factory()->admin()->create();
    $teamOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Early Bracket Cup',
        'slug' => 'early-bracket-cup',
        'venue' => 'City Oval',
        'status' => 'registration',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => false,
    ]);

    $registrations = collect(range(1, AdminTournamentController::BRACKET_TEAM_LIMIT))->map(function (int $number) use ($teamOwner, $tournament) {
        $team = Team::query()->create([
            'owner_user_id' => $teamOwner->id,
            'name' => 'Early Team '.$number,
            'address' => 'Valencia City',
            'status' => 'active',
        ]);

        return TournamentRegistration::query()->create([
            'tournament_id' => $tournament->id,
            'team_id' => $team->id,
            'status' => 'pending',
        ]);
    });

    $this->actingAs($admin);

    $response = $this->from(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'overview']))
        ->patch(route('admin.tournaments.registrations.seeding.update'), [
            'tournament_id' => $tournament->id,
            'redirect_route' => 'admin.tournaments.index',
            'redirect_tab' => 'overview',
            'registrations' => $registrations->values()->map(fn (TournamentRegistration $registration, int $index): array => [
                'id' => $registration->id,
                'seed_number' => $index + 1,
                'bracket_code' => 'A',
            ])->all(),
        ]);

    $response
        ->assertRedirect(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'overview']))
        ->assertSessionHasErrors([
            'registrations',
            'registrations.0.bracket_code',
        ]);

    expect(TournamentRegistration::query()
        ->where('tournament_id', $tournament->id)
        ->whereNotNull('bracket_code')
        ->count())->toBe(0);
});

test('admin pooling tab loads under tab=pooling and legacy format links redirect safely', function () {
    $admin = User::factory()->admin()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Frisbee Flow Cup',
        'slug' => 'frisbee-flow-cup',
        'venue' => 'Ultimate Grounds',
        'status' => 'draft',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => false,
    ]);

    $this->actingAs($admin);

    $this->get(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'format']))
        ->assertRedirect(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'pooling']));

    $this->get(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'pooling']))
        ->assertRedirect(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'overview']));

    $teamOwner = User::factory()->create();

    distrackPadRegistrationsForBracketWorkflowTabs($tournament, $teamOwner, 0);

    $this->get(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'pooling']))
        ->assertOk()
        ->assertSee('Pooling')
        ->assertSee('No crossover matches found yet')
        ->assertDontSee('POOL A');
});

test('legacy tab=matches redirects to tab=quarter-final on tournament setup', function () {
    $admin = User::factory()->admin()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Quarter Tab Cup',
        'slug' => 'quarter-tab-cup',
        'venue' => 'Central Field',
        'status' => 'draft',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => false,
    ]);

    $this->actingAs($admin);

    $this->get(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'matches']))
        ->assertRedirect(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'quarter-final']));

    $this->get(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'quarter-final']))
        ->assertOk()
        ->assertSee('Quarter Finals', false)
        ->assertSee('Generate Quarter Finals from pooling', false)
        ->assertSee('Cannot generate Quarter Finals yet', false);
});

test('generating quarter finals without finalized pooling shows validation error', function () {
    $admin = User::factory()->admin()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Quarter Gen Guard Cup',
        'slug' => 'quarter-gen-guard-cup',
        'venue' => 'Central Field',
        'status' => 'draft',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => false,
    ]);

    $this->actingAs($admin);

    $this->post(route('admin.tournaments.matches.quarter-finals.generate', $tournament), [
        'redirect_tab' => 'quarter-final',
    ])
        ->assertRedirect(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'quarter-final']))
        ->assertSessionHasErrors('quarter_final');
});

test('scorekeepers can access scoring routes without full admin setup tools', function () {
    $scorekeeper = User::factory()->scorekeeper()->create();
    $teamOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => User::factory()->admin()->create()->id,
        'name' => 'Scorekeeper Access Cup',
        'slug' => 'scorekeeper-access-cup',
        'venue' => 'River Park',
        'status' => 'live',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    $homeTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Northwind',
        'address' => 'Cagayan de Oro',
        'status' => 'active',
    ]);

    $awayTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Southline',
        'address' => 'Davao',
        'status' => 'active',
    ]);

    $homeRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $homeTeam->id,
        'status' => 'approved',
    ]);

    $awayRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $awayTeam->id,
        'status' => 'approved',
    ]);

    $match = TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'home_registration_id' => $homeRegistration->id,
        'away_registration_id' => $awayRegistration->id,
        'stage' => 'group',
        'match_number' => 2,
        'status' => 'scheduled',
    ]);

    $pitch = Pitch::query()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Scorekeeper Field',
        'sort_order' => 1,
        'scorekeeper_user_id' => $scorekeeper->id,
    ]);

    $match->update(['pitch_id' => $pitch->id]);

    $this->actingAs($scorekeeper);

    $this->get(route('admin.tournaments.list'))
        ->assertOk()
        ->assertSee('Score Matches')
        ->assertDontSee('Register Team')
        ->assertDontSee('Edit Tournament')
        ->assertDontSee('Save Tournament Changes');

    $this->get(route('admin.tournaments.index', ['tournament' => $tournament->id]))
        ->assertOk()
        ->assertSee('Tournament Scoring Console')
        ->assertSee('Manage Scoring')
        ->assertSee('Games Dashboard')
        ->assertDontSee('Create Tournament')
        ->assertDontSee('Add Pitch')
        ->assertDontSee('Register Team')
        ->assertDontSee('Add Match')
        ->assertDontSee('Add Crew Member');

    $this->get(route('admin.tournaments.matches.scoring', ['tournament' => $tournament, 'match' => $match]))
        ->assertOk()
        ->assertSee('Game Score')
        ->assertDontSee('Match Control');

    $this->get(route('admin.tournaments.matches.scoring.shortcut', ['match' => $match]))
        ->assertRedirect(route('admin.tournaments.matches.scoring', ['tournament' => $tournament, 'match' => $match]));
});

test('scorekeepers cannot open scoring for matches assigned to another scorekeepers pitch', function () {
    $admin = User::factory()->admin()->create();
    $scorekeeperA = User::factory()->scorekeeper()->create();
    $scorekeeperB = User::factory()->scorekeeper()->create();
    $teamOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Pitch Gate Cup',
        'slug' => 'pitch-gate-cup',
        'venue' => 'River Park',
        'status' => 'live',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    $homeTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Gate Home',
        'address' => 'Cagayan de Oro',
        'status' => 'active',
    ]);

    $awayTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Gate Away',
        'address' => 'Davao',
        'status' => 'active',
    ]);

    $homeRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $homeTeam->id,
        'status' => 'approved',
    ]);

    $awayRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $awayTeam->id,
        'status' => 'approved',
    ]);

    $pitch = Pitch::query()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Field B Only',
        'sort_order' => 1,
        'scorekeeper_user_id' => $scorekeeperB->id,
    ]);

    $match = TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'pitch_id' => $pitch->id,
        'home_registration_id' => $homeRegistration->id,
        'away_registration_id' => $awayRegistration->id,
        'stage' => 'crossover',
        'match_number' => 1,
        'status' => 'scheduled',
    ]);

    $this->actingAs($scorekeeperA);

    $this->get(route('admin.tournaments.matches.scoring', ['tournament' => $tournament, 'match' => $match]))
        ->assertForbidden();

    $this->get(route('admin.tournaments.matches.scoring.shortcut', ['match' => $match]))
        ->assertForbidden();
});

test('scorekeepers see games on their assigned pitches and on pitches with no assigned scorekeeper', function () {
    $admin = User::factory()->admin()->create();
    $scorekeeperA = User::factory()->scorekeeper()->create();
    $scorekeeperB = User::factory()->scorekeeper()->create();
    $teamOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Split Field Cup',
        'slug' => 'split-field-cup',
        'venue' => 'River Park',
        'status' => 'live',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    $pitchA = Pitch::query()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Alpha Lane',
        'sort_order' => 1,
        'scorekeeper_user_id' => $scorekeeperA->id,
    ]);

    $pitchB = Pitch::query()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Beta Lane',
        'sort_order' => 2,
        'scorekeeper_user_id' => $scorekeeperB->id,
    ]);

    $teams = collect(['Alpha Only Home', 'Alpha Only Away', 'Beta Only Home', 'Beta Only Away'])
        ->map(function (string $label) use ($teamOwner): Team {
            return Team::query()->create([
                'owner_user_id' => $teamOwner->id,
                'name' => $label,
                'address' => 'Test City',
                'status' => 'active',
            ]);
        });

    $regs = $teams->map(fn (Team $team): TournamentRegistration => TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $team->id,
        'status' => 'approved',
    ]));

    $matchOnA = TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'pitch_id' => $pitchA->id,
        'home_registration_id' => $regs[0]->id,
        'away_registration_id' => $regs[1]->id,
        'stage' => 'crossover',
        'match_number' => 1,
        'status' => 'scheduled',
    ]);

    $matchOnB = TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'pitch_id' => $pitchB->id,
        'home_registration_id' => $regs[2]->id,
        'away_registration_id' => $regs[3]->id,
        'stage' => 'crossover',
        'match_number' => 2,
        'status' => 'scheduled',
    ]);

    $pitchOpen = Pitch::query()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Open Lane',
        'sort_order' => 3,
        'scorekeeper_user_id' => null,
    ]);

    $openHome = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Open Lane Home',
        'address' => 'Test City',
        'status' => 'active',
    ]);

    $openAway = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Open Lane Away',
        'address' => 'Test City',
        'status' => 'active',
    ]);

    $openHomeReg = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $openHome->id,
        'status' => 'approved',
    ]);

    $openAwayReg = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $openAway->id,
        'status' => 'approved',
    ]);

    TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'pitch_id' => $pitchOpen->id,
        'home_registration_id' => $openHomeReg->id,
        'away_registration_id' => $openAwayReg->id,
        'stage' => 'crossover',
        'match_number' => 3,
        'status' => 'scheduled',
    ]);

    $this->actingAs($scorekeeperA);

    $this->get(route('admin.tournaments.index', ['tournament' => $tournament->id]))
        ->assertOk()
        ->assertSee('Alpha Only Home')
        ->assertSee('Alpha Only Away')
        ->assertSee('Open Lane Home')
        ->assertSee('Open Lane Away')
        ->assertDontSee('Beta Only Home')
        ->assertDontSee('Beta Only Away');
});

test('scorekeepers see games and can open scoring on pitches with no assigned scorekeeper', function () {
    $admin = User::factory()->admin()->create();
    $scorekeeper = User::factory()->scorekeeper()->create();
    $teamOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Unassigned Pitch Cup',
        'slug' => 'unassigned-pitch-cup',
        'venue' => 'River Park',
        'status' => 'live',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    $pitch = Pitch::query()->create([
        'tournament_id' => $tournament->id,
        'name' => 'North Field',
        'sort_order' => 1,
        'scorekeeper_user_id' => null,
    ]);

    $homeTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'North Home',
        'address' => 'Test City',
        'status' => 'active',
    ]);

    $awayTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'North Away',
        'address' => 'Test City',
        'status' => 'active',
    ]);

    $homeReg = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $homeTeam->id,
        'status' => 'approved',
    ]);

    $awayReg = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $awayTeam->id,
        'status' => 'approved',
    ]);

    $match = TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'pitch_id' => $pitch->id,
        'home_registration_id' => $homeReg->id,
        'away_registration_id' => $awayReg->id,
        'stage' => 'round_robin',
        'match_number' => 1,
        'status' => 'scheduled',
    ]);

    $this->actingAs($scorekeeper);

    $this->get(route('admin.tournaments.index', ['tournament' => $tournament->id]))
        ->assertOk()
        ->assertSee('North Home')
        ->assertSee('North Away')
        ->assertSee('Manage Scoring');

    $this->get(route('admin.tournaments.matches.scoring', ['tournament' => $tournament, 'match' => $match]))
        ->assertOk();
});

test('scorekeepers are redirected away from setup tab query strings', function () {
    $scorekeeper = User::factory()->scorekeeper()->create();
    $admin = User::factory()->admin()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Tab Strip Cup',
        'slug' => 'tab-strip-cup',
        'venue' => 'River Park',
        'status' => 'live',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    $this->actingAs($scorekeeper);

    $this->get(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'crossover']))
        ->assertRedirect(route('admin.tournaments.index', ['tournament' => $tournament->id]));
});

test('admin users cannot enter match scores', function () {
    $admin = User::factory()->admin()->create();
    $teamOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Admin No Score Cup',
        'slug' => 'admin-no-score-cup',
        'venue' => 'Metro Field',
        'status' => 'live',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    $homeTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Admin Home',
        'address' => 'Cagayan de Oro',
        'status' => 'active',
    ]);

    $awayTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Admin Away',
        'address' => 'Davao',
        'status' => 'active',
    ]);

    $homeRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $homeTeam->id,
        'status' => 'approved',
    ]);

    $awayRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $awayTeam->id,
        'status' => 'approved',
    ]);

    $match = TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'home_registration_id' => $homeRegistration->id,
        'away_registration_id' => $awayRegistration->id,
        'stage' => 'round_robin',
        'round_label' => 'Round 1',
        'match_number' => 1,
        'status' => 'scheduled',
    ]);

    $this->actingAs($admin);

    $this->get(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'round-robin']))
        ->assertOk()
        ->assertDontSee('Input Score')
        ->assertDontSee('Open Scoring');

    $this->get(route('admin.tournaments.matches.scoring', ['tournament' => $tournament, 'match' => $match]))
        ->assertForbidden();

    $scoringUrl = route('admin.tournaments.matches.scoring', ['tournament' => $tournament, 'match' => $match]);

    $this->patch($scoringUrl, [
        'status' => 'completed',
        'home_score' => 11,
        'away_score' => 9,
    ])->assertStatus(405);

    expect($match->fresh()->status)->toBe('scheduled');
    expect($match->fresh()->home_score)->toBeNull();
    expect($match->fresh()->away_score)->toBeNull();
});

test('scorekeepers cannot use admin-only tournament mutation routes', function () {
    $scorekeeper = User::factory()->scorekeeper()->create();
    $admin = User::factory()->admin()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Restricted Admin Cup',
        'slug' => 'restricted-admin-cup',
        'venue' => 'Metro Field',
        'status' => 'draft',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => true,
    ]);

    $this->actingAs($scorekeeper);

    $this->post(route('admin.tournaments.store'), [
        'name' => 'Forbidden Tournament',
        'venue' => 'Arena',
        'province_code' => '101300000',
        'province' => 'Bukidnon',
        'city_code' => '101312000',
        'city' => 'Valencia City',
        'barangay_code' => '101312000001',
        'barangay' => 'Poblacion',
        'country_name' => 'Philippines',
        'status' => 'draft',
    ])->assertForbidden();

    $this->put(route('admin.tournaments.update', $tournament), [
        'edit_name' => 'Restricted Admin Cup Updated',
        'edit_venue' => 'Metro Field',
        'edit_province_code' => '101300000',
        'edit_province' => 'Bukidnon',
        'edit_city_code' => '101312000',
        'edit_city' => 'Valencia City',
        'edit_barangay_code' => '101312000001',
        'edit_barangay' => 'Poblacion',
        'edit_country_name' => 'Philippines',
        'edit_status' => 'draft',
    ])->assertForbidden();

    $this->post(route('admin.tournaments.matches.crossover.generate'), [
        'tournament_id' => $tournament->id,
    ])->assertForbidden();

    $this->post(route('admin.tournaments.matches.quarter-finals.generate', $tournament), [
        'redirect_tab' => 'quarter-final',
    ])->assertForbidden();
});

test('admin users can search tournaments in the admin directory', function () {
    $admin = User::factory()->admin()->create();

    Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Laguna Outdoor Cup',
        'slug' => 'laguna-outdoor-cup',
        'venue' => 'Greenfield Sports Field',
        'status' => 'registration',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Indoor Private League',
        'slug' => 'indoor-private-league',
        'venue' => 'North Hall',
        'status' => 'draft',
        'country_name' => 'Philippines',
        'surface' => 'Indoor',
        'division' => 'Women',
        'is_public' => false,
    ]);

    Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Beach Public Jam',
        'slug' => 'beach-public-jam',
        'venue' => 'Seaside Grounds',
        'status' => 'live',
        'country_name' => 'Philippines',
        'surface' => 'Beach',
        'division' => 'Open',
        'is_public' => true,
    ]);

    $this->actingAs($admin);

    $this->get(route('admin.tournaments.list', [
        'search' => 'Laguna',
    ]))
        ->assertOk()
        ->assertSee('Laguna Outdoor Cup')
        ->assertDontSee('Indoor Private League')
        ->assertDontSee('Beach Public Jam')
        ->assertSee('1 of 3 tournaments');
});

test('admin users can register teams from the admin tournaments directory', function () {
    $admin = User::factory()->admin()->create();
    $teamOwner = User::factory()->create();

    $team = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Skybound',
        'address' => 'Quezon City',
        'status' => 'active',
    ]);

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Metro Open',
        'slug' => 'metro-open',
        'venue' => 'Central Grounds',
        'status' => 'registration',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => true,
    ]);

    $this->actingAs($admin);

    $this->post(route('admin.tournaments.registrations.store'), [
        'tournament_id' => $tournament->id,
        'team_id' => $team->id,
        'redirect_route' => 'admin.tournaments.list',
    ])->assertRedirect(route('admin.tournaments.list', [
        'tournament' => $tournament->id,
    ]));

    $registration = TournamentRegistration::query()
        ->where('tournament_id', $tournament->id)
        ->where('team_id', $team->id)
        ->first();

    expect($registration)->not->toBeNull();
    expect($registration->status)->toBe('pending');
    expect($registration->seed_number)->toBeNull();
    expect($registration->bracket_code)->toBeNull();
    expect($registration->bracket_rank)->toBeNull();
    expect($registration->pool_name)->toBeNull();
});

test('admin users can bulk register multiple teams from the directory modal', function () {
    $admin = User::factory()->admin()->create();
    $teamOwner = User::factory()->create();

    $firstTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Skybound',
        'address' => 'Quezon City',
        'status' => 'active',
    ]);

    $secondTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Disc Hawks',
        'address' => 'Makati City',
        'status' => 'active',
    ]);

    $thirdTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Flight Paths',
        'address' => 'Taguig City',
        'status' => 'active',
    ]);

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Metro Open',
        'slug' => 'metro-open',
        'venue' => 'Central Grounds',
        'status' => 'registration',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => true,
    ]);

    $this->actingAs($admin);

    $this->post(route('admin.tournaments.registrations.store'), [
        'tournament_id' => $tournament->id,
        'team_ids' => [$firstTeam->id, $secondTeam->id, $thirdTeam->id],
        'redirect_route' => 'admin.tournaments.list',
    ])
        ->assertRedirect(route('admin.tournaments.list', [
            'tournament' => $tournament->id,
        ]))
        ->assertSessionHas('status', 'registrations-created');

    expect(TournamentRegistration::query()
        ->where('tournament_id', $tournament->id)
        ->whereIn('team_id', [$firstTeam->id, $secondTeam->id, $thirdTeam->id])
        ->count())->toBe(3);

    expect(TournamentRegistration::query()
        ->where('tournament_id', $tournament->id)
        ->where('team_id', $firstTeam->id)
        ->value('status'))->toBe('pending');
});

test('bulk register rejects teams already registered in the tournament', function () {
    $admin = User::factory()->admin()->create();
    $teamOwner = User::factory()->create();

    $registeredTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Skybound',
        'address' => 'Quezon City',
        'status' => 'active',
    ]);

    $newTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Disc Hawks',
        'address' => 'Makati City',
        'status' => 'active',
    ]);

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Metro Open',
        'slug' => 'metro-open',
        'venue' => 'Central Grounds',
        'status' => 'registration',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => true,
    ]);

    TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $registeredTeam->id,
        'status' => 'approved',
    ]);

    $this->actingAs($admin);

    $this->from(route('admin.tournaments.list', [
        'tournament' => $tournament->id,
    ]))
        ->post(route('admin.tournaments.registrations.store'), [
            'tournament_id' => $tournament->id,
            'team_ids' => [$registeredTeam->id, $newTeam->id],
            'redirect_route' => 'admin.tournaments.list',
        ])
        ->assertRedirect(route('admin.tournaments.list', [
            'tournament' => $tournament->id,
        ]))
        ->assertSessionHasErrors([
            'team_ids.0' => 'One or more selected teams are already registered in this tournament.',
        ]);

    expect(TournamentRegistration::query()
        ->where('tournament_id', $tournament->id)
        ->where('team_id', $newTeam->id)
        ->exists())->toBeFalse();

    expect(TournamentRegistration::query()
        ->where('tournament_id', $tournament->id)
        ->where('team_id', $registeredTeam->id)
        ->count())->toBe(1);
});

test('bulk register requires at least one team to be selected', function () {
    $admin = User::factory()->admin()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Metro Open',
        'slug' => 'metro-open',
        'venue' => 'Central Grounds',
        'status' => 'registration',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => true,
    ]);

    $this->actingAs($admin);

    $this->from(route('admin.tournaments.list', [
        'tournament' => $tournament->id,
    ]))
        ->post(route('admin.tournaments.registrations.store'), [
            'tournament_id' => $tournament->id,
            'team_ids' => [],
            'redirect_route' => 'admin.tournaments.list',
        ])
        ->assertRedirect(route('admin.tournaments.list', [
            'tournament' => $tournament->id,
        ]))
        ->assertSessionHasErrors([
            'team_ids' => 'Select at least one team to register.',
        ]);
});

test('admin tournament directory disables teams already registered to a tournament', function () {
    $admin = User::factory()->admin()->create();
    $teamOwner = User::factory()->create();

    $registeredTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Skybound',
        'address' => 'Quezon City',
        'status' => 'active',
    ]);

    $availableTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Disc Hawks',
        'address' => 'Makati City',
        'status' => 'active',
    ]);

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Metro Open',
        'slug' => 'metro-open',
        'venue' => 'Central Grounds',
        'status' => 'registration',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => true,
    ]);

    TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $registeredTeam->id,
        'status' => 'approved',
    ]);

    $this->actingAs($admin);

    $response = $this->get(route('admin.tournaments.list'))
        ->assertOk()
        ->assertSee('Disabled teams are already registered in this tournament.')
        ->assertSee('Already registered')
        ->assertSee($registeredTeam->name)
        ->assertSee($availableTeam->name)
        ->assertSee('name="team_ids[]"', false)
        ->assertSee('value="'.$availableTeam->id.'"', false);

    expect($response->getContent())
        ->toMatch('/value="'.$registeredTeam->id.'"[^<]*?\sdisabled[\s>]/s')
        ->not->toMatch('/value="'.$availableTeam->id.'"[^<]*?\sdisabled[\s>]/s');
});

test('admin tournament directory register modal stays focused on team attachment', function () {
    $admin = User::factory()->admin()->create();
    $teamOwner = User::factory()->create();

    $firstSeedTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Skybound',
        'address' => 'Quezon City',
        'status' => 'active',
    ]);

    $thirdSeedTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Disc Hawks',
        'address' => 'Makati City',
        'status' => 'active',
    ]);

    $unseededTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Flight Paths',
        'address' => 'Taguig City',
        'status' => 'active',
    ]);

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Metro Open',
        'slug' => 'metro-open',
        'venue' => 'Central Grounds',
        'status' => 'registration',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => true,
    ]);

    TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $firstSeedTeam->id,
        'status' => 'approved',
        'seed_number' => 1,
    ]);

    TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $thirdSeedTeam->id,
        'status' => 'approved',
        'seed_number' => 3,
    ]);

    TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $unseededTeam->id,
        'status' => 'pending',
        'seed_number' => null,
    ]);

    $this->actingAs($admin);

    $this->get(route('admin.tournaments.list'))
        ->assertOk()
        ->assertSee('Register Team')
        ->assertDontSee('Current Seed Board')
        ->assertDontSee('Next open seed')
        ->assertDontSee('Bracket Code')
        ->assertDontSee('Bracket Rank')
        ->assertDontSee('Pool Name')
        ->assertDontSee('Seed Number');
});

test('admin users cannot register the same team twice in one tournament', function () {
    $admin = User::factory()->admin()->create();
    $teamOwner = User::factory()->create();

    $team = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Skybound',
        'address' => 'Quezon City',
        'status' => 'active',
    ]);

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Metro Open',
        'slug' => 'metro-open',
        'venue' => 'Central Grounds',
        'status' => 'registration',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => true,
    ]);

    TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $team->id,
        'status' => 'approved',
    ]);

    $this->actingAs($admin);

    $this->from(route('admin.tournaments.list', [
        'tournament' => $tournament->id,
    ]))
        ->post(route('admin.tournaments.registrations.store'), [
            'tournament_id' => $tournament->id,
            'team_id' => $team->id,
            'status' => 'approved',
            'registration_tournament_id' => $tournament->id,
            'redirect_route' => 'admin.tournaments.list',
        ])
        ->assertRedirect(route('admin.tournaments.list', [
            'tournament' => $tournament->id,
        ]))
        ->assertSessionHasErrors([
            'team_id' => 'This team is already registered in the selected tournament.',
        ]);

    expect(TournamentRegistration::query()
        ->where('tournament_id', $tournament->id)
        ->where('team_id', $team->id)
        ->count())->toBe(1);
});

test('admin tournament registrations now default to pending without seeding metadata', function () {
    $admin = User::factory()->admin()->create();
    $teamOwner = User::factory()->create();

    $team = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Disc Hawks',
        'address' => 'Makati City',
        'status' => 'active',
    ]);

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Metro Open',
        'slug' => 'metro-open',
        'venue' => 'Central Grounds',
        'status' => 'registration',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => true,
    ]);

    $this->actingAs($admin);

    $this->post(route('admin.tournaments.registrations.store'), [
        'tournament_id' => $tournament->id,
        'team_id' => $team->id,
        'redirect_route' => 'admin.tournaments.list',
    ])->assertRedirect();

    $registration = TournamentRegistration::query()
        ->where('tournament_id', $tournament->id)
        ->where('team_id', $team->id)
        ->first();

    expect($registration)->not->toBeNull();
    expect($registration->status)->toBe('pending');
    expect($registration->seed_number)->toBeNull();
    expect($registration->bracket_code)->toBeNull();
    expect($registration->bracket_rank)->toBeNull();
    expect($registration->pool_name)->toBeNull();
});

test('authenticated users can fetch philippine tournament location options', function () {
    Cache::flush();

    Http::preventStrayRequests();

    Http::fake([
        'https://barangays.sanchez.ph/downloads/provinces.json' => Http::response([
            [
                'code' => '101300000',
                'name' => 'Bukidnon',
            ],
        ]),
        'https://barangays.sanchez.ph/downloads/cities.json' => Http::response([
            [
                'code' => '101312000',
                'name' => 'Valencia City',
                'province_code' => '101300000',
                'region_code' => '1000000000',
            ],
        ]),
        'https://barangays.sanchez.ph/downloads/barangays.json' => Http::response([
            [
                'code' => '101312000001',
                'name' => 'Poblacion',
                'city_code' => '101312000',
                'province_code' => '101300000',
                'region_code' => '1000000000',
            ],
        ]),
    ]);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    $this->get(route('locations.provinces'))
        ->assertOk()
        ->assertJsonFragment(['name' => 'Bukidnon']);

    $this->get(route('locations.cities', ['province_code' => '101300000']))
        ->assertOk()
        ->assertJsonFragment(['name' => 'Valencia City']);

    $this->get(route('locations.barangays', ['city_code' => '101312000']))
        ->assertOk()
        ->assertJsonFragment(['name' => 'Poblacion']);
});

test('admin users can create tournament resources', function () {
    Storage::fake('public');

    $admin = User::factory()->admin()->create();
    $teamOwner = User::factory()->create();
    $team = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Disc Hawks',
        'address' => 'Makati City',
        'logo_path' => null,
        'status' => 'active',
    ]);

    $this->actingAs($admin);

    $this->post(route('admin.tournaments.store'), [
        'name' => 'DISCTRACK Open',
        'venue' => 'Central Field',
        'description' => 'Season opener',
        'registration_deadline' => now()->addWeek()->format('Y-m-d H:i:s'),
        'starts_at' => now()->addWeeks(2)->format('Y-m-d H:i:s'),
        'ends_at' => now()->addWeeks(2)->addDay()->format('Y-m-d H:i:s'),
        'status' => 'draft',
        'province_code' => '101300000',
        'province' => 'Bukidnon',
        'city_code' => '101312000',
        'city' => 'Valencia City',
        'barangay_code' => '101312000001',
        'barangay' => 'Poblacion',
        'country_name' => 'Philippines',
        'timezone' => 'Asia/Manila',
        'venue_google_map_link' => 'https://maps.app.goo.gl/example',
        'thumbnail_path' => 'https://example.com/disctrack-open.png',
        'event_type' => 'Tournament',
        'division' => 'Mix',
        'surface' => 'Outdoor',
        'organizer_labels' => ['Contact Email', 'Hotline'],
        'organizer_values' => ['events@distrack.test', '+63 917 555 0101'],
        'info_labels' => ['Tournament hotline', 'Roster cap'],
        'info_values' => ['+63 917 555 0199', '26 players'],
        'link_labels' => ['Registration Form', 'Event Handbook'],
        'link_urls' => ['https://example.com/register', 'https://example.com/handbook'],
        'is_public' => '1',
    ])->assertRedirect();

    $tournament = Tournament::query()->first();

    expect($tournament)->not->toBeNull();
    expect($tournament->created_by)->toBe($admin->id);
    expect($tournament->country_name)->toBe('Philippines');
    expect($tournament->province)->toBe('Bukidnon');
    expect($tournament->barangay)->toBe('Poblacion');
    expect($tournament->timezone)->toBe('Asia/Manila');
    expect($tournament->organizer_items)->toBe([
        ['label' => 'Contact Email', 'value' => 'events@distrack.test'],
        ['label' => 'Hotline', 'value' => '+63 917 555 0101'],
    ]);
    expect($tournament->info_items)->toBe([
        ['label' => 'Tournament hotline', 'value' => '+63 917 555 0199'],
        ['label' => 'Roster cap', 'value' => '26 players'],
    ]);
    expect($tournament->link_items)->toBe([
        ['label' => 'Registration Form', 'href' => 'https://example.com/register'],
        ['label' => 'Event Handbook', 'href' => 'https://example.com/handbook'],
    ]);
    expect($tournament->is_public)->toBeTrue();

    $pitchResponse = $this->post(route('admin.tournaments.pitches.store'), [
        'tournament_id' => $tournament->id,
        'name' => 'Pitch 1',
        'location' => 'North Wing',
        'sort_order' => 1,
    ]);

    $pitchResponse->assertRedirect();

    $this->post(route('admin.tournaments.pitches.store'), [
        'tournament_id' => $tournament->id,
        'name' => 'Pitch 2',
        'location' => 'South Wing',
        'sort_order' => 2,
    ])->assertRedirect();

    $this->post(route('admin.tournaments.registrations.store'), [
        'tournament_id' => $tournament->id,
        'team_id' => $team->id,
        'status' => 'approved',
        'seed_number' => 1,
        'bracket_code' => 'A',
        'bracket_rank' => 'A1',
        'pool_name' => 'POOL A',
    ])->assertRedirect();

    $opponent = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Flight Paths',
        'address' => 'Taguig City',
        'status' => 'active',
    ]);

    $this->post(route('admin.tournaments.registrations.store'), [
        'tournament_id' => $tournament->id,
        'team_id' => $opponent->id,
        'status' => 'approved',
        'seed_number' => 2,
        'bracket_code' => 'A',
        'bracket_rank' => 'A2',
        'pool_name' => 'POOL A',
    ])->assertRedirect();

    $homeRegistration = TournamentRegistration::query()
        ->where('tournament_id', $tournament->id)
        ->where('team_id', $team->id)
        ->firstOrFail();

    $awayRegistration = TournamentRegistration::query()
        ->where('tournament_id', $tournament->id)
        ->where('team_id', $opponent->id)
        ->firstOrFail();

    $pitch = Pitch::query()
        ->where('tournament_id', $tournament->id)
        ->where('name', 'Pitch 1')
        ->firstOrFail();

    $this->post(route('admin.tournaments.matches.store'), [
        'tournament_id' => $tournament->id,
        'pitch_id' => $pitch->id,
        'home_registration_id' => $homeRegistration->id,
        'away_registration_id' => $awayRegistration->id,
        'stage' => 'pool_play',
        'round_label' => 'Group A',
        'match_number' => 1,
        'scheduled_at' => now()->addWeeks(2)->setTime(9, 0)->format('Y-m-d H:i:s'),
        'status' => 'completed',
        'home_score' => 13,
        'away_score' => 10,
        'notes' => 'Opening showcase',
    ])->assertRedirect();

    $this->post(route('admin.tournaments.crews.store'), [
        'tournament_id' => $tournament->id,
        'category' => 'Tournament Admins',
        'title' => 'Head TD',
        'name' => 'Casey Lim',
        'sort_order' => 1,
        'photo' => UploadedFile::fake()->image('casey-lim.png'),
    ])->assertRedirect();

    $tournament->refresh();

    $match = TournamentMatch::query()->where('tournament_id', $tournament->id)->first();
    $crewMember = TournamentCrew::query()->where('tournament_id', $tournament->id)->first();

    expect($tournament->pitches)->toHaveCount(2);
    expect($tournament->registrations)->toHaveCount(2);
    expect($tournament->registrations->firstWhere('team_id', $team->id))->not->toBeNull();
    expect($match)->not->toBeNull();
    expect($match->pitch_id)->toBe($pitch->id);
    expect($match->home_score)->toBe(13);
    expect($match->away_score)->toBe(10);
    expect($crewMember)->not->toBeNull();
    expect($crewMember->category)->toBe('Tournament Admins');
    expect($crewMember->photo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($crewMember->photo_path);
});

test('scorekeepers can record scoring plays after the match is completed and rebuild match totals', function () {
    $admin = User::factory()->admin()->create();
    $scorekeeper = User::factory()->scorekeeper()->create();
    $teamOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Live Console Cup',
        'slug' => 'live-console-cup',
        'venue' => 'North Grounds',
        'status' => 'live',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    $homeTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Manila Storm',
        'address' => 'Pasig',
        'status' => 'active',
    ]);

    $awayTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Cebu Breakers',
        'address' => 'Cebu City',
        'status' => 'active',
    ]);

    $homeRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $homeTeam->id,
        'status' => 'approved',
        'seed_number' => 1,
    ]);

    $awayRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $awayTeam->id,
        'status' => 'approved',
        'seed_number' => 2,
    ]);

    $homeScorer = TeamMember::query()->create([
        'team_id' => $homeTeam->id,
        'name' => 'Luis Ramos',
        'gender' => 'Male',
        'role' => 'captain',
    ]);

    $homeAssister = TeamMember::query()->create([
        'team_id' => $homeTeam->id,
        'name' => 'Paula Cruz',
        'gender' => 'Female',
        'role' => 'member',
    ]);

    $awayScorer = TeamMember::query()->create([
        'team_id' => $awayTeam->id,
        'name' => 'Mico Tan',
        'gender' => 'Male',
        'role' => 'captain',
    ]);

    $match = TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'home_registration_id' => $homeRegistration->id,
        'away_registration_id' => $awayRegistration->id,
        'stage' => 'group',
        'round_label' => 'Pool A',
        'match_number' => 3,
        'scheduled_at' => now()->addDays(2)->setTime(10, 0),
        'status' => 'scheduled',
    ]);

    $pitch = Pitch::query()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Live Field',
        'sort_order' => 1,
        'scorekeeper_user_id' => $scorekeeper->id,
    ]);

    $match->update(['pitch_id' => $pitch->id]);

    $this->actingAs($scorekeeper);

    $this->post(route('admin.tournaments.matches.scoring.store', ['tournament' => $tournament, 'match' => $match]), [
        'team_registration_id' => $homeRegistration->id,
        'team_member_id' => $homeScorer->id,
        'assist_team_member_id' => $homeAssister->id,
        'minute' => 5,
    ])->assertSessionHasErrors('team_registration_id');

    $match->update([
        'status' => 'completed',
        'home_score' => 0,
        'away_score' => 0,
    ]);

    $this->get(route('admin.tournaments.matches.scoring', ['tournament' => $tournament, 'match' => $match]))
        ->assertOk()
        ->assertSee('Game Score')
        ->assertDontSee('Match Control')
        ->assertSee('Manila Storm')
        ->assertSee('Cebu Breakers');

    $this->post(route('admin.tournaments.matches.scoring.store', ['tournament' => $tournament, 'match' => $match]), [
        'team_registration_id' => $homeRegistration->id,
        'team_member_id' => $homeScorer->id,
        'assist_team_member_id' => $homeAssister->id,
        'minute' => 5,
        'replace_manual_scoreline' => true,
    ])->assertRedirect(route('admin.tournaments.matches.scoring', ['tournament' => $tournament, 'match' => $match]));

    $match->refresh();

    expect($match->status)->toBe('completed');
    expect($match->home_score)->toBe(1);
    expect($match->away_score)->toBe(0);

    $firstLog = MatchScoreLog::query()
        ->where('match_id', $match->id)
        ->where('sequence', 1)
        ->first();

    expect($firstLog)->not->toBeNull();
    expect($firstLog->home_score)->toBe(1);
    expect($firstLog->away_score)->toBe(0);
    expect($firstLog->minute)->toBe(5);

    $homeScorerStats = MatchPlayerStat::query()
        ->where('match_id', $match->id)
        ->where('team_member_id', $homeScorer->id)
        ->first();

    $homeAssisterStats = MatchPlayerStat::query()
        ->where('match_id', $match->id)
        ->where('team_member_id', $homeAssister->id)
        ->first();

    expect($homeScorerStats?->goals)->toBe(1);
    expect($homeScorerStats?->assists)->toBe(0);
    expect($homeAssisterStats?->goals)->toBe(0);
    expect($homeAssisterStats?->assists)->toBe(1);

    $this->post(route('admin.tournaments.matches.scoring.store', ['tournament' => $tournament, 'match' => $match]), [
        'team_registration_id' => $awayRegistration->id,
        'team_member_id' => $awayScorer->id,
        'minute' => 12,
    ])->assertRedirect(route('admin.tournaments.matches.scoring', ['tournament' => $tournament, 'match' => $match]));

    $match->refresh();

    expect($match->home_score)->toBe(1);
    expect($match->away_score)->toBe(1);

    $secondLog = MatchScoreLog::query()
        ->where('match_id', $match->id)
        ->where('sequence', 2)
        ->first();

    expect($secondLog)->not->toBeNull();
    expect($secondLog->home_score)->toBe(1);
    expect($secondLog->away_score)->toBe(1);

    $this->delete(route('admin.tournaments.matches.scoring.destroy', [
        'tournament' => $tournament,
        'match' => $match,
        'scoreLog' => $firstLog,
    ]))->assertRedirect(route('admin.tournaments.matches.scoring', ['tournament' => $tournament, 'match' => $match]));

    $match->refresh();
    $remainingLog = MatchScoreLog::query()->where('match_id', $match->id)->firstOrFail();

    expect(MatchScoreLog::query()->where('match_id', $match->id)->count())->toBe(1);
    expect($remainingLog->sequence)->toBe(1);
    expect($remainingLog->home_score)->toBe(0);
    expect($remainingLog->away_score)->toBe(1);
    expect($match->home_score)->toBe(0);
    expect($match->away_score)->toBe(1);
    expect(MatchPlayerStat::query()->where('match_id', $match->id)->where('team_member_id', $homeScorer->id)->exists())->toBeFalse();
    expect(MatchPlayerStat::query()->where('match_id', $match->id)->where('team_member_id', $homeAssister->id)->exists())->toBeFalse();
    expect(
        MatchPlayerStat::query()->where('match_id', $match->id)->where('team_member_id', $awayScorer->id)->value('goals')
    )->toBe(1);
});

test('scorekeepers can enter a completed game score manually from the scoring page', function () {
    $admin = User::factory()->admin()->create();
    $scorekeeper = User::factory()->scorekeeper()->create();
    $teamOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Manual Result Cup',
        'slug' => 'manual-result-cup',
        'venue' => 'North Grounds',
        'status' => 'live',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    $homeTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Manual Home',
        'address' => 'Pasig',
        'status' => 'active',
    ]);

    $awayTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Manual Away',
        'address' => 'Cebu City',
        'status' => 'active',
    ]);

    $homeRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $homeTeam->id,
        'status' => 'approved',
    ]);

    $awayRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $awayTeam->id,
        'status' => 'approved',
    ]);

    $match = TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'home_registration_id' => $homeRegistration->id,
        'away_registration_id' => $awayRegistration->id,
        'stage' => 'round_robin',
        'round_label' => 'Round 1',
        'match_number' => 4,
        'scheduled_at' => now()->addDay(),
        'status' => 'scheduled',
    ]);

    $pitch = Pitch::query()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Manual Field',
        'sort_order' => 1,
        'scorekeeper_user_id' => $scorekeeper->id,
    ]);

    $match->update(['pitch_id' => $pitch->id]);

    $match->update([
        'status' => 'completed',
        'home_score' => 11,
        'away_score' => 8,
        'notes' => 'Entered after the scheduled game finished.',
    ]);

    $this->actingAs($scorekeeper);

    $this->get(route('admin.tournaments.matches.scoring', ['tournament' => $tournament, 'match' => $match]))
        ->assertOk()
        ->assertDontSee('Match Control');

    $match->refresh();

    expect($match->status)->toBe('completed');
    expect($match->home_score)->toBe(11);
    expect($match->away_score)->toBe(8);
    expect($match->notes)->toBe('Entered after the scheduled game finished.');
});

test('completed crossover scoring stays on scoring page and syncs default pool tags', function () {
    $admin = User::factory()->admin()->create();
    $scorekeeper = User::factory()->scorekeeper()->create();
    $owner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Pooling Flow Cup',
        'slug' => 'pooling-flow-cup',
        'venue' => 'Central Grounds',
        'status' => 'live',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    $homeTeam = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Pooling Home',
        'address' => 'Cagayan de Oro',
        'status' => 'active',
    ]);

    $awayTeam = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Pooling Away',
        'address' => 'Iligan',
        'status' => 'active',
    ]);

    $homeRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $homeTeam->id,
        'status' => 'approved',
        'seed_number' => 1,
        'bracket_code' => 'Bracket A',
        'bracket_rank' => 'A1',
    ]);

    $awayRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $awayTeam->id,
        'status' => 'approved',
        'seed_number' => 2,
        'bracket_code' => 'Bracket B',
        'bracket_rank' => 'B1',
    ]);

    $match = TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'home_registration_id' => $homeRegistration->id,
        'away_registration_id' => $awayRegistration->id,
        'stage' => 'crossover',
        'round_label' => 'Cross · A vs B #1',
        'match_number' => 1,
        'scheduled_at' => now()->addDay(),
        'status' => 'scheduled',
        'notes' => TournamentMatch::CROSSOVER_AUTO_GENERATED_MARKER,
    ]);

    $pitch = Pitch::query()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Pooling Field',
        'sort_order' => 1,
        'scorekeeper_user_id' => $scorekeeper->id,
    ]);

    $match->update(['pitch_id' => $pitch->id]);

    $match->update([
        'status' => 'completed',
        'home_score' => 13,
        'away_score' => 10,
        'notes' => 'Finished crossover match.',
    ]);

    $reflectionMethod = new ReflectionMethod(AdminTournamentController::class, 'syncCrossoverPoolingAssignments');
    $reflectionMethod->setAccessible(true);
    $reflectionMethod->invoke(app(AdminTournamentController::class), $tournament->id);

    $this->actingAs($scorekeeper);

    $homeRegistration->refresh();
    $awayRegistration->refresh();

    expect($homeRegistration->pool_name)->toBe('POOL A');
    expect($awayRegistration->pool_name)->toBe('POOL B');

    distrackPadRegistrationsForBracketWorkflowTabs($tournament, $owner, 2);

    $this->actingAs($admin);

    $this->get(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'pooling']))
        ->assertOk()
        ->assertSee('POOL A')
        ->assertSee('POOL B')
        ->assertSee('Pooling Home')
        ->assertSee('Pooling Away')
        ->assertSee('Crossover is complete');
});

test('crossover tab prompts admins to continue to pooling when all crossover games are resolved', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Crossover Done Cup',
        'slug' => 'crossover-done-cup',
        'venue' => 'Riverside',
        'status' => 'live',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    $homeTeam = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Banner Home',
        'address' => 'CDO',
        'status' => 'active',
    ]);

    $awayTeam = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Banner Away',
        'address' => 'Iligan',
        'status' => 'active',
    ]);

    $homeRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $homeTeam->id,
        'status' => 'approved',
        'seed_number' => 1,
        'bracket_code' => 'Bracket A',
        'bracket_rank' => 'A1',
    ]);

    $awayRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $awayTeam->id,
        'status' => 'approved',
        'seed_number' => 2,
        'bracket_code' => 'Bracket B',
        'bracket_rank' => 'B1',
    ]);

    TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'home_registration_id' => $homeRegistration->id,
        'away_registration_id' => $awayRegistration->id,
        'stage' => 'crossover',
        'round_label' => 'Cross · A vs B #1',
        'match_number' => 1,
        'scheduled_at' => now()->subHour(),
        'status' => 'completed',
        'home_score' => 15,
        'away_score' => 11,
        'notes' => TournamentMatch::CROSSOVER_AUTO_GENERATED_MARKER,
    ]);

    distrackPadRegistrationsForBracketWorkflowTabs($tournament, $owner, 2);

    $this->actingAs($admin);

    $this->get(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'crossover']))
        ->assertOk()
        ->assertSee('Crossover completed. Continue to Pooling.')
        ->assertSee('Open Pooling tab');
});

test('pooling tab keeps pending slots when some crossover games lack decisive results', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Pending Slot Cup',
        'slug' => 'pending-slot-cup',
        'venue' => 'North Field',
        'status' => 'live',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    $teams = [];

    foreach (range(1, 4) as $i) {
        $teams[$i] = Team::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Pending Team '.$i,
            'address' => 'CDO',
            'status' => 'active',
        ]);
    }

    $reg = [];

    foreach ([1 => ['A1', 'B1'], 2 => ['A2', 'B2']] as $pair => $ranks) {
        $reg[$pair]['home'] = TournamentRegistration::query()->create([
            'tournament_id' => $tournament->id,
            'team_id' => $teams[($pair - 1) * 2 + 1]->id,
            'status' => 'approved',
            'seed_number' => $pair,
            'bracket_code' => 'Bracket A',
            'bracket_rank' => $ranks[0],
        ]);
        $reg[$pair]['away'] = TournamentRegistration::query()->create([
            'tournament_id' => $tournament->id,
            'team_id' => $teams[($pair - 1) * 2 + 2]->id,
            'status' => 'approved',
            'seed_number' => $pair,
            'bracket_code' => 'Bracket B',
            'bracket_rank' => $ranks[1],
        ]);
    }

    TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'home_registration_id' => $reg[1]['home']->id,
        'away_registration_id' => $reg[1]['away']->id,
        'stage' => 'crossover',
        'round_label' => 'Cross · A vs B #1',
        'match_number' => 1,
        'scheduled_at' => now()->subHour(),
        'status' => 'completed',
        'home_score' => 12,
        'away_score' => 10,
        'notes' => TournamentMatch::CROSSOVER_AUTO_GENERATED_MARKER,
    ]);

    TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'home_registration_id' => $reg[2]['home']->id,
        'away_registration_id' => $reg[2]['away']->id,
        'stage' => 'crossover',
        'round_label' => 'Cross · A vs B #2',
        'match_number' => 2,
        'scheduled_at' => now()->addHour(),
        'status' => 'scheduled',
        'notes' => TournamentMatch::CROSSOVER_AUTO_GENERATED_MARKER,
    ]);

    distrackPadRegistrationsForBracketWorkflowTabs($tournament, $owner, 4);

    $this->actingAs($admin);

    $this->get(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'pooling']))
        ->assertOk()
        ->assertSee('POOL A')
        ->assertSee('POOL B')
        ->assertSee('W2')
        ->assertSee('Pending result')
        ->assertSee('Pending Team 1');
});

test('pooling board JSON omits diagram slots for nonexistent crossover games when fewer than eight games exist', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Json Order Cup',
        'slug' => 'json-order-cup',
        'venue' => 'South Field',
        'status' => 'live',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    foreach (['One', 'Two'] as $label) {
        Team::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Team '.$label.' A',
            'address' => 'CDO',
            'status' => 'active',
        ]);
        Team::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Team '.$label.' B',
            'address' => 'Iligan',
            'status' => 'active',
        ]);
    }

    $teams = Team::query()->where('owner_user_id', $owner->id)->orderBy('id')->get();

    $regs = [];
    foreach ([1, 2] as $pairIndex) {
        $base = ($pairIndex - 1) * 2;
        $regs[$pairIndex]['home'] = TournamentRegistration::query()->create([
            'tournament_id' => $tournament->id,
            'team_id' => $teams[$base]->id,
            'status' => 'approved',
            'seed_number' => $pairIndex,
            'bracket_code' => 'Bracket A',
            'bracket_rank' => 'A'.$pairIndex,
        ]);
        $regs[$pairIndex]['away'] = TournamentRegistration::query()->create([
            'tournament_id' => $tournament->id,
            'team_id' => $teams[$base + 1]->id,
            'status' => 'approved',
            'seed_number' => $pairIndex,
            'bracket_code' => 'Bracket B',
            'bracket_rank' => 'B'.$pairIndex,
        ]);
    }

    foreach ([1, 2] as $num) {
        TournamentMatch::query()->create([
            'tournament_id' => $tournament->id,
            'home_registration_id' => $regs[$num]['home']->id,
            'away_registration_id' => $regs[$num]['away']->id,
            'stage' => 'crossover',
            'round_label' => 'Cross · A vs B #'.$num,
            'match_number' => $num,
            'scheduled_at' => now()->subHours(3 - $num),
            'status' => 'completed',
            'home_score' => 11,
            'away_score' => 9,
            'notes' => TournamentMatch::CROSSOVER_AUTO_GENERATED_MARKER,
        ]);
    }

    $this->actingAs($admin);

    $response = $this->getJson(route('admin.tournaments.pooling-board', $tournament));

    $response->assertOk()
        ->assertJsonPath('rulesConfigured', false)
        ->assertJsonPath('poolingMode', 'auto')
        ->assertJsonPath('crossoverGameCount', 2)
        ->assertJsonPath('diagramBelowFullCrossoverCount', true);

    $poolASlots = $response->json('pools.0.slots');
    $poolBSlots = $response->json('pools.1.slots');

    $allLabels = collect($poolASlots)->pluck('compactLabel')
        ->merge(collect($poolBSlots)->pluck('compactLabel'))
        ->all();

    expect($allLabels)->not->toContain('W7')->not->toContain('W8')->not->toContain('L7')->not->toContain('L8');

    expect($poolASlots)->toHaveCount(2)
        ->and($poolASlots[0]['compactLabel'])->toBe('W1')
        ->and($poolASlots[0]['sourceGameNumber'])->toBe(1)
        ->and($poolASlots[0]['status'])->toBe('resolved')
        ->and($poolASlots[1]['compactLabel'])->toBe('W2')
        ->and($poolASlots[1]['sourceGameNumber'])->toBe(2)
        ->and($poolASlots[1]['status'])->toBe('resolved');

    expect($poolBSlots)->toHaveCount(2)
        ->and($poolBSlots[0]['compactLabel'])->toBe('L2')
        ->and($poolBSlots[1]['compactLabel'])->toBe('L1');
});

test('manual pooling rejects the same registration in Pool A and Pool B', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Manual Overlap Cup',
        'slug' => 'manual-overlap-cup',
        'venue' => 'Riverside',
        'status' => 'live',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    $homeTeam = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Overlap Home',
        'address' => 'CDO',
        'status' => 'active',
    ]);

    $awayTeam = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Overlap Away',
        'address' => 'Iligan',
        'status' => 'active',
    ]);

    $homeRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $homeTeam->id,
        'status' => 'approved',
        'seed_number' => 1,
        'bracket_code' => 'Bracket A',
        'bracket_rank' => 'A1',
    ]);

    $awayRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $awayTeam->id,
        'status' => 'approved',
        'seed_number' => 2,
        'bracket_code' => 'Bracket B',
        'bracket_rank' => 'B1',
    ]);

    TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'home_registration_id' => $homeRegistration->id,
        'away_registration_id' => $awayRegistration->id,
        'stage' => 'crossover',
        'round_label' => 'Cross · A vs B #1',
        'match_number' => 1,
        'scheduled_at' => now()->subHour(),
        'status' => 'completed',
        'home_score' => 15,
        'away_score' => 11,
        'notes' => TournamentMatch::CROSSOVER_AUTO_GENERATED_MARKER,
    ]);

    $this->actingAs($admin);

    $this->post(route('admin.tournaments.pooling.manual', $tournament), [
        'redirect_tab' => 'pooling',
        'pool_a_registration_ids' => [$homeRegistration->id, $awayRegistration->id],
        'pool_b_registration_ids' => [$homeRegistration->id],
    ])->assertSessionHasErrors('pool_b_registration_ids');
});

test('admin can clear saved pool assignments without deleting crossover matches', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Pool Clear Cup',
        'slug' => 'pool-clear-cup',
        'venue' => 'Riverside',
        'status' => 'live',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    $homeTeam = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Clear Home',
        'address' => 'CDO',
        'status' => 'active',
    ]);

    $awayTeam = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Clear Away',
        'address' => 'Iligan',
        'status' => 'active',
    ]);

    $homeRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $homeTeam->id,
        'status' => 'approved',
        'seed_number' => 1,
        'bracket_code' => 'Bracket A',
        'bracket_rank' => 'A1',
    ]);

    $awayRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $awayTeam->id,
        'status' => 'approved',
        'seed_number' => 2,
        'bracket_code' => 'Bracket B',
        'bracket_rank' => 'B1',
    ]);

    TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'home_registration_id' => $homeRegistration->id,
        'away_registration_id' => $awayRegistration->id,
        'stage' => 'crossover',
        'round_label' => 'Cross · A vs B #1',
        'match_number' => 1,
        'scheduled_at' => now()->subHour(),
        'status' => 'completed',
        'home_score' => 15,
        'away_score' => 11,
        'notes' => TournamentMatch::CROSSOVER_AUTO_GENERATED_MARKER,
    ]);

    $this->actingAs($admin);

    $this->post(route('admin.tournaments.pooling.manual', $tournament), [
        'redirect_tab' => 'pooling',
        'pool_a_registration_ids' => [$homeRegistration->id],
        'pool_b_registration_ids' => [$awayRegistration->id],
    ])->assertSessionHas('status', 'pooling-manual-saved');

    $tournament->refresh();

    expect($tournament->pooling_mode)->toBe('manual')
        ->and($tournament->pooling_manual_slots)->toMatchArray([
            'pool_a' => [$homeRegistration->id],
            'pool_b' => [$awayRegistration->id],
        ]);

    $homeRegistration->refresh();
    $awayRegistration->refresh();

    expect($homeRegistration->pool_name)->toBe('POOL A');
    expect($awayRegistration->pool_name)->toBe('POOL B');

    $this->post(route('admin.tournaments.pooling.clear', $tournament), [
        'redirect_tab' => 'pooling',
    ])->assertSessionHas('status', 'pooling-assignments-cleared');

    $tournament->refresh();
    $homeRegistration->refresh();
    $awayRegistration->refresh();

    expect($tournament->pooling_mode)->toBe('auto')
        ->and($tournament->pooling_manual_slots)->toBeNull();

    expect($homeRegistration->pool_name)->toBeNull();
    expect($awayRegistration->pool_name)->toBeNull();

    expect(TournamentMatch::query()->where('tournament_id', $tournament->id)->where('stage', 'crossover')->count())->toBe(1);
});

test('admin can bulk clear crossover field assignments for scheduled games only', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Bulk Field Clear Cup',
        'slug' => 'bulk-field-clear-cup',
        'venue' => 'Riverside',
        'status' => 'live',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    $pitch = Pitch::query()->create([
        'tournament_id' => $tournament->id,
        'name' => 'North',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $teams = [];

    foreach (range(1, 6) as $i) {
        $teams[$i] = Team::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Field Team '.$i,
            'address' => 'CDO',
            'status' => 'active',
        ]);
    }

    $regs = [];

    foreach ([
        [1, 2, 'A1', 'B1'],
        [3, 4, 'A2', 'B2'],
        [5, 6, 'A3', 'B3'],
    ] as [$ti, $tj, $rankHome, $rankAway]) {
        $regs[] = [
            TournamentRegistration::query()->create([
                'tournament_id' => $tournament->id,
                'team_id' => $teams[$ti]->id,
                'status' => 'approved',
                'seed_number' => 1,
                'bracket_code' => 'Bracket A',
                'bracket_rank' => $rankHome,
            ]),
            TournamentRegistration::query()->create([
                'tournament_id' => $tournament->id,
                'team_id' => $teams[$tj]->id,
                'status' => 'approved',
                'seed_number' => 2,
                'bracket_code' => 'Bracket B',
                'bracket_rank' => $rankAway,
            ]),
        ];
    }

    $scheduledOnField = TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'pitch_id' => $pitch->id,
        'pitch_assigned_by' => $admin->id,
        'home_registration_id' => $regs[0][0]->id,
        'away_registration_id' => $regs[0][1]->id,
        'stage' => 'crossover',
        'round_label' => 'Cross · A vs B #1',
        'match_number' => 1,
        'scheduled_at' => now()->addHour(),
        'status' => 'scheduled',
        'notes' => TournamentMatch::CROSSOVER_AUTO_GENERATED_MARKER,
    ]);

    $scheduledOnFieldTwo = TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'pitch_id' => $pitch->id,
        'pitch_assigned_by' => $admin->id,
        'home_registration_id' => $regs[1][0]->id,
        'away_registration_id' => $regs[1][1]->id,
        'stage' => 'crossover',
        'round_label' => 'Cross · A vs B #2',
        'match_number' => 2,
        'scheduled_at' => now()->addHours(2),
        'status' => 'scheduled',
        'notes' => TournamentMatch::CROSSOVER_AUTO_GENERATED_MARKER,
    ]);

    $completedOnField = TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'pitch_id' => $pitch->id,
        'pitch_assigned_by' => $admin->id,
        'home_registration_id' => $regs[2][0]->id,
        'away_registration_id' => $regs[2][1]->id,
        'stage' => 'crossover',
        'round_label' => 'Cross · A vs B #3',
        'match_number' => 3,
        'scheduled_at' => now()->subHour(),
        'status' => 'completed',
        'home_score' => 10,
        'away_score' => 8,
        'notes' => TournamentMatch::CROSSOVER_AUTO_GENERATED_MARKER,
    ]);

    $this->actingAs($admin);

    $this->post(route('admin.tournaments.matches.crossover.clear-pitch-assignments', $tournament), [
        'redirect_tab' => 'crossover',
    ])
        ->assertSessionHas('status', 'crossover-pitch-assignments-cleared')
        ->assertSessionHas('crossover_pitch_assignments_cleared_count', 2);

    $scheduledOnField->refresh();
    $scheduledOnFieldTwo->refresh();
    $completedOnField->refresh();

    expect($scheduledOnField->pitch_id)->toBeNull()
        ->and($scheduledOnField->pitch_assigned_by)->toBeNull()
        ->and($scheduledOnFieldTwo->pitch_id)->toBeNull()
        ->and($completedOnField->pitch_id)->toBe((int) $pitch->id);
});

test('bulk clear crossover field assignments fails when no eligible games exist', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'No Clear Cup',
        'slug' => 'no-clear-cup',
        'venue' => 'Riverside',
        'status' => 'live',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    $homeTeam = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'No Clear Home',
        'address' => 'CDO',
        'status' => 'active',
    ]);

    $awayTeam = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'No Clear Away',
        'address' => 'Iligan',
        'status' => 'active',
    ]);

    $homeRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $homeTeam->id,
        'status' => 'approved',
        'seed_number' => 1,
        'bracket_code' => 'Bracket A',
        'bracket_rank' => 'A1',
    ]);

    $awayRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $awayTeam->id,
        'status' => 'approved',
        'seed_number' => 2,
        'bracket_code' => 'Bracket B',
        'bracket_rank' => 'B1',
    ]);

    TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'home_registration_id' => $homeRegistration->id,
        'away_registration_id' => $awayRegistration->id,
        'stage' => 'crossover',
        'round_label' => 'Cross · A vs B #1',
        'match_number' => 1,
        'scheduled_at' => now()->subHour(),
        'status' => 'scheduled',
        'notes' => TournamentMatch::CROSSOVER_AUTO_GENERATED_MARKER,
    ]);

    $this->actingAs($admin);

    $this->post(route('admin.tournaments.matches.crossover.clear-pitch-assignments', $tournament), [
        'redirect_tab' => 'crossover',
    ])->assertSessionHasErrors('crossover');
});

test('completed match player assists and goals can be updated independently via player stats', function () {
    $scorekeeper = User::factory()->scorekeeper()->create();
    $teamOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => User::factory()->admin()->create()->id,
        'name' => 'Split Stat Cup',
        'slug' => 'split-stat-cup',
        'venue' => 'North Grounds',
        'status' => 'live',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    $homeTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Split Home',
        'address' => 'Pasig',
        'status' => 'active',
    ]);

    $awayTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Split Away',
        'address' => 'Cebu City',
        'status' => 'active',
    ]);

    $homeRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $homeTeam->id,
        'status' => 'approved',
    ]);

    $awayRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $awayTeam->id,
        'status' => 'approved',
    ]);

    $homeMember = TeamMember::query()->create([
        'team_id' => $homeTeam->id,
        'name' => 'Roster Player',
        'gender' => 'Male',
        'role' => 'captain',
    ]);

    $match = TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'home_registration_id' => $homeRegistration->id,
        'away_registration_id' => $awayRegistration->id,
        'stage' => 'round_robin',
        'match_number' => 1,
        'status' => 'completed',
        'home_score' => 0,
        'away_score' => 0,
    ]);

    $pitch = Pitch::query()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Split Field',
        'sort_order' => 1,
        'scorekeeper_user_id' => $scorekeeper->id,
    ]);

    $match->update(['pitch_id' => $pitch->id]);

    $this->actingAs($scorekeeper);

    $url = route('admin.tournaments.matches.scoring.player-stats.update', ['tournament' => $tournament, 'match' => $match]);

    $this->patchJson($url, [
        'team_member_id' => $homeMember->id,
        'field' => 'assists',
        'value' => 3,
    ])->assertOk()->assertJson(['ok' => true]);

    $this->patchJson($url, [
        'team_member_id' => $homeMember->id,
        'field' => 'goals',
        'value' => 7,
    ])->assertOk()->assertJson(['ok' => true, 'home_score' => 7, 'away_score' => 0]);

    $stat = MatchPlayerStat::query()
        ->where('match_id', $match->id)
        ->where('team_member_id', $homeMember->id)
        ->firstOrFail();

    expect($stat->assists)->toBe(3);
    expect($stat->goals)->toBe(7);

    $match->refresh();
    expect($match->home_score)->toBe(7);
    expect($match->away_score)->toBe(0);
});

test('scorekeeper live scoring requires confirmation before replacing a manual scoreline', function () {
    $admin = User::factory()->admin()->create();
    $scorekeeper = User::factory()->scorekeeper()->create();
    $teamOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Manual Score Cup',
        'slug' => 'manual-score-cup',
        'venue' => 'South Grounds',
        'status' => 'live',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => true,
    ]);

    $homeTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Davao Flyers',
        'address' => 'Davao City',
        'status' => 'active',
    ]);

    $awayTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Iloilo Waves',
        'address' => 'Iloilo City',
        'status' => 'active',
    ]);

    $homeRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $homeTeam->id,
        'status' => 'approved',
    ]);

    $awayRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $awayTeam->id,
        'status' => 'approved',
    ]);

    $homeScorer = TeamMember::query()->create([
        'team_id' => $homeTeam->id,
        'name' => 'Anton Reyes',
        'gender' => 'Male',
        'role' => 'captain',
    ]);

    $match = TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'home_registration_id' => $homeRegistration->id,
        'away_registration_id' => $awayRegistration->id,
        'stage' => 'group',
        'match_number' => 1,
        'status' => 'completed',
        'home_score' => 13,
        'away_score' => 9,
    ]);

    $pitch = Pitch::query()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Replace Manual Field',
        'sort_order' => 1,
        'scorekeeper_user_id' => $scorekeeper->id,
    ]);

    $match->update(['pitch_id' => $pitch->id]);

    $this->actingAs($scorekeeper);

    $this->post(route('admin.tournaments.matches.scoring.store', ['tournament' => $tournament, 'match' => $match]), [
        'team_registration_id' => $homeRegistration->id,
        'team_member_id' => $homeScorer->id,
        'minute' => 7,
    ])->assertSessionHasErrors('replace_manual_scoreline');

    expect(MatchScoreLog::query()->where('match_id', $match->id)->exists())->toBeFalse();
    expect($match->fresh()->home_score)->toBe(13);
    expect($match->fresh()->away_score)->toBe(9);
});

test('admin users can update tournament details', function () {
    $admin = User::factory()->admin()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'DISCTRACK Open',
        'slug' => 'distrack-open',
        'venue' => 'Central Field',
        'description' => 'Season opener',
        'status' => 'draft',
        'country_name' => 'Philippines',
        'province' => 'Bukidnon',
        'city' => 'Valencia City',
        'barangay' => 'Poblacion',
        'timezone' => 'Asia/Manila',
        'event_type' => 'Tournament',
        'division' => 'Mix',
        'surface' => 'Outdoor',
        'organizer_items' => [
            ['label' => 'Contact Email', 'value' => 'events@distrack.test'],
        ],
        'info_items' => [
            ['label' => 'Roster cap', 'value' => '26 players'],
        ],
        'link_items' => [
            ['label' => 'Registration Form', 'href' => 'https://example.com/register'],
        ],
        'is_public' => true,
    ]);

    $this->actingAs($admin);

    $this->get(route('admin.tournaments.list'))
        ->assertOk()
        ->assertSee('Edit Tournament')
        ->assertSee('Save Tournament Changes');

    $this->put(route('admin.tournaments.update', $tournament), [
        'edit_name' => 'DISCTRACK Open Finals',
        'edit_venue' => 'City Sports Complex',
        'edit_description' => 'Updated event details',
        'edit_registration_deadline' => now()->addDays(5)->format('Y-m-d H:i:s'),
        'edit_starts_at' => now()->addWeeks(2)->format('Y-m-d H:i:s'),
        'edit_ends_at' => now()->addWeeks(2)->addDay()->format('Y-m-d H:i:s'),
        'edit_status' => 'registration',
        'edit_province_code' => '101300000',
        'edit_province' => 'Bukidnon',
        'edit_city_code' => '101312000',
        'edit_city' => 'Manolo Fortich',
        'edit_barangay_code' => '101312000001',
        'edit_barangay' => 'Tankulan',
        'edit_country_name' => 'Philippines',
        'edit_timezone' => 'Asia/Manila',
        'edit_venue_google_map_link' => 'https://maps.app.goo.gl/updated',
        'edit_thumbnail_path' => 'https://example.com/finals.png',
        'edit_event_type' => 'Cup',
        'edit_division' => 'Men',
        'edit_surface' => 'Beach',
        'edit_organizer_labels' => ['Contact Email', 'Hotline'],
        'edit_organizer_values' => ['finals@distrack.test', '+63 917 555 0202'],
        'edit_info_labels' => ['Roster cap', 'Format'],
        'edit_info_values' => ['24 players', 'Round-robin then finals'],
        'edit_link_labels' => ['Event Handbook', 'Livestream'],
        'edit_link_urls' => ['https://example.com/handbook', 'https://example.com/live'],
        'edit_is_public' => '0',
    ])->assertRedirect(route('admin.tournaments.index', ['tournament' => $tournament->id]));

    $tournament->refresh();

    expect($tournament->name)->toBe('DISCTRACK Open Finals');
    expect($tournament->slug)->toBe('distrack-open');
    expect($tournament->venue)->toBe('City Sports Complex');
    expect($tournament->description)->toBe('Updated event details');
    expect($tournament->status)->toBe('registration');
    expect($tournament->city)->toBe('Manolo Fortich');
    expect($tournament->barangay)->toBe('Tankulan');
    expect($tournament->event_type)->toBe('Cup');
    expect($tournament->division)->toBe('Men');
    expect($tournament->surface)->toBe('Beach');
    expect($tournament->organizer_items)->toBe([
        ['label' => 'Contact Email', 'value' => 'finals@distrack.test'],
        ['label' => 'Hotline', 'value' => '+63 917 555 0202'],
    ]);
    expect($tournament->info_items)->toBe([
        ['label' => 'Roster cap', 'value' => '24 players'],
        ['label' => 'Format', 'value' => 'Round-robin then finals'],
    ]);
    expect($tournament->link_items)->toBe([
        ['label' => 'Event Handbook', 'href' => 'https://example.com/handbook'],
        ['label' => 'Livestream', 'href' => 'https://example.com/live'],
    ]);
    expect($tournament->is_public)->toBeFalse();
});

test('admin can generate automatic crossover mirror matches between two brackets', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Crossover Mirror Cup',
        'slug' => 'crossover-mirror-cup',
        'venue' => 'Test Field',
        'status' => 'draft',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    Pitch::query()->create([
        'tournament_id' => $tournament->id,
        'name' => 'FIELD 1',
        'location' => null,
        'sort_order' => 1,
    ]);

    $registrationsA = [];
    $registrationsB = [];

    foreach (range(1, 5) as $n) {
        $teamA = Team::query()->create([
            'owner_user_id' => $owner->id,
            'name' => "Mirror Team A{$n}",
            'address' => 'Cagayan de Oro',
            'status' => 'active',
        ]);
        $registrationsA[$n] = TournamentRegistration::query()->create([
            'tournament_id' => $tournament->id,
            'team_id' => $teamA->id,
            'status' => 'approved',
            'seed_number' => $n,
            'bracket_code' => 'Bracket A',
            'bracket_rank' => 'A'.$n,
        ]);

        $teamB = Team::query()->create([
            'owner_user_id' => $owner->id,
            'name' => "Mirror Team B{$n}",
            'address' => 'Iligan',
            'status' => 'active',
        ]);
        $registrationsB[$n] = TournamentRegistration::query()->create([
            'tournament_id' => $tournament->id,
            'team_id' => $teamB->id,
            'status' => 'approved',
            'seed_number' => $n,
            'bracket_code' => 'Bracket B',
            'bracket_rank' => 'B'.$n,
        ]);
    }

    $this->actingAs($admin);

    $this->post(route('admin.tournaments.matches.crossover.generate'), [
        'tournament_id' => $tournament->id,
        'redirect_tab' => 'crossover',
        'redirect_route' => 'admin.tournaments.index',
    ])->assertRedirect(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'crossover']));

    $crossoverMatches = TournamentMatch::query()
        ->where('tournament_id', $tournament->id)
        ->where('stage', 'crossover')
        ->orderBy('match_number')
        ->get();

    expect($crossoverMatches)->toHaveCount(5);

    foreach ($crossoverMatches as $match) {
        expect(str_contains((string) $match->notes, TournamentMatch::CROSSOVER_AUTO_GENERATED_MARKER))->toBeTrue();
    }

    foreach (range(1, 5) as $rank) {
        $homeId = $registrationsA[$rank]->id;
        $awayId = $registrationsB[6 - $rank]->id;

        $paired = $crossoverMatches->contains(fn (TournamentMatch $match): bool => (int) $match->home_registration_id === $homeId
            && (int) $match->away_registration_id === $awayId);

        expect($paired)->toBeTrue();
    }
});

test('crossover generation continues global match numbering after earlier-stage matches', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Global Crossover Number Cup',
        'slug' => 'global-crossover-number-cup',
        'venue' => 'Test Field',
        'status' => 'draft',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    $pitch = Pitch::query()->create([
        'tournament_id' => $tournament->id,
        'name' => 'FIELD 1',
        'location' => null,
        'sort_order' => 1,
    ]);

    $teamA1 = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Global Num A1',
        'address' => 'Cagayan de Oro',
        'status' => 'active',
    ]);
    $teamA2 = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Global Num A2',
        'address' => 'Iligan',
        'status' => 'active',
    ]);
    $teamB1 = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Global Num B1',
        'address' => 'Bukidnon',
        'status' => 'active',
    ]);
    $teamB2 = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Global Num B2',
        'address' => 'Davao',
        'status' => 'active',
    ]);

    $registrationA1 = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $teamA1->id,
        'status' => 'approved',
        'seed_number' => 1,
        'bracket_code' => 'Bracket A',
        'bracket_rank' => 'A1',
    ]);
    $registrationA2 = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $teamA2->id,
        'status' => 'approved',
        'seed_number' => 2,
        'bracket_code' => 'Bracket A',
        'bracket_rank' => 'A2',
    ]);
    $registrationB1 = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $teamB1->id,
        'status' => 'approved',
        'seed_number' => 1,
        'bracket_code' => 'Bracket B',
        'bracket_rank' => 'B1',
    ]);
    $registrationB2 = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $teamB2->id,
        'status' => 'approved',
        'seed_number' => 2,
        'bracket_code' => 'Bracket B',
        'bracket_rank' => 'B2',
    ]);

    TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'pitch_id' => $pitch->id,
        'home_registration_id' => $registrationA1->id,
        'away_registration_id' => $registrationA2->id,
        'stage' => 'round_robin',
        'round_label' => 'Bracket A RR placeholder',
        'match_number' => 42,
        'scheduled_at' => now()->addHour(),
        'status' => 'scheduled',
    ]);

    $this->actingAs($admin);

    $this->post(route('admin.tournaments.matches.crossover.generate'), [
        'tournament_id' => $tournament->id,
        'redirect_tab' => 'crossover',
        'redirect_route' => 'admin.tournaments.index',
    ])->assertRedirect(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'crossover']));

    $numbers = TournamentMatch::query()
        ->where('tournament_id', $tournament->id)
        ->where('stage', 'crossover')
        ->orderBy('match_number')
        ->pluck('match_number')
        ->all();

    expect($numbers)->toBe([43, 44]);
});

test('storing a match without match_number assigns the next global game number', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Auto Match Number Cup',
        'slug' => 'auto-match-number-cup',
        'venue' => 'Arena',
        'status' => 'draft',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => true,
    ]);

    foreach (range(1, 3) as $index) {
        $team = Team::query()->create([
            'owner_user_id' => $owner->id,
            'name' => "Auto Num Team {$index}",
            'address' => 'Valencia City',
            'status' => 'active',
        ]);
        TournamentRegistration::query()->create([
            'tournament_id' => $tournament->id,
            'team_id' => $team->id,
            'status' => 'approved',
            'seed_number' => $index,
            'bracket_code' => 'Bracket A',
        ]);
    }

    $registrations = TournamentRegistration::query()
        ->where('tournament_id', $tournament->id)
        ->orderBy('id')
        ->get();

    TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'pitch_id' => null,
        'home_registration_id' => $registrations[0]->id,
        'away_registration_id' => $registrations[1]->id,
        'stage' => 'crossover',
        'round_label' => 'Cross hold',
        'match_number' => 100,
        'scheduled_at' => now()->addDay(),
        'status' => 'scheduled',
    ]);

    $this->actingAs($admin);

    $this->post(route('admin.tournaments.matches.store'), [
        'match_form_intent' => 'general_match_add',
        'tournament_id' => $tournament->id,
        'match_tournament_id' => $tournament->id,
        'home_registration_id' => $registrations[1]->id,
        'away_registration_id' => $registrations[2]->id,
        'stage' => 'quarterfinal',
        'round_label' => 'QF Auto',
        'status' => 'scheduled',
        'redirect_route' => 'admin.tournaments.index',
        'redirect_tab' => 'quarter-final',
    ])->assertRedirect(route('admin.tournaments.index', [
        'tournament' => $tournament->id,
        'tab' => 'quarter-final',
    ]))->assertSessionHasNoErrors();

    $latestQuarterFinal = TournamentMatch::query()
        ->where('tournament_id', $tournament->id)
        ->where('stage', 'quarterfinal')
        ->latest('id')
        ->firstOrFail();

    expect($latestQuarterFinal->match_number)->toBe(101);
});

test('crossover generation fails when a ranked team has no bracket code', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Crossover Missing Bracket Cup',
        'slug' => 'crossover-missing-bracket-cup',
        'venue' => 'Test Field',
        'status' => 'draft',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    foreach (['A', 'B'] as $suffix) {
        foreach (range(1, 2) as $n) {
            $team = Team::query()->create([
                'owner_user_id' => $owner->id,
                'name' => "Missing Bracket {$suffix}{$n}",
                'address' => 'Cagayan de Oro',
                'status' => 'active',
            ]);
            TournamentRegistration::query()->create([
                'tournament_id' => $tournament->id,
                'team_id' => $team->id,
                'status' => 'approved',
                'seed_number' => $n,
                'bracket_code' => $suffix === 'A' ? null : 'Bracket B',
                'bracket_rank' => $suffix.$n,
            ]);
        }
    }

    $this->actingAs($admin);

    $this->from(route('admin.tournaments.index', ['tournament' => $tournament->id]))
        ->post(route('admin.tournaments.matches.crossover.generate'), [
            'tournament_id' => $tournament->id,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('crossover');
});

test('automatic crossover numbers games per bracket pair when four brackets are paired', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Crossover Four Bracket Cup',
        'slug' => 'crossover-four-bracket-cup',
        'venue' => 'Test Field',
        'status' => 'draft',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    Pitch::query()->create([
        'tournament_id' => $tournament->id,
        'name' => 'FIELD 1',
        'sort_order' => 1,
    ]);

    $brackets = ['Bracket A', 'Bracket B', 'Bracket C', 'Bracket D'];
    foreach ($brackets as $bracket) {
        $letter = strtoupper(substr($bracket, -1));
        foreach (range(1, 2) as $n) {
            $team = Team::query()->create([
                'owner_user_id' => $owner->id,
                'name' => "Four {$letter}{$n}",
                'address' => 'Cagayan de Oro',
                'status' => 'active',
            ]);
            TournamentRegistration::query()->create([
                'tournament_id' => $tournament->id,
                'team_id' => $team->id,
                'status' => 'approved',
                'seed_number' => $n,
                'bracket_code' => $bracket,
                'bracket_rank' => $letter.$n,
            ]);
        }
    }

    $this->actingAs($admin);

    $this->post(route('admin.tournaments.matches.crossover.generate'), [
        'tournament_id' => $tournament->id,
    ])->assertRedirect();

    $labels = TournamentMatch::query()
        ->where('tournament_id', $tournament->id)
        ->where('stage', 'crossover')
        ->orderBy('match_number')
        ->pluck('round_label')
        ->all();

    expect($labels)->toBe([
        'Cross · A vs B #1',
        'Cross · A vs B #2',
        'Cross · C vs D #1',
        'Cross · C vs D #2',
    ]);
});

test('generating crossover twice skips pairing duplicates', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Crossover Dedupe Cup',
        'slug' => 'crossover-dedupe-cup',
        'venue' => 'Test Field',
        'status' => 'draft',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    foreach (range(1, 2) as $n) {
        $teamA = Team::query()->create([
            'owner_user_id' => $owner->id,
            'name' => "Dedupe A{$n}",
            'address' => 'Cagayan de Oro',
            'status' => 'active',
        ]);
        TournamentRegistration::query()->create([
            'tournament_id' => $tournament->id,
            'team_id' => $teamA->id,
            'status' => 'approved',
            'seed_number' => $n,
            'bracket_code' => 'Bracket A',
            'bracket_rank' => 'A'.$n,
        ]);

        $teamB = Team::query()->create([
            'owner_user_id' => $owner->id,
            'name' => "Dedupe B{$n}",
            'address' => 'Iligan',
            'status' => 'active',
        ]);
        TournamentRegistration::query()->create([
            'tournament_id' => $tournament->id,
            'team_id' => $teamB->id,
            'status' => 'approved',
            'seed_number' => $n,
            'bracket_code' => 'Bracket B',
            'bracket_rank' => 'B'.$n,
        ]);
    }

    $this->actingAs($admin);

    $first = $this->post(route('admin.tournaments.matches.crossover.generate'), [
        'tournament_id' => $tournament->id,
    ]);

    $first->assertRedirect();
    expect(TournamentMatch::query()->where('tournament_id', $tournament->id)->where('stage', 'crossover')->count())->toBe(2);

    $second = $this->from(route('admin.tournaments.index', ['tournament' => $tournament->id]))->post(route('admin.tournaments.matches.crossover.generate'), [
        'tournament_id' => $tournament->id,
    ]);

    $second->assertRedirect();

    expect(TournamentMatch::query()->where('tournament_id', $tournament->id)->where('stage', 'crossover')->count())->toBe(2);

    $second->assertSessionHas('crossover_matches_created', 0)
        ->assertSessionHas('crossover_matches_skipped_duplicates', 2);
});

test('regenerating crossover after bracket rank changes replaces stale auto generated matches', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Crossover Regeneration Cup',
        'slug' => 'crossover-regeneration-cup',
        'venue' => 'Test Field',
        'status' => 'draft',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    Pitch::query()->create([
        'tournament_id' => $tournament->id,
        'name' => 'FIELD 1',
        'sort_order' => 1,
    ]);

    $registrationA1 = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => Team::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Regen A1',
            'address' => 'Cagayan de Oro',
            'status' => 'active',
        ])->id,
        'status' => 'approved',
        'seed_number' => 1,
        'bracket_code' => 'Bracket A',
        'bracket_rank' => 'A1',
    ]);

    $registrationA2 = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => Team::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Regen A2',
            'address' => 'Iligan',
            'status' => 'active',
        ])->id,
        'status' => 'approved',
        'seed_number' => 2,
        'bracket_code' => 'Bracket A',
        'bracket_rank' => 'A2',
    ]);

    $registrationB1 = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => Team::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Regen B1',
            'address' => 'Bukidnon',
            'status' => 'active',
        ])->id,
        'status' => 'approved',
        'seed_number' => 1,
        'bracket_code' => 'Bracket B',
        'bracket_rank' => 'B1',
    ]);

    $registrationB2 = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => Team::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Regen B2',
            'address' => 'Davao',
            'status' => 'active',
        ])->id,
        'status' => 'approved',
        'seed_number' => 2,
        'bracket_code' => 'Bracket B',
        'bracket_rank' => 'B2',
    ]);

    $this->actingAs($admin);

    $this->post(route('admin.tournaments.matches.crossover.generate'), [
        'tournament_id' => $tournament->id,
    ])->assertRedirect();

    $registrationA1->update(['bracket_rank' => 'A2']);
    $registrationA2->update(['bracket_rank' => 'A1']);

    $second = $this->post(route('admin.tournaments.matches.crossover.generate'), [
        'tournament_id' => $tournament->id,
    ]);

    $second->assertRedirect()
        ->assertSessionHas('crossover_matches_created', 2)
        ->assertSessionHas('crossover_matches_skipped_duplicates', 0);

    $pairings = TournamentMatch::query()
        ->where('tournament_id', $tournament->id)
        ->where('stage', 'crossover')
        ->get()
        ->map(fn (TournamentMatch $match): string => collect([(int) $match->home_registration_id, (int) $match->away_registration_id])
            ->sort()
            ->implode('-'))
        ->sort()
        ->values()
        ->all();

    expect($pairings)->toBe([
        collect([$registrationA1->id, $registrationB1->id])->sort()->implode('-'),
        collect([$registrationA2->id, $registrationB2->id])->sort()->implode('-'),
    ]);
});

test('admin can update an existing crossover match', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Crossover Update Cup',
        'slug' => 'crossover-update-cup',
        'venue' => 'Test Field',
        'status' => 'draft',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    $pitch = Pitch::query()->create([
        'tournament_id' => $tournament->id,
        'name' => 'FIELD 1',
        'sort_order' => 1,
    ]);

    $teamA1 = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Update A1',
        'address' => 'Cagayan de Oro',
        'status' => 'active',
    ]);
    $registrationA1 = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $teamA1->id,
        'status' => 'approved',
        'seed_number' => 1,
        'bracket_code' => 'Bracket A',
        'bracket_rank' => 'A1',
    ]);

    $teamA2 = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Update A2',
        'address' => 'Iligan',
        'status' => 'active',
    ]);
    $registrationA2 = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $teamA2->id,
        'status' => 'approved',
        'seed_number' => 2,
        'bracket_code' => 'Bracket A',
        'bracket_rank' => 'A2',
    ]);

    $teamB1 = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Update B1',
        'address' => 'Davao',
        'status' => 'active',
    ]);
    $registrationB1 = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $teamB1->id,
        'status' => 'approved',
        'seed_number' => 3,
        'bracket_code' => 'Bracket B',
        'bracket_rank' => 'B1',
    ]);

    $teamB2 = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Update B2',
        'address' => 'Cebu',
        'status' => 'active',
    ]);
    $registrationB2 = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $teamB2->id,
        'status' => 'approved',
        'seed_number' => 4,
        'bracket_code' => 'Bracket B',
        'bracket_rank' => 'B2',
    ]);

    $match = TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'pitch_id' => null,
        'home_registration_id' => $registrationA1->id,
        'away_registration_id' => $registrationB2->id,
        'stage' => 'crossover',
        'round_label' => 'Cross 1',
        'match_number' => 1,
        'scheduled_at' => now()->addDay(),
        'status' => 'scheduled',
    ]);

    $this->actingAs($admin);

    $response = $this->put(route('admin.tournaments.matches.update', ['match' => $match]), [
        'pitch_id' => $pitch->id,
        'home_registration_id' => $registrationA2->id,
        'away_registration_id' => $registrationB1->id,
        'round_label' => 'Cross 2',
        'match_number' => 7,
        'scheduled_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
        'redirect_route' => 'admin.tournaments.index',
        'redirect_tab' => 'crossover',
    ]);

    $response->assertRedirect(route('admin.tournaments.index', [
        'tournament' => $tournament->id,
        'tab' => 'crossover',
    ]));

    $match->refresh();

    expect($match->pitch_id)->toBe($pitch->id);
    expect($match->pitch_assigned_by)->toBe($admin->id);
    expect($match->home_registration_id)->toBe($registrationA2->id);
    expect($match->away_registration_id)->toBe($registrationB1->id);
    expect($match->round_label)->toBe('Cross 2');
    expect($match->match_number)->toBe(7);

    distrackPadRegistrationsForBracketWorkflowTabs($tournament, $owner, 4);

    $this->get(route('admin.tournaments.index', [
        'tournament' => $tournament->id,
        'tab' => 'crossover',
    ]))
        ->assertOk()
        ->assertSee('No crossover games are waiting for field assignment.')
        ->assertSee('data-crossover-pitch-group="'.$pitch->id.'"', false)
        ->assertSee('FIELD 1')
        ->assertSee('Update A2')
        ->assertSee('Update B1')
        ->assertSee('Assigned by:', false);
});

test('admin can delete crossover field assignment and move the game back to unassigned', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Crossover Unassign Cup',
        'slug' => 'crossover-unassign-cup',
        'venue' => 'Test Field',
        'status' => 'draft',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    $pitch = Pitch::query()->create([
        'tournament_id' => $tournament->id,
        'name' => 'FIELD 9',
        'sort_order' => 1,
    ]);

    $teamA1 = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Unassign A1',
        'address' => 'Cagayan de Oro',
        'status' => 'active',
    ]);
    $registrationA1 = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $teamA1->id,
        'status' => 'approved',
        'seed_number' => 1,
        'bracket_code' => 'Bracket A',
        'bracket_rank' => 'A1',
    ]);

    $teamB2 = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Unassign B2',
        'address' => 'Cebu',
        'status' => 'active',
    ]);
    $registrationB2 = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $teamB2->id,
        'status' => 'approved',
        'seed_number' => 2,
        'bracket_code' => 'Bracket B',
        'bracket_rank' => 'B2',
    ]);

    $match = TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'pitch_id' => $pitch->id,
        'pitch_assigned_by' => $admin->id,
        'home_registration_id' => $registrationA1->id,
        'away_registration_id' => $registrationB2->id,
        'stage' => 'crossover',
        'round_label' => 'Cross · Manual',
        'match_number' => 11,
        'scheduled_at' => now()->addDay(),
        'status' => 'scheduled',
    ]);

    $this->actingAs($admin);

    $this->post(route('admin.tournaments.matches.crossover-unassign-pitch', ['match' => $match]), [
        'redirect_route' => 'admin.tournaments.index',
        'redirect_tab' => 'crossover',
    ])
        ->assertRedirect(route('admin.tournaments.index', [
            'tournament' => $tournament->id,
            'tab' => 'crossover',
        ]))
        ->assertSessionHas('status', 'crossover-pitch-unassigned');

    $match->refresh();

    expect($match->pitch_id)->toBeNull();
    expect($match->pitch_assigned_by)->toBeNull();

    distrackPadRegistrationsForBracketWorkflowTabs($tournament, $owner, 2);

    $this->get(route('admin.tournaments.index', [
        'tournament' => $tournament->id,
        'tab' => 'crossover',
    ]))
        ->assertOk()
        ->assertSee(__('Cross · Manual'))
        ->assertSee(__('waiting for field'), false);
});

test('admin can delete a completed crossover game and destroy rejects wrong tournament scope', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();

    $tournamentA = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Crossover Delete Cup A',
        'slug' => 'crossover-delete-cup-a',
        'venue' => 'Test Field',
        'status' => 'draft',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    $tournamentB = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Crossover Delete Cup B',
        'slug' => 'crossover-delete-cup-b',
        'venue' => 'Test Field',
        'status' => 'draft',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    $pitch = Pitch::query()->create([
        'tournament_id' => $tournamentA->id,
        'name' => 'FIELD X',
        'sort_order' => 1,
    ]);

    $teamA = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Delete Cup A',
        'address' => 'Cagayan de Oro',
        'status' => 'active',
    ]);
    $regA = TournamentRegistration::query()->create([
        'tournament_id' => $tournamentA->id,
        'team_id' => $teamA->id,
        'status' => 'approved',
        'bracket_code' => 'Bracket A',
        'bracket_rank' => 'A1',
    ]);

    $teamB = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Delete Cup B',
        'address' => 'Iligan',
        'status' => 'active',
    ]);
    $regB = TournamentRegistration::query()->create([
        'tournament_id' => $tournamentA->id,
        'team_id' => $teamB->id,
        'status' => 'approved',
        'bracket_code' => 'Bracket B',
        'bracket_rank' => 'B1',
    ]);

    $match = TournamentMatch::query()->create([
        'tournament_id' => $tournamentA->id,
        'pitch_id' => $pitch->id,
        'pitch_assigned_by' => $admin->id,
        'home_registration_id' => $regA->id,
        'away_registration_id' => $regB->id,
        'stage' => 'crossover',
        'round_label' => 'Cross · Delete test',
        'match_number' => 99,
        'scheduled_at' => now()->subHour(),
        'status' => 'completed',
        'home_score' => 13,
        'away_score' => 11,
    ]);

    MatchPlayerStat::query()->create([
        'match_id' => $match->id,
        'team_member_id' => TeamMember::query()->create([
            'team_id' => $teamA->id,
            'name' => 'Stat Player',
            'gender' => 'Male',
            'role' => 'member',
        ])->id,
        'goals' => 2,
        'assists' => 1,
        'blocks' => 0,
    ]);

    $this->actingAs($admin);

    $this->delete(route('admin.tournaments.matches.destroy', ['match' => $match]), [
        'tournament_id' => $tournamentB->id,
    ])->assertForbidden();

    expect(TournamentMatch::query()->whereKey($match->id)->exists())->toBeTrue();

    $this->delete(route('admin.tournaments.matches.destroy', ['match' => $match]), [
        'tournament_id' => $tournamentA->id,
    ])->assertRedirect(route('admin.tournaments.index', [
        'tournament' => $tournamentA->id,
    ]))->assertSessionHas('status', 'match-deleted');

    expect(TournamentMatch::query()->whereKey($match->id)->exists())->toBeFalse();
    expect(MatchPlayerStat::query()->where('match_id', $match->id)->exists())->toBeFalse();
});

test('crossover edit modal preselects the existing assigned teams', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Crossover Modal Cup',
        'slug' => 'crossover-modal-cup',
        'venue' => 'Test Field',
        'status' => 'draft',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    $teamA1 = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Modal A1',
        'address' => 'Cagayan de Oro',
        'status' => 'active',
    ]);
    $registrationA1 = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $teamA1->id,
        'status' => 'approved',
        'seed_number' => 1,
        'bracket_code' => 'Bracket A',
        'bracket_rank' => 'A1',
    ]);

    $teamB2 = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Modal B2',
        'address' => 'Cebu',
        'status' => 'active',
    ]);
    $registrationB2 = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $teamB2->id,
        'status' => 'approved',
        'seed_number' => 2,
        'bracket_code' => 'Bracket B',
        'bracket_rank' => 'B2',
    ]);

    $match = TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'home_registration_id' => $registrationA1->id,
        'away_registration_id' => $registrationB2->id,
        'stage' => 'crossover',
        'round_label' => 'Cross 1',
        'match_number' => 1,
        'scheduled_at' => now()->addDay(),
        'status' => 'scheduled',
    ]);

    distrackPadRegistrationsForBracketWorkflowTabs($tournament, $owner, 2);

    $this->actingAs($admin);

    $response = $this->get(route('admin.tournaments.index', [
        'tournament' => $tournament->id,
        'tab' => 'crossover',
    ]));

    $content = $response->getContent();

    $response->assertOk()
        ->assertSee('setup-edit-crossover-match-modal-'.$match->id, false)
        ->assertSee('Modal A1 - A1 (Bracket A)', false)
        ->assertSee('Modal B2 - B2 (Bracket B)', false);

    expect(preg_match('/<option[^>]*(value="'.preg_quote((string) $registrationA1->id, '/').'"[^>]*selected|selected[^>]*value="'.preg_quote((string) $registrationA1->id, '/').'")[^>]*>/', $content))->toBe(1);
    expect(preg_match('/<option[^>]*(value="'.preg_quote((string) $registrationB2->id, '/').'"[^>]*selected|selected[^>]*value="'.preg_quote((string) $registrationB2->id, '/').'")[^>]*>/', $content))->toBe(1);
});

test('admin users can sync the small tournament fixed Day 1 round robin grid as twenty-four tracked matches', function (): void {
    $admin = User::factory()->admin()->create();
    $teamOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Small Day One Cup',
        'slug' => 'small-day-one-cup',
        'venue' => 'City Complex',
        'status' => 'registration',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => false,
    ]);

    foreach (range(1, 9) as $number) {
        $team = Team::query()->create([
            'owner_user_id' => $teamOwner->id,
            'name' => 'Small RR Team '.$number,
            'address' => 'Valencia City',
            'status' => 'active',
        ]);

        TournamentRegistration::query()->create([
            'tournament_id' => $tournament->id,
            'team_id' => $team->id,
            'status' => 'approved',
            'seed_number' => $number,
            'bracket_code' => null,
        ]);
    }

    $this->actingAs($admin);

    $this->post(route('admin.tournaments.matches.small-day1-schedule.sync', $tournament), [
        'redirect_route' => 'admin.tournaments.index',
        'redirect_tab' => 'round-robin',
    ])
        ->assertRedirect(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'round-robin']))
        ->assertSessionHas('status', 'small-day1-schedule-synced');

    $tracked = TournamentMatch::query()
        ->where('tournament_id', $tournament->id)
        ->where('stage', 'round_robin')
        ->where('notes', 'like', '%'.SmallFixedRoundRobinDayOneSchedule::MARKER_PREFIX.'%')
        ->orderBy('match_number')
        ->get();

    expect($tracked)->toHaveCount(24);

    $pitches = Pitch::query()->where('tournament_id', $tournament->id)->orderBy('sort_order')->orderBy('id')->get();

    expect($pitches->count())->toBeGreaterThanOrEqual(2);

    expect($tracked->where('pitch_id', $pitches->get(0)->id))->toHaveCount(12);
    expect($tracked->where('pitch_id', $pitches->get(1)->id))->toHaveCount(12);
});

test('admin users cannot sync the fixed Day 1 grid once the tournament reaches the bracket team threshold', function (): void {
    $admin = User::factory()->admin()->create();
    $teamOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Large Threshold Cup',
        'slug' => 'large-threshold-cup',
        'venue' => 'City Complex',
        'status' => 'registration',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => false,
    ]);

    foreach (range(1, AdminTournamentController::MINIMUM_BRACKET_TEAM_COUNT) as $number) {
        $team = Team::query()->create([
            'owner_user_id' => $teamOwner->id,
            'name' => 'Threshold Team '.$number,
            'address' => 'Valencia City',
            'status' => 'active',
        ]);

        TournamentRegistration::query()->create([
            'tournament_id' => $tournament->id,
            'team_id' => $team->id,
            'status' => 'approved',
            'seed_number' => $number,
            'bracket_code' => $number <= AdminTournamentController::BRACKET_TEAM_LIMIT ? 'Bracket A' : 'Bracket B',
        ]);
    }

    $this->actingAs($admin);

    $this->from(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'round-robin']))
        ->post(route('admin.tournaments.matches.small-day1-schedule.sync', $tournament), [
            'redirect_route' => 'admin.tournaments.index',
            'redirect_tab' => 'round-robin',
        ])
        ->assertRedirect(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'round-robin']))
        ->assertSessionHasErrors('small_day1_schedule');
});

test('admin users can update round robin match status from the small tournament fixed schedule flow', function (): void {
    $admin = User::factory()->admin()->create();
    $teamOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Small Status Cup',
        'slug' => 'small-status-cup',
        'venue' => 'City Complex',
        'status' => 'registration',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => false,
    ]);

    foreach (range(1, 4) as $number) {
        $team = Team::query()->create([
            'owner_user_id' => $teamOwner->id,
            'name' => 'Status RR Team '.$number,
            'address' => 'Valencia City',
            'status' => 'active',
        ]);

        TournamentRegistration::query()->create([
            'tournament_id' => $tournament->id,
            'team_id' => $team->id,
            'status' => 'approved',
            'seed_number' => $number,
            'bracket_code' => null,
        ]);
    }

    SmallFixedRoundRobinDayOneSchedule::sync($tournament);

    $slotMatches = TournamentMatch::query()
        ->where('tournament_id', $tournament->id)
        ->where('stage', 'round_robin')
        ->where('notes', 'like', '%'.SmallFixedRoundRobinDayOneSchedule::MARKER_PREFIX.'%')
        ->whereIn('match_number', [1, 2])
        ->orderBy('match_number')
        ->get();

    expect($slotMatches)->not->toBeEmpty();

    $this->actingAs($admin);

    $this->patch(route('admin.tournaments.matches.small-day1-slot-status.update', $tournament), [
        'round' => 1,
        'status' => 'live',
        'redirect_route' => 'admin.tournaments.index',
        'redirect_tab' => 'round-robin',
    ])
        ->assertRedirect(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'round-robin']))
        ->assertSessionHas('status', 'match-status-updated');

    foreach ($slotMatches as $match) {
        expect($match->fresh()->status)->toBe('live');
    }
});

test('small tournament Round Robin row can be marked completed without scores', function (): void {
    $admin = User::factory()->admin()->create();
    $teamOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Row Completed Without Scores Cup',
        'slug' => 'row-completed-no-scores-cup',
        'venue' => 'City Complex',
        'status' => 'registration',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => false,
    ]);

    foreach (range(1, 4) as $number) {
        $team = Team::query()->create([
            'owner_user_id' => $teamOwner->id,
            'name' => 'No Score RR Team '.$number,
            'address' => 'Valencia City',
            'status' => 'active',
        ]);

        TournamentRegistration::query()->create([
            'tournament_id' => $tournament->id,
            'team_id' => $team->id,
            'status' => 'approved',
            'seed_number' => $number,
            'bracket_code' => null,
        ]);
    }

    SmallFixedRoundRobinDayOneSchedule::sync($tournament);

    $slotMatches = TournamentMatch::query()
        ->where('tournament_id', $tournament->id)
        ->where('stage', 'round_robin')
        ->where('notes', 'like', '%'.SmallFixedRoundRobinDayOneSchedule::MARKER_PREFIX.'%')
        ->whereIn('match_number', [1, 2])
        ->orderBy('match_number')
        ->get();

    expect($slotMatches)->not->toBeEmpty();

    $this->actingAs($admin);

    $this->patch(route('admin.tournaments.matches.small-day1-slot-status.update', $tournament), [
        'round' => 1,
        'status' => 'completed',
        'redirect_route' => 'admin.tournaments.index',
        'redirect_tab' => 'round-robin',
    ])
        ->assertRedirect(route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'round-robin']))
        ->assertSessionHas('status', 'match-status-updated')
        ->assertSessionDoesntHaveErrors();

    foreach ($slotMatches as $match) {
        $fresh = $match->fresh();
        expect($fresh->status)->toBe('completed')
            ->and($fresh->home_score)->toBeNull()
            ->and($fresh->away_score)->toBeNull();
    }
});
