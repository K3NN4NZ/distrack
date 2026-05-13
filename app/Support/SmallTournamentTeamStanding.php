<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentRegistration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class SmallTournamentTeamStanding
{
    /**
     * Pool / group-play stages that count toward the same standings and head-to-head logic as `round_robin` matches.
     */
    private static function matchStageCountsForRoundRobinStandings(?string $stage): bool
    {
        $normalized = strtolower(trim((string) $stage));
        $normalized = str_replace(['-', ' '], '_', $normalized);
        $normalized = (string) preg_replace('/_+/', '_', $normalized);

        if ($normalized === '') {
            return false;
        }

        if (in_array($normalized, [
            'round_robin',
            'group',
            'group_play',
            'pool',
            'pool_play',
            'rr',
            'regular_season',
        ], true)) {
            return true;
        }

        $squashed = str_replace('_', '', $normalized);

        if (in_array($squashed, ['roundrobin', 'groupplay', 'poolplay', 'regularseason'], true)) {
            return true;
        }

        // Labels such as "Round Robin · Pitch 1", "round_robin_phase", etc.
        if (str_contains($normalized, 'round_robin')
            || (str_contains($normalized, 'round') && str_contains($normalized, 'robin'))) {
            return true;
        }

        return false;
    }

    /**
     * @return array{
     *     total: int,
     *     completed: int,
     *     is_complete: bool,
     *     standings_status: 'none'|'provisional'|'final',
     * }
     */
    public static function roundRobinScheduleCompletion(Tournament $tournament): array
    {
        $tournament->loadMissing(['matches', 'registrations']);

        $registrationIds = $tournament->registrations
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $state = self::accumulateRoundRobinMatchState($tournament, $registrationIds);

        return [
            'total' => $state['rr_total'],
            'completed' => $state['rr_completed'],
            'is_complete' => $state['rr_total'] === 0 || $state['rr_completed'] === $state['rr_total'],
            'standings_status' => $state['standings_status'],
        ];
    }

    /**
     * @param  list<int>  $registrationIds
     * @return array{
     *     stats: array<int, array{wins: int, losses: int, ties: int, goals_for: int, goals_against: int}>,
     *     rr_total: int,
     *     rr_completed: int,
     *     standings_status: 'none'|'provisional'|'final',
     * }
     */
    private static function accumulateRoundRobinMatchState(Tournament $tournament, array $registrationIds): array
    {
        $allowed = array_flip($registrationIds);

        $stats = [];
        foreach ($registrationIds as $id) {
            $stats[(int) $id] = [
                'wins' => 0,
                'losses' => 0,
                'ties' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
            ];
        }

        $rrTotal = 0;
        $rrCompleted = 0;

        foreach ($tournament->matches ?? [] as $match) {
            if (! $match instanceof TournamentMatch) {
                continue;
            }

            if (! self::matchStageCountsForRoundRobinStandings($match->stage)) {
                continue;
            }

            $homeId = (int) $match->home_registration_id;
            $awayId = (int) $match->away_registration_id;

            if (! isset($allowed[$homeId], $allowed[$awayId])) {
                continue;
            }

            $rrTotal++;

            if ($match->status !== 'completed' || $match->home_score === null || $match->away_score === null) {
                continue;
            }

            $rrCompleted++;

            $stats[$homeId]['goals_for'] += (int) $match->home_score;
            $stats[$homeId]['goals_against'] += (int) $match->away_score;
            $stats[$awayId]['goals_for'] += (int) $match->away_score;
            $stats[$awayId]['goals_against'] += (int) $match->home_score;

            if ($match->home_score > $match->away_score) {
                $stats[$homeId]['wins']++;
                $stats[$awayId]['losses']++;
            } elseif ($match->home_score < $match->away_score) {
                $stats[$awayId]['wins']++;
                $stats[$homeId]['losses']++;
            } else {
                $stats[$homeId]['ties']++;
                $stats[$awayId]['ties']++;
            }
        }

        $standingsStatus = $rrTotal === 0
            ? 'none'
            : ($rrCompleted === $rrTotal ? 'final' : 'provisional');

        return [
            'stats' => $stats,
            'rr_total' => $rrTotal,
            'rr_completed' => $rrCompleted,
            'standings_status' => $standingsStatus,
        ];
    }

    /**
     * Relative order for two registrations using **one** decisive completed pool/round-robin direct match
     * ({@see findDecisiveDirectMatch}), ignoring playoff/knockout rows. Returns 0 when no decisive meeting exists.
     */
    public static function compareHeadToHead(int $registrationIdA, int $registrationIdB, Tournament $tournament): int
    {
        if ($registrationIdA === $registrationIdB) {
            return 0;
        }

        $match = self::findDecisiveDirectMatch($tournament, $registrationIdA, $registrationIdB);

        if ($match === null) {
            return 0;
        }

        $winnerId = (int) $match->home_score > (int) $match->away_score
            ? (int) $match->home_registration_id
            : (int) $match->away_registration_id;

        if ($winnerId === $registrationIdA) {
            return -1;
        }

        if ($winnerId === $registrationIdB) {
            return 1;
        }

        return 0;
    }

    public static function resolveRoundRobinAdvancingTeamCount(Tournament $tournament, int $registeredTeamCount): int
    {
        if ($tournament->round_robin_advancing_count !== null) {
            return max(1, min($registeredTeamCount, (int) $tournament->round_robin_advancing_count));
        }

        $stages = collect($tournament->matches ?? [])
            ->pluck('stage')
            ->filter()
            ->unique();

        $normalized = $stages->map(static function ($stage): string {
            $s = strtolower(trim((string) $stage));
            $s = str_replace(['-', ' '], '_', $s);

            return (string) preg_replace('/_+/', '_', $s);
        });

        if ($normalized->contains(fn (string $s): bool => str_contains($s, 'championship'))) {
            return min(2, $registeredTeamCount);
        }

        if ($normalized->contains(fn (string $s): bool => str_contains($s, 'semifinal'))) {
            return min(4, $registeredTeamCount);
        }

        if ($normalized->contains(fn (string $s): bool => str_contains($s, 'quarter'))) {
            return min(8, $registeredTeamCount);
        }

        return $registeredTeamCount;
    }

    /**
     * @return array{
     *     rows: Collection<int, array<string, mixed>>,
     *     meta: array<string, mixed>,
     * }
     */
    public static function roundRobinTeamStanding(Tournament $tournament): array
    {
        $tournament->loadMissing(['matches', 'registrations.team']);

        $registrations = $tournament->registrations
            ->sort(static fn ($left, $right) => [
                $left->seed_number ?? PHP_INT_MAX,
                $left->team?->name ?? '',
                $left->id,
            ] <=> [
                $right->seed_number ?? PHP_INT_MAX,
                $right->team?->name ?? '',
                $right->id,
            ])
            ->values();

        $registrationIds = $registrations->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        $progress = self::accumulateRoundRobinMatchState($tournament, $registrationIds);
        $stats = $progress['stats'];
        $rrTotal = $progress['rr_total'];
        $rrCompleted = $progress['rr_completed'];
        $standingsStatus = $progress['standings_status'];
        $standingsFinal = $standingsStatus === 'final';

        $baseRows = $registrations->map(static function (TournamentRegistration $registration) use ($stats): array {
            $id = (int) $registration->id;
            $s = $stats[$id];
            $wins = $s['wins'];
            $losses = $s['losses'];
            $ties = $s['ties'];

            return [
                'registration_id' => $id,
                'team_id' => $registration->team_id !== null ? (int) $registration->team_id : null,
                'seed_sort_key' => $registration->seed_number ?? PHP_INT_MAX,
                'seed_display' => $registration->seed_number !== null ? (string) $registration->seed_number : '—',
                'team_name' => $registration->team?->name ?? '—',
                'wins' => $wins,
                'losses' => $losses,
                'ties' => $ties,
                'rank_score' => $wins - $losses,
                'point_differential' => $s['goals_for'] - $s['goals_against'],
                'accumulated_score' => $s['goals_for'],
                'goals_against' => $s['goals_against'],
            ];
        });

        $toPublicRow = static function (array $row, string $rankDisplay): array {
            $tb = $row['tiebreaker'] ?? null;
            $tiebreakerNote = null;
            if (is_array($tb)) {
                $tiebreakerNote = $tb['tiebreaker_note'] ?? $tb['note'] ?? null;
            }

            $out = [
                'registration_id' => (int) ($row['registration_id'] ?? 0),
                'seed_display' => $row['seed_display'],
                'team_name' => $row['team_name'],
                'team_id' => $row['team_id'] ?? null,
                'wins' => $row['wins'],
                'losses' => $row['losses'],
                'ties' => $row['ties'],
                'rank_score' => $row['rank_score'],
                'point_differential' => $row['point_differential'],
                'accumulated_score' => $row['accumulated_score'],
                'rank_display' => $rankDisplay,
                'tiebreaker_note' => $tiebreakerNote,
                'remarks' => $tiebreakerNote,
                'note' => $tiebreakerNote,
            ];

            if (isset($row['tiebreaker']) && is_array($row['tiebreaker'])) {
                $out['tiebreaker'] = $row['tiebreaker'];
            }

            return $out;
        };

        $totalTeams = $registrations->count();
        $advancing = self::resolveRoundRobinAdvancingTeamCount($tournament, $totalTeams);

        if ($rrCompleted === 0) {
            $rows = $baseRows->map(static function (array $row) use ($toPublicRow): array {
                $r = $toPublicRow($row, '—');
                $r['rank'] = null;
                $r['registration_id'] = (int) ($row['registration_id'] ?? 0);
                $r['is_eliminated'] = false;
                $r['is_provisional_below_cutoff'] = false;
                $r['elimination_note'] = null;
                $r['advancement_note'] = null;

                return $r;
            })->values();

            return [
                'rows' => $rows,
                'meta' => self::buildRoundRobinStandingMeta(
                    $totalTeams,
                    $advancing,
                    0,
                    false,
                    $standingsStatus,
                    $rrTotal,
                    $rrCompleted,
                    0,
                ),
            ];
        }

        $sortedRows = self::sortRowsByRankScoreWithTiebreakers($baseRows->all(), $tournament);

        self::debugLogStandingRankScoreManifest($tournament, $sortedRows);

        $out = [];
        foreach ($sortedRows as $index => $row) {
            $out[] = $toPublicRow($row, (string) ($index + 1));
        }

        $wouldEliminateCount = max(0, $totalTeams - $advancing);
        $appliesEliminationFinal = $standingsFinal && $wouldEliminateCount > 0;
        $sharedAdvancementNoteFinal = $appliesEliminationFinal
            ? __('Only top :n teams advance.', ['n' => $advancing])
            : null;
        $sharedAdvancementNoteProvisional = (! $standingsFinal && $standingsStatus === 'provisional' && $wouldEliminateCount > 0)
            ? __('If the final order matches today, only the top :n teams advance. This can change after the remaining games.', ['n' => $advancing])
            : null;

        $provisionalBelowCount = 0;

        foreach ($out as $i => $row) {
            $rank = $i + 1;
            $belowCutoff = $rank > $advancing;

            if ($standingsFinal) {
                $isEliminated = $belowCutoff;
                $isProvisionalBelow = false;
                $eliminationNote = $belowCutoff ? __('Will not advance') : null;
                $advancementNote = $belowCutoff ? $sharedAdvancementNoteFinal : null;
            } else {
                $isEliminated = false;
                $isProvisionalBelow = $standingsStatus === 'provisional' && $belowCutoff;
                if ($isProvisionalBelow) {
                    $provisionalBelowCount++;
                }
                $eliminationNote = $isProvisionalBelow ? __('Currently below cutoff') : null;
                $advancementNote = $isProvisionalBelow ? $sharedAdvancementNoteProvisional : null;
            }

            $out[$i]['rank'] = $rank;
            $out[$i]['is_eliminated'] = $isEliminated;
            $out[$i]['is_provisional_below_cutoff'] = $isProvisionalBelow;
            $out[$i]['elimination_note'] = $eliminationNote;
            $out[$i]['advancement_note'] = $advancementNote;
        }

        $eliminatedCountMeta = $standingsFinal ? $wouldEliminateCount : 0;

        return [
            'rows' => collect($out)->values(),
            'meta' => self::buildRoundRobinStandingMeta(
                $totalTeams,
                $advancing,
                $eliminatedCountMeta,
                $appliesEliminationFinal,
                $standingsStatus,
                $rrTotal,
                $rrCompleted,
                $provisionalBelowCount,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildRoundRobinStandingMeta(
        int $totalTeams,
        int $advancing,
        int $eliminatedTeamsCount,
        bool $appliesEliminationFinal,
        string $standingsStatus,
        int $rrTotal,
        int $rrCompleted,
        int $provisionalBelowCount,
    ): array {
        $advancementSummary = $appliesEliminationFinal
            ? __('Top :x teams advance. Bottom :y team(s) will not advance.', ['x' => $advancing, 'y' => $eliminatedTeamsCount])
            : null;

        $standingsConfirmationNote = ($standingsStatus === 'provisional' && $rrTotal > 0)
            ? __('Final ranks and advancing teams will be confirmed after all Round Robin games are completed.')
            : null;

        $roundRobinProgressNote = ($standingsStatus === 'provisional' && $rrTotal > 0)
            ? __(':done of :total Round Robin games completed.', ['done' => $rrCompleted, 'total' => $rrTotal])
            : null;

        $provisionalCutoffSummary = ($standingsStatus === 'provisional' && $provisionalBelowCount > 0)
            ? __('Based on completed games so far, :y team(s) are below the advancing cutoff (top :x).', ['x' => $advancing, 'y' => $provisionalBelowCount])
            : null;

        return [
            'total_teams' => $totalTeams,
            'advancing_teams_count' => $advancing,
            'eliminated_teams_count' => $eliminatedTeamsCount,
            'advancement_summary' => $advancementSummary,
            'applies_elimination' => $appliesEliminationFinal,
            'standings_status' => $standingsStatus,
            'round_robin_matches_total' => $rrTotal,
            'round_robin_matches_completed' => $rrCompleted,
            'standings_confirmation_note' => $standingsConfirmationNote,
            'round_robin_progress_note' => $roundRobinProgressNote,
            'provisional_cutoff_summary' => $provisionalCutoffSummary,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private static function sortRowsByRankScoreWithTiebreakers(array $rows, Tournament $tournament): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $rs = (int) ($row['rank_score'] ?? 0);
            $grouped[$rs][] = $row;
        }

        $rankScores = array_keys($grouped);
        rsort($rankScores, SORT_NUMERIC);

        $ordered = [];
        foreach ($rankScores as $rs) {
            $group = $grouped[$rs];
            $ordered = array_merge($ordered, self::sortTieGroup((int) $rs, $group, $tournament));
        }

        return self::annotateTiebreakers($ordered, $tournament);
    }

    /**
     * @param  list<array<string, mixed>>  $group
     * @return list<array<string, mixed>>
     */
    private static function sortTieGroup(int $rankScore, array $group, Tournament $tournament): array
    {
        $n = count($group);
        if ($n <= 1) {
            return $group;
        }

        $beforeNames = array_column($group, 'team_name');

        if ($n === 2) {
            $a = $group[0];
            $b = $group[1];
            $idA = (int) $a['registration_id'];
            $idB = (int) $b['registration_id'];
            $direct = self::findDecisiveDirectMatch($tournament, $idA, $idB);

            if ($direct !== null) {
                $winnerId = (int) $direct->home_score > (int) $direct->away_score
                    ? (int) $direct->home_registration_id
                    : (int) $direct->away_registration_id;
                $sorted = $winnerId === $idA ? [$a, $b] : [$b, $a];
                self::debugLogStandingTieGroup(
                    $tournament,
                    $rankScore,
                    $beforeNames,
                    array_column($sorted, 'team_name'),
                    'head_to_head',
                    $direct,
                );

                return $sorted;
            }

            usort($group, static fn (array $x, array $y): int => self::compareSecondaryStanding($x, $y));
            self::debugLogStandingTieGroup(
                $tournament,
                $rankScore,
                $beforeNames,
                array_column($group, 'team_name'),
                'fallback_secondary',
                null,
            );

            return $group;
        }

        usort($group, static fn (array $x, array $y): int => self::compareSecondaryStanding($x, $y));
        self::debugLogStandingTieGroup(
            $tournament,
            $rankScore,
            $beforeNames,
            array_column($group, 'team_name'),
            'accumulated_points',
            null,
        );

        return $group;
    }

    /**
     * @param  list<string>  $namesBefore
     * @param  list<string>  $namesAfter
     */
    private static function debugLogStandingTieGroup(
        Tournament $tournament,
        int $rankScore,
        array $namesBefore,
        array $namesAfter,
        string $tiebreakerUsed,
        ?TournamentMatch $directMatch,
    ): void {
        if (! config('app.debug')) {
            return;
        }

        Log::debug('team_standing.tie_group', [
            'tournament_id' => $tournament->id,
            'rank_score' => $rankScore,
            'group_size' => count($namesBefore),
            'team_names' => $namesBefore,
            'tiebreaker_used' => $tiebreakerUsed,
            'direct_match_id' => $directMatch?->id,
            'direct_match_score' => $directMatch !== null
                ? ((int) $directMatch->home_score).'-'.((int) $directMatch->away_score)
                : null,
            'final_order' => $namesAfter,
        ]);
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private static function compareSecondaryStanding(array $a, array $b): int
    {
        foreach ([
            ((int) $b['accumulated_score']) <=> ((int) $a['accumulated_score']),
            ((int) $b['point_differential']) <=> ((int) $a['point_differential']),
            ((int) $a['goals_against']) <=> ((int) $b['goals_against']),
            ((string) $a['team_name']) <=> ((string) $b['team_name']),
            ((int) $a['seed_sort_key']) <=> ((int) $b['seed_sort_key']),
            ((int) $a['registration_id']) <=> ((int) $b['registration_id']),
        ] as $cmp) {
            if ($cmp !== 0) {
                return $cmp;
            }
        }

        return 0;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private static function annotateTiebreakers(array $rows, Tournament $tournament): array
    {
        $n = count($rows);
        $i = 0;
        while ($i < $n) {
            $start = $i;
            $rs = (int) ($rows[$i]['rank_score'] ?? 0);
            while ($i + 1 < $n && (int) ($rows[$i + 1]['rank_score'] ?? 0) === $rs) {
                $i++;
            }
            $len = $i - $start + 1;
            if ($len >= 2) {
                $slice = array_slice($rows, $start, $len);
                self::applyTiebreakerMetadata($slice, $tournament);
                for ($k = 0; $k < $len; $k++) {
                    $rows[$start + $k] = $slice[$k];
                }
            }
            $i++;
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $slice
     */
    private static function applyTiebreakerMetadata(array &$slice, Tournament $tournament): void
    {
        $len = count($slice);
        if ($len < 2) {
            return;
        }

        if ($len === 2) {
            $a = $slice[0];
            $b = $slice[1];
            $idA = (int) $a['registration_id'];
            $idB = (int) $b['registration_id'];
            $match = self::findDecisiveDirectMatch($tournament, $idA, $idB);

            self::debugLogTwoTeamHeadToHeadDiagnostics(
                $tournament,
                $idA,
                $idB,
                (string) ($a['team_name'] ?? ''),
                (string) ($b['team_name'] ?? ''),
                $match,
            );

            if ($match !== null) {
                $winnerId = (int) $match->home_score > (int) $match->away_score
                    ? (int) $match->home_registration_id
                    : (int) $match->away_registration_id;

                foreach ($slice as $idx => $row) {
                    $rid = (int) $row['registration_id'];
                    $won = $rid === $winnerId;
                    $opponentName = $rid === $idA ? (string) ($b['team_name'] ?? '—') : (string) ($a['team_name'] ?? '—');
                    $slice[$idx]['tiebreaker'] = [
                        'type' => 'head_to_head',
                        'tiebreaker_type' => 'head_to_head',
                        'result' => $won ? 'won' : 'lost',
                        'tiebreaker_result' => $won ? 'won' : 'lost',
                        'direct_match_id' => (int) $match->id,
                        'badge' => __('Head-to-head'),
                        'tiebreaker_note' => $won
                            ? __('Head-to-head winner · Won direct match vs :opponent', ['opponent' => $opponentName])
                            : __('Lost head-to-head vs :opponent', ['opponent' => $opponentName]),
                    ];
                }

                return;
            }

            $fallbackNote = __('No decisive completed round-robin match between these teams; order uses accumulated points, goal difference, and goals against.');

            foreach ($slice as $idx => $_row) {
                $slice[$idx]['tiebreaker'] = [
                    'type' => 'tie_secondary',
                    'tiebreaker_type' => 'tie_secondary',
                    'badge' => __('Ranking tiebreaker'),
                    'tiebreaker_note' => $fallbackNote,
                ];
            }

            return;
        }

        $multiNote = __('Accumulated points tiebreaker');

        foreach ($slice as $idx => $_row) {
            $slice[$idx]['tiebreaker'] = [
                'type' => 'accumulated_points_multi',
                'tiebreaker_type' => 'accumulated_points_multi',
                'badge' => $multiNote,
                'tiebreaker_note' => $multiNote,
            ];
        }
    }

    /**
     * Completed decisive pool/RR row between two registrations. Uses {@code home_registration_id} /
     * {@code away_registration_id} as stored on {@code matches}. Queries the database so head-to-head is not
     * missed when the in-memory relation is partial or filtered.
     */
    /**
     * Same notion of “game finished” as used when resolving head-to-head (not necessarily identical to RR standings accumulation).
     *
     * @param  list<string>  $alsoCompletedStatuses
     */
    private static function matchStatusIndicatesRoundRobinComplete(?string $status, array $alsoCompletedStatuses = ['completed', 'complete', 'done', 'final']): bool
    {
        $needle = strtolower(trim((string) $status));

        foreach ($alsoCompletedStatuses as $token) {
            if ($needle === strtolower((string) $token)) {
                return true;
            }
        }

        return false;
    }

    private static function findDecisiveDirectMatch(Tournament $tournament, int $registrationIdA, int $registrationIdB): ?TournamentMatch
    {
        $tournamentId = (int) $tournament->getKey();

        $candidates = TournamentMatch::query()
            ->where('tournament_id', $tournamentId)
            ->where(function ($query): void {
                foreach (['completed', 'complete', 'done', 'final'] as $token) {
                    $query->orWhereRaw('lower(trim(status)) = ?', [strtolower((string) $token)]);
                }
            })
            ->whereNotNull('home_registration_id')
            ->whereNotNull('away_registration_id')
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->whereRaw('home_score != away_score')
            ->where(function ($query) use ($registrationIdA, $registrationIdB): void {
                $query->where(function ($inner) use ($registrationIdA, $registrationIdB): void {
                    $inner->where('home_registration_id', $registrationIdA)
                        ->where('away_registration_id', $registrationIdB);
                })->orWhere(function ($inner) use ($registrationIdA, $registrationIdB): void {
                    $inner->where('home_registration_id', $registrationIdB)
                        ->where('away_registration_id', $registrationIdA);
                });
            })
            ->orderByRaw('case when match_number is null then 1 else 0 end')
            ->orderBy('match_number')
            ->orderBy('id')
            ->get();

        foreach ($candidates as $match) {
            if (self::matchStageCountsForRoundRobinStandings($match->stage)) {
                return $match;
            }
        }

        return null;
    }

    private static function debugLogTwoTeamHeadToHeadDiagnostics(
        Tournament $tournament,
        int $registrationIdA,
        int $registrationIdB,
        string $teamNameA,
        string $teamNameB,
        ?TournamentMatch $decisiveMatch,
    ): void {
        if (! config('app.debug')) {
            return;
        }

        $directRows = TournamentMatch::query()
            ->where('tournament_id', (int) $tournament->getKey())
            ->where(function ($query) use ($registrationIdA, $registrationIdB): void {
                $query->where(function ($inner) use ($registrationIdA, $registrationIdB): void {
                    $inner->where('home_registration_id', $registrationIdA)
                        ->where('away_registration_id', $registrationIdB);
                })->orWhere(function ($inner) use ($registrationIdA, $registrationIdB): void {
                    $inner->where('home_registration_id', $registrationIdB)
                        ->where('away_registration_id', $registrationIdA);
                });
            })
            ->orderByRaw('case when match_number is null then 1 else 0 end')
            ->orderBy('match_number')
            ->orderBy('id')
            ->get(['id', 'match_number', 'stage', 'status', 'home_registration_id', 'away_registration_id', 'home_score', 'away_score']);

        $candidateDiagnostics = $directRows->map(function (TournamentMatch $m): array {
            $scoresPresent = $m->home_score !== null && $m->away_score !== null;
            $decisive = $scoresPresent && ((int) $m->home_score !== (int) $m->away_score);
            $stageOk = self::matchStageCountsForRoundRobinStandings($m->stage);
            $statusOk = self::matchStatusIndicatesRoundRobinComplete($m->status);
            $reject = [];
            if (! $stageOk) {
                $reject[] = 'stage_not_round_robin_like';
            }
            if (! $statusOk) {
                $reject[] = 'status_not_completed_like';
            }
            if (! $scoresPresent) {
                $reject[] = 'scores_missing';
            } elseif (! $decisive) {
                $reject[] = 'score_tied';
            }

            $scoreLabel = $scoresPresent ? ((int) $m->home_score).'-'.((int) $m->away_score) : 'null-null';

            return [
                'match_id' => $m->id,
                'match_number' => $m->match_number,
                'stage' => $m->stage,
                'stage_eligible' => $stageOk,
                'status' => $m->status,
                'status_eligible' => $statusOk,
                'score' => $scoreLabel,
                'would_apply_head_to_head' => $stageOk && $statusOk && $decisive,
                'reject_reasons' => $reject,
            ];
        })->values()->all();

        Log::debug('team_standing.two_team_head_to_head', [
            'tournament_id' => (int) $tournament->getKey(),
            'registration_id_a' => $registrationIdA,
            'registration_id_b' => $registrationIdB,
            'team_a' => $teamNameA,
            'team_b' => $teamNameB,
            'decisive_match_id' => $decisiveMatch?->id,
            'head_to_head_applied' => $decisiveMatch !== null,
            'direct_registration_pair_candidates' => $directRows->count(),
            'candidate_evaluation' => $candidateDiagnostics,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $sortedRows
     */
    private static function debugLogStandingRankScoreManifest(Tournament $tournament, array $sortedRows): void
    {
        if (! config('app.debug')) {
            return;
        }

        $groups = [];
        foreach ($sortedRows as $row) {
            $rs = (int) ($row['rank_score'] ?? 0);
            $groups[$rs][] = [
                'team_name' => (string) ($row['team_name'] ?? ''),
                'registration_id' => (int) ($row['registration_id'] ?? 0),
            ];
        }
        krsort($groups, SORT_NUMERIC);

        Log::debug('team_standing.rank_score_groups', [
            'tournament_id' => (int) $tournament->getKey(),
            'groups' => $groups,
        ]);

        foreach ($groups as $rankScore => $members) {
            if (count($members) !== 2) {
                continue;
            }

            $idA = $members[0]['registration_id'];
            $idB = $members[1]['registration_id'];
            $direct = self::findDecisiveDirectMatch($tournament, $idA, $idB);

            $winnerRegId = null;
            if ($direct !== null) {
                $winnerRegId = (int) $direct->home_score > (int) $direct->away_score
                    ? (int) $direct->home_registration_id
                    : (int) $direct->away_registration_id;
            }

            $winnerName = null;
            if ($winnerRegId === $idA) {
                $winnerName = $members[0]['team_name'];
            } elseif ($winnerRegId === $idB) {
                $winnerName = $members[1]['team_name'];
            }

            Log::debug('team_standing.two_team_rank_score_tie', [
                'tournament_id' => (int) $tournament->getKey(),
                'rank_score' => $rankScore,
                'teams' => $members,
                'direct_round_robin_match_id' => $direct?->id,
                'direct_match_score' => $direct !== null
                    ? ((int) $direct->home_score).'-'.((int) $direct->away_score)
                    : null,
                'winner_registration_id' => $winnerRegId,
                'winner_team_name' => $winnerName,
                'tiebreaker_used' => $direct !== null ? 'head_to_head' : 'none_found',
            ]);
        }
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function forRoundRobin(Tournament $tournament): Collection
    {
        return self::roundRobinTeamStanding($tournament)['rows'];
    }
}
