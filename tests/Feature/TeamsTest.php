<?php

use App\Models\MatchPlayerStat;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentRegistration;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();

    Http::preventStrayRequests();

    Http::fake([
        'https://barangays.sanchez.ph/downloads/provinces.json' => Http::response([
            [
                'code' => '0722000000',
                'name' => 'Cebu',
                'region_code' => '0700000000',
                'id' => 1,
            ],
        ]),
        'https://barangays.sanchez.ph/downloads/cities.json' => Http::response([
            [
                'code' => '1300000001',
                'name' => 'Quezon City',
                'region_code' => '1300000000',
                'province_code' => null,
                'id' => 1,
            ],
            [
                'code' => '0722170000',
                'name' => 'Cebu City',
                'region_code' => '0700000000',
                'province_code' => '0722000000',
                'id' => 2,
            ],
        ]),
        'https://barangays.sanchez.ph/downloads/barangays.json' => Http::response([
            [
                'code' => '1300000001001',
                'name' => 'Bagumbayan',
                'city_code' => '1300000001',
                'province_code' => null,
                'region_code' => '1300000000',
                'id' => 1,
            ],
            [
                'code' => '0722170000001',
                'name' => 'Lahug',
                'city_code' => '0722170000',
                'province_code' => '0722000000',
                'region_code' => '0700000000',
                'id' => 2,
            ],
        ]),
    ]);
});

test('guests are redirected to the login page for team management', function () {
    $response = $this->get(route('teams.index'));

    $response->assertRedirect(route('login'));
});

test('authenticated users can visit team management', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->get(route('teams.index'))->assertOk();
});

test('admin users cannot visit team management', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    $this->get(route('teams.index'))->assertForbidden();
});

test('captain sidebar tournament menu points to the captain registration directory', function () {
    $captain = User::factory()->create();

    $this->actingAs($captain);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('captain.tournaments.index'), false);
});

test('captains can view available tournaments for registration', function () {
    $captain = User::factory()->create();

    Team::query()->create([
        'owner_user_id' => $captain->id,
        'name' => 'Captain Squad',
        'address' => 'Bagumbayan',
        'city' => 'Quezon City',
        'province' => 'Metro Manila (NCR)',
        'country_name' => 'Philippines',
        'status' => 'active',
    ]);

    Tournament::query()->create([
        'name' => 'Banog Cup',
        'slug' => 'banog-cup',
        'venue' => 'Central Grounds',
        'status' => 'registration',
        'country_name' => 'Philippines',
        'division' => 'Open',
        'surface' => 'Outdoor',
        'registration_deadline' => now()->addDays(5),
    ]);

    Tournament::query()->create([
        'name' => 'Closed Event',
        'slug' => 'closed-event',
        'venue' => 'Private Grounds',
        'status' => 'draft',
        'country_name' => 'Philippines',
    ]);

    $this->actingAs($captain);

    $this->get(route('captain.tournaments.index'))
        ->assertOk()
        ->assertSee('Available Tournaments')
        ->assertSee('Banog Cup')
        ->assertSee('Register This Team')
        ->assertDontSee('Closed Event');
});

test('captains can register their own team into an available tournament', function () {
    $captain = User::factory()->create();

    $team = Team::query()->create([
        'owner_user_id' => $captain->id,
        'name' => 'Skybreakers',
        'address' => 'Bagumbayan',
        'city' => 'Quezon City',
        'province' => 'Metro Manila (NCR)',
        'country_name' => 'Philippines',
        'status' => 'active',
    ]);

    $tournament = Tournament::query()->create([
        'name' => 'Captain Signups Cup',
        'slug' => 'captain-signups-cup',
        'venue' => 'Oval Grounds',
        'status' => 'registration',
        'country_name' => 'Philippines',
        'registration_deadline' => now()->addDays(7),
    ]);

    $this->actingAs($captain);

    $this->post(route('captain.tournaments.registrations.store'), [
        'tournament_id' => $tournament->id,
        'team_id' => $team->id,
    ])->assertRedirect(route('captain.tournaments.index'));

    $registration = TournamentRegistration::query()
        ->where('tournament_id', $tournament->id)
        ->where('team_id', $team->id)
        ->first();

    expect($registration)->not->toBeNull();
    expect($registration->status)->toBe('pending');
});

