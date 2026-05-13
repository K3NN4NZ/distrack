<?php

namespace Database\Seeders;

use App\Http\Controllers\Admin\TournamentController;
use App\Models\MatchPlayerStat;
use App\Models\MatchScoreLog;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Support\SmallFixedRoundRobinDayOneSchedule;
use App\Support\SmallTournamentTeamStanding;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fills per-player stats for Round Robin "Day 1" matches on tournament 1, then aligns match totals.
 *
 * Run: php artisan db:seed --class=RoundRobinDay1ScoreSeeder
 *
 * Day 1 filter (no dedicated `day` column on `matches`):
 * - Below {@see TournamentController::MINIMUM_BRACKET_TEAM_COUNT} registered teams, the admin Round Robin tab
 *   uses {@see SmallFixedRoundRobinDayOneSchedule}: Day 1 games are `stage = round_robin` rows whose `notes`
 *   contain the `[[small-day1:` marker (Pitch 1 / Pitch 2 fixed grid).
 * - At or above that threshold, every `round_robin` row for the tournament is treated as Day 1 bracket RR.
 *
 * Scoring form source of truth ({@see resources/views/admin/tournaments/scoring.blade.php},
 * {@see TournamentController::updateMatchPlayerStat}, {@see TournamentController::syncMatchScoreFromPlayerStats}):
 * - Each roster row reads {@see MatchPlayerStat} (`match_player_stats`: goals, assists, blocks per `team_member_id`).
 * - `matches.home_score` / `away_score` are the sums of **goals** on each side (not assists/blocks).
 * - {@see MatchScoreLog} drives the point-by-point timeline; we clear logs so totals come from player stats only.
 *
 * Team Standing ({@see SmallTournamentTeamStanding}): primary sort is `rank_score = wins − losses`; exactly
 * two teams tied on rank_score use head-to-head from a completed direct round-robin match; three or more tied
 * use accumulated points (goals for), then goal difference, etc.
 *
 * This seeder fills **every** eligible Day 1 match with player stats and completed scores.
 *
 * **Head-to-head (2-team) demo:** When the schedule contains a K₂,₂ helper pattern, five matches are scripted
 * for teams A–D so A and B finish with the same rank_score while A wins the direct meeting; A-only and B-only
 * peripherals are mirrored so A and B stay tied before those five games are applied.
 *
 * **Accumulated-points (3-team) demo:** Three registrations outside {A,B,C,D} are chosen so each pair has a
 * Day 1 match. Their non–triple-team games are mirrored across the three (same win/loss pattern vs each
 * outsider), then a 3-cycle is scored with different goals-for so rank_score stays tied within the trio but
 * accumulated points break the tie (P best, then Q, then R).
 */
class RoundRobinDay1ScoreSeeder extends Seeder
{
    public const TOURNAMENT_ID = 1;

    /** Max goals per player (matches admin input max in practice for seed data). */
    private const MAX_GOALS_PER_PLAYER = 5;

    /** Cap each team's summed goals when choosing team totals (generic fill). */
    private const MAX_TEAM_GOALS = 15;

    private const MAX_ASSISTS_PER_PLAYER = 2;

    private const MAX_BLOCKS_PER_PLAYER = 2;

    /** Head-to-head fixture: winner (Team A = home) goals vs loser (Team B) goals. */
    private const HEAD_TO_HEAD_WINNER_GOALS = 12;

    private const HEAD_TO_HEAD_LOSER_GOALS = 9;

    /** Scenario helpers: A loses to C, A beats D, B beats C, B beats D (winner goals, loser goals). */
    private const SCENARIO_A_LOSES_TO_C_WINNER = 15;

    private const SCENARIO_A_LOSES_TO_C_LOSER = 10;

    private const SCENARIO_A_BEATS_D_WINNER = 5;

    private const SCENARIO_A_BEATS_D_LOSER = 0;

    private const SCENARIO_B_BEATS_C_WINNER = 30;

    private const SCENARIO_B_BEATS_C_LOSER = 0;

    private const SCENARIO_B_BEATS_D_WINNER = 5;

    private const SCENARIO_B_BEATS_D_LOSER = 0;

    /**
     * Internal 3-cycle among P,Q,R after mirrored peripherals: each finishes 1W-1L in the mini-league with
     * distinct goals-for so {@see SmallTournamentTeamStanding} orders them by accumulated points (P > Q > R).
     */
    private const TRIPLE_P_BEATS_Q_WINNER = 60;

    private const TRIPLE_P_BEATS_Q_LOSER = 20;

    private const TRIPLE_Q_BEATS_R_WINNER = 50;

    private const TRIPLE_Q_BEATS_R_LOSER = 15;

    private const TRIPLE_R_BEATS_P_WINNER = 40;

    private const TRIPLE_R_BEATS_P_LOSER = 25;

