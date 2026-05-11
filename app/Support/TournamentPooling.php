<?php

namespace App\Support;

use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentRegistration;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class TournamentPooling
{
    public const POOLING_MODE_AUTO = 'auto';

    public const POOLING_MODE_MANUAL = 'manual';

    /** @var list<string> Tournament diagram — Pool A crossover sources (W#/L#). */
    public const DIAGRAM_POOL_A_SLOT_CODES = ['W5', 'W6', 'L5', 'L6', 'W1', 'W2', 'W4', 'W3'];

    /** @var list<string> Tournament diagram — Pool B crossover sources (W#/L#). */
    public const DIAGRAM_POOL_B_SLOT_CODES = ['L3', 'L4', 'L2', 'L1', 'W7', 'W8', 'L7', 'L8'];

    /** Maximum crossover game index referenced by the full standard diagram (W8/L8). */
    public const DIAGRAM_FULL_CROSSOVER_GAME_COUNT = 8;

    /**
     * Crossover display/order game number (not necessarily the database row ID).
     */
    public static function crossoverGameDisplayNumber(TournamentMatch $match): ?int
    {
        $n = $match->match_number;

        if ($n !== null && (int) $n > 0) {
            return (int) $n;
        }

        $label = trim((string) ($match->round_label ?? ''));

        // Allow optional spaces after "#" (e.g. "# 6") — must be at end of label so bracket codes like "B3" are not matched.
        if ($label !== '' && preg_match('/#\s*(\d+)\s*$/', $label, $m) === 1) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * Completed crossover matches with a decisive scoreline (ties excluded).
     *
     * @return Collection<int, TournamentMatch>
     */
    public static function getCompletedCrossoverGames(int $tournamentId): Collection
    {
        return TournamentMatch::query()
            ->where('tournament_id', $tournamentId)
            ->where('stage', 'crossover')
            ->with(['homeRegistration.team', 'awayRegistration.team'])
            ->orderBy('match_number')
            ->orderBy('id')
            ->get()
            ->filter(fn (TournamentMatch $match): bool => self::isDecisiveCompletedCrossoverMatch($match))
            ->values();
    }

    /**
     * Winner registration for a decisive completed crossover game, or null.
     */
    public static function getCrossoverWinner(TournamentMatch $game): ?TournamentRegistration
    {
        if (! self::isDecisiveCompletedCrossoverMatch($game)) {
            return null;
        }

        return (int) $game->home_score > (int) $game->away_score
            ? $game->homeRegistration
            : $game->awayRegistration;
    }

    /**
     * Loser registration for a decisive completed crossover game, or null.
     */
    public static function getCrossoverLoser(TournamentMatch $game): ?TournamentRegistration
    {
        if (! self::isDecisiveCompletedCrossoverMatch($game)) {
            return null;
        }

        return (int) $game->home_score > (int) $game->away_score
            ? $game->awayRegistration
            : $game->homeRegistration;
    }

    /**
     * Optional explicit pooling layout from DB JSON or division defaults — never required for automatic pooling.
     *
     * @return list<array{name: string, slots: list<array{source: string, crossover_game_number: int}>}>|null
     */
    public static function getPoolingRules(?Tournament $tournament): ?array
    {
        if ($tournament === null) {
            return null;
        }

        $normalized = self::normalizePoolingRulesPayload($tournament->pooling_rules);

        if ($normalized !== null) {
            return $normalized;
        }

        $divisionKey = trim((string) ($tournament->division ?? ''));

        if ($divisionKey !== '') {
            $fromConfig = config('tournament_pooling.defaults_by_division.'.$divisionKey);

            return self::normalizePoolingRulesPayload(is_array($fromConfig) ? $fromConfig : null);
        }

        return null;
    }

    /**
     * Every crossover match exists with two registrations and has a decisive completed score.
     *
     * @param  Collection<int, TournamentMatch>  $crossoverMatches
     */
    /**
     * Distinct registration IDs appearing on either side of crossover matches for this schedule slice.
     *
     * @param  Collection<int, TournamentMatch>  $crossoverMatches
     * @return list<int>
     */
    public static function qualifiedCrossoverRegistrationIds(Collection $crossoverMatches): array
    {
        $seen = [];

        foreach ($crossoverMatches as $match) {
            foreach ([$match->home_registration_id, $match->away_registration_id] as $rid) {
                if ($rid === null) {
                    continue;
                }
                $seen[(int) $rid] = true;
            }
        }

        $ids = array_map('intval', array_keys($seen));
        sort($ids);

        return $ids;
    }

    /**
     * Registration IDs in first-seen order (multi-select friendly).
     *
     * @param  array<int|string|null>  $raw
     * @return list<int>
     */
    public static function dedupeRegistrationIdsPreserveOrder(array $raw): array
    {
        $seen = [];
        $ordered = [];

        foreach ($raw as $value) {
            if (! is_numeric($value)) {
                continue;
            }

            $id = (int) $value;

            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;
            $ordered[] = $id;
        }

        return $ordered;
    }

    /**
     * @param  array<int|string|null>  $poolARegistrationIds
     * @param  array<int|string|null>  $poolBRegistrationIds
     *
     * @throws ValidationException
     */
    public static function validateManualPoolSelections(
        Tournament $tournament,
        array $poolARegistrationIds,
        array $poolBRegistrationIds,
        Collection $crossoverMatches,
    ): void {
        $poolAIds = self::dedupeRegistrationIdsPreserveOrder($poolARegistrationIds);
        $poolBIds = self::dedupeRegistrationIdsPreserveOrder($poolBRegistrationIds);

        $qualifiedSorted = self::qualifiedCrossoverRegistrationIds($crossoverMatches);

        if ($qualifiedSorted === []) {
            throw ValidationException::withMessages([
                'pooling' => [__('Add crossover games before assigning pools.')],
            ]);
        }

        if ($poolAIds === [] || $poolBIds === []) {
            throw ValidationException::withMessages([
                'pool_a_registration_ids' => [__('Choose at least one team for each pool.')],
            ]);
        }

        $overlap = array_intersect($poolAIds, $poolBIds);

        if ($overlap !== []) {
            throw ValidationException::withMessages([
                'pool_b_registration_ids' => [__('The same team cannot be assigned to Pool A and Pool B.')],
            ]);
        }

        $unionSorted = array_merge($poolAIds, $poolBIds);
        sort($unionSorted);

        if ($unionSorted !== $qualifiedSorted) {
            throw ValidationException::withMessages([
                'pool_a_registration_ids' => [__('Every crossover team must appear exactly once across Pool A and Pool B.')],
            ]);
        }

        $crossoverGameCount = count(self::gamesKeyedByCrossoverNumber($crossoverMatches));
        $poolACapacity = count(self::diagramPoolASlotCodesForCrossoverGameCount($crossoverGameCount));
        $poolBCapacity = count(self::diagramPoolBSlotCodesForCrossoverGameCount($crossoverGameCount));
        $diagramSlotsTotal = $poolACapacity + $poolBCapacity;

        if (count($qualifiedSorted) === $diagramSlotsTotal) {
            if (count($poolAIds) !== $poolACapacity || count($poolBIds) !== $poolBCapacity) {
                throw ValidationException::withMessages([
                    'pool_a_registration_ids' => [__('When there are :total crossover participants, Pool A must have :a teams and Pool B must have :b teams for :games crossover games.', [
                        'total' => $diagramSlotsTotal,
                        'a' => $poolACapacity,
                        'b' => $poolBCapacity,
                        'games' => $crossoverGameCount,
                    ])],
                ]);
            }
        } else {
            if (count($poolAIds) > $poolACapacity || count($poolBIds) > $poolBCapacity) {
                throw ValidationException::withMessages([
                    'pool_a_registration_ids' => [__('Each pool cannot exceed the diagram capacity for :games crossover games (Pool A :a max, Pool B :b max).', [
                        'games' => $crossoverGameCount,
                        'a' => $poolACapacity,
                        'b' => $poolBCapacity,
                    ])],
                ]);
            }
        }

        $registrationScopeCount = TournamentRegistration::query()
            ->where('tournament_id', $tournament->id)
            ->whereIn('id', $unionSorted)
            ->count();

        if ($registrationScopeCount !== count($unionSorted)) {
            throw ValidationException::withMessages([
                'pool_a_registration_ids' => [__('One or more selections are not valid registrations for this tournament.')],
            ]);
        }
    }

    /**
     * Convert diagram codes such as W5 / L3 into winner/loser rules keyed by crossover game number.
     *
     * @return array{source: string, crossover_game_number: int}
     */
    public static function slotCodeToPoolingRule(string $slotCode): array
    {
        $slotCode = strtoupper(trim($slotCode));

        if (preg_match('/^([WL])(\d+)$/', $slotCode, $m) !== 1) {
            throw new \InvalidArgumentException('Invalid diagram pooling slot code: '.$slotCode);
        }

        return [
            'source' => $m[1] === 'W' ? 'winner' : 'loser',
            'crossover_game_number' => (int) $m[2],
        ];
    }

    /**
     * Crossover game index (e.g. 7 for W7 / L7) referenced by a diagram slot code.
     */
    public static function crossoverGameIndexFromSlotCode(string $slotCode): int
    {
        return self::slotCodeToPoolingRule($slotCode)['crossover_game_number'];
    }

    /**
     * Pool A diagram slots that apply when there are exactly $crossoverGameCount crossover games (no slots for missing games).
     *
     * @return list<string>
     */
    public static function diagramPoolASlotCodesForCrossoverGameCount(int $crossoverGameCount): array
    {
        if ($crossoverGameCount < 1) {
            return [];
        }

        return array_values(array_filter(
            self::DIAGRAM_POOL_A_SLOT_CODES,
            fn (string $code): bool => self::crossoverGameIndexFromSlotCode($code) <= $crossoverGameCount,
        ));
    }

    /**
     * @return list<string>
     */
    public static function diagramPoolBSlotCodesForCrossoverGameCount(int $crossoverGameCount): array
    {
        if ($crossoverGameCount < 1) {
            return [];
        }

        return array_values(array_filter(
            self::DIAGRAM_POOL_B_SLOT_CODES,
            fn (string $code): bool => self::crossoverGameIndexFromSlotCode($code) <= $crossoverGameCount,
        ));
    }

    public static function diagramSlotCapacityForCrossoverGameCount(int $crossoverGameCount): int
    {
        return count(self::diagramPoolASlotCodesForCrossoverGameCount($crossoverGameCount))
            + count(self::diagramPoolBSlotCodesForCrossoverGameCount($crossoverGameCount));
    }

    public static function isCrossoverScheduleFullyResolved(Collection $crossoverMatches): bool
    {
        if ($crossoverMatches->isEmpty()) {
            return false;
        }

        $participantUsage = [];

        foreach ($crossoverMatches as $match) {
            foreach ([$match->home_registration_id, $match->away_registration_id] as $rid) {
                if ($rid === null) {
                    return false;
                }
                $id = (int) $rid;
                $participantUsage[$id] = ($participantUsage[$id] ?? 0) + 1;
            }

            if (! self::isDecisiveCompletedCrossoverMatch($match)) {
                return false;
            }
        }

        return collect($participantUsage)->every(fn (int $count): bool => $count === 1);
    }

    /**
     * @param  array<int, TournamentMatch>  $gamesByCrossoverNumber
     * @return array{
     *     pending: bool,
     *     registration: TournamentRegistration|null,
     *     team_id: int|null,
     *     team_name: string|null,
     *     registration_id: int|null,
     *     game_number: int|null,
     *     source: string,
     *     compact_label: string,
     *     crossover_round_label: string|null
     * }
     */
    public static function resolvePoolingSlot(array $rule, array $gamesByCrossoverNumber): array
    {
        $source = strtolower(trim((string) ($rule['source'] ?? '')));

        if (! in_array($source, ['winner', 'loser'], true)) {
            return self::pendingSlotResolution(null, 'winner', '', null);
        }

        $gameNumber = (int) ($rule['crossover_game_number'] ?? $rule['game_number'] ?? 0);
        $compactLabel = ($source === 'winner' ? 'W' : 'L').(string) max($gameNumber, 0);

        if ($gameNumber < 1) {
            return self::pendingSlotResolution(null, $source, $compactLabel, null);
        }

        $game = $gamesByCrossoverNumber[$gameNumber] ?? null;

        if (! $game instanceof TournamentMatch) {
            return self::pendingSlotResolution($gameNumber, $source, $compactLabel, null);
        }

        $registration = $source === 'winner'
            ? self::getCrossoverWinner($game)
            : self::getCrossoverLoser($game);

        $pending = $registration === null;
        $team = $registration?->team;
        $roundLabel = self::crossoverRoundLabel($game);

        return [
            'pending' => $pending,
            'registration' => $registration,
            'team_id' => $team !== null ? (int) $team->id : null,
            'team_name' => $team?->name,
            'registration_id' => $registration?->id !== null ? (int) $registration->id : null,
            'game_number' => $gameNumber,
            'source' => $source,
            'compact_label' => $compactLabel,
            'crossover_round_label' => $roundLabel,
        ];
    }

    /**
     * @param  Collection<int, TournamentMatch>|array<int, TournamentMatch>  $crossoverGames
     * @return array{
     *     rules_configured: bool,
     *     pools: list<array{name: string, subtitle: string, rows: list<array<string, mixed>>}>,
     *     assignments: array<int, string>,
     *     duplicate_team_warnings: list<string>,
     *     participant_ids: list<int>,
     *     has_matches: bool,
     *     all_crossover_finalized: bool,
     *     finalized_match_count: int,
     *     total_match_count: int,
     *     participant_usage: array<int, int>,
     *     pooling_mode: string
     * }
     */
    public static function buildPoolingAssignments(?Tournament $tournament, Collection|array $crossoverGames): array
    {
        $matches = $crossoverGames instanceof Collection
            ? $crossoverGames->values()
            : collect($crossoverGames)->values();

        $participantUsage = [];

        foreach ($matches as $match) {
            foreach ([$match->home_registration_id, $match->away_registration_id] as $rid) {
                if ($rid === null) {
                    continue;
                }
                $id = (int) $rid;
                $participantUsage[$id] = ($participantUsage[$id] ?? 0) + 1;
            }
        }

        $participantIds = array_map('intval', array_keys($participantUsage));

        $gamesByNumber = self::gamesKeyedByCrossoverNumber($matches);

        $finalizedMatchCount = 0;

        foreach ($matches as $match) {
            if (self::isDecisiveCompletedCrossoverMatch($match)) {
                $finalizedMatchCount++;
            }
        }

        $allCrossoverFinalized = self::isCrossoverScheduleFullyResolved($matches);

        $poolingMode = self::POOLING_MODE_AUTO;

        if ($tournament !== null) {
            $candidateMode = (string) ($tournament->pooling_mode ?? self::POOLING_MODE_AUTO);

            $poolingMode = in_array($candidateMode, [self::POOLING_MODE_AUTO, self::POOLING_MODE_MANUAL], true)
                ? $candidateMode
                : self::POOLING_MODE_AUTO;
        }

        $crossoverGameCount = count($gamesByNumber);
        $diagramSlotCapacity = self::diagramSlotCapacityForCrossoverGameCount($crossoverGameCount);

        $base = [
            'participant_ids' => $participantIds,
            'has_matches' => $matches->isNotEmpty(),
            'all_crossover_finalized' => $allCrossoverFinalized,
            'finalized_match_count' => $finalizedMatchCount,
            'total_match_count' => $matches->count(),
            'participant_usage' => $participantUsage,
            'pooling_mode' => $poolingMode,
            'crossover_game_count' => $crossoverGameCount,
            'diagram_slot_capacity' => $diagramSlotCapacity,
            'diagram_below_full_crossover_count' => $crossoverGameCount > 0
                && $crossoverGameCount < self::DIAGRAM_FULL_CROSSOVER_GAME_COUNT,
        ];

        if ($tournament !== null
            && $poolingMode === self::POOLING_MODE_MANUAL
            && self::manualSlotsPayloadPresent($tournament)) {
            return array_merge($base, self::buildManualPoolsFromSnapshot($tournament));
        }

        $rules = self::getPoolingRules($tournament);

        if ($rules !== null) {
            return array_merge($base, self::buildCustomRulesPools($rules, $gamesByNumber, true));
        }

        return array_merge($base, self::buildDiagramAutoPools($gamesByNumber, $crossoverGameCount));
    }

    /**
     * @return array{
     *     assignments: array<int, string>,
     *     participant_ids: list<int>,
     *     has_matches: bool,
     *     all_finalized: bool,
     *     finalized_match_count: int,
     *     total_match_count: int,
     *     rules_configured: bool,
     *     duplicate_team_warnings: list<string>,
     *     pool_board: array<string, mixed>
     * }
     */
    public static function buildPersistenceSnapshot(?Tournament $tournament, Collection $crossoverGames): array
    {
        $built = self::buildPoolingAssignments($tournament, $crossoverGames);

        return [
            'assignments' => $built['assignments'],
            'participant_ids' => $built['participant_ids'],
            'has_matches' => $built['has_matches'],
            'all_finalized' => $built['all_crossover_finalized'],
            'finalized_match_count' => $built['finalized_match_count'],
            'total_match_count' => $built['total_match_count'],
            'rules_configured' => $built['rules_configured'],
            'duplicate_team_warnings' => $built['duplicate_team_warnings'],
            'pool_board' => [
                'pools' => $built['pools'],
                'all_crossover_finalized' => $built['all_crossover_finalized'],
                'rules_configured' => $built['rules_configured'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $built  Output of {@see self::buildPoolingAssignments()}
     * @return array<string, mixed>
     */
    public static function toApiPayload(array $built): array
    {
        return [
            'rulesConfigured' => $built['rules_configured'],
            'poolingMode' => $built['pooling_mode'] ?? self::POOLING_MODE_AUTO,
            'duplicateWarnings' => $built['duplicate_team_warnings'],
            'crossoverFinalized' => $built['all_crossover_finalized'],
            'crossoverGameCount' => $built['crossover_game_count'] ?? 0,
            'diagramFullCrossoverGameCount' => self::DIAGRAM_FULL_CROSSOVER_GAME_COUNT,
            'diagramSlotCapacity' => $built['diagram_slot_capacity'] ?? 0,
            'diagramBelowFullCrossoverCount' => $built['diagram_below_full_crossover_count'] ?? false,
            'pools' => collect($built['pools'])->map(fn (array $pool): array => [
                'name' => $pool['name'],
                'slots' => collect($pool['rows'])->map(fn (array $row): array => [
                    'slot' => $row['position'],
                    'compactLabel' => $row['slot_code'],
                    'source' => $row['outcome'],
                    'sourceGameNumber' => $row['game_number'],
                    'teamId' => $row['team_id'],
                    'teamName' => $row['team_name'],
                    'registrationId' => $row['registration_id'],
                    'crossoverRoundLabel' => $row['crossover_round_label'] ?? null,
                    'status' => $row['pending'] ? 'pending' : 'resolved',
                ])->all(),
            ])->all(),
        ];
    }

    /**
     * Map crossover diagram slot numbers (1…N) to matches.
     *
     * Keys come from each row's {@see crossoverGameDisplayNumber()} (match_number, then trailing "#n" in round_label).
     * Automatic crossover resets "#n" for each bracket pair (e.g. A vs B and C vs D both use "#1…#k"), so labels collide:
     * only the last pair survives in a naive map and slots such as W6–W8 never resolve. Non‑contiguous numbering (e.g. DB
     * rows numbered 10–17 while the diagram expects games 1–8) causes the same symptom.
     *
     * When any integer key from 1 through the crossover match count is missing, or when two slots reference the same match id,
     * we discard that map and assign positions 1…N using global order: positive match_number ascending, then match id.
     *
     * @param  Collection<int, TournamentMatch>  $matches
     * @return array<int, TournamentMatch>
     */
    public static function gamesKeyedByCrossoverNumber(Collection $matches): array
    {
        $gamesByNumber = [];

        foreach ($matches->sortBy('id')->all() as $match) {
            $num = self::crossoverGameDisplayNumber($match);

            if ($num !== null && $num > 0) {
                $gamesByNumber[$num] = $match;
            }
        }

        $sortedForSequential = $matches->sort(function (TournamentMatch $left, TournamentMatch $right): int {
            $nl = $left->match_number;
            $nr = $right->match_number;
            $ol = $nl !== null && (int) $nl > 0 ? (int) $nl : PHP_INT_MAX;
            $or = $nr !== null && (int) $nr > 0 ? (int) $nr : PHP_INT_MAX;

            if ($ol !== $or) {
                return $ol <=> $or;
            }

            return $left->id <=> $right->id;
        })->values();

        $totalCrossoverGames = $sortedForSequential->count();

        $missingSequentialKey = false;

        for ($k = 1; $k <= $totalCrossoverGames; $k++) {
            if (! array_key_exists($k, $gamesByNumber)) {
                $missingSequentialKey = true;

                break;
            }
        }

        $explicitIds = collect($gamesByNumber)->pluck('id')->filter(fn ($id): bool => $id !== null && (int) $id > 0);
        $duplicateMatchAssigned = $explicitIds->count() !== $explicitIds->unique()->count();

        if ($missingSequentialKey || $duplicateMatchAssigned) {
            $gamesByNumber = [];

            foreach ($sortedForSequential as $idx => $match) {
                $gamesByNumber[$idx + 1] = $match;
            }
        }

        return $gamesByNumber;
    }

    /**
     * Deterministic ordering: known crossover numbers ascending, unknown numbers last (stable by match id).
     *
     * @param  Collection<int, TournamentMatch>  $matches
     * @return Collection<int, TournamentMatch>
     */
    public static function sortCrossoverMatchesDeterministic(Collection $matches): Collection
    {
        return $matches
            ->sort(function (TournamentMatch $left, TournamentMatch $right): int {
                $nl = self::crossoverGameDisplayNumber($left);
                $nr = self::crossoverGameDisplayNumber($right);
                $ol = $nl ?? PHP_INT_MAX;
                $or = $nr ?? PHP_INT_MAX;

                if ($ol !== $or) {
                    return $ol <=> $or;
                }

                return $left->id <=> $right->id;
            })
            ->values();
    }

    /**
     * @param  list<array{name: string, slots: list<array{source: string, crossover_game_number: int}>}>  $rules
     * @param  array<int, TournamentMatch>  $gamesByCrossoverNumber
     * @return array{
     *     rules_configured: bool,
     *     pools: list<array{name: string, subtitle: string, rows: list<array<string, mixed>>}>,
     *     assignments: array<int, string>,
     *     duplicate_team_warnings: list<string>,
     * }
     */
    protected static function buildCustomRulesPools(array $rules, array $gamesByCrossoverNumber, bool $rulesConfigured): array
    {
        $assignments = [];
        $poolsOutput = [];
        $resolvedTeamSlots = [];

        foreach ($rules as $pool) {
            $poolDisplayName = mb_strtoupper(trim($pool['name'])) ?: __('POOL');
            $rows = [];

            foreach ($pool['slots'] as $index => $slotRule) {
                $resolved = self::resolvePoolingSlot($slotRule, $gamesByCrossoverNumber);
                $registration = $resolved['registration'];
                $registrationId = $resolved['registration_id'];
                $teamId = $resolved['team_id'];

                if ($registrationId !== null && ! $resolved['pending']) {
                    $assignments[$registrationId] = $poolDisplayName;
                }

                if ($teamId !== null && ! $resolved['pending']) {
                    $ref = $poolDisplayName.' · #'.($index + 1).' ('.$resolved['compact_label'].')';
                    $resolvedTeamSlots[$teamId][] = $ref;
                }

                $rows[] = [
                    'position' => $index + 1,
                    'slot_code' => $resolved['compact_label'],
                    'game_number' => $resolved['game_number'],
                    'outcome' => $resolved['source'],
                    'pending' => $resolved['pending'],
                    'registration_id' => $registrationId,
                    'team_id' => $teamId,
                    'team_name' => $resolved['team_name'],
                    'crossover_round_label' => $resolved['crossover_round_label'],
                ];
            }

            $compactPreview = collect($rows)->pluck('slot_code')->filter()->take(12)->implode(', ');
            $subtitle = $compactPreview !== ''
                ? __('Custom layout · :slots', ['slots' => $compactPreview])
                : __('Custom pooling layout');

            $poolsOutput[] = [
                'name' => $poolDisplayName,
                'subtitle' => $subtitle,
                'rows' => $rows,
            ];
        }

        return [
            'rules_configured' => $rulesConfigured,
            'pools' => $poolsOutput,
            'assignments' => $assignments,
            'duplicate_team_warnings' => self::duplicateTeamWarningsFromSlots($resolvedTeamSlots),
        ];
    }

    protected static function manualSlotsPayloadPresent(Tournament $tournament): bool
    {
        $slots = $tournament->pooling_manual_slots;

        if (! is_array($slots)) {
            return false;
        }

        $poolA = $slots['pool_a'] ?? [];
        $poolB = $slots['pool_b'] ?? [];

        return is_array($poolA)
            && is_array($poolB)
            && ($poolA !== [] || $poolB !== []);
    }

    /**
     * Auto pooling using the tournament diagram (W#/L#), restricted to crossover games 1…N that actually exist.
     *
     * @param  array<int, TournamentMatch>  $gamesByCrossoverNumber
     * @return array{
     *     rules_configured: bool,
     *     pools: list<array{name: string, subtitle: string, rows: list<array<string, mixed>>}>,
     *     assignments: array<int, string>,
     *     duplicate_team_warnings: list<string>,
     * }
     */
    protected static function buildDiagramAutoPools(array $gamesByCrossoverNumber, int $crossoverGameCount): array
    {
        $poolACodes = self::diagramPoolASlotCodesForCrossoverGameCount($crossoverGameCount);
        $poolBCodes = self::diagramPoolBSlotCodesForCrossoverGameCount($crossoverGameCount);

        $diagramPools = [
            [
                'name' => 'POOL A',
                'slots' => array_map(
                    fn (string $code): array => self::slotCodeToPoolingRule($code),
                    $poolACodes,
                ),
            ],
            [
                'name' => 'POOL B',
                'slots' => array_map(
                    fn (string $code): array => self::slotCodeToPoolingRule($code),
                    $poolBCodes,
                ),
            ],
        ];

        $built = self::buildCustomRulesPools($diagramPools, $gamesByCrossoverNumber, false);

        $built['pools'][0]['subtitle'] = $crossoverGameCount >= self::DIAGRAM_FULL_CROSSOVER_GAME_COUNT
            ? __('Diagram Pool A: :slots', ['slots' => implode(', ', self::DIAGRAM_POOL_A_SLOT_CODES)])
            : __('Diagram Pool A (:games crossover games): :slots', [
                'games' => $crossoverGameCount,
                'slots' => implode(', ', $poolACodes),
            ]);

        $built['pools'][1]['subtitle'] = $crossoverGameCount >= self::DIAGRAM_FULL_CROSSOVER_GAME_COUNT
            ? __('Diagram Pool B: :slots', ['slots' => implode(', ', self::DIAGRAM_POOL_B_SLOT_CODES)])
            : __('Diagram Pool B (:games crossover games): :slots', [
                'games' => $crossoverGameCount,
                'slots' => implode(', ', $poolBCodes),
            ]);

        return $built;
    }

    /**
     * @return array{
     *     rules_configured: bool,
     *     pools: list<array{name: string, subtitle: string, rows: list<array<string, mixed>>}>,
     *     assignments: array<int, string>,
     *     duplicate_team_warnings: list<string>,
     * }
     */
    protected static function buildManualPoolsFromSnapshot(Tournament $tournament): array
    {
        $slots = $tournament->pooling_manual_slots ?? [];
        $poolAIds = array_values(array_map('intval', is_array($slots['pool_a'] ?? null) ? $slots['pool_a'] : []));
        $poolBIds = array_values(array_map('intval', is_array($slots['pool_b'] ?? null) ? $slots['pool_b'] : []));

        $registrationLookup = TournamentRegistration::query()
            ->where('tournament_id', $tournament->id)
            ->whereIn('id', array_values(array_unique(array_merge($poolAIds, $poolBIds))))
            ->with('team')
            ->get()
            ->keyBy('id');

        $poolNameA = 'POOL A';
        $poolNameB = 'POOL B';

        $assignments = [];
        $resolvedTeamSlots = [];

        $makeRows = function (array $orderedIds, string $poolLabel) use ($registrationLookup, &$assignments, &$resolvedTeamSlots): array {
            $rows = [];

            foreach ($orderedIds as $index => $registrationId) {
                $registrationId = (int) $registrationId;
                /** @var TournamentRegistration|null $registration */
                $registration = $registrationLookup->get($registrationId);
                $team = $registration?->team;
                $teamId = $team !== null ? (int) $team->id : null;
                $pending = $registration === null;

                if ($registration !== null && ! $pending) {
                    $assignments[$registrationId] = $poolLabel;
                }

                if ($teamId !== null && $registration !== null) {
                    $resolvedTeamSlots[$teamId][] = $poolLabel.' · #'.($index + 1).' (manual)';
                }

                $rows[] = [
                    'position' => $index + 1,
                    'slot_code' => 'M'.($index + 1),
                    'game_number' => null,
                    'outcome' => 'manual',
                    'pending' => $pending,
                    'registration_id' => $registrationId > 0 && $registration !== null ? $registrationId : null,
                    'team_id' => $teamId,
                    'team_name' => $team?->name,
                    'crossover_round_label' => __('Manual assignment'),
                ];
            }

            return $rows;
        };

        return [
            'rules_configured' => false,
            'pools' => [
                [
                    'name' => $poolNameA,
                    'subtitle' => __('Manual assignments · saved slot order.'),
                    'rows' => $makeRows($poolAIds, $poolNameA),
                ],
                [
                    'name' => $poolNameB,
                    'subtitle' => __('Manual assignments · saved slot order.'),
                    'rows' => $makeRows($poolBIds, $poolNameB),
                ],
            ],
            'assignments' => $assignments,
            'duplicate_team_warnings' => self::duplicateTeamWarningsFromSlots($resolvedTeamSlots),
        ];
    }

    /**
     * @param  array<int, list<string>>  $resolvedTeamSlots
     * @return list<string>
     */
    protected static function duplicateTeamWarningsFromSlots(array $resolvedTeamSlots): array
    {
        $warnings = [];

        foreach ($resolvedTeamSlots as $teamId => $refs) {
            if (count($refs) > 1) {
                $warnings[] = __('Team ID :id appears in multiple resolved slots: :refs.', [
                    'id' => $teamId,
                    'refs' => implode('; ', $refs),
                ]);
            }
        }

        return $warnings;
    }

    protected static function crossoverRoundLabel(TournamentMatch $match): ?string
    {
        $label = trim((string) ($match->round_label ?? ''));

        return $label !== '' ? $label : null;
    }

    protected static function isDecisiveCompletedCrossoverMatch(TournamentMatch $match): bool
    {
        return $match->home_registration_id !== null
            && $match->away_registration_id !== null
            && $match->status === 'completed'
            && $match->home_score !== null
            && $match->away_score !== null
            && (int) $match->home_score !== (int) $match->away_score;
    }

    /**
     * @return list<array{name: string, slots: list<array{source: string, crossover_game_number: int}>}>|null
     */
    protected static function normalizePoolingRulesPayload(mixed $payload): ?array
    {
        if (! is_array($payload)) {
            return null;
        }

        $pools = $payload['pools'] ?? null;

        if (! is_array($pools) || $pools === []) {
            return null;
        }

        $out = [];

        foreach ($pools as $pool) {
            if (! is_array($pool)) {
                continue;
            }

            $name = trim((string) ($pool['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $slotsIn = $pool['slots'] ?? [];

            if (! is_array($slotsIn) || $slotsIn === []) {
                continue;
            }

            $slotsOut = [];

            foreach ($slotsIn as $slot) {
                if (! is_array($slot)) {
                    continue;
                }

                $source = strtolower(trim((string) ($slot['source'] ?? '')));

                if (! in_array($source, ['winner', 'loser'], true)) {
                    continue;
                }

                $gameNumber = (int) ($slot['crossover_game_number'] ?? $slot['game_number'] ?? 0);

                if ($gameNumber < 1) {
                    continue;
                }

                $slotsOut[] = [
                    'source' => $source,
                    'crossover_game_number' => $gameNumber,
                ];
            }

            if ($slotsOut === []) {
                continue;
            }

            $out[] = [
                'name' => $name,
                'slots' => $slotsOut,
            ];
        }

        return $out === [] ? null : $out;
    }

    /**
     * @return array{
     *     pending: bool,
     *     registration: null,
     *     team_id: null,
     *     team_name: null,
     *     registration_id: null,
     *     game_number: int|null,
     *     source: string,
     *     compact_label: string,
     *     crossover_round_label: string|null
     * }
     */
    protected static function pendingSlotResolution(?int $gameNumber, string $source, string $compactLabel, ?string $roundLabel): array
    {
        return [
            'pending' => true,
            'registration' => null,
            'team_id' => null,
            'team_name' => null,
            'registration_id' => null,
            'game_number' => $gameNumber,
            'source' => $source,
            'compact_label' => $compactLabel !== '' ? $compactLabel : ($source === 'loser' ? 'L?' : 'W?'),
            'crossover_round_label' => $roundLabel,
        ];
    }

    /**
     * Pool A / Pool B diagram slots are fully resolved (no pending placeholders) and crossover is decisively complete.
     *
     * @param  array<string, mixed>  $built  Output of {@see self::buildPoolingAssignments()}
     */
    public static function poolingFinalizedForQuarterFinalGeneration(array $built): bool
    {
        if (! ($built['has_matches'] ?? false)) {
            return false;
        }

        if (! ($built['all_crossover_finalized'] ?? false)) {
            return false;
        }

        if (($built['duplicate_team_warnings'] ?? []) !== []) {
            return false;
        }

        $pools = $built['pools'] ?? [];

        if (count($pools) < 2) {
            return false;
        }

        $totalSlots = 0;
        $resolvedSlots = 0;

        foreach ($pools as $pool) {
            foreach ($pool['rows'] ?? [] as $row) {
                $totalSlots++;

                if (($row['pending'] ?? true) === false && isset($row['registration_id']) && (int) $row['registration_id'] > 0) {
                    $resolvedSlots++;
                }
            }
        }

        return $totalSlots > 0 && $resolvedSlots === $totalSlots;
    }

    /**
     * Ordered registration IDs for pool columns (first two pools only), slot order → bracket seed order for QF pairing.
     *
     * @param  array<string, mixed>  $built  Output of {@see self::buildPoolingAssignments()}
     * @return array{0: list<int>, 1: list<int>}|null
     */
    public static function quarterFinalBracketColumnsRegistrationIds(array $built): ?array
    {
        $pools = $built['pools'] ?? [];

        if (count($pools) < 2) {
            return null;
        }

        $columnA = [];
        $columnB = [];

        foreach ($pools[0]['rows'] ?? [] as $row) {
            $rid = $row['registration_id'] ?? null;

            if (($row['pending'] ?? true) || ! is_numeric($rid) || (int) $rid <= 0) {
                return null;
            }

            $columnA[] = (int) $rid;
        }

        foreach ($pools[1]['rows'] ?? [] as $row) {
            $rid = $row['registration_id'] ?? null;

            if (($row['pending'] ?? true) || ! is_numeric($rid) || (int) $rid <= 0) {
                return null;
            }

            $columnB[] = (int) $rid;
        }

        if ($columnA === [] || $columnB === []) {
            return null;
        }

        return [$columnA, $columnB];
    }
}
