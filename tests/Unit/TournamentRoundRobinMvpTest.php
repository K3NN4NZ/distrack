<?php

use App\Models\MatchPlayerStat;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentRegistration;
use App\Models\User;
use App\Support\RoundRobinStage;
use App\Support\TournamentRoundRobinMvp;
use App\Support\TournamentRoundRobinMvpPresentation;

test('round robin stage matcher accepts common round robin labels', function () {
    expect(RoundRobinStage::isRoundRobinStage('round_robin'))->toBeTrue()
        ->and(RoundRobinStage::isRoundRobinStage('round-robin'))->toBeTrue()
        ->and(RoundRobinStage::isRoundRobinStage('roundrobin'))->toBeTrue()
        ->and(RoundRobinStage::isRoundRobinStage('Round Robin'))->toBeTrue()
        ->and(RoundRobinStage::isRoundRobinStage('semifinal'))->toBeFalse()
        ->and(RoundRobinStage::isRoundRobinStage('pool_play'))->toBeFalse()
        ->and(RoundRobinStage::isRoundRobinStage('quarterfinal'))->toBeFalse();
});

test('tournament round robin mvp aggregates completed round robin stats only', function () {
    $owner = User::factory()->create();
    $tournament = Tournament::query()->create([
        'created_by' => $owner->id,
        'name' => 'RR MVP Cup',
        'slug' => 'rr-mvp-cup',
        'venue' => 'Field',
        'starts_at' => now(),
        'ends_at' => now()->addDay(),
        'status' => 'live',
        'is_public' => true,
    ]);
    $homeTeam = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Home RR',
        'address' => 'A',
        'city' => 'A',
        'country_name' => 'PH',
        'status' => 'active',
    ]);
    $awayTeam = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Away RR',
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
    $player = TeamMember::query()->create([
        'team_id' => $homeTeam->id,
        'name' => 'Ace Player',
        'nickname' => 'Ace',
        'gender' => 'Male',
        'age' => 20,
        'address' => 'A',
        'role' => 'player',
    ]);

    $roundRobinMatch = TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'home_registration_id' => $homeRegistration->id,
        'away_registration_id' => $awayRegistration->id,
        'stage' => 'round_robin',
        'status' => 'completed',
        'home_score' => 10,
        'away_score' => 8,
    ]);

    $bracketMatch = TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'home_registration_id' => $homeRegistration->id,
        'away_registration_id' => $awayRegistration->id,
        'stage' => 'semifinal',
        'status' => 'completed',
        'home_score' => 12,
        'away_score' => 11,
    ]);

    MatchPlayerStat::query()->create([
        'match_id' => $roundRobinMatch->id,
        'team_member_id' => $player->id,
        'goals' => 3,
        'assists' => 2,
        'blocks' => 1,
    ]);

    MatchPlayerStat::query()->create([
        'match_id' => $bracketMatch->id,
        'team_member_id' => $player->id,
        'goals' => 10,
        'assists' => 10,
        'blocks' => 10,
    ]);

    $tournament->load(['matches.playerStats.teamMember.team', 'registrations']);

    $presentation = TournamentRoundRobinMvp::presentationForTournament($tournament);

    expect($presentation->status)->toBe(TournamentRoundRobinMvpPresentation::STATUS_READY)
        ->and($presentation->overall)->toHaveCount(1)
        ->and($presentation->overall->first()['display_name'])->toBe('Ace Player')
        ->and($presentation->overall->first()['total'])->toBe(6);
});

