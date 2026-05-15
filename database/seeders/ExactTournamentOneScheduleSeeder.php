<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Pitch;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentRegistration;
use App\Support\SmallDayTwoKnockoutBracket;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Official tournament #1 schedule (Day 1 May 16 + Day 2 May 17, 2026).
 *
 * Replaces legacy {@see RoundRobinDay1ScheduleSeeder} / {@see RoundRobinDayTwoScheduleSeeder} fixture data.
 *
 * Run: php artisan db:seed --class=ExactTournamentOneScheduleSeeder
 */
final class ExactTournamentOneScheduleSeeder extends Seeder
{
    public const TOURNAMENT_ID = 4;

    public const DAY_ONE_DATE = '2026-05-16';

    public const DAY_TWO_DATE = '2026-05-17';

    public const MARKER_PREFIX = '[[exact-tournament-1:v1:';

    /** When true, clears scores/status on rows whose pairing changes. */
    public bool $clearResultsOnPairingChange = false;

    /**
     * Schedule label → persisted {@see Team::$name}.
     *
     * @var array<string, string>
     */
    private const LABEL_TO_TEAM_NAME = [
        'TOOTHLESS' => 'TOOTHLESS ULTI',
        'EMPOX' => 'EMPOX ULTI',
    ];

    public function run(): void
    {
        $tournament = Tournament::query()
            ->with(['registrations.team', 'pitches'])
            ->find(self::TOURNAMENT_ID);

        if ($tournament === null) {
            $this->command?->error('Tournament '.self::TOURNAMENT_ID.' not found.');

            return;
        }

        $this->normalizeTournamentTimezone($tournament);
        $this->ensureTwoPitches($tournament);

        $registrationByKey = $this->registrationMap($tournament);
        $tz = $this->timezone($tournament);

        $tournament->unsetRelation('pitches');
        $pitches = $tournament->pitches()->orderBy('sort_order')->orderBy('id')->get();
        $pitch1 = $pitches->get(0);
        $pitch2 = $pitches->get(1);

        if ($pitch1 === null || $pitch2 === null) {
            throw new RuntimeException('Pitch 1 and Pitch 2 are required.');
        }

        $this->renamePitches($pitch1, $pitch2);

        $stats = ['created' => 0, 'updated' => 0, 'skipped_awarding' => 0];

        DB::transaction(function () use ($tournament, $registrationByKey, $tz, $pitch1, $pitch2, &$stats): void {
            foreach (self::fixtureRows() as $row) {
                if (($row['skip'] ?? false) === true) {
                    $stats['skipped_awarding']++;

                    continue;
                }

                [$startAt, $endAt] = $this->parseSlot($row['date'], $row['start'], $row['end'], $tz);

                foreach ($row['games'] as $game) {
                    if (($game['skip'] ?? false) === true) {
                        continue;
                    }

                    $gameNumber = (int) $game['number'];
                    $pitch = ($game['pitch_slot'] ?? 1) === 2 ? $pitch2 : $pitch1;
                    $stage = (string) $game['stage'];
                    $roundLabel = (string) $game['round_label'];

                    [$homeRegId, $awayRegId, $placeholderNotes] = $this->resolveSides(
                        $game,
                        $registrationByKey,
                    );

                    $this->upsertMatch(
                        $tournament,
                        $gameNumber,
                        $stage,
                        $roundLabel,
                        $pitch,
                        $startAt,
                        $endAt,
                        $homeRegId,
                        $awayRegId,
                        $placeholderNotes,
                        (int) ($game['pitch_slot'] ?? 1),
                        $stats,
                    );
                }
            }
        });

        $this->dedupeByMatchNumber(self::TOURNAMENT_ID, range(1, 48));

        $this->command?->info(sprintf(
            'ExactTournamentOneScheduleSeeder finished (created/updated via updateOrCreate: %d touched, %d awarding rows skipped).',
            $stats['created'] + $stats['updated'],
            $stats['skipped_awarding'],
        ));

        $this->printSummary(self::TOURNAMENT_ID, $tz);
    }

