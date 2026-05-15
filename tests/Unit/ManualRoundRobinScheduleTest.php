<?php

use App\Models\Tournament;
use App\Support\ManualRoundRobinSchedule;

test('manual round robin day headers fall back to fixed day one and day two dates', function (): void {
    $tournament = new Tournament([
        'timezone' => 'Asia/Manila',
    ]);

    expect(ManualRoundRobinSchedule::dayDateIso(ManualRoundRobinSchedule::roundRobinDayOneLocal($tournament)))
        ->toBe('2026-05-16')
        ->and(ManualRoundRobinSchedule::dayDateLabel(ManualRoundRobinSchedule::roundRobinDayOneLocal($tournament)))
        ->toBe('May 16, 2026')
        ->and(ManualRoundRobinSchedule::dayDateIso(ManualRoundRobinSchedule::roundRobinDayTwoLocal($tournament)))
        ->toBe('2026-05-17')
        ->and(ManualRoundRobinSchedule::dayDateLabel(ManualRoundRobinSchedule::roundRobinDayTwoLocal($tournament)))
        ->toBe('May 17, 2026');
});

test('manual round robin day headers use tournament start and end calendar dates without shifting them', function (): void {
    $tournament = new Tournament();
    $tournament->setRawAttributes([
        'timezone' => 'Asia/Manila',
        'starts_at' => '2026-05-16 00:00:00',
        'ends_at' => '2026-05-17 00:00:00',
    ], true);

    expect(ManualRoundRobinSchedule::dayDateIso(ManualRoundRobinSchedule::roundRobinDayOneLocal($tournament)))
        ->toBe('2026-05-16')
        ->and(ManualRoundRobinSchedule::dayDateIso(ManualRoundRobinSchedule::roundRobinDayTwoLocal($tournament)))
        ->toBe('2026-05-17');
});
