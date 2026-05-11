<?php

use App\Models\TournamentMatch;
use App\Support\TournamentPooling;

test('crossoverGameDisplayNumber accepts optional space after hash in round label', function () {
    expect(TournamentPooling::crossoverGameDisplayNumber((new TournamentMatch)->forceFill([
        'match_number' => null,
        'round_label' => 'Cross · A vs B # 7',
    ])))->toBe(7);

    expect(TournamentPooling::crossoverGameDisplayNumber((new TournamentMatch)->forceFill([
        'match_number' => null,
        'round_label' => 'Cross · A vs B #8',
    ])))->toBe(8);
});

test('gamesKeyedByCrossoverNumber fills missing indices when trailing hash labels repeat across bracket pairs', function () {
    $matches = collect([
        (new TournamentMatch)->forceFill(['id' => 101, 'match_number' => null, 'round_label' => 'Cross · A vs B #1']),
        (new TournamentMatch)->forceFill(['id' => 102, 'match_number' => null, 'round_label' => 'Cross · A vs B #2']),
        (new TournamentMatch)->forceFill(['id' => 103, 'match_number' => null, 'round_label' => 'Cross · A vs B #3']),
        (new TournamentMatch)->forceFill(['id' => 104, 'match_number' => null, 'round_label' => 'Cross · A vs B #4']),
        (new TournamentMatch)->forceFill(['id' => 201, 'match_number' => null, 'round_label' => 'Cross · C vs D #1']),
        (new TournamentMatch)->forceFill(['id' => 202, 'match_number' => null, 'round_label' => 'Cross · C vs D #2']),
        (new TournamentMatch)->forceFill(['id' => 203, 'match_number' => null, 'round_label' => 'Cross · C vs D #3']),
        (new TournamentMatch)->forceFill(['id' => 204, 'match_number' => null, 'round_label' => 'Cross · C vs D #4']),
    ]);

    $byNum = TournamentPooling::gamesKeyedByCrossoverNumber($matches);

    expect($byNum)->toHaveKeys([1, 2, 3, 4, 5, 6, 7, 8])
        ->and($byNum[4]->id)->toBe(104)
        ->and($byNum[5]->id)->toBe(201)
        ->and($byNum[6]->id)->toBe(202)
        ->and($byNum[7]->id)->toBe(203)
        ->and($byNum[8]->id)->toBe(204);
});

test('gamesKeyedByCrossoverNumber preserves explicit match_number when present', function () {
    $matches = collect([
        (new TournamentMatch)->forceFill(['id' => 50, 'match_number' => 1, 'round_label' => 'Cross · A vs B #99']),
        (new TournamentMatch)->forceFill(['id' => 51, 'match_number' => 2, 'round_label' => 'Cross · A vs B #98']),
    ]);

    $byNum = TournamentPooling::gamesKeyedByCrossoverNumber($matches);

    expect($byNum[1]->id)->toBe(50)
        ->and($byNum[2]->id)->toBe(51);
});

test('diagram slot lists shrink when fewer than eight crossover games exist', function () {
    expect(TournamentPooling::diagramPoolASlotCodesForCrossoverGameCount(6))
        ->toHaveCount(8);

    $poolB6 = TournamentPooling::diagramPoolBSlotCodesForCrossoverGameCount(6);

    expect($poolB6)->not->toContain('W7')->not->toContain('W8')->not->toContain('L7')->not->toContain('L8')
        ->and($poolB6)->toContain('L1')->toContain('L2')->toContain('L3')->toContain('L4');

    expect(TournamentPooling::diagramSlotCapacityForCrossoverGameCount(6))->toBe(12);

    expect(TournamentPooling::diagramPoolASlotCodesForCrossoverGameCount(2))->toBe(['W1', 'W2'])
        ->and(TournamentPooling::diagramPoolBSlotCodesForCrossoverGameCount(2))->toBe(['L2', 'L1']);
});

test('gamesKeyedByCrossoverNumber remaps non contiguous match_number values to diagram slots 1 through N', function () {
    $matches = collect([
        (new TournamentMatch)->forceFill(['id' => 1, 'match_number' => 101, 'round_label' => 'Cross #1']),
        (new TournamentMatch)->forceFill(['id' => 2, 'match_number' => 102, 'round_label' => 'Cross #2']),
    ]);

    $byNum = TournamentPooling::gamesKeyedByCrossoverNumber($matches);

    expect($byNum[1]->id)->toBe(1)
        ->and($byNum[2]->id)->toBe(2);
});
