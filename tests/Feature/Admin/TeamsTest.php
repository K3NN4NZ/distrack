<?php

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('non admin users cannot visit the admin teams directory', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->get(route('admin.teams.index'))->assertForbidden();
});

test('admin users can view all teams in the admin teams directory', function () {
    $admin = User::factory()->admin()->create();
    $captain = User::factory()->create([
        'name' => 'Captain Owner',
        'email' => 'captain-owner@distrack.test',
    ]);

    $team = Team::query()->create([
        'owner_user_id' => $captain->id,
        'name' => 'Skybreakers',
        'address' => 'Bagumbayan',
        'city' => 'Quezon City',
        'province' => 'Metro Manila (NCR)',
        'country_name' => 'Philippines',
        'status' => 'active',
    ]);

    TeamMember::query()->create([
        'team_id' => $team->id,
        'user_id' => $captain->id,
        'name' => 'Captain Owner',
        'nickname' => 'Cap',
        'gender' => 'Male',
        'age' => 25,
        'address' => 'Bagumbayan, Quezon City, Metro Manila (NCR)',
        'role' => 'captain',
    ]);

    TeamMember::query()->create([
        'team_id' => $team->id,
        'name' => 'Spirit Lead',
        'nickname' => 'Spirit',
        'gender' => 'Female',
        'age' => 24,
        'address' => 'Bagumbayan, Quezon City, Metro Manila (NCR)',
        'role' => 'spirit_captain',
    ]);

    TeamMember::query()->create([
        'team_id' => $team->id,
        'name' => 'Roster Member',
        'nickname' => 'Member',
        'gender' => 'Male',
        'age' => 21,
        'address' => 'Bagumbayan, Quezon City, Metro Manila (NCR)',
        'role' => 'member',
    ]);

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Admin Team View Cup',
        'slug' => 'admin-team-view-cup',
        'venue' => 'Main Grounds',
        'status' => 'draft',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    $team->registrations()->create([
        'tournament_id' => $tournament->id,
        'status' => 'approved',
    ]);

    $this->actingAs($admin);

    $this->get(route('admin.teams.index'))
        ->assertOk()
        ->assertSee('Teams Directory')
        ->assertSee('total roster')
        ->assertSee('Skybreakers')
        ->assertSee('Captain Owner')
        ->assertSee('Spirit Lead')
        ->assertSee('Quezon City, Metro Manila (NCR), Philippines')
        ->assertDontSee('captain-owner@distrack.test')
        ->assertSee('3')
        ->assertSee('1');
});

test('admin users can create teams in the admin teams directory', function () {
    Storage::fake('public');

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    $this->post(route('admin.teams.store'), [
        'name' => 'Disc Falcons',
        'captain_name' => 'Mika Captain',
        'spirit_captain_name' => 'Sam Spirit',
        'address' => 'Poblacion',
        'city' => 'Valencia City',
        'province' => 'Bukidnon',
        'country_name' => 'Philippines',
        'status' => 'active',
        'logo' => UploadedFile::fake()->image('disc-falcons.png'),
    ])->assertRedirect();

    $team = Team::query()->where('name', 'Disc Falcons')->first();
    $captainUser = User::query()
        ->where('name', 'Mika Captain')
        ->where('role', User::ROLE_CAPTAIN)
        ->first();
    $captainMember = $team->members()->where('role', 'captain')->first();

    expect($team)->not->toBeNull();
    expect($captainUser)->not->toBeNull();
    expect($team->owner_user_id)->toBe($captainUser->id);
    expect($team->city)->toBe('Valencia City');
    expect($team->province)->toBe('Bukidnon');
    expect($team->status)->toBe('active');
    expect($team->logo_path)->not->toBeNull();
    expect($captainMember->name)->toBe('Mika Captain');
    expect($captainMember->user_id)->toBe($captainUser->id);
    expect($team->members()->where('role', 'spirit_captain')->value('name'))->toBe('Sam Spirit');

    Storage::disk('public')->assertExists($team->logo_path);
});

