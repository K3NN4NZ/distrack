<?php

use App\Models\MatchPlayerStat;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentRegistration;
use App\Models\User;
use App\Support\MatchMvp;
use App\Support\MatchMvpPresentation;

test('match mvp picks highest total with tie breakers within a registration', function () {
    $owner = User::factory()->create();
    $tournament = Tournament::query()->create([
        'created_by' => $owner->id,
        'name' => 'MVP Test Cup',
        'slug' => 'mvp-test-cup',
        'venue' => 'Field',
        'starts_at' => now(),
        'ends_at' => now()->addDay(),
        'status' => 'live',
        'is_public' => true,
    ]);
    $team = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Home Side',
        'address' => 'A',
        'city' => 'A',
        'country_name' => 'PH',
        'status' => 'active',
    ]);
    $otherTeam = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Away Side',
        'address' => 'B',
        'city' => 'B',
        'country_name' => 'PH',
        'status' => 'active',
    ]);
    $registration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $team->id,
        'status' => 'approved',
    ]);
    $otherRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $otherTeam->id,
        'status' => 'approved',
    ]);

    $match = TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'home_registration_id' => $registration->id,
        'away_registration_id' => $otherRegistration->id,
        'status' => 'completed',
        'home_score' => 10,
        'away_score' => 5,
    ]);

    $alpha = TeamMember::query()->create([
        'team_id' => $team->id,
        'name' => 'Alpha Player',
        'nickname' => 'A',
        'gender' => 'male',
        'age' => 20,
        'address' => 'A',
        'role' => 'player',
    ]);
    $beta = TeamMember::query()->create([
        'team_id' => $team->id,
        'name' => 'Beta Player',
        'nickname' => 'B',
        'gender' => 'male',
        'age' => 21,
        'address' => 'B',
        'role' => 'player',
    ]);

    MatchPlayerStat::query()->create([
        'match_id' => $match->id,
        'team_member_id' => $alpha->id,
        'goals' => 2,
        'assists' => 2,
        'blocks' => 2,
    ]);
    MatchPlayerStat::query()->create([
        'match_id' => $match->id,
        'team_member_id' => $beta->id,
        'goals' => 3,
        'assists' => 1,
        'blocks' => 2,
    ]);

    $match->load(['playerStats.teamMember', 'homeRegistration.team', 'awayRegistration.team']);

    $mvp = MatchMvp::forRegistration($match, $registration);

    expect($mvp)->not->toBeNull()
        ->and($mvp['player']->id)->toBe($beta->id)
        ->and($mvp['scores'])->toBe(3)
        ->and($mvp['assists'])->toBe(1)
        ->and($mvp['blocks'])->toBe(2)
        ->and($mvp['total'])->toBe(6);
});

test('match mvp presentation returns pending for tied completed matches', function () {
    $owner = User::factory()->create();
    $tournament = Tournament::query()->create([
        'created_by' => $owner->id,
        'name' => 'MVP Tie Cup',
        'slug' => 'mvp-tie-cup',
        'venue' => 'Field',
        'starts_at' => now(),
        'ends_at' => now()->addDay(),
        'status' => 'live',
        'is_public' => true,
    ]);
    $homeTeam = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Home',
        'address' => 'A',
        'city' => 'A',
        'country_name' => 'PH',
        'status' => 'active',
    ]);
    $awayTeam = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Away',
        'address' => 'B',
        'city' => 'B',
        'country_name' => 'PH',
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
        'status' => 'completed',
        'home_score' => 8,
        'away_score' => 8,
    ]);

    $presentation = MatchMvp::presentationForMatch($match);

    expect($presentation->status)->toBe(MatchMvpPresentation::STATUS_PENDING);
});
