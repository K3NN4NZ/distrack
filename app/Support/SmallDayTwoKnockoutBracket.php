<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Controllers\Admin\TournamentController;
use App\Models\Pitch;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Fixed Day 2 knockout bracket (games 37–48) for tournaments below {@see TournamentController::MINIMUM_BRACKET_TEAM_COUNT}.
 *
 * Rows are identified by {@see self::MARKER_PREFIX} in `matches.notes` and `match_number` 37–48.
 * Quarter Final teams are filled from final {@see SmallTournamentTeamStanding} order (ranks 1–8) only when
 * round robin is complete; downstream slots propagate from completed source games.
 */
final class SmallDayTwoKnockoutBracket
{
    public const MARKER_PREFIX = '[[small-day2-knockout:v1:';

    public const FIRST_GAME_NUMBER = 37;

    public const LAST_GAME_NUMBER = 48;

    public const SCHEDULE_DATE_ISO = '2026-05-17';

    /**
     * Alternate {@code matches.stage} values seen for Quarter Final rows (37–40).
     *
     * @return list<string>
     */
    public static function quarterFinalStageAliases(): array
    {
        return ['quarterfinal', 'quarter-final', 'quarter_final', 'qf'];
    }

    /**
     * Stages for Ranking Path and downstream ladder rows (41+) when notes lack the bracket marker.
     *
     * @return list<string>
     */
    public static function rankingPathStageAliases(): array
    {
        return ['placement', 'ranking-path', 'ranking_path'];
    }

    /**
     * Alternate {@code matches.stage} values for Semi Final rows (games 43–44).
     *
     * @return list<string>
     */
    public static function semiFinalStageAliases(): array
    {
        return ['semifinal', 'semi-final', 'semi_final', 'sf', 'semis'];
    }

    /**
     * Alternate {@code matches.stage} values for the championship row (game 48).
     *
     * @return list<string>
     */
    public static function championshipStageAliases(): array
    {
        return ['championship', 'final', 'finals'];
    }

    public static function marker(int $gameNumber): string
    {
        return self::MARKER_PREFIX.'g='.$gameNumber.']]';
    }

    public static function isBracketMatch(TournamentMatch $match): bool
    {
        return str_contains((string) ($match->notes ?? ''), self::MARKER_PREFIX);
    }

    /**
     * Day 2 ladder rows for small tournaments (games 37–48): marker in {@code notes}, or legacy rows resolved by
     * {@code match_number} plus quarter-final / placement / semi / championship stage aliases.
     */
    public static function isSmallDayTwoKnockoutScheduleRow(TournamentMatch $match): bool
    {
        if (self::isBracketMatch($match)) {
            return true;
        }

        $n = (int) ($match->match_number ?? 0);
        if ($n < self::FIRST_GAME_NUMBER || $n > self::LAST_GAME_NUMBER) {
            return false;
        }

        $stage = (string) $match->stage;

        if ($n <= 40 && in_array($stage, self::quarterFinalStageAliases(), true)) {
            return true;
        }

        if ($n >= 41 && in_array($stage, self::rankingPathStageAliases(), true)) {
            return true;
        }

        return in_array($stage, self::semiFinalStageAliases(), true)
            || in_array($stage, self::championshipStageAliases(), true);
    }

