<?php

use App\Models\MatchSpiritScore;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentRegistration;
use App\Models\User;
use App\Support\MatchSpiritPresentation;
use App\Support\MatchSpiritScores;

test('match spirit scores use received scores keyed by scored team id', function () {
    $owner = User::factory()->create();
    $tournament = Tournament::query()->create([
        'created_by' => $owner->id,
        'name' => 'Spirit Cup',
        'slug' => 'spirit-cup',
        'venue' => 'Field',
        'starts_at' => now(),
        'ends_at' => now()->addDay(),
        'status' => 'live',
        'is_public' => true,
    ]);
    $homeTeam = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'BOMBANA',
        'address' => 'A',
        'city' => 'A',
        'country_name' => 'PH',
        'status' => 'active',
    ]);
    $awayTeam = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'CPU',
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
        'home_score' => 10,
        'away_score' => 8,
    ]);

    MatchSpiritScore::query()->create([
        'tournament_id' => $tournament->id,
        'match_id' => $match->id,
        'scoring_team_id' => $awayTeam->id,
        'scored_team_id' => $homeTeam->id,
        'knowledge_rules_score' => 3,
        'fouls_body_contact_score' => 2,
        'fair_mindedness_score' => 3,
        'positive_attitude_score' => 2,
        'communication_respect_score' => 3,
        'total_score' => 13,
    ]);

    MatchSpiritScore::query()->create([
        'tournament_id' => $tournament->id,
        'match_id' => $match->id,
        'scoring_team_id' => $homeTeam->id,
        'scored_team_id' => $awayTeam->id,
        'knowledge_rules_score' => 2,
        'fouls_body_contact_score' => 2,
        'fair_mindedness_score' => 2,
        'positive_attitude_score' => 2,
        'communication_respect_score' => 3,
        'total_score' => 11,
    ]);

    $match->load(['spiritScores', 'homeRegistration.team', 'awayRegistration.team']);

    $presentation = MatchSpiritScores::presentationForMatch($match);

    expect($presentation->status)->toBe(MatchSpiritPresentation::STATUS_READY)
        ->and($presentation->home['team']->name)->toBe('BOMBANA')
        ->and($presentation->home['total_score'])->toBe(13)
        ->and($presentation->away['team']->name)->toBe('CPU')
        ->and($presentation->away['total_score'])->toBe(11)
        ->and($presentation->winnerMessage)->toBe('More spirited team: BOMBANA');
});