    public function run(): void
    {
        $tournament = Tournament::query()->find(self::TOURNAMENT_ID);

        if ($tournament === null) {
            $this->command?->warn('Tournament ID '.self::TOURNAMENT_ID.' not found; skipping.');

            return;
        }

        $registrationCount = $tournament->registrations()->count();

        $matchesQuery = TournamentMatch::query()
            ->where('tournament_id', $tournament->id)
            ->where('stage', 'round_robin')
            ->whereNotNull('home_registration_id')
            ->whereNotNull('away_registration_id');

        if ($registrationCount < TournamentController::MINIMUM_BRACKET_TEAM_COUNT) {
            $matchesQuery->where('notes', 'like', '%'.SmallFixedRoundRobinDayOneSchedule::MARKER_PREFIX.'%');
        }

        $matches = $matchesQuery
            ->orderBy('match_number')
            ->orderBy('id')
            ->get();

        if ($matches->isEmpty()) {
            $this->command?->info('No eligible Day 1 round robin matches (two teams) found for tournament '.self::TOURNAMENT_ID.'.');

            return;
        }

        foreach ($matches as $match) {
            self::wipeMatchScoresOnly($match);
        }

        $scenario = self::tryFindHeadToHeadTieScenario($matches);

        $triple = ($scenario !== null)
            ? self::tryFindTripleRankScoreTieScenario($tournament, $scenario, $matches)
            : null;

        /** @var array<int, true> $scenarioMatchIds */
        $scenarioMatchIds = [];
        if ($scenario !== null) {
            foreach ([
                $scenario['head_to_head'],
                $scenario['ac'],
                $scenario['ad'],
                $scenario['bc'],
                $scenario['bd'],
            ] as $m) {
                $scenarioMatchIds[(int) $m->id] = true;
            }
        }

        /** @var array<int, true> $tripleMatchIds */
        $tripleMatchIds = [];
        if ($triple !== null) {
            foreach ([$triple['pq'], $triple['pr'], $triple['qr']] as $m) {
                $tripleMatchIds[(int) $m->id] = true;
            }
        }

        $regA = $scenario !== null ? $scenario['team_a_registration_id'] : null;
        $regB = $scenario !== null ? $scenario['team_b_registration_id'] : null;

        $pReg = $triple !== null ? (int) $triple['p_registration_id'] : null;
        $qReg = $triple !== null ? (int) $triple['q_registration_id'] : null;
        $rReg = $triple !== null ? (int) $triple['r_registration_id'] : null;

        /** @var list<int> $tripleSkipOpponentIds */
        $tripleSkipOpponentIds = ($triple !== null && $pReg !== null && $qReg !== null && $rReg !== null)
            ? [$pReg, $qReg, $rReg]
            : [];

        $salt = 0;
        foreach ($matches as $match) {
            if (isset($scenarioMatchIds[(int) $match->id])) {
                continue;
            }

            if (isset($tripleMatchIds[(int) $match->id])) {
                continue;
            }

            if ($triple !== null && $pReg !== null && $qReg !== null && $rReg !== null
                && self::matchInvolvesExactlyOneTripleTeam($match, $pReg, $qReg, $rReg)) {
                continue;
            }

            if ($scenario !== null && $regA !== null && $regB !== null && self::matchInvolvesNeitherAb($match, $regA, $regB)) {
                $salt++;
                if (self::seedGenericDay1Match($match, $salt * 1_000_003 + (int) $match->id)) {
                    continue;
                }
                $this->command?->warn('Skipped generic match '.$match->id.' (missing roster).');

                continue;
            }

            if ($scenario !== null) {
                continue;
            }

            $salt++;
            if (! self::seedGenericDay1Match($match, $salt * 1_000_003 + (int) $match->id)) {
                $this->command?->warn('Skipped generic match '.$match->id.' (missing roster).');
            }
        }

        if ($triple !== null && $pReg !== null && $qReg !== null && $rReg !== null) {
            self::seedMirroredPeripheralsForTriple($matches, $scenarioMatchIds, $tripleMatchIds, $pReg, $qReg, $rReg);
        }

        if ($scenario !== null && $regA !== null && $regB !== null) {
            self::seedMirroredPeripheralsForAb($matches, $scenarioMatchIds, $regA, $regB, $tripleSkipOpponentIds);
            self::applyHeadToHeadScenarioMatches($scenario);
        }

        if ($triple !== null) {
            self::applyTripleInternalCycle($triple);
        }

        $matchIds = $matches->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        $matchesCompleted = (int) TournamentMatch::query()
            ->whereIn('id', $matchIds)
            ->where('status', 'completed')
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->count();

        $matchesSkippedNoRoster = $matches->count() - $matchesCompleted;

        $tournament->unsetRelation('matches');
        $tournament->load([
            'matches' => fn ($q) => $q->orderByRaw('case when scheduled_at is null then 1 else 0 end')
                ->orderBy('scheduled_at')
                ->orderBy('match_number')
                ->orderBy('id'),
            'registrations.team',
        ]);

        $playerStatRows = MatchPlayerStat::query()->whereIn('match_id', $matchIds)->count();

        $this->command?->newLine();
        $this->command?->info('── Round Robin Day 1 score seeding ('.self::TOURNAMENT_ID.') ──');
        $this->command?->info("Total Day 1 matches (target set): {$matches->count()}");
        $this->command?->info("Matches completed (with scores): {$matchesCompleted}");
        if ($matchesSkippedNoRoster > 0) {
            $this->command?->warn("Matches skipped (missing roster on one side): {$matchesSkippedNoRoster}");
        }
        $this->command?->info("Player score rows (match_player_stats for these Day 1 matches): {$playerStatRows}");

        if ($scenario !== null) {
            $this->logHeadToHeadScenarioSummary($tournament, $scenario);
        } else {
            $this->command?->warn('No K₂,₂ head-to-head demo pattern in this Day 1 graph; all matches were filled with generic scores only.');
        }

        if ($triple !== null) {
            $this->logTripleAccumulatedPointsScenarioSummary($tournament, $triple);
        } elseif ($scenario !== null) {
            $this->command?->warn('No separate 3-team triangle found outside the head-to-head helper set; accumulated-points tie demo skipped.');
        }

        $this->command?->info('Done.');
    }

