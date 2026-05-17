<?php

namespace Database\Seeders;

use App\Models\Tournament;
use App\Support\SmallFixedRoundRobinDayTwoSchedule;
use Illuminate\Database\Seeder;
use InvalidArgumentException;

/**
 * @deprecated Use {@see ExactTournamentOneScheduleSeeder} for the official tournament #1 sheet (Day 1 + Day 2 + bracket).
 *
 * Creates Round Robin Day 2 fixtures (games continue after Day 1’s first 24 Berger slots; typically 25–36 for nine teams)
 * for tournament 1 on May 17, 2026.
 *
 * Idempotent: re-running upserts existing tracked Day 2 rows (matched by `match_number` + Day 2 marker)
 * instead of duplicating them. Scores and status on already-played Day 2 games are preserved.
 *
 * Run: php artisan db:seed --class=RoundRobinDayTwoScheduleSeeder
 */
class RoundRobinDayTwoScheduleSeeder extends Seeder
{
    public const TOURNAMENT_ID = 9;
    public function run(): void
    {
        $tournament = Tournament::query()->find(self::TOURNAMENT_ID);

        if ($tournament === null) {
            $this->command?->warn('Tournament ID '.self::TOURNAMENT_ID.' not found; skipping Day 2 schedule seeding.');

            return;
        }

        try {
            SmallFixedRoundRobinDayTwoSchedule::sync($tournament);
        } catch (InvalidArgumentException $exception) {
            $this->command?->warn('Day 2 schedule sync skipped: '.$exception->getMessage());

            return;
        }

        $tracked = $tournament->matches()
            ->where('stage', 'round_robin')
            ->where('notes', 'like', '%'.SmallFixedRoundRobinDayTwoSchedule::MARKER_PREFIX.'%')
            ->count();

        $this->command?->info('Round Robin Day 2 schedule synced for tournament '.self::TOURNAMENT_ID.'. Tracked Day 2 games: '.$tracked.'.');
    }
}
