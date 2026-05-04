<?php

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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

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
        ->assertSee('Overview')
        ->assertSee('Basic Info')
        ->assertSee('Teams')
        ->assertSee('Pitches')
        ->assertSee('Format')
        ->assertSee('Matches')
        ->assertSee('Crew')
        ->assertSee('Publish & Preview')
        ->assertSee('Quick Actions')
        ->assertSee('Readiness Checklist');
});

test('admin format tab shows the frisbee tournament workflow', function () {
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
        ->assertOk()
        ->assertSee('Frisbee Tournament Format')
        ->assertSee('Day 0')
        ->assertSee('Seeding')
        ->assertSee('Day 1')
        ->assertSee('Round Robin')
        ->assertSee('Bracket Ranking')
        ->assertSee('Crossover')
        ->assertSee('Pooling')
        ->assertSee('Day 2')
        ->assertSee('Quarter Finals')
        ->assertSee('Semi-Finals')
        ->assertSee('Championship');
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
        ->assertSee('Matches Ready for Scoring')
        ->assertSee('Live Scoring')
        ->assertDontSee('Create Tournament')
        ->assertDontSee('Add Pitch')
        ->assertDontSee('Register Team')
        ->assertDontSee('Add Match')
        ->assertDontSee('Add Crew Member');

    $this->get(route('admin.tournaments.matches.scoring', ['tournament' => $tournament, 'match' => $match]))
        ->assertOk()
        ->assertSee('Live Scoring')
        ->assertSee('Add Scoring Play');
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

test('admin users can record live scoring plays and rebuild match totals', function () {
    $admin = User::factory()->admin()->create();
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

    $this->actingAs($admin);

    $this->get(route('admin.tournaments.matches.scoring', ['tournament' => $tournament, 'match' => $match]))
        ->assertOk()
        ->assertSee('Live Scoring')
        ->assertSee('Add Scoring Play')
        ->assertSee('Manila Storm')
        ->assertSee('Cebu Breakers');

    $this->post(route('admin.tournaments.matches.scoring.store', ['tournament' => $tournament, 'match' => $match]), [
        'team_registration_id' => $homeRegistration->id,
        'team_member_id' => $homeScorer->id,
        'assist_team_member_id' => $homeAssister->id,
        'minute' => 5,
    ])->assertRedirect(route('admin.tournaments.matches.scoring', ['tournament' => $tournament, 'match' => $match]));

    $match->refresh();

    expect($match->status)->toBe('live');
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

    $this->patch(route('admin.tournaments.matches.scoring.update', ['tournament' => $tournament, 'match' => $match]), [
        'status' => 'completed',
        'notes' => 'Universe point settled the pool race.',
    ])->assertRedirect(route('admin.tournaments.matches.scoring', ['tournament' => $tournament, 'match' => $match]));

    $match->refresh();

    expect($match->status)->toBe('completed');
    expect($match->notes)->toBe('Universe point settled the pool race.');

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

test('admin live scoring requires confirmation before replacing a manual scoreline', function () {
    $admin = User::factory()->admin()->create();
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

    $this->actingAs($admin);

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
