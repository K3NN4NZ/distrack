<?php

use App\Models\MatchSpiritScore;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentRegistration;
use App\Models\User;
use App\Support\TournamentSpiritLeaderboard;

test('tournament spirit leaderboard ranks teams by average received spirit score', function () {
    $organizer = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $organizer->id,
        'name' => 'Spirit Leaderboard Cup',
        'slug' => 'spirit-leaderboard-cup',
        'venue' => 'Field',
        'starts_at' => now(),
        'ends_at' => now()->addDay(),
        'status' => 'active',
        'is_public' => true,
    ]);

    $alpha = Team::query()->create([
        'owner_user_id' => $organizer->id,
        'name' => 'Alpha',
        'address' => 'A',
        'city' => 'A',
        'country_name' => 'PH',
        'status' => 'active',
    ]);

    $beta = Team::query()->create([
        'owner_user_id' => $organizer->id,
        'name' => 'Beta',
        'address' => 'B',
        'city' => 'B',
        'country_name' => 'PH',
        'status' => 'active',
    ]);

    $alphaRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $alpha->id,
        'status' => 'approved',
    ]);

    $betaRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $beta->id,
        'status' => 'approved',
    ]);

    $matchOne = TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'home_registration_id' => $alphaRegistration->id,
        'away_registration_id' => $betaRegistration->id,
        'stage' => 'pool_play',
        'status' => 'completed',
        'home_score' => 10,
        'away_score' => 9,
    ]);

    $matchTwo = TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'home_registration_id' => $betaRegistration->id,
        'away_registration_id' => $alphaRegistration->id,
        'stage' => 'pool_play',
        'status' => 'completed',
        'home_score' => 11,
        'away_score' => 10,
    ]);

    MatchSpiritScore::query()->create([
        'tournament_id' => $tournament->id,
        'match_id' => $matchOne->id,
        'scoring_team_id' => $beta->id,
        'scored_team_id' => $alpha->id,
        'total_score' => 10,
    ]);

    MatchSpiritScore::query()->create([
        'tournament_id' => $tournament->id,
        'match_id' => $matchOne->id,
        'scoring_team_id' => $alpha->id,
        'scored_team_id' => $beta->id,
        'total_score' => 14,
    ]);

    MatchSpiritScore::query()->create([
        'tournament_id' => $tournament->id,
        'match_id' => $matchTwo->id,
        'scoring_team_id' => $alpha->id,
        'scored_team_id' => $beta->id,
        'total_score' => 12,
    ]);

    MatchSpiritScore::query()->create([
        'tournament_id' => $tournament->id,
        'match_id' => $matchTwo->id,
        'scoring_team_id' => $beta->id,
        'scored_team_id' => $alpha->id,
        'total_score' => 11,
    ]);

    $leaderboard = TournamentSpiritLeaderboard::getTournamentSpiritLeaderboard($tournament->fresh());

    expect($leaderboard)->toHaveCount(2)
        ->and($leaderboard[0]['team']->name)->toBe('Beta')
        ->and($leaderboard[0]['total_spirit_score'])->toBe(26)
        ->and($leaderboard[0]['total_games_played'])->toBe(2)
        ->and($leaderboard[0]['average_spirit_score'])->toBe(13.0)
        ->and($leaderboard[1]['team']->name)->toBe('Alpha')
        ->and($leaderboard[1]['total_spirit_score'])->toBe(21)
        ->and($leaderboard[1]['total_games_played'])->toBe(2)
        ->and($leaderboard[1]['average_spirit_score'])->toBe(10.5);
});

test('tournament spirit leaderboard ignores scheduled matches without received scores', function () {
    $organizer = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $organizer->id,
        'name' => 'Spirit Pending Cup',
        'slug' => 'spirit-pending-cup',
        'venue' => 'Field',
        'starts_at' => now(),
        'ends_at' => now()->addDay(),
        'status' => 'active',
        'is_public' => true,
    ]);

    $team = Team::query()->create([
        'owner_user_id' => $organizer->id,
        'name' => 'Solo',
        'address' => 'A',
        'city' => 'A',
        'country_name' => 'PH',
        'status' => 'active',
    ]);

    $opponent = Team::query()->create([
        'owner_user_id' => $organizer->id,
        'name' => 'Opponent',
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

    $opponentRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $opponent->id,
        'status' => 'approved',
    ]);

    TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'home_registration_id' => $registration->id,
        'away_registration_id' => $opponentRegistration->id,
        'stage' => 'pool_play',
        'status' => 'scheduled',
    ]);

    expect(TournamentSpiritLeaderboard::getTournamentSpiritLeaderboard($tournament->fresh()))->toBeEmpty();
});
