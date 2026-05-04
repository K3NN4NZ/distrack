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
        ->assertSee('Skybreakers')
        ->assertSee('Captain Owner')
        ->assertSee('Spirit Lead')
        ->assertSee('Quezon City, Metro Manila (NCR), Philippines')
        ->assertSee('captain-owner@distrack.test')
        ->assertSee('3')
        ->assertSee('1');
});

test('admin users can create teams in the admin teams directory', function () {
    Storage::fake('public');

    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create([
        'name' => 'Team Owner',
        'email' => 'owner@distrack.test',
    ]);

    $this->actingAs($admin);

    $this->post(route('admin.teams.store'), [
        'owner_user_id' => $owner->id,
        'name' => 'Disc Falcons',
        'address' => 'Poblacion',
        'city' => 'Valencia City',
        'province' => 'Bukidnon',
        'country_name' => 'Philippines',
        'status' => 'active',
        'logo' => UploadedFile::fake()->image('disc-falcons.png'),
    ])->assertRedirect();

    $team = Team::query()->where('name', 'Disc Falcons')->first();

    expect($team)->not->toBeNull();
    expect($team->owner_user_id)->toBe($owner->id);
    expect($team->city)->toBe('Valencia City');
    expect($team->province)->toBe('Bukidnon');
    expect($team->status)->toBe('active');
    expect($team->logo_path)->not->toBeNull();

    Storage::disk('public')->assertExists($team->logo_path);
});

test('admin users can update teams in the admin teams directory', function () {
    Storage::fake('public');

    $admin = User::factory()->admin()->create();
    $originalOwner = User::factory()->create([
        'name' => 'Original Owner',
        'email' => 'original-owner@distrack.test',
    ]);
    $replacementOwner = User::factory()->create([
        'name' => 'Replacement Owner',
        'email' => 'replacement-owner@distrack.test',
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
        'edit_owner_user_id' => $replacementOwner->id,
        'edit_name' => 'Skybreakers Elite',
        'edit_address' => 'Poblacion',
        'edit_city' => 'Valencia City',
        'edit_province' => 'Bukidnon',
        'edit_country_name' => 'Philippines',
        'edit_status' => 'inactive',
        'edit_logo' => UploadedFile::fake()->image('skybreakers-new.png'),
    ])->assertRedirect(route('admin.teams.index', ['selected_team' => $team->id]));

    $team->refresh();

    expect($team->owner_user_id)->toBe($replacementOwner->id);
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