test('captains cannot register teams they do not own', function () {
    $captain = User::factory()->create();
    $otherOwner = User::factory()->create();

    $foreignTeam = Team::query()->create([
        'owner_user_id' => $otherOwner->id,
        'name' => 'Disc Hawks',
        'address' => 'Makati City',
        'city' => 'Makati City',
        'province' => 'Metro Manila (NCR)',
        'country_name' => 'Philippines',
        'status' => 'active',
    ]);

    $tournament = Tournament::query()->create([
        'name' => 'Guard Rail Cup',
        'slug' => 'guard-rail-cup',
        'venue' => 'City Field',
        'status' => 'registration',
        'country_name' => 'Philippines',
        'registration_deadline' => now()->addDays(7),
    ]);

    $this->actingAs($captain);

    $this->post(route('captain.tournaments.registrations.store'), [
        'tournament_id' => $tournament->id,
        'team_id' => $foreignTeam->id,
    ])->assertNotFound();

    expect(TournamentRegistration::query()->count())->toBe(0);
});

test('admin users cannot create teams', function () {
    Storage::fake('public');

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    $this->post(route('teams.store'), [
        'name' => 'Blocked Admin Team',
        'address' => 'Poblacion',
        'city' => 'Valencia City',
        'province' => 'Bukidnon',
        'country_name' => 'Philippines',
    ])->assertForbidden();

    expect(Team::query()->where('owner_user_id', $admin->id)->exists())->toBeFalse();
});

test('authenticated users can register teams and roster members', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::teams.index')
        ->set('team_name', 'Skybreakers')
        ->set('team_province_code', '1300000000')
        ->set('team_city_code', '1300000001')
        ->set('team_barangay_code', '1300000001001')
        ->set('logo', UploadedFile::fake()->image('skybreakers.png'))
        ->call('createTeam');

    $response->assertHasNoErrors();

    $team = Team::query()->first();

    expect($team)->not->toBeNull();
    expect($team->owner_user_id)->toBe($user->id);
    expect($team->name)->toBe('Skybreakers');
    expect($team->address)->toBe('Bagumbayan');
    expect($team->city)->toBe('Quezon City');
    expect($team->province)->toBe('Metro Manila (NCR)');
    expect($team->country_name)->toBe('Philippines');
    expect($team->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($team->logo_path);

    $response
        ->set('selectedTeamId', $team->id)
        ->set('member_name', 'Alex Ramos')
        ->set('member_nickname', 'Axe')
        ->set('member_gender', 'Male')
        ->set('member_age', 24)
        ->set('member_province_code', '0722000000')
        ->set('member_city_code', '0722170000')
        ->set('member_barangay_code', '0722170000001')
        ->set('member_role', 'captain')
        ->call('addMember')
        ->assertHasNoErrors();

    expect($team->fresh()->members)->toHaveCount(1);
    expect($team->fresh()->members->first()->role)->toBe('captain');
    expect($team->fresh()->members->first()->address)->toBe('Lahug, Cebu City, Cebu');
});

test('authenticated users can upload an svg team logo', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::teams.index')
        ->set('team_name', 'Vector Flyers')
        ->set('team_province_code', '0722000000')
        ->set('team_city_code', '0722170000')
        ->set('team_barangay_code', '0722170000001')
        ->set('logo', UploadedFile::fake()->createWithContent(
            'vector-flyers.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><circle cx="16" cy="16" r="14" fill="#111827"/></svg>',
        ))
        ->call('createTeam');

    $response->assertHasNoErrors();

    $team = Team::query()->where('name', 'Vector Flyers')->first();

    expect($team)->not->toBeNull();
    expect($team->logo_path)->not->toBeNull();
    expect($team->logo_path)->toEndWith('.svg');
    expect($team->locationLabel())->toBe('Cebu City, Cebu, Philippines');
    Storage::disk('public')->assertExists($team->logo_path);
});

