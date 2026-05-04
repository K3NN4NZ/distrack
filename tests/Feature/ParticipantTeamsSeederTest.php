<?php

use App\Models\Team;
use App\Models\User;
use Database\Seeders\ParticipantTeamsSeeder;
use Database\Seeders\UserAccountSeeder;

test('participant team seeder creates nine teams with captain and spirit captain roles', function () {
    $this->seed([
        UserAccountSeeder::class,
        ParticipantTeamsSeeder::class,
    ]);

    $teams = Team::query()
        ->with(['owner', 'members'])
        ->where('name', 'like', 'Seeded %')
        ->orderBy('name')
        ->get();

    expect($teams)->toHaveCount(9);

    foreach ($teams as $team) {
        $captains = $team->members->where('role', 'captain');
        $spiritCaptains = $team->members->where('role', 'spirit_captain');
        $members = $team->members->where('role', 'member');

        expect($team->owner)->not->toBeNull();
        expect($team->owner->role)->toBe(User::ROLE_CAPTAIN);
        expect($captains)->toHaveCount(1);
        expect($spiritCaptains)->toHaveCount(1);
        expect($members->count())->toBeGreaterThanOrEqual(12);
        expect($members->count())->toBeLessThanOrEqual(15);
        expect($team->members)->toHaveCount($members->count() + 2);
        expect($captains->first()->user_id)->toBe($team->owner_user_id);
        expect($team->country_name)->toBe('Philippines');
    }
});
