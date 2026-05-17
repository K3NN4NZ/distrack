<?php

use App\Http\Controllers\PublicTournamentController;
use App\Support\SmallDayTwoKnockoutBracket;

test('small day two knockout bracket defines cross-over championship feeder pairs', function () {
    expect(SmallDayTwoKnockoutBracket::championshipTreeFeederPairs())->toBe([
        [37, 43],
        [40, 43],
        [38, 44],
        [39, 44],
        [43, 48],
        [44, 48],
    ]);
});

test('public bracket board resolves cross-over feeder links from match numbers', function () {
    $controller = app(PublicTournamentController::class);
    $method = new ReflectionMethod($controller, 'resolvePublicBracketFeederLinks');

    $makeMatch = fn (int $number): object => (object) ['match_number' => $number];

    $columns = collect([
        [
            'cards' => collect([
                ['match' => $makeMatch(37), 'slot' => 1],
                ['match' => $makeMatch(38), 'slot' => 3],
                ['match' => $makeMatch(39), 'slot' => 5],
                ['match' => $makeMatch(40), 'slot' => 7],
            ]),
        ],
        [
            'cards' => collect([
                ['match' => $makeMatch(43), 'slot' => 2],
                ['match' => $makeMatch(44), 'slot' => 6],
            ]),
        ],
        [
            'cards' => collect([
                ['match' => $makeMatch(48), 'slot' => 4],
            ]),
        ],
    ]);

    $links = $method->invoke($controller, $columns);

    expect($links)->toHaveCount(6)
        ->and($links->pluck('from')->all())->toContain(37, 40, 38, 39, 43, 44)
        ->and($links->firstWhere('from', 37))->toMatchArray([
            'from' => 37,
            'to' => 43,
            'from_slot' => 1,
            'to_slot' => 2,
            'from_column' => 0,
            'to_column' => 1,
        ])
        ->and($links->firstWhere('from', 40))->toMatchArray([
            'from' => 40,
            'to' => 43,
            'from_slot' => 7,
            'to_slot' => 2,
            'from_column' => 0,
            'to_column' => 1,
        ]);
});