test('authenticated users can edit their team details and replace the logo', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $team = Team::query()->create([
        'owner_user_id' => $user->id,
        'name' => 'Skybreakers',
        'address' => 'Bagumbayan',
        'city' => 'Quezon City',
        'province' => 'Metro Manila (NCR)',
        'country_name' => 'Philippines',
        'logo_path' => 'team-logos/skybreakers-old.png',
        'status' => 'active',
    ]);

    Storage::disk('public')->put($team->logo_path, 'old-logo');

    $this->actingAs($user);

    Livewire::test('pages::teams.index')
        ->call('editTeam', $team->id)
        ->assertSet('editingTeamId', $team->id)
        ->assertSet('team_name', 'Skybreakers')
        ->assertSet('team_province_code', '1300000000')
        ->set('team_name', 'Skybreakers Reloaded')
        ->set('team_province_code', '0722000000')
        ->set('team_city_code', '0722170000')
        ->set('team_barangay_code', '0722170000001')
        ->set('logo', UploadedFile::fake()->image('skybreakers-new.png'))
        ->call('updateTeam')
        ->assertHasNoErrors()
        ->assertSet('status', 'team-updated')
        ->assertSet('editingTeamId', null);

    $team->refresh();

    expect($team->name)->toBe('Skybreakers Reloaded');
    expect($team->address)->toBe('Lahug');
    expect($team->city)->toBe('Cebu City');
    expect($team->province)->toBe('Cebu');
    expect($team->country_name)->toBe('Philippines');
    expect($team->logo_path)->not->toBe('team-logos/skybreakers-old.png');
    Storage::disk('public')->assertMissing('team-logos/skybreakers-old.png');
    Storage::disk('public')->assertExists($team->logo_path);
});