    /**
     * @return array<int, array{
     *     section: string,
     *     stage: string,
     *     round_label: string,
     *     time_label: string,
     *     scheduled_iso: string,
     *     pitch_slot: int,
     *     home_placeholder: string,
     *     away_placeholder: string,
     *     home_rank: int|null,
     *     away_rank: int|null,
     *     schedule_row_label?: string|null,
     * }>
     */
    public static function gameDefinitions(): array
    {
        return [
            37 => [
                'section' => 'quarter_finals',
                'stage' => 'quarterfinal',
                'round_label' => __('Quarter Final · Game 37'),
                'time_label' => '11:40am – 12:20pm',
                'scheduled_iso' => self::SCHEDULE_DATE_ISO.' 11:40:00',
                'pitch_slot' => 1,
                'home_placeholder' => __('Rank 1'),
                'away_placeholder' => __('Rank 8'),
                'home_rank' => 1,
                'away_rank' => 8,
                'schedule_row_label' => __('Quarter finals 19'),
            ],
            38 => [
                'section' => 'quarter_finals',
                'stage' => 'quarterfinal',
                'round_label' => __('Quarter Final · Game 38'),
                'time_label' => '11:40am – 12:20pm',
                'scheduled_iso' => self::SCHEDULE_DATE_ISO.' 11:40:00',
                'pitch_slot' => 2,
                'home_placeholder' => __('Rank 2'),
                'away_placeholder' => __('Rank 7'),
                'home_rank' => 2,
                'away_rank' => 7,
                'schedule_row_label' => __('Quarter finals 19'),
            ],
            39 => [
                'section' => 'quarter_finals',
                'stage' => 'quarterfinal',
                'round_label' => __('Quarter Final · Game 39'),
                'time_label' => '12:05pm – 12:45pm',
                'scheduled_iso' => self::SCHEDULE_DATE_ISO.' 12:05:00',
                'pitch_slot' => 1,
                'home_placeholder' => __('Rank 3'),
                'away_placeholder' => __('Rank 6'),
                'home_rank' => 3,
                'away_rank' => 6,
                'schedule_row_label' => __('Quarterfinals 20'),
            ],
            40 => [
                'section' => 'quarter_finals',
                'stage' => 'quarterfinal',
                'round_label' => __('Quarter Final · Game 40'),
                'time_label' => '12:05pm – 12:45pm',
                'scheduled_iso' => self::SCHEDULE_DATE_ISO.' 12:05:00',
                'pitch_slot' => 2,
                'home_placeholder' => __('Rank 4'),
                'away_placeholder' => __('Rank 5'),
                'home_rank' => 4,
                'away_rank' => 5,
                'schedule_row_label' => __('Quarterfinals 20'),
            ],
            41 => [
                'section' => 'ranking_path',
                // Persist as `placement` so bracket rows stay one canonical stage (matches downstream 45–46).
                'stage' => 'placement',
                'round_label' => __('Ranking Path · Game 41'),
                'time_label' => '12:50pm – 01:30pm',
                'scheduled_iso' => self::SCHEDULE_DATE_ISO.' 12:50:00',
                'pitch_slot' => 1,
                'home_placeholder' => __('L37'),
                'away_placeholder' => __('L40'),
                'home_rank' => null,
                'away_rank' => null,
                // Placement round label only — not team rank 21.
                'schedule_row_label' => __('Ranking 21'),
            ],
            42 => [
                'section' => 'ranking_path',
                'stage' => 'placement',
                'round_label' => __('Ranking Path · Game 42'),
                'time_label' => '12:50pm – 01:30pm',
                'scheduled_iso' => self::SCHEDULE_DATE_ISO.' 12:50:00',
                'pitch_slot' => 2,
                'home_placeholder' => __('L38'),
                'away_placeholder' => __('L39'),
                'home_rank' => null,
                'away_rank' => null,
                'schedule_row_label' => __('Ranking 21'),
            ],
            43 => [
                'section' => 'semi_finals',
                'stage' => 'semifinal',
                'round_label' => __('Semi Final · Game 43'),
                'time_label' => '01:35pm – 02:15pm',
                'scheduled_iso' => self::SCHEDULE_DATE_ISO.' 13:35:00',
                'pitch_slot' => 1,
                'home_placeholder' => __('W37'),
                'away_placeholder' => __('W40'),
                'home_rank' => null,
                'away_rank' => null,
                'schedule_row_label' => __('Semis'),
            ],
            44 => [
                'section' => 'semi_finals',
                'stage' => 'semifinal',
                'round_label' => __('Semi Final · Game 44'),
                'time_label' => '01:35pm – 02:15pm',
                'scheduled_iso' => self::SCHEDULE_DATE_ISO.' 13:35:00',
                'pitch_slot' => 2,
                'home_placeholder' => __('W38'),
                'away_placeholder' => __('W39'),
                'home_rank' => null,
                'away_rank' => null,
                'schedule_row_label' => __('Semis'),
            ],
            45 => [
                'section' => 'ranking_56_78',
                'stage' => 'placement',
                'round_label' => __('Ranking 5–8 · Game 45'),
                'time_label' => '02:20pm – 03:00pm',
                'scheduled_iso' => self::SCHEDULE_DATE_ISO.' 14:20:00',
                'pitch_slot' => 1,
                'home_placeholder' => __('W41'),
                'away_placeholder' => __('W42'),
                'home_rank' => null,
                'away_rank' => null,
                'schedule_row_label' => __('Ranking 5/6 & 7/8'),
            ],
            46 => [
                'section' => 'ranking_56_78',
                'stage' => 'placement',
                'round_label' => __('Ranking 7–8 · Game 46'),
                'time_label' => '02:20pm – 03:00pm',
                'scheduled_iso' => self::SCHEDULE_DATE_ISO.' 14:20:00',
                'pitch_slot' => 2,
                'home_placeholder' => __('L41'),
                'away_placeholder' => __('L42'),
                'home_rank' => null,
                'away_rank' => null,
                'schedule_row_label' => __('Ranking 5/6 & 7/8'),
            ],
            47 => [
                'section' => 'ranking_34',
                'stage' => 'placement',
                'round_label' => __('Ranking 3–4 · Game 47'),
                'time_label' => '03:05pm – 04:05pm',
                'scheduled_iso' => self::SCHEDULE_DATE_ISO.' 15:05:00',
                'pitch_slot' => 1,
                'home_placeholder' => __('L43'),
                'away_placeholder' => __('L44'),
                'home_rank' => null,
                'away_rank' => null,
                'schedule_row_label' => __('Ranking 3/4'),
            ],
            48 => [
                'section' => 'championship',
                'stage' => 'championship',
                'round_label' => __('Championship'),
                'time_label' => '03:20pm – 05:20pm',
                'scheduled_iso' => self::SCHEDULE_DATE_ISO.' 15:20:00',
                'pitch_slot' => 2,
                'home_placeholder' => __('W43'),
                'away_placeholder' => __('W44'),
                'home_rank' => null,
                'away_rank' => null,
            ],
        ];
    }