    /**
     * @return list<array{
     *     date: string,
     *     start: string,
     *     end: string,
     *     skip?: bool,
     *     games: list<array{
     *         number: int,
     *         pitch_slot: int,
     *         stage: string,
     *         round_label: string,
     *         home: string,
     *         away: string,
     *         placeholder?: bool,
     *         skip?: bool,
     *     }>
     * }>
     */
    private static function fixtureRows(): array
    {
        $d1 = self::DAY_ONE_DATE;
        $d2 = self::DAY_TWO_DATE;
        $rr = 'round_robin';
        $qf = 'quarterfinal';
        $placement = 'placement';
        $sf = 'semifinal';
        $final = 'championship';

        return [
            // ── Day 1 (games 1–26) ─────────────────────────────────────────────
            self::row($d1, '07:00', '07:40', $rr, 'Round 1', [
                [1, 1, 'BOMBANA', 'UTI'],
                [2, 2, 'ATSU', 'RINGER'],
            ]),
            self::row($d1, '07:45', '08:25', $rr, 'Round 2', [
                [3, 1, 'YOOY', 'TOOTHLESS'],
                [4, 2, 'SIGBIN', 'CPU'],
            ]),
            self::row($d1, '08:30', '09:10', $rr, 'Round 3', [
                [5, 1, 'EMPOX', 'UTI'],
                [6, 2, 'BOMBANA', 'TOOTHLESS'],
            ]),
            self::row($d1, '09:15', '09:55', $rr, 'Round 4', [
                [7, 1, 'ATSU', 'CPU'],
                [8, 2, 'YOOY', 'SIGBIN'],
            ]),
            self::row($d1, '10:00', '10:40', $rr, 'Round 5', [
                [9, 1, 'EMPOX', 'RINGER'],
                [10, 2, 'UTI', 'TOOTHLESS'],
            ]),
            self::row($d1, '10:45', '11:25', $rr, 'Round 6', [
                [11, 1, 'BOMBANA', 'SIGBIN'],
                [12, 2, 'ATSU', 'YOOY'],
            ]),
            self::row($d1, '11:30', '12:10', $rr, 'Round 7', [
                [13, 1, 'RINGER', 'CPU'],
                [14, 2, 'EMPOX', 'TOOTHLESS'],
            ]),
            self::row($d1, '12:15', '12:55', $rr, 'Round 8', [
                [15, 1, 'UTI', 'SIGBIN'],
                [16, 2, 'BOMBANA', 'ATSU'],
            ]),
            self::row($d1, '13:00', '13:40', $rr, 'Round 9', [
                [17, 1, 'EMPOX', 'CPU'],
                [18, 2, 'TOOTHLESS', 'SIGBIN'],
            ]),
            self::row($d1, '13:45', '14:25', $rr, 'Round 10', [
                [19, 1, 'RINGER', 'YOOY'],
                [20, 2, 'UTI', 'ATSU'],
            ]),
            self::row($d1, '14:30', '15:10', $rr, 'Round 11', [
                [21, 1, 'CPU', 'YOOY'],
                [22, 2, 'EMPOX', 'SIGBIN'],
            ]),
            self::row($d1, '15:15', '15:55', $rr, 'Round 12', [
                [23, 1, 'TOOTHLESS', 'ATSU'],
                [24, 2, 'RINGER', 'BOMBANA'],
            ]),
            self::row($d1, '16:00', '16:40', $rr, 'Round 13', [
                [25, 1, 'SIGBIN', 'ATSU'],
                [26, 2, 'EMPOX', 'YOOY'],
            ]),

            // ── Day 2 round robin (games 27–36) ────────────────────────────────
            // Row 1 pitch 2 uses game 28 (screenshot listed 26, but 26 is already Day 1 game 26).
            self::row($d2, '07:00', '07:40', $rr, 'Round 14', [
                [27, 1, 'CPU', 'BOMBANA'],
                [28, 2, 'RINGER', 'UTI'],
            ]),
            self::row($d2, '07:45', '08:25', $rr, 'Round 15', [
                [29, 1, 'EMPOX', 'ATSU'],
                [30, 2, 'YOOY', 'BOMBANA'],
            ]),
            self::row($d2, '08:30', '09:10', $rr, 'Round 16', [
                [31, 1, 'CPU', 'UTI'],
                [32, 2, 'TOOTHLESS', 'RINGER'],
            ]),
            self::row($d2, '09:15', '09:55', $rr, 'Round 17', [
                [33, 1, 'YOOY', 'UTI'],
                [34, 2, 'EMPOX', 'BOMBANA'],
            ]),
            self::row($d2, '10:00', '10:40', $rr, 'Round 18', [
                [35, 1, 'SIGBIN', 'RINGER'],
                [36, 2, 'CPU', 'TOOTHLESS'],
            ]),

            // ── Day 2 bracket (games 37–48) ────────────────────────────────────
            self::row($d2, '10:55', '11:35', $qf, 'Quarterfinals 19', [
                [37, 1, 'Rank 1', 'Rank 8', true],
                [38, 2, 'Rank 2', 'Rank 7', true],
            ]),
            self::row($d2, '11:40', '12:20', $qf, 'Quarterfinals 20', [
                [39, 1, 'Rank 3', 'Rank 6', true],
                [40, 2, 'Rank 4', 'Rank 5', true],
            ]),
            self::row($d2, '12:25', '13:05', $placement, 'Ranking 21', [
                [41, 1, 'L37', 'L40', true],
                [42, 2, 'L38', 'L39', true],
            ]),
            self::row($d2, '13:10', '13:50', $sf, 'Semis', [
                [43, 1, 'W37', 'W40', true],
                [44, 2, 'W38', 'W39', true],
            ]),
            self::row($d2, '13:55', '14:35', $placement, 'Ranking 5/6 & 7/8', [
                [45, 1, 'W41', 'W42', true],
                [46, 2, 'L41', 'L42', true],
            ]),
            self::row($d2, '14:40', '15:40', $placement, 'Ranking 3/4', [
                ['number' => 47, 'pitch_slot' => 2, 'stage' => $placement, 'round_label' => 'Ranking 3/4', 'home' => 'L43', 'away' => 'L44', 'placeholder' => true],
            ]),
            self::row($d2, '15:00', '17:00', $final, 'Championship', [
                [48, 1, 'W43', 'W44', true],
            ]),

            // Awarding — no match row
            [
                'date' => $d2,
                'start' => '17:00',
                'end' => '17:00',
                'skip' => true,
                'games' => [],
            ],
        ];
    }

