<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response
        ->assertOk()
        ->assertSee('Tournaments')
        ->assertDontSee('Tournament Setup')
        ->assertSee(route('captain.tournaments.index'), false);
});

test('admin dashboard shows tournament management menus', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Create Tournament')
        ->assertSee('Tournaments')
        ->assertSee('Tournament Setup')
        ->assertSee(route('admin.tournaments.index').'#create-tournament', false)
        ->assertSee(route('admin.tournaments.list'), false)
        ->assertSee(route('admin.tournaments.index'), false);
});

test('scorekeeper dashboard shows tournament scoring access without admin setup', function () {
    $scorekeeper = User::factory()->scorekeeper()->create();
    $this->actingAs($scorekeeper);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Tournaments')
        ->assertSee('Scorekeeper Console')
        ->assertDontSee('Create Tournament')
        ->assertDontSee('Tournament Setup')
        ->assertSee(route('admin.tournaments.list'), false)
        ->assertDontSee('href="'.route('admin.tournaments.index').'"', false);
});