    /**
     * @param array{
     *     head_to_head: TournamentMatch,
     *     team_a_registration_id: int,
     *     team_b_registration_id: int,
     *     helper_c_registration_id: int,
     *     helper_d_registration_id: int,
     *     ac: TournamentMatch,
     *     ad: TournamentMatch,
     *     bc: TournamentMatch,
     *     bd: TournamentMatch,
     * } $scenario
     */
    private function logHeadToHeadScenarioSummary(Tournament $tournament, array $scenario): void
    {
        $tournament->loadMissing(['registrations.team']);

        $name = static function (int $registrationId) use ($tournament): string {
            $reg = $tournament->registrations->firstWhere('id', $registrationId);

            return $reg?->team?->name ?? 'Registration '.$registrationId;
        };

        $regA = $scenario['team_a_registration_id'];
        $regB = $scenario['team_b_registration_id'];
        $teamAName = $name($regA);
        $teamBName = $name($regB);

        $ab = TournamentMatch::query()->find($scenario['head_to_head']->id) ?? $scenario['head_to_head'];
        $homeReg = (int) $ab->home_registration_id;
        $awayReg = (int) $ab->away_registration_id;
        $homeName = $name($homeReg);
        $awayName = $name($awayReg);

        $rows = SmallTournamentTeamStanding::forRoundRobin($tournament);
        $rowA = $rows->first(static fn (array $row): bool => ($row['team_name'] ?? '') === $teamAName);
        $rowB = $rows->first(static fn (array $row): bool => ($row['team_name'] ?? '') === $teamBName);

        $this->command?->info('── 2-team tie (head-to-head tiebreaker) ──');
        $this->command?->line('Teams A and B share the same rank_score after Day 1; A wins the completed direct RR match.');
        $this->command?->line("  • Team A (registration {$regA}): {$teamAName}");
        $this->command?->line("  • Team B (registration {$regB}): {$teamBName}");

        if (is_array($rowA)) {
            $w = $rowA['wins'] ?? '—';
            $l = $rowA['losses'] ?? '—';
            $rs = $rowA['rank_score'] ?? '—';
            $this->command?->line("  • {$teamAName}: {$w}W {$l}L, rank_score {$rs}");
        } else {
            $this->command?->warn("  • Could not resolve standing row for {$teamAName}.");
        }
        if (is_array($rowB)) {
            $w = $rowB['wins'] ?? '—';
            $l = $rowB['losses'] ?? '—';
            $rs = $rowB['rank_score'] ?? '—';
            $this->command?->line("  • {$teamBName}: {$w}W {$l}L, rank_score {$rs}");
        } else {
            $this->command?->warn("  • Could not resolve standing row for {$teamBName}.");
        }

        $winnerLabel = ((int) $ab->home_score > (int) $ab->away_score) ? $homeName : (((int) $ab->away_score > (int) $ab->home_score) ? $awayName : '—');
        $this->command?->line('  • Direct match id: '.(int) $ab->id." — scoreline {$homeName} {$ab->home_score}, {$awayName} {$ab->away_score} (winner: {$winnerLabel})");
        $this->command?->line("  • Tiebreaker: Head-to-head — expected higher rank: {$teamAName} (direct match winner vs {$teamBName}).");
    }

    /**
     * @param  array{
     *     p_registration_id: int,
     *     q_registration_id: int,
     *     r_registration_id: int,
     *     pq: TournamentMatch,
     *     pr: TournamentMatch,
     *     qr: TournamentMatch,
     * }  $triple
     */
    private function logTripleAccumulatedPointsScenarioSummary(Tournament $tournament, array $triple): void
    {
        $tournament->loadMissing(['registrations.team']);

        $name = static function (int $registrationId) use ($tournament): string {
            $reg = $tournament->registrations->firstWhere('id', $registrationId);

            return $reg?->team?->name ?? 'Registration '.$registrationId;
        };

        $p = (int) $triple['p_registration_id'];
        $q = (int) $triple['q_registration_id'];
        $r = (int) $triple['r_registration_id'];
        $nP = $name($p);
        $nQ = $name($q);
        $nR = $name($r);

        $rows = SmallTournamentTeamStanding::forRoundRobin($tournament);
        $rowP = $rows->first(static fn (array $row): bool => ($row['team_name'] ?? '') === $nP);
        $rowQ = $rows->first(static fn (array $row): bool => ($row['team_name'] ?? '') === $nQ);
        $rowR = $rows->first(static fn (array $row): bool => ($row['team_name'] ?? '') === $nR);

        $this->command?->info('── 3-team tie (accumulated points tiebreaker) ──');
        $this->command?->line('Teams P, Q, R are not A/B/C/D. Mirrored peripherals give them identical wins/losses; the internal 3-cycle');
        $this->command?->line('keeps the same rank_score with different goals-for so standings use accumulated points (P > Q > R).');
        $this->command?->line("  • P (registration {$p}): {$nP}");
        $this->command?->line("  • Q (registration {$q}): {$nQ}");
        $this->command?->line("  • R (registration {$r}): {$nR}");

        foreach ([['label' => 'P', 'name' => $nP, 'row' => $rowP], ['label' => 'Q', 'name' => $nQ, 'row' => $rowQ], ['label' => 'R', 'name' => $nR, 'row' => $rowR]] as $slot) {
            $row = $slot['row'];
            if (! is_array($row)) {
                $this->command?->warn("  • Could not resolve standing row for {$slot['name']}.");

                continue;
            }
            $w = $row['wins'] ?? '—';
            $l = $row['losses'] ?? '—';
            $rs = $row['rank_score'] ?? '—';
            $ap = $row['accumulated_score'] ?? '—';
            $this->command?->line("  • {$slot['label']} {$slot['name']}: {$w}W {$l}L, rank_score {$rs}, accumulated points {$ap}");
        }

        $this->command?->line('  • Internal cycle match ids: pq='.(int) $triple['pq']->id.', pr='.(int) $triple['pr']->id.', qr='.(int) $triple['qr']->id);
        $this->command?->line("  • Tiebreaker: Accumulated points — expected order among the tied group: {$nP}, then {$nQ}, then {$nR}.");
    }

