<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\MatchPlayerStat;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds printable tournament report fields from persisted match, spirit, and player stat data.
 */
final class TournamentReportBuilder
{
    /**
     * @return array{
     *     team_awards: array{
     *         most_spirited_team: string|null,
     *         champion: string|null,
     *         first_runner_up: string|null,
     *         second_runner_up: string|null,
     *         third_runner_up: string|null,
     *     },
     *     individual_awards: array{
     *         most_blocks_male: string|null,
     *         most_blocks_female: string|null,
     *         most_assists_male: string|null,
     *         most_assists_female: string|null,
     *         most_scores_male: string|null,
     *         most_scores_female: string|null,
     *         mythical_7_male: list<string|null>,
     *         mythical_7_female: list<string|null>,
     *         finals_mvp_male: string|null,
     *         finals_mvp_female: string|null,
     *         tournament_mvp_male: string|null,
     *         tournament_mvp_female: string|null,
     *     },
     * }
     */
    public function build(Tournament $tournament): array
    {
        $matches = TournamentMatch::query()
            ->where('tournament_id', $tournament->id)
            ->with(['homeRegistration.team', 'awayRegistration.team'])
            ->orderBy('match_number')
            ->orderBy('id')
            ->get();

        $playerRows = $this->aggregateTournamentPlayerRows($tournament->id);

        return [
            'team_awards' => [
                'most_spirited_team' => $this->resolveMostSpiritedTeamLabel($tournament->id),
                'champion' => $this->resolveChampionLabel($matches),
                'first_runner_up' => $this->resolveFirstRunnerUpLabel($matches),
                'second_runner_up' => $this->resolveRanking34WinnerLabel($matches),
                'third_runner_up' => $this->resolveRanking34LoserLabel($matches),
            ],
            'individual_awards' => $this->resolveIndividualAwards($playerRows, $matches),
        ];
    }