test('admin users can update teams in the admin teams directory', function () {
    Storage::fake('public');

    $admin = User::factory()->admin()->create();
    $originalOwner = User::factory()->create([
        'name' => 'Original Owner',
        'email' => 'original-owner@distrack.test',
    ]);

    $team = Team::query()->create([
        'owner_user_id' => $originalOwner->id,
        'name' => 'Skybreakers',
        'address' => 'Bagumbayan',
        'city' => 'Quezon City',
        'province' => 'Metro Manila (NCR)',
        'country_name' => 'Philippines',
        'logo_path' => 'team-logos/skybreakers-old.png',
        'status' => 'active',
    ]);

    Storage::disk('public')->put($team->logo_path, 'old-logo');

    $this->actingAs($admin);

    $this->put(route('admin.teams.update', $team), [
        'edit_name' => 'Skybreakers Elite',
        'edit_address' => 'Poblacion',
        'edit_city' => 'Valencia City',
        'edit_province' => 'Bukidnon',
        'edit_country_name' => 'Philippines',
        'edit_status' => 'inactive',
        'edit_logo' => UploadedFile::fake()->image('skybreakers-new.png'),
    ])->assertRedirect(route('admin.teams.index', ['selected_team' => $team->id]));

    $team->refresh();

    expect($team->owner_user_id)->toBe($originalOwner->id);
    expect($team->name)->toBe('Skybreakers Elite');
    expect($team->address)->toBe('Poblacion');
    expect($team->city)->toBe('Valencia City');
    expect($team->province)->toBe('Bukidnon');
    expect($team->status)->toBe('inactive');
    expect($team->logo_path)->not->toBe('team-logos/skybreakers-old.png');

    Storage::disk('public')->assertMissing('team-logos/skybreakers-old.png');
    Storage::disk('public')->assertExists($team->logo_path);
});

test('admin users can delete teams without recorded activity', function () {
    Storage::fake('public');

    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();

    $team = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Riptide',
        'address' => 'Makati City',
        'city' => 'Makati City',
        'province' => 'Metro Manila (NCR)',
        'country_name' => 'Philippines',
        'logo_path' => 'team-logos/riptide.png',
        'status' => 'active',
    ]);

    Storage::disk('public')->put($team->logo_path, 'fake-logo');

    TeamMember::query()->create([
        'team_id' => $team->id,
        'name' => 'Captain Riptide',
        'nickname' => 'Cap',
        'gender' => 'Male',
        'age' => 25,
        'address' => 'Makati City',
        'role' => 'captain',
    ]);

    $this->actingAs($admin);

    $this->delete(route('admin.teams.destroy', $team))
        ->assertRedirect(route('admin.teams.index'));

    expect(Team::query()->whereKey($team->id)->exists())->toBeFalse();
    Storage::disk('public')->assertMissing('team-logos/riptide.png');
});

test('admin users cannot delete teams with tournament registrations', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();

    $team = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Northwind',
        'address' => 'Pasig',
        'city' => 'Pasig',
        'province' => 'Metro Manila (NCR)',
        'country_name' => 'Philippines',
        'status' => 'active',
    ]);

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Delete Lock Cup',
        'slug' => 'delete-lock-cup',
        'venue' => 'Main Grounds',
        'status' => 'draft',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    $team->registrations()->create([
        'tournament_id' => $tournament->id,
        'status' => 'approved',
    ]);

    $this->actingAs($admin);

    $this->delete(route('admin.teams.destroy', $team))
        ->assertRedirect(route('admin.teams.index', ['selected_team' => $team->id]));

    expect(Team::query()->whereKey($team->id)->exists())->toBeTrue();
});

test('admin can view team detail roster and edit pages', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();

    $team = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Detail Viewers',
        'short_name' => 'DV',
        'description' => 'Test team',
        'address' => 'Street',
        'city' => 'City',
        'province' => 'Province',
        'country_name' => 'Philippines',
        'status' => 'active',
    ]);

    TeamMember::query()->create([
        'team_id' => $team->id,
        'name' => 'Alex Player',
        'gender' => 'male',
        'jersey_number' => 7,
        'role' => 'member',
    ]);

    $this->actingAs($admin);

    $this->get(route('admin.teams.show', $team))
        ->assertOk()
        ->assertSee('Detail Viewers')
        ->assertSee('DV')
        ->assertSee('Alex Player');

    $this->get(route('admin.teams.edit', $team))
        ->assertOk()
        ->assertSee('Edit Team');

    $this->get(route('admin.teams.roster.index', $team))
        ->assertOk()
        ->assertSee('Alex Player');
});

test('admin can import roster from csv', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();

    $team = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'CSV Importers',
        'address' => 'Street',
        'city' => 'City',
        'province' => 'Province',
        'country_name' => 'Philippines',
        'status' => 'active',
    ]);

    $csv = "name,gender,jersey_number,email,role,is_captain,is_spirit_captain\nJane Doe,female,3,jane@example.com,member,0,0\n";

    $this->actingAs($admin);

    $this->post(route('admin.teams.roster.upload', $team), [
        'roster_file' => UploadedFile::fake()->createWithContent('roster.csv', $csv),
    ])->assertRedirect(route('admin.teams.roster.index', $team));

    expect(TeamMember::query()->where('team_id', $team->id)->where('name', 'Jane Doe')->exists())->toBeTrue();
});