    /**
     * @param  list<array{0: int, 1: int, 2: string, 3: string}|array<string, mixed>>  $games
     * @return array<string, mixed>
     */
    private static function row(string $date, string $start, string $end, string $stage, string $roundLabel, array $games): array
    {
        $normalized = [];

        foreach ($games as $game) {
            if (isset($game['number'])) {
                $normalized[] = $game;

                continue;
            }

            $normalized[] = [
                'number' => $game[0],
                'pitch_slot' => $game[1],
                'stage' => $stage,
                'round_label' => $roundLabel,
                'home' => $game[2],
                'away' => $game[3],
                'placeholder' => $game[4] ?? false,
            ];
        }

        return [
            'date' => $date,
            'start' => $start,
            'end' => $end,
            'games' => $normalized,
        ];
    }

    /**
     * @param  array<string, mixed>  $game
     * @return array{0: ?int, 1: ?int, 2: string}
     */
    private function resolveSides(array $game, Collection $registrationByKey): array
    {
        if (($game['placeholder'] ?? false) === true) {
            $home = (string) $game['home'];
            $away = (string) $game['away'];

            return [null, null, self::placeholderNotes($home, $away)];
        }

        $homeReg = $this->resolveRegistration($registrationByKey, (string) $game['home']);
        $awayReg = $this->resolveRegistration($registrationByKey, (string) $game['away']);

        return [$homeReg->id, $awayReg->id, ''];
    }

