<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Pitch;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentRegistration;
use App\Support\SmallFixedRoundRobinDayOneSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * @deprecated Use {@see ExactTournamentOneScheduleSeeder} for the official tournament #1 sheet (Day 1 games 1–26 + Day 2).
 *
 * Seeds tournament #1 Day 1 Round Robin to match the official fixture list (games 1–24).
 *
 * Run: php artisan db:seed --class=RoundRobinDay1ScheduleSeeder
 *
 * Pairings use {@see TournamentRegistration} IDs — schedule labels like “TOOTHLESS” / “EMPOX” map to
 * persisted team names “TOOTHLESS ULTI” / “EMPOX ULTI”.
 *
 * Score preservation:
 * - If an existing row already has the same {@code home_registration_id} and {@code away_registration_id} as this sheet,
 *   scores and status are kept.
 * - If the pairing changes but old scores exist, scores/status are cleared so the row matches the official schedule
 *   (schedule correctness wins; see inline warnings).
 *
 * For a full wipe of Day 1 results before re-seeding, set {@see self::$clearDayOneResults} to true on this class
 * before running the seeder (or extend this class).
 */
class RoundRobinDay1ScheduleSeeder extends Seeder
{
    public const TOURNAMENT_ID = 5;

    /** When true, clears home_score, away_score and sets status scheduled for all Day 1 tracked games 1–24 before upsert. */
    public bool $clearDayOneResults = false;

    /**
     * Labels in the official PDF → canonical {@see Team::$name} / registration lookup keys (uppercase).
     *
     * @var array<string, string>
     */
    private const SCHEDULE_LABEL_TO_CANONICAL_TEAM_NAME = [
        'TOOTHLESS' => 'TOOTHLESS ULTI',
        'EMPOX' => 'EMPOX ULTI',
    ];

    /**
     * Official Day 1 sheet: round index 1–12, wall-clock slot, then Pitch 1 / Pitch 2 games.
     *
     * @return list<array{
     *     round: int,
     *     time_label: string,
     *     start: string,
     *     games: list<array{game: int, pitch_slot: int, home_label: string, away_label: string}>
     * }>
     */
    private static function officialRounds(): array
    {
        return [
            [
                'round' => 1,
                'time_label' => '07:00am – 07:40am',
                'start' => '07:00',
                'games' => [
                    ['game' => 1, 'pitch_slot' => 1, 'home_label' => 'BOMBANA', 'away_label' => 'UTI'],
                    ['game' => 2, 'pitch_slot' => 2, 'home_label' => 'ATSU', 'away_label' => 'RINGER'],
                ],
            ],
            [
                'round' => 2,
                'time_label' => '07:45am – 08:35am',
                'start' => '07:45',
                'games' => [
                    ['game' => 3, 'pitch_slot' => 1, 'home_label' => 'YOOY', 'away_label' => 'TOOTHLESS'],
                    ['game' => 4, 'pitch_slot' => 2, 'home_label' => 'SIGBIN', 'away_label' => 'CPU'],
                ],
            ],
            [
                'round' => 3,
                'time_label' => '08:40am – 09:25am',
                'start' => '08:40',
                'games' => [
                    ['game' => 5, 'pitch_slot' => 1, 'home_label' => 'EMPOX', 'away_label' => 'UTI'],
                    ['game' => 6, 'pitch_slot' => 2, 'home_label' => 'BOMBANA', 'away_label' => 'TOOTHLESS'],
                ],
            ],
            [
                'round' => 4,
                'time_label' => '09:30am – 10:15am',
                'start' => '09:30',
                'games' => [
                    ['game' => 7, 'pitch_slot' => 1, 'home_label' => 'ATSU', 'away_label' => 'CPU'],
                    ['game' => 8, 'pitch_slot' => 2, 'home_label' => 'YOOY', 'away_label' => 'SIGBIN'],
                ],
            ],
            [
                'round' => 5,
                'time_label' => '10:20am – 11:05am',
                'start' => '10:20',
                'games' => [
                    ['game' => 9, 'pitch_slot' => 1, 'home_label' => 'EMPOX', 'away_label' => 'RINGER'],
                    ['game' => 10, 'pitch_slot' => 2, 'home_label' => 'UTI', 'away_label' => 'TOOTHLESS'],
                ],
            ],
            [
                'round' => 6,
                'time_label' => '11:10am – 11:55am',
                'start' => '11:10',
                'games' => [
                    ['game' => 11, 'pitch_slot' => 1, 'home_label' => 'BOMBANA', 'away_label' => 'SIGBIN'],
                    ['game' => 12, 'pitch_slot' => 2, 'home_label' => 'ATSU', 'away_label' => 'YOOY'],
                ],
            ],
            [
                'round' => 7,
                'time_label' => '12:00nn – 12:45pm',
                'start' => '12:00',
                'games' => [
                    ['game' => 13, 'pitch_slot' => 1, 'home_label' => 'RINGER', 'away_label' => 'CPU'],
                    ['game' => 14, 'pitch_slot' => 2, 'home_label' => 'EMPOX', 'away_label' => 'TOOTHLESS'],
                ],
            ],
            [
                'round' => 8,
                'time_label' => '12:50pm – 01:35pm',
                'start' => '12:50',
                'games' => [
                    ['game' => 15, 'pitch_slot' => 1, 'home_label' => 'UTI', 'away_label' => 'SIGBIN'],
                    ['game' => 16, 'pitch_slot' => 2, 'home_label' => 'BOMBANA', 'away_label' => 'ATSU'],
                ],
            ],
            [
                'round' => 9,
                'time_label' => '01:40pm – 02:25pm',
                'start' => '13:40',
                'games' => [
                    ['game' => 17, 'pitch_slot' => 1, 'home_label' => 'EMPOX', 'away_label' => 'CPU'],
                    ['game' => 18, 'pitch_slot' => 2, 'home_label' => 'TOOTHLESS', 'away_label' => 'SIGBIN'],
                ],
            ],
            [
                'round' => 10,
                'time_label' => '02:30pm – 03:15pm',
                'start' => '14:30',
                'games' => [
                    ['game' => 19, 'pitch_slot' => 1, 'home_label' => 'RINGER', 'away_label' => 'YOOY'],
                    ['game' => 20, 'pitch_slot' => 2, 'home_label' => 'UTI', 'away_label' => 'ATSU'],
                ],
            ],
            [
                'round' => 11,
                'time_label' => '03:20pm – 04:05pm',
                'start' => '15:20',
                'games' => [
                    ['game' => 21, 'pitch_slot' => 1, 'home_label' => 'CPU', 'away_label' => 'YOOY'],
                    ['game' => 22, 'pitch_slot' => 2, 'home_label' => 'EMPOX', 'away_label' => 'SIGBIN'],
                ],
            ],
            [
                'round' => 12,
                'time_label' => '04:10pm – 04:55pm',
                'start' => '16:10',
                'games' => [
                    ['game' => 23, 'pitch_slot' => 1, 'home_label' => 'TOOTHLESS', 'away_label' => 'ATSU'],
                    ['game' => 24, 'pitch_slot' => 2, 'home_label' => 'RINGER', 'away_label' => 'BOMBANA'],
                ],
            ],
        ];
    }

