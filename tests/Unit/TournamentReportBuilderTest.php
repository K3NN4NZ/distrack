<?php

declare(strict_types=1);

use App\Models\MatchPlayerStat;
use App\Models\MatchSpiritScore;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentRegistration;
use App\Models\User;
use App\Support\SmallDayTwoKnockoutBracket;
use App\Support\TournamentReportBuilder;

test('tournament report builder resolves team awards from spirit and placement matches', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $admin->id,
        'name' => 'Report Builder Cup',
        'slug' => 'report-builder-cup',
        'venue' => 'Field',
        'status' => 'draft',
        'country_name' => 'Philippines',
        'surface' => 'Outdoor',
        'division' => 'Open',
        'is_public' => false,
    ]);

    $teams = collect(['Alpha Spirits', 'Beta Losers', 'Gamma Third', 'Delta Fourth'])->map(function (string $name) use ($owner): Team {
        return Team::query()->create([
            'owner_user_id' => $owner->id,
            'name' => $name,
            'address' => 'X',
            'country_name' => 'Philippines',
            'status' => 'active',
        ]);
    });

    $regs = $teams->map(fn (Team $team): TournamentRegistration => TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $team->id,
        'status' => 'approved',
        'seed_number' => null,
    ]));

    $rAlpha = $regs[0];
    $rBeta = $regs[1];
    $rGamma = $regs[2];
    $rDelta = $regs[3];

    $ace = TeamMember::query()->create([
        'team_id' => $teams[1]->id,
        'user_id' => null,
        'name' => 'Ace Goals',
        'nickname' => null,
        'gender' => 'Male',
        'role' => 'member',
    ]);
    $blocky = TeamMember::query()->create([
        'team_id' => $teams[0]->id,
        'user_id' => null,
        'name' => 'Blocky Wall',
        'nickname' => null,
        'gender' => 'Male',
        'role' => 'member',
    ]);
    $helper = TeamMember::query()->create([
        'team_id' => $teams[1]->id,
        'user_id' => null,
        'name' => 'Helper Pass',
        'nickname' => null,
        'gender' => 'Male',
        'role' => 'member',
    ]);
    $lane = TeamMember::query()->create([
        'team_id' => $teams[0]->id,
        'user_id' => null,
        'name' => 'Lane Score',
        'nickname' => null,
        'gender' => 'Female',
        'role' => 'member',
    ]);

    $mSpirit = TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'pitch_id' => null,
        'home_registration_id' => $rAlpha->id,
        'away_registration_id' => $rBeta->id,
        'stage' => 'group',
        'match_number' => 1,
        'scheduled_at' => now(),
        'status' => TournamentMatch::STATUS_COMPLETED,
        'home_score' => 10,
        'away_score' => 8,
    ]);

    MatchSpiritScore::query()->create([
        'tournament_id' => $tournament->id,
        'match_id' => $mSpirit->id,
        'scoring_team_id' => $teams[1]->id,
        'scored_team_id' => $teams[0]->id,
        'spirit_captain_id' => null,
        'knowledge_rules_score' => null,
        'fouls_body_contact_score' => null,
        'fair_mindedness_score' => null,
        'positive_attitude_score' => null,
        'communication_respect_score' => null,
        'total_score' => 18,
        'notes' => null,
    ]);

    MatchSpiritScore::query()->create([
        'tournament_id' => $tournament->id,
        'match_id' => $mSpirit->id,
        'scoring_team_id' => $teams[0]->id,
        'scored_team_id' => $teams[1]->id,
        'spirit_captain_id' => null,
        'knowledge_rules_score' => null,
        'fouls_body_contact_score' => null,
        'fair_mindedness_score' => null,
        'positive_attitude_score' => null,
        'communication_respect_score' => null,
        'total_score' => 10,
        'notes' => null,
    ]);

    MatchPlayerStat::query()->create([
        'match_id' => $mSpirit->id,
        'team_member_id' => $ace->id,
        'goals' => 15,
        'assists' => 3,
        'blocks' => 2,
    ]);
    MatchPlayerStat::query()->create([
        'match_id' => $mSpirit->id,
        'team_member_id' => $blocky->id,
        'goals' => 0,
        'assists' => 0,
        'blocks' => 12,
    ]);
    MatchPlayerStat::query()->create([
        'match_id' => $mSpirit->id,
        'team_member_id' => $helper->id,
        'goals' => 0,
        'assists' => 20,
        'blocks' => 0,
    ]);
    MatchPlayerStat::query()->create([
        'match_id' => $mSpirit->id,
        'team_member_id' => $lane->id,
        'goals' => 8,
        'assists' => 0,
        'blocks' => 0,
    ]);

    $mSpirit2 = TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'pitch_id' => null,
        'home_registration_id' => $rGamma->id,
        'away_registration_id' => $rDelta->id,
        'stage' => 'group',
        'match_number' => 2,
        'scheduled_at' => now(),
        'status' => TournamentMatch::STATUS_COMPLETED,
        'home_score' => 7,
        'away_score' => 7,
    ]);

    MatchSpiritScore::query()->create([
        'tournament_id' => $tournament->id,
        'match_id' => $mSpirit2->id,
        'scoring_team_id' => $teams[3]->id,
        'scored_team_id' => $teams[0]->id,
        'spirit_captain_id' => null,
        'knowledge_rules_score' => null,
        'fouls_body_contact_score' => null,
        'fair_mindedness_score' => null,
        'positive_attitude_score' => null,
        'communication_respect_score' => null,
        'total_score' => 20,
        'notes' => null,
    ]);

    $m47 = TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'pitch_id' => null,
        'home_registration_id' => $rGamma->id,
        'away_registration_id' => $rDelta->id,
        'stage' => 'placement',
        'match_number' => 47,
        'scheduled_at' => now(),
        'status' => TournamentMatch::STATUS_COMPLETED,
        'home_score' => 11,
        'away_score' => 9,
        'notes' => SmallDayTwoKnockoutBracket::marker(47),
    ]);

    $m48 = TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'pitch_id' => null,
        'home_registration_id' => $rAlpha->id,
        'away_registration_id' => $rBeta->id,
        'stage' => 'championship',
        'match_number' => 48,
        'scheduled_at' => now(),
        'status' => TournamentMatch::STATUS_COMPLETED,
        'home_score' => 13,
        'away_score' => 10,
        'notes' => SmallDayTwoKnockoutBracket::marker(48),
    ]);

    MatchPlayerStat::query()->create([
        'match_id' => $m48->id,
        'team_member_id' => $ace->id,
        'goals' => 4,
        'assists' => 1,
        'blocks' => 0,
    ]);
    MatchPlayerStat::query()->create([
        'match_id' => $m48->id,
        'team_member_id' => $lane->id,
        'goals' => 2,
        'assists' => 0,
        'blocks' => 1,
    ]);

    $report = app(TournamentReportBuilder::class)->build($tournament);

    expect($report['team_awards']['most_spirited_team'])->toContain('Alpha Spirits');
    expect($report['team_awards']['most_spirited_team'])->toContain('19.0');
    expect($report['team_awards']['champion'])->toBe('Alpha Spirits');
    expect($report['team_awards']['first_runner_up'])->toBe('Beta Losers');
    expect($report['team_awards']['second_runner_up'])->toBe('Gamma Third');
    expect($report['team_awards']['third_runner_up'])->toBe('Delta Fourth');

    $ia = $report['individual_awards'];
    expect($ia['most_blocks_male'])->toBe('Blocky Wall – Alpha Spirits');
    expect($ia['most_assists_male'])->toBe('Helper Pass – Beta Losers');
    expect($ia['most_scores_male'])->toBe('Ace Goals – Beta Losers');
    expect($ia['most_scores_female'])->toBe('Lane Score – Alpha Spirits');
    expect($ia['tournament_mvp_male'])->toBe('Ace Goals – Beta Losers');
    expect($ia['finals_mvp_male'])->toBe('Ace Goals – Beta Losers');
    expect($ia['finals_mvp_female'])->toBe('Lane Score – Alpha Spirits');
    expect($ia['mythical_7_male'][0])->toBe('Ace Goals – Beta Losers');
    expect($ia['mythical_7_male'][1])->toBe('Helper Pass – Beta Losers');
    expect($ia['mythical_7_male'][2])->toBe('Blocky Wall – Alpha Spirits');
    expect($ia['mythical_7_female'][0])->toBe('Lane Score – Alpha Spirits');
});