    /**
     * Quarter final rows for the Day 2 schedule (games 37–40): two simultaneous pitches per row.
     *
     * @return list<array{label: string, time_label: string, game_numbers: list<int>}>
     */
    public static function quarterFinalScheduleRowGroups(): array
    {
        $defs = self::gameDefinitions();
        $groups = [];

        foreach ([37, 38, 39, 40] as $gameNumber) {
            $def = $defs[$gameNumber] ?? null;
            $label = $def['schedule_row_label'] ?? null;
            if (! is_string($label) || $label === '') {
                continue;
            }

            if (! isset($groups[$label])) {
                $groups[$label] = [
                    'label' => $label,
                    'time_label' => (string) ($def['time_label'] ?? ''),
                    'game_numbers' => [],
                ];
            }

            $groups[$label]['game_numbers'][] = $gameNumber;
        }

        return array_values($groups);
    }

    /**
     * Semi final rows for the Day 2 schedule (games 43–44): two simultaneous pitches, band label {@code Semis}.
     *
     * @return list<array{label: string, time_label: string, game_numbers: list<int>}>
     */
    public static function semiFinalScheduleRowGroups(): array
    {
        return self::scheduleRowGroupsFromDefinitions([43, 44]);
    }

    /**
     * Ranking 5/6 & 7/8 band (games 45–46).
     *
     * @return list<array{label: string, time_label: string, game_numbers: list<int>}>
     */
    public static function rankingFiveEightScheduleRowGroups(): array
    {
        return self::scheduleRowGroupsFromDefinitions([45, 46]);
    }