    public function run(): void
    {
        $tournament = Tournament::query()
            ->with(['registrations.team', 'pitches'])
            ->find(self::TOURNAMENT_ID);

        if ($tournament === null) {
            $this->command?->warn('Tournament '.self::TOURNAMENT_ID.' not found — skipping RoundRobinDay1ScheduleSeeder.');

            return;
        }

        if ($this->clearDayOneResults) {
            $this->command?->warn('clearDayOneResults=true — wiping scores/status for Day 1 tracked games 1–24 before applying the official sheet.');
            TournamentMatch::query()
                ->where('tournament_id', self::TOURNAMENT_ID)
                ->where('stage', 'round_robin')
                ->where('notes', 'like', '%'.SmallFixedRoundRobinDayOneSchedule::MARKER_PREFIX.'%')
                ->whereBetween('match_number', [1, 24])
                ->update([
                    'status' => 'scheduled',
                    'home_score' => null,
                    'away_score' => null,
                ]);
        }

        $this->ensureTwoPitches($tournament);

        $registrationByCanonicalUpper = $this->registrationMapByCanonicalTeamNameUpper($tournament);

        $tournament->unsetRelation('pitches');
        $pitches = $tournament->pitches()->orderBy('sort_order')->orderBy('id')->get();
        $pitch1 = $pitches->get(0);
        $pitch2 = $pitches->get(1);

        if ($pitch1 === null || $pitch2 === null) {
            throw new RuntimeException('Two pitches are required for the Day 1 grid.');
        }

        $tz = SmallFixedRoundRobinDayOneSchedule::tournamentTimezone($tournament);
        $dateIso = SmallFixedRoundRobinDayOneSchedule::SCHEDULE_DATE_ISO;

        foreach (self::officialRounds() as $roundBlock) {
            $roundNum = $roundBlock['round'];
            $startTime = $roundBlock['start'];
            $scheduledAt = CarbonImmutable::parse($dateIso.' '.$startTime.':00', $tz);

            foreach ($roundBlock['games'] as $gameSpec) {
                $gameNumber = $gameSpec['game'];
                $pitchSlot = $gameSpec['pitch_slot'];
                $homeReg = $this->resolveRegistration($registrationByCanonicalUpper, $gameSpec['home_label']);
                $awayReg = $this->resolveRegistration($registrationByCanonicalUpper, $gameSpec['away_label']);

                $pitch = $pitchSlot === 1 ? $pitch1 : $pitch2;

                $criteria = [
                    'tournament_id' => self::TOURNAMENT_ID,
                    'match_number' => $gameNumber,
                    'stage' => 'round_robin',
                ];

                $existing = TournamentMatch::query()->where($criteria)->first();

                $samePairing = $existing !== null
                    && (int) $existing->home_registration_id === $homeReg->id
                    && (int) $existing->away_registration_id === $awayReg->id;

                $hadResults = $existing !== null && (
                    $existing->home_score !== null
                    || $existing->away_score !== null
                    || in_array((string) $existing->status, ['completed', 'live'], true)
                );

                $clearResultsForThisRow = false;
                if ($existing !== null && $hadResults && ! $samePairing) {
                    $clearResultsForThisRow = true;
                    $this->command?->warn("Game {$gameNumber}: pairing changed vs database — clearing scores/status to apply official schedule (was reg {$existing->home_registration_id} vs {$existing->away_registration_id}).");
                }

                $basePayload = [
                    'tournament_id' => self::TOURNAMENT_ID,
                    'pitch_id' => $pitch->id,
                    'pitch_assigned_by' => $existing?->pitch_assigned_by,
                    'home_registration_id' => $homeReg->id,
                    'away_registration_id' => $awayReg->id,
                    'stage' => 'round_robin',
                    'round_label' => 'Round '.$roundNum,
                    'match_number' => $gameNumber,
                    'scheduled_at' => $scheduledAt,
                    'notes' => SmallFixedRoundRobinDayOneSchedule::marker($gameNumber, $pitchSlot),
                ];

                if ($existing === null) {
                    $basePayload['status'] = 'scheduled';
                    $basePayload['home_score'] = null;
                    $basePayload['away_score'] = null;
                } elseif ($clearResultsForThisRow) {
                    $basePayload['status'] = 'scheduled';
                    $basePayload['home_score'] = null;
                    $basePayload['away_score'] = null;
                } else {
                    $basePayload['status'] = $existing->status;
                    $basePayload['home_score'] = $existing->home_score;
                    $basePayload['away_score'] = $existing->away_score;
                }

                TournamentMatch::query()->updateOrCreate($criteria, $basePayload);
            }
        }

        $this->validateAndPrintSchedule(self::TOURNAMENT_ID);

        $this->command?->info('RoundRobinDay1ScheduleSeeder finished for tournament '.self::TOURNAMENT_ID.'. Next: php artisan db:seed --class=TournamentGameRosterScoreSeeder (optional).');
    }