    /**
     * Aggregate per {@see TeamMember} totals across all tournament matches (same basis as the public Stats tab).
     *
     * @return Collection<int, array{
     *     gender_bucket: string,
     *     player_name: string,
     *     team_name: string,
     *     goals: int,
     *     assists: int,
     *     blocks: int,
     *     total_offense: int,
     *     mythical_score: int,
     *     matches_played: int,
     * }>
     */
    private function aggregateTournamentPlayerRows(int $tournamentId): Collection
    {
        $stats = MatchPlayerStat::query()
            ->whereHas('match', fn (Builder $q) => $q->where('tournament_id', $tournamentId))
            ->with(['teamMember.team', 'match'])
            ->get();

        return $stats
            ->groupBy('team_member_id')
            ->map(function (Collection $group): ?array {
                $first = $group->first();
                $member = $first?->teamMember;
                $team = $member?->team;

                if (! $member || ! $team) {
                    return null;
                }

                $goals = (int) $group->sum('goals');
                $assists = (int) $group->sum('assists');
                $blocks = (int) $group->sum('blocks');

                if ($goals + $assists + $blocks <= 0) {
                    return null;
                }

                return [
                    'gender_bucket' => $this->normalizeGenderBucket($member->gender),
                    'player_name' => (string) $member->name,
                    'team_name' => (string) $team->name,
                    'goals' => $goals,
                    'assists' => $assists,
                    'blocks' => $blocks,
                    'total_offense' => $goals + $assists,
                    'mythical_score' => $goals + $assists + $blocks,
                    'matches_played' => $group->pluck('match_id')->unique()->count(),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Same buckets as {@see PublicTournamentController::normalizePlayerGender()}.
     */
    private function normalizeGenderBucket(?string $gender): string
    {
        $normalized = str($gender ?? '')->lower()->trim()->toString();

        return match (true) {
            in_array($normalized, ['man', 'men', 'male', 'boy'], true) => 'men',
            in_array($normalized, ['woman', 'women', 'female', 'girl'], true) => 'women',
            default => 'unknown',
        };
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $playerRows
     * @param  Collection<int, TournamentMatch>  $matches
     * @return array{
     *     most_blocks_male: string|null,
     *     most_blocks_female: string|null,
     *     most_assists_male: string|null,
     *     most_assists_female: string|null,
     *     most_scores_male: string|null,
     *     most_scores_female: string|null,
     *     mythical_7_male: list<string|null>,
     *     mythical_7_female: list<string|null>,
     *     finals_mvp_male: string|null,
     *     finals_mvp_female: string|null,
     *     tournament_mvp_male: string|null,
     *     tournament_mvp_female: string|null,
     * }
     */
    private function resolveIndividualAwards(Collection $playerRows, Collection $matches): array
    {
        $m7m = $this->pickMythicalSevenLines($playerRows, 'men', 4);
        $m7f = $this->pickMythicalSevenLines($playerRows, 'women', 3);

        $finals = $this->resolveFinalsMvpLabels($matches);

        return [
            'most_blocks_male' => $this->pickTopStatLine($playerRows, 'men', 'blocks'),
            'most_blocks_female' => $this->pickTopStatLine($playerRows, 'women', 'blocks'),
            'most_assists_male' => $this->pickTopStatLine($playerRows, 'men', 'assists'),
            'most_assists_female' => $this->pickTopStatLine($playerRows, 'women', 'assists'),
            'most_scores_male' => $this->pickTopStatLine($playerRows, 'men', 'goals'),
            'most_scores_female' => $this->pickTopStatLine($playerRows, 'women', 'goals'),
            'mythical_7_male' => $m7m,
            'mythical_7_female' => $m7f,
            'finals_mvp_male' => $finals['male'],
            'finals_mvp_female' => $finals['female'],
            'tournament_mvp_male' => $this->pickTournamentMvpLine($playerRows, 'men'),
            'tournament_mvp_female' => $this->pickTournamentMvpLine($playerRows, 'women'),
        ];
    }

    /**
     * @param  'men'|'women'  $genderBucket
     */
    private function pickTopStatLine(Collection $playerRows, string $genderBucket, string $metric): ?string
    {
        $candidates = $playerRows->where('gender_bucket', $genderBucket);
        if ($candidates->isEmpty()) {
            return null;
        }

        $keys = $this->statSortKeys($metric);
        $winner = $candidates
            ->sort(fn (array $a, array $b): int => $this->comparePlayerRowsDesc($a, $b, $keys))
            ->values()
            ->first();

        return $winner !== null ? $this->formatPlayerTeamLine($winner) : null;
    }

    /**
     * @param  'men'|'women'  $genderBucket
     * @return list<string|null>
     */
    private function pickMythicalSevenLines(Collection $playerRows, string $genderBucket, int $count): array
    {
        $candidates = $playerRows->where('gender_bucket', $genderBucket);
        if ($candidates->isEmpty()) {
            return array_fill(0, $count, null);
        }

        $keys = ['mythical_score', 'goals', 'assists', 'blocks', '_player'];

        $sorted = $candidates
            ->sort(fn (array $a, array $b): int => $this->comparePlayerRowsDesc($a, $b, $keys))
            ->values();

        $lines = [];
        for ($i = 0; $i < $count; $i++) {
            $row = $sorted->get($i);
            $lines[] = $row !== null ? $this->formatPlayerTeamLine($row) : null;
        }

        return $lines;
    }

    /**
     * @param  'men'|'women'  $genderBucket
     */
    private function pickTournamentMvpLine(Collection $playerRows, string $genderBucket): ?string
    {
        return $this->pickTopStatLine($playerRows, $genderBucket, 'mvp');
    }

    /**
     * @return array{male: string|null, female: string|null}
     */
    private function resolveFinalsMvpLabels(Collection $matches): array
    {
        $championship = $this->findChampionshipMatch($matches);

        if (! $championship instanceof TournamentMatch) {
            return ['male' => null, 'female' => null];
        }

        if (! $championship->isCompletedMatchStatus()) {
            return ['male' => __('Pending'), 'female' => __('Pending')];
        }

        $finalRows = $this->aggregateMatchPlayerRows((int) $championship->id);
        if ($finalRows->isEmpty()) {
            return ['male' => null, 'female' => null];
        }

        $mvpKeys = ['mythical_score', 'goals', 'assists', 'blocks', '_player'];

        $male = $finalRows->where('gender_bucket', 'men')->sort(fn (array $a, array $b): int => $this->comparePlayerRowsDesc($a, $b, $mvpKeys))->values()->first();
        $female = $finalRows->where('gender_bucket', 'women')->sort(fn (array $a, array $b): int => $this->comparePlayerRowsDesc($a, $b, $mvpKeys))->values()->first();

        return [
            'male' => $male !== null ? $this->formatPlayerTeamLine($male) : null,
            'female' => $female !== null ? $this->formatPlayerTeamLine($female) : null,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function aggregateMatchPlayerRows(int $matchId): Collection
    {
        $stats = MatchPlayerStat::query()
            ->where('match_id', $matchId)
            ->with(['teamMember.team'])
            ->get();

        return $stats
            ->groupBy('team_member_id')
            ->map(function (Collection $group): ?array {
                $first = $group->first();
                $member = $first?->teamMember;
                $team = $member?->team;

                if (! $member || ! $team) {
                    return null;
                }

                $goals = (int) $group->sum('goals');
                $assists = (int) $group->sum('assists');
                $blocks = (int) $group->sum('blocks');

                if ($goals + $assists + $blocks <= 0) {
                    return null;
                }

                return [
                    'gender_bucket' => $this->normalizeGenderBucket($member->gender),
                    'player_name' => (string) $member->name,
                    'team_name' => (string) $team->name,
                    'goals' => $goals,
                    'assists' => $assists,
                    'blocks' => $blocks,
                    'total_offense' => $goals + $assists,
                    'mythical_score' => $goals + $assists + $blocks,
                    'matches_played' => $group->pluck('match_id')->unique()->count(),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return list<string>
     */
    private function statSortKeys(string $metric): array
    {
        return match ($metric) {
            'assists' => ['assists', 'total_offense', 'goals', 'blocks', 'matches_played', '_team', '_player'],
            'blocks' => ['blocks', 'total_offense', 'goals', 'assists', 'matches_played', '_team', '_player'],
            'total_offense' => ['total_offense', 'goals', 'assists', 'blocks', 'matches_played', '_team', '_player'],
            'mvp' => ['mythical_score', 'goals', 'assists', 'blocks', 'matches_played', '_team', '_player'],
            default => ['goals', 'total_offense', 'assists', 'blocks', 'matches_played', '_team', '_player'],
        };
    }

    /**
     * @param  list<string>  $keys
     */
    private function comparePlayerRowsDesc(array $a, array $b, array $keys): int
    {
        foreach ($keys as $key) {
            if ($key === '_team') {
                $c = strcmp($a['team_name'] ?? '', $b['team_name'] ?? '');
                if ($c !== 0) {
                    return $c;
                }

                continue;
            }

            if ($key === '_player') {
                return strcmp($a['player_name'] ?? '', $b['player_name'] ?? '');
            }

            $va = (int) ($a[$key] ?? 0);
            $vb = (int) ($b[$key] ?? 0);
            if ($va !== $vb) {
                return $vb <=> $va;
            }
        }

        return 0;
    }

    /**
     * @param  array{player_name: string, team_name: string}  $row
     */
    private function formatPlayerTeamLine(array $row): string
    {
        return $row['player_name'].' – '.$row['team_name'];
    }

    private function resolveMostSpiritedTeamLabel(int $tournamentId): ?string
    {
        $aggregates = DB::table('match_spirit_scores as mss')
            ->selectRaw('mss.scored_team_id as team_id')
            ->selectRaw('SUM(COALESCE(mss.total_score, 0)) as total_spirit')
            ->selectRaw('COUNT(*) as games_played')
            ->join('matches as m', 'm.id', '=', 'mss.match_id')
            ->where('mss.tournament_id', $tournamentId)
            ->where('m.tournament_id', $tournamentId)
            ->whereRaw('LOWER(m.status) = ?', [TournamentMatch::STATUS_COMPLETED])
            ->whereNotNull('mss.total_score')
            ->groupBy('mss.scored_team_id')
            ->havingRaw('COUNT(*) >= 1')
            ->get();

        if ($aggregates->isEmpty()) {
            return null;
        }

        $teamNames = Team::query()
            ->whereIn('id', $aggregates->pluck('team_id')->all())
            ->pluck('name', 'id');

        $best = $aggregates
            ->map(function ($row) use ($teamNames): array {
                $teamId = (int) $row->team_id;
                $total = (int) $row->total_spirit;
                $games = (int) $row->games_played;
                $average = $games > 0 ? $total / $games : 0.0;
                $name = (string) ($teamNames[$teamId] ?? '');

                return [
                    'team_id' => $teamId,
                    'name' => $name,
                    'total' => $total,
                    'games' => $games,
                    'average' => $average,
                ];
            })
            ->sort(function (array $a, array $b): int {
                if (abs($a['average'] - $b['average']) > 1e-6) {
                    return $a['average'] < $b['average'] ? 1 : -1;
                }
                if ($a['total'] !== $b['total']) {
                    return $a['total'] < $b['total'] ? 1 : -1;
                }
                if ($a['games'] !== $b['games']) {
                    return $a['games'] < $b['games'] ? 1 : -1;
                }

                return $a['name'] <=> $b['name'];
            })
            ->first();

        if ($best === null || $best['name'] === '') {
            return null;
        }

        $avgLabel = number_format($best['average'], 1, '.', '');

        return $best['name'].' ('.$avgLabel.' '.__('avg').')';
    }

    private function findChampionshipMatch(Collection $matches): ?TournamentMatch
    {
        $completed = fn (TournamentMatch $m): bool => $m->isCompletedMatchStatus()
            && $m->home_registration_id
            && $m->away_registration_id;

        $aliases = array_map(strtolower(...), SmallDayTwoKnockoutBracket::championshipStageAliases());

        $byNumber = $matches->first(function (TournamentMatch $m) use ($completed, $aliases): bool {
            if ((int) $m->match_number !== 48 || ! $completed($m)) {
                return false;
            }

            $stage = strtolower((string) $m->stage);

            return in_array($stage, $aliases, true)
                || SmallDayTwoKnockoutBracket::isBracketMatch($m)
                || str_contains((string) ($m->notes ?? ''), SmallDayTwoKnockoutBracket::marker(48));
        });

        if ($byNumber instanceof TournamentMatch) {
            return $byNumber;
        }

        return $matches
            ->filter($completed)
            ->filter(fn (TournamentMatch $m): bool => in_array(strtolower((string) $m->stage), $aliases, true))
            ->sortByDesc(fn (TournamentMatch $m): int => (int) ($m->match_number ?? 0))
            ->first();
    }

    private function findRanking34Match(Collection $matches): ?TournamentMatch
    {
        $completed = fn (TournamentMatch $m): bool => $m->isCompletedMatchStatus()
            && $m->home_registration_id
            && $m->away_registration_id;

        $placementStages = ['placement', 'ranking-path', 'ranking_path', 'ranking_34'];

        $marker47 = SmallDayTwoKnockoutBracket::marker(47);

        $preferred = $matches->first(function (TournamentMatch $m) use ($completed, $placementStages, $marker47): bool {
            if ((int) $m->match_number !== 47 || ! $completed($m)) {
                return false;
            }

            $stage = strtolower((string) $m->stage);

            return in_array($stage, $placementStages, true)
                || str_contains((string) ($m->notes ?? ''), $marker47);
        });

        if ($preferred instanceof TournamentMatch) {
            return $preferred;
        }

        return $matches->first(fn (TournamentMatch $m): bool => (int) $m->match_number === 47 && $completed($m));
    }

    private function resolveChampionLabel(Collection $matches): ?string
    {
        $match = $this->findChampionshipMatch($matches);

        return $this->matchOutcomeTeamName($match, 'winner');
    }

    private function resolveFirstRunnerUpLabel(Collection $matches): ?string
    {
        $match = $this->findChampionshipMatch($matches);

        return $this->matchOutcomeTeamName($match, 'loser');
    }

    private function resolveRanking34WinnerLabel(Collection $matches): ?string
    {
        $match = $this->findRanking34Match($matches);

        return $this->matchOutcomeTeamName($match, 'winner');
    }

    private function resolveRanking34LoserLabel(Collection $matches): ?string
    {
        $match = $this->findRanking34Match($matches);

        return $this->matchOutcomeTeamName($match, 'loser');
    }

    /**
     * @param  'winner'|'loser'  $role
     */
    private function matchOutcomeTeamName(?TournamentMatch $match, string $role): ?string
    {
        if (! $match instanceof TournamentMatch) {
            return null;
        }

        if (! $match->isCompletedMatchStatus()) {
            return __('Pending');
        }

        $homeScore = $match->home_score;
        $awayScore = $match->away_score;

        if ($homeScore === null || $awayScore === null) {
            return __('Pending');
        }

        if ($homeScore === $awayScore) {
            return __('Pending');
        }

        $homeWins = $homeScore > $awayScore;
        $pickHome = ($role === 'winner' && $homeWins) || ($role === 'loser' && ! $homeWins);
        $registration = $pickHome ? $match->homeRegistration : $match->awayRegistration;
        $name = $registration?->team?->name;

        return filled($name) ? (string) $name : null;
    }
}