    /**
     * Ranking 3/4 band (game 47).
     *
     * @return list<array{label: string, time_label: string, game_numbers: list<int>}>
     */
    public static function rankingThreeFourScheduleRowGroups(): array
    {
        return self::scheduleRowGroupsFromDefinitions([47]);
    }

    /**
     * @param  list<int>  $gameNumbers
     * @return list<array{label: string, time_label: string, game_numbers: list<int>}>
     */
    private static function scheduleRowGroupsFromDefinitions(array $gameNumbers): array
    {
        $defs = self::gameDefinitions();
        $groups = [];

        foreach ($gameNumbers as $gameNumber) {
            $def = $defs[$gameNumber] ?? null;
            $label = $def['schedule_row_label'] ?? null;
            if (! is_string($label) || $label === '') {
                continue;
            }

            if (! isset($groups[$label])) {
                $groups[$label] = [
                    'label' => $label,
                    'time_label' => (string) ($def['time_label'] ?? ''),
                    'game_numbers' => [],
                ];
            }

            $groups[$label]['game_numbers'][] = $gameNumber;
        }

        return array_values($groups);
    }

    /**
     * Ranking Path schedule row (games 41–42): one labelled band, two pitches (sources: L37/L40, L38/L39).
     *
     * @return list<array{label: string, time_label: string, game_numbers: list<int>}>
     */
    public static function rankingPathScheduleRowGroups(): array
    {
        $defs = self::gameDefinitions();
        $groups = [];

        foreach ([41, 42] as $gameNumber) {
            $def = $defs[$gameNumber] ?? null;
            $label = $def['schedule_row_label'] ?? null;
            if (! is_string($label) || $label === '') {
                continue;
            }

            if (! isset($groups[$label])) {
                $groups[$label] = [
                    'label' => $label,
                    'time_label' => (string) ($def['time_label'] ?? ''),
                    'game_numbers' => [],
                ];
            }

            $groups[$label]['game_numbers'][] = $gameNumber;
        }

        return array_values($groups);
    }

    /**
     * True when Games 37–40 exist as bracket matches and each has a decisive scoreline
     * (so losers exist for Ranking Path 41–42). Aligns with {@see winnerRegistrationId}/{@see loserRegistrationId}
     * ({@code completed} or {@code live} with both scores set — same idea as a finished quarter final).
     */
    public static function quarterFinalsThrough40Decided(Tournament $tournament): bool
    {
        $byNum = self::bracketRowByGameNumber($tournament);

        foreach ([37, 38, 39, 40] as $n) {
            $match = $byNum[$n] ?? null;
            if ($match === null || ! self::matchHasDecisiveWinner($match)) {
                return false;
            }
        }

        return true;
    }

    /**
     * True when Ranking Path games 41–42 both have a decisive scoreline (winners and losers exist for 45–46).
     */
    public static function rankingPath41Through42Decided(Tournament $tournament): bool
    {
        $byNum = self::bracketRowByGameNumber($tournament);

        foreach ([41, 42] as $n) {
            $match = $byNum[$n] ?? null;
            if ($match === null || ! self::matchHasDecisiveWinner($match)) {
                return false;
            }
        }

        return true;
    }

    /**
     * True when both semi-finals (43–44) are decided so losers exist for game 47.
     */
    public static function semiFinals43Through44Decided(Tournament $tournament): bool
    {
        $byNum = self::bracketRowByGameNumber($tournament);

        foreach ([43, 44] as $n) {
            $match = $byNum[$n] ?? null;
            if ($match === null || ! self::matchHasDecisiveWinner($match)) {
                return false;
            }
        }

        return true;
    }

    private static function matchHasDecisiveWinner(TournamentMatch $match): bool
    {
        return self::matchHasDecisiveScoreline($match);
    }

    /**
     * Decisive outcome available for bracket propagation (winner/loser registration IDs).
     */
    private static function matchHasDecisiveScoreline(TournamentMatch $match): bool
    {
        if (! in_array($match->status, ['completed', 'live'], true)) {
            return false;
        }

        if ($match->home_score === null || $match->away_score === null) {
            return false;
        }

        return (int) $match->home_score !== (int) $match->away_score;
    }