    /**
     * @return Collection<string, TournamentRegistration> uppercase canonical team name → registration
     */
    private function registrationMapByCanonicalTeamNameUpper(Tournament $tournament): Collection
    {
        $map = collect();

        foreach ($tournament->registrations as $registration) {
            $name = $registration->team?->name;
            if ($name === null || trim($name) === '') {
                continue;
            }
            $map[strtoupper(trim($name))] = $registration;
        }

        return $map;
    }

    private function resolveRegistration(Collection $registrationByCanonicalUpper, string $scheduleLabel): TournamentRegistration
    {
        $trimmed = strtoupper(trim($scheduleLabel));
        $canonicalName = self::SCHEDULE_LABEL_TO_CANONICAL_TEAM_NAME[$trimmed] ?? $trimmed;
        $key = strtoupper(trim($canonicalName));

        $registration = $registrationByCanonicalUpper->get($key);
        if ($registration !== null) {
            return $registration;
        }

        throw new RuntimeException(
            'Cannot resolve schedule label "'.$scheduleLabel.'" (canonical lookup "'.$key.'"). Ensure tournament '.self::TOURNAMENT_ID.' has that team registered.'
        );
    }

    private function ensureTwoPitches(Tournament $tournament): void
    {
        while ($tournament->pitches()->count() < 2) {
            $nextOrder = (int) ($tournament->pitches()->max('sort_order') ?? 0) + 1;

            Pitch::query()->create([
                'tournament_id' => $tournament->id,
                'name' => 'Pitch '.$nextOrder,
                'sort_order' => $nextOrder,
                'scorekeeper_user_id' => null,
                'is_active' => true,
            ]);
        }

        $tournament->unsetRelation('pitches');
    }

