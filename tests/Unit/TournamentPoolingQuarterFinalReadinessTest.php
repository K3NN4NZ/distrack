<?php

use App\Support\TournamentPooling;

test('pooling finalized for quarter finals rejects incomplete crossover', function (): void {
    $built = [
        'has_matches' => true,
        'all_crossover_finalized' => false,
        'duplicate_team_warnings' => [],
        'pools' => [
            ['rows' => [['pending' => false, 'registration_id' => 1]]],
            ['rows' => [['pending' => false, 'registration_id' => 2]]],
        ],
    ];

    expect(TournamentPooling::poolingFinalizedForQuarterFinalGeneration($built))->toBeFalse();
});

test('pooling finalized for quarter finals rejects duplicate slot warnings', function (): void {
    $built = [
        'has_matches' => true,
        'all_crossover_finalized' => true,
        'duplicate_team_warnings' => ['dup'],
        'pools' => [
            ['rows' => [['pending' => false, 'registration_id' => 1]]],
            ['rows' => [['pending' => false, 'registration_id' => 2]]],
        ],
    ];

    expect(TournamentPooling::poolingFinalizedForQuarterFinalGeneration($built))->toBeFalse();
});

test('pooling finalized for quarter finals rejects pending slots', function (): void {
    $built = [
        'has_matches' => true,
        'all_crossover_finalized' => true,
        'duplicate_team_warnings' => [],
        'pools' => [
            ['rows' => [['pending' => true, 'registration_id' => null]]],
            ['rows' => [['pending' => false, 'registration_id' => 2]]],
        ],
    ];

    expect(TournamentPooling::poolingFinalizedForQuarterFinalGeneration($built))->toBeFalse();
});

test('pooling finalized for quarter finals succeeds when both pools are fully resolved', function (): void {
    $built = [
        'has_matches' => true,
        'all_crossover_finalized' => true,
        'duplicate_team_warnings' => [],
        'pools' => [
            ['rows' => [
                ['pending' => false, 'registration_id' => 10],
                ['pending' => false, 'registration_id' => 11],
            ]],
            ['rows' => [
                ['pending' => false, 'registration_id' => 20],
                ['pending' => false, 'registration_id' => 21],
            ]],
        ],
    ];

    expect(TournamentPooling::poolingFinalizedForQuarterFinalGeneration($built))->toBeTrue();

    $columns = TournamentPooling::quarterFinalBracketColumnsRegistrationIds($built);
    expect($columns)->toBe([[10, 11], [20, 21]]);
});