    public static function sync(Tournament $tournament): void
    {
        if ($tournament->registrations()->count() >= TournamentController::MINIMUM_BRACKET_TEAM_COUNT) {
            return;
        }

        $tournament->loadMissing(['pitches', 'registrations', 'matches']);

        DB::transaction(function () use ($tournament): void {
            self::ensureTwoPitches($tournament);

            self::normalizeLegacyBracketRowDuplicates($tournament);

            $tournament->unsetRelation('pitches');
            $pitches = $tournament->pitches()->orderBy('sort_order')->orderBy('id')->get();
            $pitch1 = $pitches->get(0);
            $pitch2 = $pitches->get(1);

            if ($pitch1 === null || $pitch2 === null) {
                throw new InvalidArgumentException(__('Two pitches are required for the Day 2 knockout bracket.'));
            }

            $tz = SmallFixedRoundRobinDayOneSchedule::tournamentTimezone($tournament);

            foreach (self::gameDefinitions() as $gameNumber => $def) {
                $pitch = $def['pitch_slot'] === 1 ? $pitch1 : $pitch2;
                $scheduledAt = CarbonImmutable::parse($def['scheduled_iso'], $tz);

                $criteria = [
                    'tournament_id' => $tournament->id,
                    'match_number' => $gameNumber,
                    'stage' => $def['stage'],
                ];

                $existing = TournamentMatch::query()
                    ->where('tournament_id', $tournament->id)
                    ->where('match_number', $gameNumber)
                    ->where('stage', $def['stage'])
                    ->first();

                $roundLabel = isset($def['schedule_row_label']) && $def['schedule_row_label'] !== ''
                    ? $def['schedule_row_label']
                    : $def['round_label'];

                $base = [
                    'pitch_id' => $pitch->id,
                    'pitch_assigned_by' => $existing?->pitch_assigned_by,
                    'stage' => $def['stage'],
                    'round_label' => $roundLabel,
                    'scheduled_at' => $scheduledAt,
                    'notes' => self::marker($gameNumber),
                ];

                if ($existing === null) {
                    $payload = array_merge($base, [
                        'home_registration_id' => null,
                        'away_registration_id' => null,
                        'status' => 'scheduled',
                        'home_score' => null,
                        'away_score' => null,
                    ]);
                } else {
                    $keepScoreline = in_array($existing->status, ['completed', 'live'], true)
                        && $existing->home_score !== null
                        && $existing->away_score !== null;

                    $payload = array_merge($base, [
                        'home_registration_id' => $existing->home_registration_id,
                        'away_registration_id' => $existing->away_registration_id,
                        'status' => $existing->status,
                        'home_score' => $keepScoreline ? $existing->home_score : null,
                        'away_score' => $keepScoreline ? $existing->away_score : null,
                    ]);

                    if (! $keepScoreline && $existing->status === 'scheduled') {
                        $payload['home_score'] = null;
                        $payload['away_score'] = null;
                    }
                }

                TournamentMatch::query()->updateOrCreate($criteria, $payload);
            }
        });

        self::applyStandingsAndPropagation($tournament);
    }

    /**
     * Re-run bracket propagation after a knockout result may have changed (e.g. player stats updated).
     */
    public static function syncAfterResultChange(Tournament $tournament, ?TournamentMatch $sourceMatch = null): void
    {
        if ($tournament->registrations()->count() >= TournamentController::MINIMUM_BRACKET_TEAM_COUNT) {
            return;
        }

        if ($sourceMatch !== null && ! self::smallBracketKnockoutMatchMayDrivePropagation($sourceMatch)) {
            return;
        }

        self::applyStandingsAndPropagation($tournament);
    }

    /**
     * @return Collection<int, TournamentMatch>
     */
    public static function bracketMatches(Tournament $tournament): Collection
    {
        return collect(self::bracketRowByGameNumber($tournament))
            ->sortKeys()
            ->values();
    }