    /**
     * @param array{
     *     head_to_head: TournamentMatch,
     *     team_a_registration_id: int,
     *     team_b_registration_id: int,
     *     helper_c_registration_id: int,
     *     helper_d_registration_id: int,
     *     ac: TournamentMatch,
     *     ad: TournamentMatch,
     *     bc: TournamentMatch,
     *     bd: TournamentMatch,
     * } $scenario
     */
    private static function applyHeadToHeadScenarioMatches(array $scenario): void
    {
        $regA = $scenario['team_a_registration_id'];
        $regB = $scenario['team_b_registration_id'];

        self::seedCompletedMatch($scenario['head_to_head'], $regA, self::HEAD_TO_HEAD_WINNER_GOALS, self::HEAD_TO_HEAD_LOSER_GOALS);
        self::seedCompletedMatch($scenario['ac'], $scenario['helper_c_registration_id'], self::SCENARIO_A_LOSES_TO_C_WINNER, self::SCENARIO_A_LOSES_TO_C_LOSER);
        self::seedCompletedMatch($scenario['ad'], $regA, self::SCENARIO_A_BEATS_D_WINNER, self::SCENARIO_A_BEATS_D_LOSER);
        self::seedCompletedMatch($scenario['bc'], $regB, self::SCENARIO_B_BEATS_C_WINNER, self::SCENARIO_B_BEATS_C_LOSER);
        self::seedCompletedMatch($scenario['bd'], $regB, self::SCENARIO_B_BEATS_D_WINNER, self::SCENARIO_B_BEATS_D_LOSER);
    }

    private static function matchInvolvesNeitherAb(TournamentMatch $match, int $regA, int $regB): bool
    {
        $h = (int) $match->home_registration_id;
        $a = (int) $match->away_registration_id;

        return $h !== $regA && $h !== $regB && $a !== $regA && $a !== $regB;
    }

    private static function matchInvolvesExactlyOneTripleTeam(TournamentMatch $match, int $p, int $q, int $r): bool
    {
        $h = (int) $match->home_registration_id;
        $a = (int) $match->away_registration_id;
        $inT = static fn (int $x): bool => $x === $p || $x === $q || $x === $r;

        return $inT($h) xor $inT($a);
    }