test('tournament round robin mvp filters by player name and registration', function () {
    $owner = User::factory()->create();
    $tournament = Tournament::query()->create([
        'created_by' => $owner->id,
        'name' => 'RR Filter Cup',
        'slug' => 'rr-filter-cup',
        'venue' => 'Field',
        'starts_at' => now(),
        'ends_at' => now()->addDay(),
        'status' => 'live',
        'is_public' => true,
    ]);
    $homeTeam = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Home RR',
        'address' => 'A',
        'city' => 'A',
        'country_name' => 'PH',
        'status' => 'active',
    ]);
    $awayTeam = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Away RR',
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
    $homePlayer = TeamMember::query()->create([
        'team_id' => $homeTeam->id,
        'name' => 'Shyne Ace',
        'gender' => 'Male',
        'role' => 'player',
    ]);
    $awayPlayer = TeamMember::query()->create([
        'team_id' => $awayTeam->id,
        'name' => 'Other Player',
        'gender' => 'Male',
        'role' => 'player',
    ]);
    $roundRobinMatch = TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'home_registration_id' => $homeRegistration->id,
        'away_registration_id' => $awayRegistration->id,
        'stage' => 'round_robin',
        'status' => 'completed',
        'home_score' => 10,
        'away_score' => 8,
    ]);

    foreach ([$homePlayer, $awayPlayer] as $player) {
        MatchPlayerStat::query()->create([
            'match_id' => $roundRobinMatch->id,
            'team_member_id' => $player->id,
            'goals' => 2,
            'assists' => 1,
            'blocks' => 0,
        ]);
    }

    $tournament->load(['matches.playerStats.teamMember.team', 'registrations']);

    $bySearch = TournamentRoundRobinMvp::presentationForTournament($tournament, ['search' => 'shyne']);
    $byTeam = TournamentRoundRobinMvp::presentationForTournament($tournament, ['team' => $homeRegistration->id]);

    expect($bySearch->overall)->toHaveCount(1)
        ->and($bySearch->overall->first()['display_name'])->toBe('Shyne Ace')
        ->and($byTeam->overall)->toHaveCount(1)
        ->and($byTeam->overall->first()['display_name'])->toBe('Shyne Ace');
});

test('tournament round robin mvp filters by gender', function () {
    $owner = User::factory()->create();
    $tournament = Tournament::query()->create([
        'created_by' => $owner->id,
        'name' => 'RR Gender Cup',
        'slug' => 'rr-gender-cup',
        'venue' => 'Field',
        'starts_at' => now(),
        'ends_at' => now()->addDay(),
        'status' => 'live',
        'is_public' => true,
    ]);
    $homeTeam = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Home RR',
        'address' => 'A',
        'city' => 'A',
        'country_name' => 'PH',
        'status' => 'active',
    ]);
    $awayTeam = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Away RR',
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
    $malePlayer = TeamMember::query()->create([
        'team_id' => $homeTeam->id,
        'name' => 'Male Star',
        'gender' => 'Male',
        'role' => 'player',
    ]);
    $femalePlayer = TeamMember::query()->create([
        'team_id' => $awayTeam->id,
        'name' => 'Female Star',
        'gender' => 'Female',
        'role' => 'player',
    ]);
    $roundRobinMatch = TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'home_registration_id' => $homeRegistration->id,
        'away_registration_id' => $awayRegistration->id,
        'stage' => 'round_robin',
        'status' => 'completed',
        'home_score' => 10,
        'away_score' => 8,
    ]);

    foreach ([$malePlayer, $femalePlayer] as $player) {
        MatchPlayerStat::query()->create([
            'match_id' => $roundRobinMatch->id,
            'team_member_id' => $player->id,
            'goals' => 2,
            'assists' => 1,
            'blocks' => 0,
        ]);
    }

    $tournament->load(['matches.playerStats.teamMember.team', 'registrations']);

    $all = TournamentRoundRobinMvp::presentationForTournament($tournament, ['gender' => 'all']);
    $men = TournamentRoundRobinMvp::presentationForTournament($tournament, ['gender' => 'men']);
    $women = TournamentRoundRobinMvp::presentationForTournament($tournament, ['gender' => 'women']);
    $mix = TournamentRoundRobinMvp::presentationForTournament($tournament, ['gender' => 'mix']);

    expect($all->overall)->toHaveCount(2)
        ->and($men->overall)->toHaveCount(1)
        ->and($men->overall->first()['display_name'])->toBe('Male Star')
        ->and($women->overall)->toHaveCount(1)
        ->and($women->overall->first()['display_name'])->toBe('Female Star')
        ->and($mix->overall)->toHaveCount(2)
        ->and($men->showMaleSection)->toBeFalse()
        ->and($men->showFemaleSection)->toBeFalse()
        ->and($all->showMaleSection)->toBeTrue()
        ->and($all->showFemaleSection)->toBeTrue();
});