    /**
     * Games 37–48 keyed by {@code match_number}: bracket marker rows win; otherwise stage fallbacks (QF / Ranking Path aliases).
     *
     * @return array<int, TournamentMatch>
     */
    private static function bracketRowByGameNumber(Tournament $tournament): array
    {
        $byNum = [];
        foreach ($tournament->matches ?? [] as $m) {
            if ($m->match_number !== null && self::isBracketMatch($m)) {
                $byNum[(int) $m->match_number] = $m;
            }
        }

        foreach ($tournament->matches ?? [] as $m) {
            if ($m->match_number === null) {
                continue;
            }
            $n = (int) $m->match_number;
            if ($n < self::FIRST_GAME_NUMBER || $n > self::LAST_GAME_NUMBER) {
                continue;
            }
            if (isset($byNum[$n])) {
                continue;
            }
            if ($n <= 40 && in_array((string) $m->stage, self::quarterFinalStageAliases(), true)) {
                $byNum[$n] = $m;
            }
            if ($n >= 41 && $n <= 42 && in_array((string) $m->stage, self::rankingPathStageAliases(), true)) {
                $byNum[$n] = $m;
            }

            if (($n === 43 || $n === 44) && in_array((string) $m->stage, self::semiFinalStageAliases(), true)) {
                $byNum[$n] = $m;
            }

            if ($n >= 45 && $n <= 47 && in_array((string) $m->stage, self::rankingPathStageAliases(), true)) {
                $byNum[$n] = $m;
            }

            if ($n === 47 && in_array((string) $m->stage, self::semiFinalStageAliases(), true)) {
                $byNum[$n] = $m;
            }
        }

        return $byNum;
    }

    /**
     * Allow propagation after score edits on legacy rows that omit {@see MARKER_PREFIX} but use a known stage alias.
     */
    private static function smallBracketKnockoutMatchMayDrivePropagation(TournamentMatch $match): bool
    {
        if (self::isBracketMatch($match)) {
            return true;
        }

        $n = (int) ($match->match_number ?? 0);
        if ($n < self::FIRST_GAME_NUMBER || $n > self::LAST_GAME_NUMBER) {
            return false;
        }

        $stage = (string) $match->stage;

        if ($n <= 40 && in_array($stage, self::quarterFinalStageAliases(), true)) {
            return true;
        }

        if ($n >= 41 && $n <= 42 && in_array($stage, self::rankingPathStageAliases(), true)) {
            return true;
        }

        if (($n === 43 || $n === 44) && in_array($stage, self::semiFinalStageAliases(), true)) {
            return true;
        }

        if ($n >= 45 && $n <= 47 && in_array($stage, self::rankingPathStageAliases(), true)) {
            return true;
        }

        return $n === 47 && in_array($stage, self::semiFinalStageAliases(), true);
    }

    /**
     * Legacy rows may duplicate {@code match_number} (e.g. game 47 stored as {@code semifinal} before canonical {@code placement}).
     */
    private static function normalizeLegacyBracketRowDuplicates(Tournament $tournament): void
    {
        $defs = self::gameDefinitions();
        $markerLike = '%'.self::MARKER_PREFIX.'%';

        foreach ([47] as $gameNumber) {
            $canonicalStage = $defs[$gameNumber]['stage'] ?? null;
            if (! is_string($canonicalStage) || $canonicalStage === '') {
                continue;
            }

            $rows = TournamentMatch::query()
                ->where('tournament_id', $tournament->id)
                ->where('match_number', $gameNumber)
                ->orderByRaw('case when notes like ? then 0 else 1 end', [$markerLike])
                ->orderByRaw('case when stage = ? then 0 else 1 end', [$canonicalStage])
                ->orderBy('id')
                ->get();

            foreach ($rows->skip(1) as $duplicate) {
                $isEmptySlot = $duplicate->home_registration_id === null
                    && $duplicate->away_registration_id === null
                    && $duplicate->home_score === null
                    && $duplicate->away_score === null
                    && $duplicate->status === 'scheduled';

                if ($isEmptySlot) {
                    $duplicate->delete();
                }
            }

            $primary = TournamentMatch::query()
                ->where('tournament_id', $tournament->id)
                ->where('match_number', $gameNumber)
                ->orderByRaw('case when notes like ? then 0 else 1 end', [$markerLike])
                ->orderBy('id')
                ->first();

            if ($primary !== null
                && (string) $primary->stage !== $canonicalStage
                && in_array((string) $primary->stage, self::semiFinalStageAliases(), true)
            ) {
                $primary->forceFill(['stage' => $canonicalStage])->save();
            }
        }
    }