test('authenticated users can edit team members and change their role', function () {
    $user = User::factory()->create();

    $team = Team::query()->create([
        'owner_user_id' => $user->id,
        'name' => 'Northwind',
        'address' => 'Bagumbayan',
        'city' => 'Quezon City',
        'province' => 'Metro Manila (NCR)',
        'country_name' => 'Philippines',
        'status' => 'active',
    ]);

    $member = TeamMember::query()->create([
        'team_id' => $team->id,
        'name' => 'Jamie Cruz',
        'nickname' => 'Jam',
        'gender' => 'Female',
        'age' => 22,
        'address' => 'Bagumbayan, Quezon City, Metro Manila (NCR)',
        'role' => 'captain',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::teams.index')
        ->set('selectedTeamId', $team->id)
        ->call('editMember', $member->id)
        ->assertSet('editingMemberId', $member->id)
        ->assertSet('member_name', 'Jamie Cruz')
        ->assertSet('member_province_code', '1300000000')
        ->set('member_name', 'Jamie Cruz-Delos Reyes')
        ->set('member_nickname', 'Jade')
        ->set('member_gender', 'Female')
        ->set('member_age', 23)
        ->set('member_province_code', '0722000000')
        ->set('member_city_code', '0722170000')
        ->set('member_barangay_code', '0722170000001')
        ->set('member_role', 'spirit_captain')
        ->call('updateMember')
        ->assertHasNoErrors()
        ->assertSet('status', 'member-updated')
        ->assertSet('editingMemberId', null);

    $member->refresh();

    expect($member->name)->toBe('Jamie Cruz-Delos Reyes');
    expect($member->nickname)->toBe('Jade');
    expect($member->age)->toBe(23);
    expect($member->address)->toBe('Lahug, Cebu City, Cebu');
    expect($member->role)->toBe('spirit_captain');
});

test('authenticated users can delete roster members from their own teams', function () {
    $user = User::factory()->create();

    $team = Team::query()->create([
        'owner_user_id' => $user->id,
        'name' => 'Northwind',
        'address' => 'Cagayan de Oro',
        'status' => 'active',
    ]);

    $member = TeamMember::query()->create([
        'team_id' => $team->id,
        'name' => 'Jamie Cruz',
        'nickname' => 'Jam',
        'gender' => 'Female',
        'age' => 22,
        'address' => 'Malaybalay',
        'role' => 'member',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::teams.index')
        ->set('selectedTeamId', $team->id)
        ->call('deleteMember', $member->id)
        ->assertHasNoErrors();

    expect(TeamMember::query()->find($member->id))->toBeNull();
});

test('authenticated users can delete their own teams without tournament history', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $team = Team::query()->create([
        'owner_user_id' => $user->id,
        'name' => 'Riptide',
        'address' => 'Iloilo City',
        'logo_path' => 'team-logos/riptide.png',
        'status' => 'active',
    ]);

    $member = TeamMember::query()->create([
        'team_id' => $team->id,
        'name' => 'Kai Torres',
        'nickname' => 'Kai',
        'gender' => 'Male',
        'age' => 25,
        'address' => 'Iloilo City',
        'role' => 'captain',
    ]);

    Storage::disk('public')->put($team->logo_path, 'fake-logo');

    $this->actingAs($user);

    Livewire::test('pages::teams.index')
        ->set('selectedTeamId', $team->id)
        ->call('deleteTeam', $team->id)
        ->assertHasNoErrors()
        ->assertSet('status', 'team-deleted');

    expect(Team::query()->find($team->id))->toBeNull();
    expect(TeamMember::query()->find($member->id))->toBeNull();
    Storage::disk('public')->assertMissing('team-logos/riptide.png');
});

test('teams with tournament registrations cannot be deleted', function () {
    $user = User::factory()->create();

    $team = Team::query()->create([
        'owner_user_id' => $user->id,
        'name' => 'Harbor Hawks',
        'address' => 'General Santos City',
        'status' => 'active',
    ]);

    $tournament = Tournament::query()->create([
        'name' => 'Captain Lock Cup',
        'slug' => 'captain-lock-cup',
        'venue' => 'Oval Grounds',
        'status' => 'draft',
    ]);

    $team->registrations()->create([
        'tournament_id' => $tournament->id,
        'status' => 'pending',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::teams.index')
        ->set('selectedTeamId', $team->id)
        ->call('deleteTeam', $team->id)
        ->assertHasNoErrors()
        ->assertSet('status', 'team-delete-blocked');

    expect(Team::query()->find($team->id))->not->toBeNull();
});

test('team members with recorded match stats cannot be deleted', function () {
    $user = User::factory()->create();

    $team = Team::query()->create([
        'owner_user_id' => $user->id,
        'name' => 'Southwind',
        'address' => 'Davao City',
        'status' => 'active',
    ]);

    $member = TeamMember::query()->create([
        'team_id' => $team->id,
        'name' => 'Mia Santos',
        'nickname' => 'Mia',
        'gender' => 'Female',
        'age' => 23,
        'address' => 'Davao City',
        'role' => 'captain',
    ]);

    $tournament = Tournament::query()->create([
        'name' => 'Delete Guard Cup',
        'slug' => 'delete-guard-cup',
        'venue' => 'Matina Field',
        'status' => 'live',
    ]);

    $match = TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'stage' => 'pool_play',
        'match_number' => 1,
        'status' => 'completed',
    ]);

    MatchPlayerStat::query()->create([
        'match_id' => $match->id,
        'team_member_id' => $member->id,
        'goals' => 2,
        'assists' => 1,
        'blocks' => 0,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::teams.index')
        ->set('selectedTeamId', $team->id)
        ->call('deleteMember', $member->id)
        ->assertHasNoErrors()
        ->assertSet('status', 'member-delete-blocked');

    expect(TeamMember::query()->find($member->id))->not->toBeNull();
});