    private static function placeholderNotes(string $home, string $away): string
    {
        return self::MARKER_PREFIX.'placeholder]]'
            .'[[home_placeholder='.$home.']]'
            .'[[away_placeholder='.$away.']]';
    }

    /**
     * @param  array{created: int, updated: int}  $stats
     */
    private function upsertMatch(
        Tournament $tournament,
        int $gameNumber,
        string $stage,
        string $roundLabel,
        Pitch $pitch,
        CarbonImmutable $startAt,
        CarbonImmutable $endAt,
        ?int $homeRegId,
        ?int $awayRegId,
        string $placeholderNotes,
        int $pitchSlot,
        array &$stats,
    ): void {
        $criteria = [
            'tournament_id' => self::TOURNAMENT_ID,
            'match_number' => $gameNumber,
        ];

        $existing = TournamentMatch::query()->where($criteria)->first();

        $samePairing = $existing !== null
            && (int) ($existing->home_registration_id ?? 0) === (int) ($homeRegId ?? 0)
            && (int) ($existing->away_registration_id ?? 0) === (int) ($awayRegId ?? 0);

        $hadResults = $existing !== null && (
            $existing->home_score !== null
            || $existing->away_score !== null
            || in_array((string) $existing->status, ['completed', 'live'], true)
        );

        $clearResults = $this->clearResultsOnPairingChange && $existing !== null && $hadResults && ! $samePairing;

        $notes = $placeholderNotes !== ''
            ? $placeholderNotes
            : self::marker($gameNumber, $pitchSlot);

        if ($gameNumber >= SmallDayTwoKnockoutBracket::FIRST_GAME_NUMBER) {
            $knockoutMarker = SmallDayTwoKnockoutBracket::marker($gameNumber);
            if (! str_contains($notes, $knockoutMarker)) {
                $notes = $knockoutMarker.' '.$notes;
            }
        }

        $payload = [
            'tournament_id' => self::TOURNAMENT_ID,
            'pitch_id' => $pitch->id,
            'pitch_assigned_by' => $existing?->pitch_assigned_by,
            'home_registration_id' => $homeRegId,
            'away_registration_id' => $awayRegId,
            'stage' => $stage,
            'round_label' => $roundLabel,
            'match_number' => $gameNumber,
            'scheduled_at' => $startAt->utc(),
            'scheduled_ends_at' => $endAt->utc(),
            'notes' => trim($notes),
        ];

        if ($existing === null) {
            $payload['status'] = 'scheduled';
            $payload['home_score'] = null;
            $payload['away_score'] = null;
            $stats['created']++;
        } elseif ($clearResults) {
            $payload['status'] = 'scheduled';
            $payload['home_score'] = null;
            $payload['away_score'] = null;
            $stats['updated']++;
            $this->command?->warn("Game {$gameNumber}: pairing changed — cleared scores/status.");
        } else {
            $payload['status'] = $existing->status;
            $payload['home_score'] = $existing->home_score;
            $payload['away_score'] = $existing->away_score;
            $stats['updated']++;
        }

        TournamentMatch::query()->updateOrCreate($criteria, $payload);
    }