    private static function ensureTwoPitches(Tournament $tournament): void
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
    }

    private static function applyStandingsAndPropagation(Tournament $tournament): void
    {
        $tournament->unsetRelation('matches');
        $tournament->load([
            'matches' => fn ($q) => $q
                ->with([
                    'pitch',
                    'pitchAssignedBy:id,name',
                    'homeRegistration.team.members',
                    'awayRegistration.team.members',
                ])
                ->withCount('scoreLogs')
                ->orderByRaw('case when scheduled_at is null then 1 else 0 end')
                ->orderBy('scheduled_at')
                ->orderBy('match_number')
                ->orderBy('id'),
        ]);

        /** @var array<int, TournamentMatch> $byNum */
        $byNum = self::bracketRowByGameNumber($tournament);

        $bundle = SmallTournamentTeamStanding::roundRobinTeamStanding($tournament);
        $rrFinal = ($bundle['meta']['standings_status'] ?? '') === 'final';

        if ($rrFinal) {
            $rows = $bundle['rows']->values()->filter(fn (array $r): bool => isset($r['rank']) && is_int($r['rank']) && $r['rank'] >= 1);

            $byRank = [];
            foreach ($rows->take(8) as $row) {
                $rk = (int) ($row['rank'] ?? 0);
                $regId = (int) ($row['registration_id'] ?? 0);
                if ($rk >= 1 && $rk <= 8 && $regId > 0) {
                    $byRank[$rk] = $regId;
                }
            }

            self::maybeAssignQuarterFinals($byNum, $byRank);
        }

        // Ranking Path (41–42): same population pattern as Quarter Finals — explicit assignment pass from sources,
        // then downstream bracket slots (43+) via propagateFromSources.
        self::maybeAssignRankingPathFromQuarterFinalLosers($byNum);

        self::propagateFromSources($byNum);
    }

    /**
     * @param  array<int, TournamentMatch>  $byNum
     * @param  array<int, int>  $byRank  registration_id by rank 1..8
     */
    private static function maybeAssignQuarterFinals(array $byNum, array $byRank): void
    {
        $pairs = [
            37 => [1, 8],
            38 => [2, 7],
            39 => [3, 6],
            40 => [4, 5],
        ];

        foreach ($pairs as $g => [$hr, $ar]) {
            $m = $byNum[$g] ?? null;
            if ($m === null) {
                continue;
            }

            if (! self::canReplaceTeamSlots($m)) {
                continue;
            }

            if ($m->home_registration_id !== null || $m->away_registration_id !== null) {
                continue;
            }

            $homeId = $byRank[$hr] ?? null;
            $awayId = $byRank[$ar] ?? null;

            if (! $homeId || ! $awayId) {
                continue;
            }

            $m->forceFill([
                'home_registration_id' => $homeId,
                'away_registration_id' => $awayId,
            ])->save();
        }
    }

    /**
     * Assign Ranking Path slots from Quarter Final losers — mirrors {@see maybeAssignQuarterFinals} structure.
     *
     * Game 41: loser of 37 vs loser of 40. Game 42: loser of 38 vs loser of 39.
     *
     * @param  array<int, TournamentMatch>  $byNum
     */
    private static function maybeAssignRankingPathFromQuarterFinalLosers(array $byNum): void
    {
        $assignments = [
            41 => [37, 40],
            42 => [38, 39],
        ];

        foreach ($assignments as $targetGame => [$srcHome, $srcAway]) {
            $target = $byNum[$targetGame] ?? null;
            $matchHome = $byNum[$srcHome] ?? null;
            $matchAway = $byNum[$srcAway] ?? null;

            if ($target === null || $matchHome === null || $matchAway === null) {
                continue;
            }

            if (! self::canReplaceTeamSlots($target)) {
                continue;
            }

            if ($target->home_registration_id !== null || $target->away_registration_id !== null) {
                continue;
            }

            $homeId = self::loserRegistrationId($matchHome);
            $awayId = self::loserRegistrationId($matchAway);

            if (! $homeId || ! $awayId || $homeId === $awayId) {
                continue;
            }

            $target->forceFill([
                'home_registration_id' => $homeId,
                'away_registration_id' => $awayId,
            ])->save();
        }
    }

    private static function canReplaceTeamSlots(TournamentMatch $m): bool
    {
        if ($m->status !== 'scheduled') {
            return false;
        }

        if ($m->home_score !== null || $m->away_score !== null) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<int, TournamentMatch>  $byNum
     */
    private static function propagateFromSources(array $byNum): void
    {
        $m = static fn (int $n): ?TournamentMatch => $byNum[$n] ?? null;

        // Games 41–42 populated in maybeAssignRankingPathFromQuarterFinalLosers (same tier as QF assignment).
        self::fillIfReady($m(43), [$m(37), $m(40)], ['winner', 'winner']);
        self::fillIfReady($m(44), [$m(38), $m(39)], ['winner', 'winner']);

        self::fillIfReady($m(45), [$m(41), $m(42)], ['winner', 'winner']);
        self::fillIfReady($m(46), [$m(41), $m(42)], ['loser', 'loser']);

        self::fillIfReady($m(47), [$m(43), $m(44)], ['loser', 'loser']);
        self::fillIfReady($m(48), [$m(43), $m(44)], ['winner', 'winner']);
    }

    /**
     * @param  list<TournamentMatch|null>  $sources
     * @param  list<'winner'|'loser'>  $roles
     */
    private static function fillIfReady(?TournamentMatch $target, array $sources, array $roles): void
    {
        if ($target === null) {
            return;
        }

        if ($target->home_registration_id !== null || $target->away_registration_id !== null) {
            return;
        }

        if (! self::canReplaceTeamSlots($target)) {
            return;
        }

        $ids = [];
        foreach ($sources as $i => $src) {
            if ($src === null || ! isset($roles[$i])) {
                return;
            }

            $role = $roles[$i];
            $id = $role === 'winner' ? self::winnerRegistrationId($src) : self::loserRegistrationId($src);

            if ($id === null) {
                return;
            }

            $ids[] = $id;
        }

        if (count($ids) !== 2 || $ids[0] === $ids[1]) {
            return;
        }

        $target->forceFill([
            'home_registration_id' => $ids[0],
            'away_registration_id' => $ids[1],
        ])->save();
    }

    private static function winnerRegistrationId(TournamentMatch $match): ?int
    {
        if ($match->home_registration_id === null || $match->away_registration_id === null) {
            return null;
        }

        if (! self::matchHasDecisiveScoreline($match)) {
            return null;
        }

        if ((int) $match->home_score > (int) $match->away_score) {
            return (int) $match->home_registration_id;
        }

        if ((int) $match->away_score > (int) $match->home_score) {
            return (int) $match->away_registration_id;
        }

        return null;
    }

    private static function loserRegistrationId(TournamentMatch $match): ?int
    {
        if ($match->home_registration_id === null || $match->away_registration_id === null) {
            return null;
        }

        if (! self::matchHasDecisiveScoreline($match)) {
            return null;
        }

        if ((int) $match->home_score > (int) $match->away_score) {
            return (int) $match->away_registration_id;
        }

        if ((int) $match->away_score > (int) $match->home_score) {
            return (int) $match->home_registration_id;
        }

        return null;
    }
}
