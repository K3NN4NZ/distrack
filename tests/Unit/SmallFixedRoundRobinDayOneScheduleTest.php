<?php

use App\Support\SmallFixedRoundRobinDayOneSchedule;

test('berger ordered pairs produces thirty-six pairings for nine teams', function (): void {
    $pairs = SmallFixedRoundRobinDayOneSchedule::bergerOrderedPairs(9);

    expect($pairs)->toHaveCount(36);
});

test('first twenty four pair slots are all filled for nine teams', function (): void {
    $slots = SmallFixedRoundRobinDayOneSchedule::firstTwentyFourPairSlots(9);

    expect($slots)->toHaveCount(24);
    expect(collect($slots)->every(fn ($slot): bool => $slot !== null))->toBeTrue();
});