    public static function marker(int $gameNumber, int $pitchSlot): string
    {
        return self::MARKER_PREFIX.'g='.$gameNumber.':p='.$pitchSlot.']]';
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function parseSlot(string $dateIso, string $start, string $end, string $tz): array
    {
        $startAt = CarbonImmutable::parse($dateIso.' '.$start.':00', $tz);
        $endAt = CarbonImmutable::parse($dateIso.' '.$end.':00', $tz);

        if ($endAt->lessThanOrEqualTo($startAt)) {
            $endAt = $endAt->addDay();
        }

        return [$startAt, $endAt];
    }

    private function timezone(Tournament $tournament): string
    {
        $tz = trim((string) ($tournament->timezone ?? ''));

        if ($tz === '' || $tz === 'UTC+8' || $tz === 'UTC +8') {
            return 'Asia/Manila';
        }

        return $tz;
    }

    private function normalizeTournamentTimezone(Tournament $tournament): void
    {
        $normalized = $this->timezone($tournament);

        if ((string) ($tournament->timezone ?? '') !== $normalized) {
            $tournament->forceFill(['timezone' => $normalized])->save();
            $this->command?->info('Tournament timezone set to '.$normalized.'.');
        }
    }

    /**
     * @return Collection<string, TournamentRegistration>
     */
    private function registrationMap(Tournament $tournament): Collection
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

    private function resolveRegistration(Collection $registrationByKey, string $label): TournamentRegistration
    {
        $trimmed = strtoupper(trim($label));
        $canonical = self::LABEL_TO_TEAM_NAME[$trimmed] ?? $trimmed;
        $registration = $registrationByKey->get(strtoupper($canonical));

        if ($registration !== null) {
            return $registration;
        }

        throw new RuntimeException(
            'Cannot resolve team label "'.$label.'" for tournament '.self::TOURNAMENT_ID.'.'
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

    private function renamePitches(Pitch $pitch1, Pitch $pitch2): void
    {
        if ($pitch1->name !== 'Pitch 1') {
            $pitch1->forceFill(['name' => 'Pitch 1', 'sort_order' => 1])->save();
        }

        if ($pitch2->name !== 'Pitch 2') {
            $pitch2->forceFill(['name' => 'Pitch 2', 'sort_order' => 2])->save();
        }
    }

    /**
     * @param  list<int>  $matchNumbers
     */
    private function dedupeByMatchNumber(int $tournamentId, array $matchNumbers): void
    {
        foreach ($matchNumbers as $matchNumber) {
            $ids = TournamentMatch::query()
                ->where('tournament_id', $tournamentId)
                ->where('match_number', $matchNumber)
                ->orderBy('id')
                ->pluck('id');

            if ($ids->count() <= 1) {
                continue;
            }

            $keepId = (int) $ids->first();
            $deleteIds = $ids->slice(1)->values()->all();

            TournamentMatch::query()->whereIn('id', $deleteIds)->delete();

            $this->command?->warn('Removed '.count($deleteIds)." duplicate row(s) for match_number {$matchNumber} (kept id {$keepId}).");
        }
    }

    private function printSummary(int $tournamentId, string $tz): void
    {
        $matches = TournamentMatch::query()
            ->where('tournament_id', $tournamentId)
            ->whereBetween('match_number', [1, 48])
            ->with(['pitch', 'homeRegistration.team', 'awayRegistration.team'])
            ->orderBy('match_number')
            ->orderBy('id')
            ->get();

        $this->command?->newLine();
        $this->command?->info('── Tournament #1 schedule (games 1–48) ──');

        foreach ($matches as $match) {
            $home = $match->homeRegistration?->team?->name;
            $away = $match->awayRegistration?->team?->name;

            if ($home === null && preg_match('/\[\[home_placeholder=([^\]]+)\]\]/', (string) $match->notes, $m)) {
                $home = $m[1];
            }
            if ($away === null && preg_match('/\[\[away_placeholder=([^\]]+)\]\]/', (string) $match->notes, $m)) {
                $away = $m[1];
            }

            $start = $match->scheduled_at?->timezone($tz)->format('Y-m-d g:i A') ?? '—';
            $end = $match->scheduled_ends_at?->timezone($tz)->format('g:i A') ?? '—';

            $this->command?->line(sprintf(
                'G%2d | %s vs %s | %s | %s–%s | %s | %s',
                (int) $match->match_number,
                $home ?? 'TBD',
                $away ?? 'TBD',
                $match->stage,
                $start,
                $end,
                $match->pitch?->name ?? '—',
                $match->round_label ?? '—',
            ));
        }

        $count = $matches->unique('match_number')->count();
        $this->command?->info("Unique match numbers seeded: {$count} / 48.");
    }
}