    /**
     * Pick three registrations not used by the head-to-head K₂,₂ demo (A,B,C,D) whose teams form a triangle
     * in the Day 1 match graph — first such triple in seed/name order.
     *
     * @param  array{
     *     head_to_head: TournamentMatch,
     *     team_a_registration_id: int,
     *     team_b_registration_id: int,
     *     helper_c_registration_id: int,
     *     helper_d_registration_id: int,
     *     ac: TournamentMatch,
     *     ad: TournamentMatch,
     *     bc: TournamentMatch,
     *     bd: TournamentMatch,
     * }  $hhScenario
     * @param  Collection<int, TournamentMatch>  $matches
     * @return array{
     *     p_registration_id: int,
     *     q_registration_id: int,
     *     r_registration_id: int,
     *     pq: TournamentMatch,
     *     pr: TournamentMatch,
     *     qr: TournamentMatch,
     * }|null
     */
    private static function tryFindTripleRankScoreTieScenario(Tournament $tournament, array $hhScenario, Collection $matches): ?array
    {
        $blocklist = [
            (int) $hhScenario['team_a_registration_id'],
            (int) $hhScenario['team_b_registration_id'],
            (int) $hhScenario['helper_c_registration_id'],
            (int) $hhScenario['helper_d_registration_id'],
        ];

        $sorted = SmallFixedRoundRobinDayOneSchedule::sortedRegistrations($tournament);
        $edgeMap = self::buildUndirectedEdgeMap($matches);

        if ((int) $tournament->id === self::TOURNAMENT_ID) {
            // Day 1 uses only the first 24 Berger slots — not every team pair appears. For tournament 1 this
            // triangle (SIGBIN–BOMBANA–UTI) is fully covered and disjoint from the head-to-head helper set {A,B,C,D}.
            $preferredTeamNames = ['SIGBIN', 'BOMBANA', 'UTI'];
            $preferredIds = [];
            foreach ($preferredTeamNames as $teamName) {
                $reg = $sorted->first(static fn ($r): bool => ($r->team?->name ?? '') === $teamName);
                if ($reg === null || in_array((int) $reg->id, $blocklist, true)) {
                    $preferredIds = [];
                    break;
                }
                $preferredIds[] = (int) $reg->id;
            }

            if (count($preferredIds) === 3) {
                $p = $preferredIds[0];
                $q = $preferredIds[1];
                $r = $preferredIds[2];
                $mPq = self::getMatchBetween($edgeMap, $p, $q);
                $mPr = self::getMatchBetween($edgeMap, $p, $r);
                $mQr = self::getMatchBetween($edgeMap, $q, $r);

                if ($mPq !== null && $mPr !== null && $mQr !== null) {
                    return [
                        'p_registration_id' => $p,
                        'q_registration_id' => $q,
                        'r_registration_id' => $r,
                        'pq' => $mPq,
                        'pr' => $mPr,
                        'qr' => $mQr,
                    ];
                }
            }
        }

        $candidates = $sorted->filter(static fn ($reg): bool => ! in_array((int) $reg->id, $blocklist, true))->values();

        if ($candidates->count() < 3) {
            return null;
        }

        $n = $candidates->count();

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                for ($k = $j + 1; $k < $n; $k++) {
                    $p = (int) $candidates[$i]->id;
                    $q = (int) $candidates[$j]->id;
                    $r = (int) $candidates[$k]->id;

                    $mPq = self::getMatchBetween($edgeMap, $p, $q);
                    $mPr = self::getMatchBetween($edgeMap, $p, $r);
                    $mQr = self::getMatchBetween($edgeMap, $q, $r);

                    if ($mPq === null || $mPr === null || $mQr === null) {
                        continue;
                    }

                    $ids = collect([$mPq->id, $mPr->id, $mQr->id]);
                    if ($ids->unique()->count() !== 3) {
                        continue;
                    }

                    return [
                        'p_registration_id' => $p,
                        'q_registration_id' => $q,
                        'r_registration_id' => $r,
                        'pq' => $mPq,
                        'pr' => $mPr,
                        'qr' => $mQr,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, true>  $scenarioMatchIds
     * @param  list<int>  $skipOpponentRegistrationIds  Registrations (P,Q,R) whose games vs A/B are filled by the triple mirror, not this helper.
     */
    private static function seedMirroredPeripheralsForAb(Collection $matches, array $scenarioMatchIds, int $regA, int $regB, array $skipOpponentRegistrationIds = []): void
    {
        $extraA = [];
        $extraB = [];

        foreach ($matches as $m) {
            if (isset($scenarioMatchIds[(int) $m->id])) {
                continue;
            }

            $h = (int) $m->home_registration_id;
            $aw = (int) $m->away_registration_id;
            $hasA = $h === $regA || $aw === $regA;
            $hasB = $h === $regB || $aw === $regB;

            if ($hasA && ! $hasB) {
                $other = $h === $regA ? $aw : $h;
                if (in_array($other, $skipOpponentRegistrationIds, true)) {
                    continue;
                }
                $extraA[] = $m;
            }
            if ($hasB && ! $hasA) {
                $other = $h === $regB ? $aw : $h;
                if (in_array($other, $skipOpponentRegistrationIds, true)) {
                    continue;
                }
                $extraB[] = $m;
            }
        }

        $sortFn = static function (TournamentMatch $x, TournamentMatch $y): int {
            return [
                $x->match_number ?? PHP_INT_MAX,
                $x->id,
            ] <=> [
                $y->match_number ?? PHP_INT_MAX,
                $y->id,
            ];
        };

        usort($extraA, $sortFn);
        usort($extraB, $sortFn);

        $n = min(count($extraA), count($extraB));
        for ($i = 0; $i < $n; $i++) {
            $wantWin = ($i % 2 === 0);
            self::seedMatchOutcomeForFocalRegistration($extraA[$i], $regA, $wantWin, $i * 31 + 7);
            self::seedMatchOutcomeForFocalRegistration($extraB[$i], $regB, $wantWin, $i * 31 + 7);
        }

        foreach (array_slice($extraA, $n) as $j => $m) {
            $wantWin = (($n + $j) % 2 === 0);
            self::seedMatchOutcomeForFocalRegistration($m, $regA, $wantWin, 10_000 + $j * 13);
        }

        foreach (array_slice($extraB, $n) as $j => $m) {
            $wantWin = (($n + $j) % 2 === 0);
            self::seedMatchOutcomeForFocalRegistration($m, $regB, $wantWin, 20_000 + $j * 13);
        }
    }

    /**
     * Mirror win/loss across P,Q,R for every match where exactly one side is in {P,Q,R} (same outcome index
     * for all three), so they stay tied on rank_score before the internal 3-cycle applies.
     *
     * @param  array<int, true>  $scenarioMatchIds
     * @param  array<int, true>  $tripleMatchIds
     */
    private static function seedMirroredPeripheralsForTriple(
        Collection $matches,
        array $scenarioMatchIds,
        array $tripleMatchIds,
        int $p,
        int $q,
        int $r,
    ): void {
        $extraP = [];
        $extraQ = [];
        $extraR = [];

        foreach ($matches as $m) {
            $mid = (int) $m->id;
            if (isset($scenarioMatchIds[$mid]) || isset($tripleMatchIds[$mid])) {
                continue;
            }

            $h = (int) $m->home_registration_id;
            $aw = (int) $m->away_registration_id;
            $hasP = $h === $p || $aw === $p;
            $hasQ = $h === $q || $aw === $q;
            $hasR = $h === $r || $aw === $r;

            if ($hasP && ! $hasQ && ! $hasR) {
                $extraP[] = $m;
            }
            if ($hasQ && ! $hasP && ! $hasR) {
                $extraQ[] = $m;
            }
            if ($hasR && ! $hasP && ! $hasQ) {
                $extraR[] = $m;
            }
        }

        $sortFn = static function (TournamentMatch $x, TournamentMatch $y): int {
            return [
                $x->match_number ?? PHP_INT_MAX,
                $x->id,
            ] <=> [
                $y->match_number ?? PHP_INT_MAX,
                $y->id,
            ];
        };

        usort($extraP, $sortFn);
        usort($extraQ, $sortFn);
        usort($extraR, $sortFn);

        $n = min(count($extraP), count($extraQ), count($extraR));
        for ($i = 0; $i < $n; $i++) {
            $wantWin = ($i % 2 === 0);
            self::seedMatchOutcomeForFocalRegistration($extraP[$i], $p, $wantWin, $i * 31 + 401);
            self::seedMatchOutcomeForFocalRegistration($extraQ[$i], $q, $wantWin, $i * 31 + 401);
            self::seedMatchOutcomeForFocalRegistration($extraR[$i], $r, $wantWin, $i * 31 + 401);
        }

        foreach (array_slice($extraP, $n) as $j => $m) {
            $wantWin = (($n + $j) % 2 === 0);
            self::seedMatchOutcomeForFocalRegistration($m, $p, $wantWin, 30_000 + $j * 17);
        }

        foreach (array_slice($extraQ, $n) as $j => $m) {
            $wantWin = (($n + $j) % 2 === 0);
            self::seedMatchOutcomeForFocalRegistration($m, $q, $wantWin, 40_000 + $j * 17);
        }

        foreach (array_slice($extraR, $n) as $j => $m) {
            $wantWin = (($n + $j) % 2 === 0);
            self::seedMatchOutcomeForFocalRegistration($m, $r, $wantWin, 50_000 + $j * 17);
        }
    }

    /**
     * @param  array{
     *     p_registration_id: int,
     *     q_registration_id: int,
     *     r_registration_id: int,
     *     pq: TournamentMatch,
     *     pr: TournamentMatch,
     *     qr: TournamentMatch,
     * }  $triple
     */
    private static function applyTripleInternalCycle(array $triple): void
    {
        $p = (int) $triple['p_registration_id'];
        $q = (int) $triple['q_registration_id'];
        $r = (int) $triple['r_registration_id'];

        self::seedCompletedMatch($triple['pq'], $p, self::TRIPLE_P_BEATS_Q_WINNER, self::TRIPLE_P_BEATS_Q_LOSER);
        self::seedCompletedMatch($triple['qr'], $q, self::TRIPLE_Q_BEATS_R_WINNER, self::TRIPLE_Q_BEATS_R_LOSER);
        self::seedCompletedMatch($triple['pr'], $r, self::TRIPLE_R_BEATS_P_WINNER, self::TRIPLE_R_BEATS_P_LOSER);
    }

    /**
     * Seed a completed match where $focalRegId wins or loses; goals are varied but deterministic from $salt.
     */
    private static function seedMatchOutcomeForFocalRegistration(
        TournamentMatch $match,
        int $focalRegId,
        bool $focalShouldWin,
        int $salt,
    ): void {
        $home = (int) $match->home_registration_id;
        $away = (int) $match->away_registration_id;
        $other = $home === $focalRegId ? $away : $home;
        $winnerReg = $focalShouldWin ? $focalRegId : $other;

        $wGoals = 6 + (($salt * 5 + 1) % 6);
        $lGoals = 1 + (($salt * 3) % 4);
        if ($wGoals <= $lGoals) {
            $wGoals = $lGoals + 1 + (int) ($salt % 3);
        }

        self::seedCompletedMatch($match, $winnerReg, $wGoals, $lGoals);
    }

    /**
     * @return bool true if the match was completed with stats
     */
    private static function seedGenericDay1Match(TournamentMatch $match, int $salt): bool
    {
        $match->loadMissing([
            'homeRegistration.team.members',
            'awayRegistration.team.members',
        ]);

        $homeMembers = self::membersInSheetOrder($match->homeRegistration?->team);
        $awayMembers = self::membersInSheetOrder($match->awayRegistration?->team);

        if ($homeMembers->isEmpty() || $awayMembers->isEmpty()) {
            return false;
        }

        DB::transaction(function () use ($match, $homeMembers, $awayMembers, $salt): void {
            MatchScoreLog::query()->where('match_id', $match->id)->delete();
            MatchPlayerStat::query()->where('match_id', $match->id)->delete();

            $homeSlots = $homeMembers->count();
            $awaySlots = $awayMembers->count();

            [$homeTotal, $awayTotal] = self::pickDistinctTeamTotalsDeterministic(
                $homeSlots,
                $awaySlots,
                self::MAX_GOALS_PER_PLAYER,
                self::MAX_TEAM_GOALS,
                $salt,
            );

            $homeGoals = self::distributeTotalDeterministicSalted(
                $homeSlots,
                $homeTotal,
                self::MAX_GOALS_PER_PLAYER,
                $salt,
            );
            $awayGoals = self::distributeTotalDeterministicSalted(
                $awaySlots,
                $awayTotal,
                self::MAX_GOALS_PER_PLAYER,
                $salt + 17_011,
            );

            foreach ($homeMembers as $index => $member) {
                $goals = $homeGoals[$index] ?? 0;
                $a = ($salt + (int) $member->id + $index) % (self::MAX_ASSISTS_PER_PLAYER + 1);
                $b = ($salt * 2 + (int) $member->id + $index) % (self::MAX_BLOCKS_PER_PLAYER + 1);
                self::upsertPlayerStat($match->id, (int) $member->id, $goals, $a, $b);
            }

            foreach ($awayMembers as $index => $member) {
                $goals = $awayGoals[$index] ?? 0;
                $a = ($salt + (int) $member->id * 3 + $index) % (self::MAX_ASSISTS_PER_PLAYER + 1);
                $b = ($salt * 3 + (int) $member->id + $index * 2) % (self::MAX_BLOCKS_PER_PLAYER + 1);
                self::upsertPlayerStat($match->id, (int) $member->id, $goals, $a, $b);
            }

            $homeScore = (int) array_sum($homeGoals);
            $awayScore = (int) array_sum($awayGoals);

            $match->forceFill([
                'home_score' => $homeScore,
                'away_score' => $awayScore,
                'status' => 'completed',
            ])->save();
        });

        return true;
    }

    /**
     * Clear score-only data for a match so the seeder can re-run safely.
     */
    private static function wipeMatchScoresOnly(TournamentMatch $match): void
    {
        DB::transaction(static function () use ($match): void {
            MatchScoreLog::query()->where('match_id', $match->id)->delete();
            MatchPlayerStat::query()->where('match_id', $match->id)->delete();

            $match->forceFill([
                'home_score' => null,
                'away_score' => null,
                'status' => 'scheduled',
            ])->save();
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function tryFindHeadToHeadTieScenario(Collection $orderedMatches): ?array
    {
        $edgeMap = self::buildUndirectedEdgeMap($orderedMatches);

        /** @var Collection<int, int> $regIds */
        $regIds = $orderedMatches
            ->flatMap(static fn (TournamentMatch $m): array => [
                (int) $m->home_registration_id,
                (int) $m->away_registration_id,
            ])
            ->unique()
            ->sort()
            ->values();

        foreach ($orderedMatches as $abMatch) {
            $regA = (int) $abMatch->home_registration_id;
            $regB = (int) $abMatch->away_registration_id;

            foreach ($regIds as $c) {
                if ($c === $regA || $c === $regB) {
                    continue;
                }

                foreach ($regIds as $d) {
                    if ($d <= $c || $d === $regA || $d === $regB) {
                        continue;
                    }

                    $mAc = self::getMatchBetween($edgeMap, $regA, $c);
                    $mAd = self::getMatchBetween($edgeMap, $regA, $d);
                    $mBc = self::getMatchBetween($edgeMap, $regB, $c);
                    $mBd = self::getMatchBetween($edgeMap, $regB, $d);

                    if ($mAc === null || $mAd === null || $mBc === null || $mBd === null) {
                        continue;
                    }

                    $ids = collect([$abMatch->id, $mAc->id, $mAd->id, $mBc->id, $mBd->id]);

                    if ($ids->unique()->count() !== 5) {
                        continue;
                    }

                    return [
                        'head_to_head' => $abMatch,
                        'team_a_registration_id' => $regA,
                        'team_b_registration_id' => $regB,
                        'helper_c_registration_id' => (int) $c,
                        'helper_d_registration_id' => (int) $d,
                        'ac' => $mAc,
                        'ad' => $mAd,
                        'bc' => $mBc,
                        'bd' => $mBd,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, TournamentMatch>  $matches
     * @return array<string, TournamentMatch>
     */
    private static function buildUndirectedEdgeMap(Collection $matches): array
    {
        $map = [];

        foreach ($matches as $m) {
            $h = (int) $m->home_registration_id;
            $a = (int) $m->away_registration_id;
            $key = min($h, $a).'-'.max($h, $a);
            $map[$key] = $m;
        }

        return $map;
    }

    /**
     * @param  array<string, TournamentMatch>  $edgeMap
     */
    private static function getMatchBetween(array $edgeMap, int $x, int $y): ?TournamentMatch
    {
        $key = min($x, $y).'-'.max($x, $y);

        return $edgeMap[$key] ?? null;
    }

    /**
     * Seed one completed match from per-player goals (deterministic spread) so home_score/away_score equal team sums.
     */
    private static function seedCompletedMatch(TournamentMatch $match, int $winningRegistrationId, int $winnerGoals, int $loserGoals): void
    {
        $match->loadMissing([
            'homeRegistration.team.members',
            'awayRegistration.team.members',
        ]);

        $homeRegId = (int) $match->home_registration_id;
        $awayRegId = (int) $match->away_registration_id;

        $homeWins = $winningRegistrationId === $homeRegId;
        $homeTotal = $homeWins ? $winnerGoals : $loserGoals;
        $awayTotal = $homeWins ? $loserGoals : $winnerGoals;

        $homeMembers = self::membersInSheetOrder($match->homeRegistration?->team);
        $awayMembers = self::membersInSheetOrder($match->awayRegistration?->team);

        if ($homeMembers->isEmpty() || $awayMembers->isEmpty()) {
            return;
        }

        $homeCap = $homeMembers->count() * self::MAX_GOALS_PER_PLAYER;
        $awayCap = $awayMembers->count() * self::MAX_GOALS_PER_PLAYER;
        $homeTotal = min($homeTotal, $homeCap);
        $awayTotal = min($awayTotal, $awayCap);

        if ($homeWins && $homeTotal <= $awayTotal) {
            $homeTotal = min($homeCap, $awayTotal + 1);
        }
        if (! $homeWins && $awayTotal <= $homeTotal) {
            $awayTotal = min($awayCap, $homeTotal + 1);
        }

        DB::transaction(function () use ($match, $homeMembers, $awayMembers, $homeTotal, $awayTotal): void {
            MatchScoreLog::query()->where('match_id', $match->id)->delete();
            MatchPlayerStat::query()->where('match_id', $match->id)->delete();

            $homeGoals = self::distributeTotalDeterministic(
                $homeMembers->count(),
                $homeTotal,
                self::MAX_GOALS_PER_PLAYER,
            );
            $awayGoals = self::distributeTotalDeterministic(
                $awayMembers->count(),
                $awayTotal,
                self::MAX_GOALS_PER_PLAYER,
            );

            foreach ($homeMembers as $index => $member) {
                $goals = $homeGoals[$index] ?? 0;
                self::upsertPlayerStat($match->id, (int) $member->id, $goals, 0, 0);
            }

            foreach ($awayMembers as $index => $member) {
                $goals = $awayGoals[$index] ?? 0;
                self::upsertPlayerStat($match->id, (int) $member->id, $goals, 0, 0);
            }

            $homeScore = (int) array_sum($homeGoals);
            $awayScore = (int) array_sum($awayGoals);

            $match->forceFill([
                'home_score' => $homeScore,
                'away_score' => $awayScore,
                'status' => 'completed',
            ])->save();
        });
    }

    /**
     * @return Collection<int, TeamMember>
     */
    private static function membersInSheetOrder(?Team $team): Collection
    {
        if ($team === null) {
            return collect();
        }

        $members = $team->relationLoaded('members')
            ? $team->getRelation('members')
            : $team->members()->orderBy('id')->get();

        if ($members->isEmpty()) {
            return collect();
        }

        $male = $members->filter(fn (TeamMember $m): bool => strtolower((string) $m->gender) === 'male')->values();
        $female = $members->filter(fn (TeamMember $m): bool => strtolower((string) $m->gender) === 'female')->values();
        $other = $members->filter(fn (TeamMember $m): bool => ! in_array(strtolower((string) $m->gender), ['male', 'female'], true))->values();

        $sortRoster = static function (Collection $group): Collection {
            return $group->sort(static function (TeamMember $a, TeamMember $b): int {
                $rank = static fn (TeamMember $m): int => match (strtolower((string) $m->role)) {
                    'captain' => 0,
                    'spirit_captain' => 1,
                    default => 2,
                };

                return [$rank($a), $a->name ?? '', $a->id] <=> [$rank($b), $b->name ?? '', $b->id];
            })->values();
        };

        return $sortRoster($male)
            ->concat($sortRoster($female))
            ->concat($sortRoster($other))
            ->values();
    }

    /**
     * @return list<int>
     */
    private static function distributeTotalDeterministic(int $slots, int $total, int $maxPerSlot): array
    {
        if ($slots <= 0) {
            return [];
        }

        $maxAchievable = $slots * $maxPerSlot;
        $total = max(0, min($total, $maxAchievable));

        $amounts = array_fill(0, $slots, 0);
        $remaining = $total;
        $cursor = 0;
        $guard = 0;

        while ($remaining > 0 && $guard < 100_000) {
            $guard++;
            if ($amounts[$cursor % $slots] < $maxPerSlot) {
                $amounts[$cursor % $slots]++;
                $remaining--;
            }
            $cursor++;
        }

        return $amounts;
    }

    /**
     * Same total as {@see distributeTotalDeterministic} but rotates which players get goals first for variety.
     *
     * @return list<int>
     */
    private static function distributeTotalDeterministicSalted(int $slots, int $total, int $maxPerSlot, int $salt): array
    {
        $amounts = self::distributeTotalDeterministic($slots, $total, $maxPerSlot);
        if ($slots <= 1) {
            return $amounts;
        }

        $rotate = abs($salt) % $slots;

        return array_values(array_merge(array_slice($amounts, $rotate), array_slice($amounts, 0, $rotate)));
    }

    /**
     * Non-random distinct team totals for generic games (no draws), stable for a given salt.
     *
     * @return array{0: int, 1: int} [homeTotal, awayTotal]
     */
    private static function pickDistinctTeamTotalsDeterministic(
        int $homeSlots,
        int $awaySlots,
        int $maxPerPlayer,
        int $maxTeam,
        int $salt,
    ): array {
        $maxH = min($maxTeam, $homeSlots * $maxPerPlayer);
        $maxA = min($maxTeam, $awaySlots * $maxPerPlayer);

        $minH = $homeSlots > 0 ? 1 : 0;
        $minA = $awaySlots > 0 ? 1 : 0;

        if ($maxH < $minH) {
            $minH = $maxH;
        }
        if ($maxA < $minA) {
            $minA = $maxA;
        }

        $spanH = max(0, $maxH - $minH);
        $spanA = max(0, $maxA - $minA);

        $h = $minH + ($spanH > 0 ? (($salt * 3 + 5) % ($spanH + 1)) : 0);
        $a = $minA + ($spanA > 0 ? (($salt * 11 + 2) % ($spanA + 1)) : 0);

        if ($h === $a) {
            if ($h < $maxH) {
                $h++;
            } elseif ($a < $maxA) {
                $a++;
            } elseif ($h > $minH) {
                $h--;
            } elseif ($a > $minA) {
                $a--;
            }
        }

        if ($h === $a) {
            if ($maxH >= 2) {
                return [2, min($maxA, 1)];
            }
            if ($maxA >= 2) {
                return [min($maxH, 1), 2];
            }

            return [1, 0];
        }

        return [$h, $a];
    }

    private static function upsertPlayerStat(int $matchId, int $teamMemberId, int $goals, int $assists, int $blocks): void
    {
        MatchPlayerStat::query()->updateOrCreate(
            [
                'match_id' => $matchId,
                'team_member_id' => $teamMemberId,
            ],
            [
                'goals' => $goals,
                'assists' => $assists,
                'blocks' => $blocks,
            ],
        );
    }
}
