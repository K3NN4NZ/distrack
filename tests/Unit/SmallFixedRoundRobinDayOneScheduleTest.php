<?php

use App\Models\Tournament;
use App\Support\SmallFixedRoundRobinDayOneSchedule;

test('normalizeTimezone maps legacy offset strings to Asia/Manila', function (): void {
    expect(SmallFixedRoundRobinDayOneSchedule::normalizeTimezone('UTC+8'))->toBe('Asia/Manila')
        ->and(SmallFixedRoundRobinDayOneSchedule::normalizeTimezone('GMT+8'))->toBe('Asia/Manila')
        ->and(SmallFixedRoundRobinDayOneSchedule::normalizeTimezone('UTC+08'))->toBe('Asia/Manila')
        ->and(SmallFixedRoundRobinDayOneSchedule::normalizeTimezone('UTC+8:00'))->toBe('Asia/Manila')
        ->and(SmallFixedRoundRobinDayOneSchedule::normalizeTimezone(''))->toBe('Asia/Manila')
        ->and(SmallFixedRoundRobinDayOneSchedule::normalizeTimezone('Europe/London'))->toBe('Europe/London')
        ->and(SmallFixedRoundRobinDayOneSchedule::normalizeTimezone('Not/AZone'))->toBe('Asia/Manila');
});

test('tournamentTimezone normalizes stored legacy UTC+8 and defaults empty to Asia/Manila', function (): void {
    $legacy = new Tournament(['timezone' => 'UTC+8']);
    expect(SmallFixedRoundRobinDayOneSchedule::tournamentTimezone($legacy))->toBe('Asia/Manila');

    $empty = new Tournament(['timezone' => '']);
    expect(SmallFixedRoundRobinDayOneSchedule::tournamentTimezone($empty))->toBe('Asia/Manila');
});

test('berger ordered pairs produces thirty-six pairings for nine teams', function (): void {
    $pairs = SmallFixedRoundRobinDayOneSchedule::bergerOrderedPairs(9);

    expect($pairs)->toHaveCount(36);
});

test('first twenty four pair slots are all filled for nine teams', function (): void {
    $slots = SmallFixedRoundRobinDayOneSchedule::firstTwentyFourPairSlots(9);

    expect($slots)->toHaveCount(24);
    expect(collect($slots)->every(fn ($slot): bool => $slot !== null))->toBeTrue();
});