    private function validateAndPrintSchedule(int $tournamentId): void
    {
        $this->dedupeRoundRobinMatchesOneThroughTwentyFour($tournamentId);

        $marker = SmallFixedRoundRobinDayOneSchedule::MARKER_PREFIX;

        $matches = TournamentMatch::query()
            ->where('tournament_id', $tournamentId)
            ->where('stage', 'round_robin')
            ->whereBetween('match_number', [1, 24])
            ->where('notes', 'like', '%'.$marker.'%')
            ->with(['pitch', 'homeRegistration.team', 'awayRegistration.team'])
            ->orderBy('match_number')
            ->get();

        $this->command?->newLine();
        $this->command?->info('── Day 1 schedule (official sheet) ──');

        foreach ($matches as $match) {
            $home = $match->homeRegistration?->team?->name ?? '?';
            $away = $match->awayRegistration?->team?->name ?? '?';
            $pitchName = $match->pitch?->name ?? '—';
            $time = $match->scheduled_at?->format('Y-m-d H:i') ?? '—';

            $this->command?->line(sprintf(
                'Game %2d | %s vs %s | stage=%s | %s | pitch=%s | %s | home_reg=%s away_reg=%s',
                (int) $match->match_number,
                $home,
                $away,
                $match->stage,
                $match->round_label ?? '—',
                $pitchName,
                $time,
                (string) $match->home_registration_id,
                (string) $match->away_registration_id,
            ));
        }

        $count = $matches->count();
        if ($count !== 24) {
            throw new RuntimeException("Expected 24 Day 1 tracked matches for tournament {$tournamentId}; found {$count}.");
        }

        $dupNumbers = DB::table('matches')
            ->where('tournament_id', $tournamentId)
            ->where('stage', 'round_robin')
            ->whereBetween('match_number', [1, 24])
            ->selectRaw('match_number, COUNT(*) as aggregate')
            ->groupBy('match_number')
            ->having('aggregate', '>', 1)
            ->pluck('match_number');

        if ($dupNumbers->isNotEmpty()) {
            throw new RuntimeException('Duplicate match_number rows for round_robin games 1–24: '.$dupNumbers->implode(', '));
        }

        $game23 = $matches->firstWhere('match_number', 23);
        if ($game23 === null) {
            throw new RuntimeException('Game 23 missing.');
        }

        $home23 = strtoupper(trim((string) ($game23->homeRegistration?->team?->name ?? '')));
        $away23 = strtoupper(trim((string) ($game23->awayRegistration?->team?->name ?? '')));

        if ($home23 !== 'TOOTHLESS ULTI' || $away23 !== 'ATSU') {
            throw new RuntimeException("Game 23 must be TOOTHLESS ULTI (home) vs ATSU (away); got {$home23} vs {$away23}.");
        }

        $allowedRegIds = TournamentRegistration::query()
            ->where('tournament_id', $tournamentId)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        $allowedFlip = array_flip($allowedRegIds);

        foreach ($matches as $match) {
            $hid = (int) $match->home_registration_id;
            $aid = (int) $match->away_registration_id;
            if (! isset($allowedFlip[$hid], $allowedFlip[$aid])) {
                throw new RuntimeException("Game {$match->match_number}: invalid registration id(s) {$hid}, {$aid}.");
            }
        }

        $this->command?->info('Validation OK: 24 games, Game 23 = TOOTHLESS ULTI vs ATSU, no duplicate match_number, registration IDs in tournament.');
    }

    /**
     * Keep the lowest-id row per (tournament, round_robin, match_number 1–24); delete any extra duplicates.
     */
    private function dedupeRoundRobinMatchesOneThroughTwentyFour(int $tournamentId): void
    {
        foreach (range(1, 24) as $matchNumber) {
            $ids = TournamentMatch::query()
                ->where('tournament_id', $tournamentId)
                ->where('stage', 'round_robin')
                ->where('match_number', $matchNumber)
                ->orderBy('id')
                ->pluck('id');

            if ($ids->count() <= 1) {
                continue;
            }

            $keepId = (int) $ids->first();
            $deleteIds = $ids->slice(1)->values()->all();

            TournamentMatch::query()
                ->whereIn('id', $deleteIds)
                ->delete();

            $this->command?->warn('Removed '.count($deleteIds)." duplicate row(s) for tournament {$tournamentId} match_number {$matchNumber} (kept id {$keepId}).");
        }
    }
}
