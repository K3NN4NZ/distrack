<?php

namespace App\Http\Controllers;

use App\Models\MatchSpiritScore;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Support\SmallFixedRoundRobinDayOneSchedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PublicTournamentController extends Controller
{
    /**
     * Show the public tournament discovery board.
     */
    public function index(Request $request): View
    {
        $baseQuery = Tournament::query()->publiclyVisible();
        $filteredQuery = $this->applyFilters(clone $baseQuery, $request);
        $period = $this->normalizePeriod($request);
        $boardView = $this->normalizeBoardView($request);
        $today = now()->startOfDay();
        $periodQuery = $this->applyPeriod(clone $filteredQuery, $period, $today);

        $tournamentsQuery = (clone $periodQuery)
            ->with(['creator', 'registrations.team'])
            ->withCount(['registrations', 'matches', 'pitches']);

        $tournaments = $this->applySort($tournamentsQuery, $period)
            ->paginate(9)
            ->withQueryString();

        $calendar = $this->buildPublicBoardCalendar(
            $this->applySort(clone $periodQuery, $period)->get(),
            $period,
            $request,
        );

        $pickerYear = $this->normalizeCalendarPickerYear($request, $calendar['month']);
        $pickerMonth = $this->normalizeCalendarPickerMonth($request, $pickerYear, $calendar['month']);

        return view('tournaments.index', [
            'tournaments' => $tournaments,
            'filters' => [
                'period' => $period,
                'view' => $boardView,
                'search' => trim($request->string('search')->toString()),
                'country' => trim($request->string('country')->toString()),
                'type' => trim($request->string('type')->toString()),
                'division' => trim($request->string('division')->toString()),
                'surface' => trim($request->string('surface')->toString()),
                'event_type' => trim($request->string('event_type')->toString()),
                'month' => $calendar['month']->format('Y-m'),
                'day' => $calendar['selected_day']->format('Y-m-d'),
                'picker_year' => $pickerYear,
                'picker_month' => $pickerMonth,
            ],
            'periodCounts' => [
                'upcoming' => $this->applyPeriod(clone $filteredQuery, 'upcoming', $today)->count(),
                'past' => $this->applyPeriod(clone $filteredQuery, 'past', $today)->count(),
            ],
            'calendar' => $calendar,
            'filterOptions' => [
                'countries' => $this->distinctValues(clone $baseQuery, 'country_name'),
                'types' => collect([
                    $this->distinctValues(clone $baseQuery, 'division'),
                    $this->distinctValues(clone $baseQuery, 'surface'),
                    $this->distinctValues(clone $baseQuery, 'event_type'),
                ])->flatten()->filter()->unique()->sort()->values(),
                'divisions' => $this->distinctValues(clone $baseQuery, 'division'),
                'surfaces' => $this->distinctValues(clone $baseQuery, 'surface'),
                'eventTypes' => $this->distinctValues(clone $baseQuery, 'event_type'),
            ],
        ]);
    }

    /**
     * Show a public tournament profile page.
     */
    public function show(Request $request, Tournament $tournament): View
    {
        abort_unless($tournament->is_public, 404);

        $activeTab = $this->normalizeTab($request);
        $showCaptains = $request->boolean('captains');

        $tournament->load([
            'creator',
            'crewMembers',
            'pitches',
            'registrations' => fn ($query) => $query
                ->with([
                    'team' => fn ($teamQuery) => $teamQuery
                        ->with([
                            'members' => fn ($membersQuery) => $membersQuery
                                ->orderByRaw("case when role = 'captain' then 0 when role = 'spirit_captain' then 1 else 2 end")
                                ->orderBy('name'),
                        ])
                        ->withCount('members'),
                ])
                ->orderByRaw('case when seed_number is null then 1 else 0 end')
                ->orderBy('seed_number')
                ->orderBy('id'),
            'matches' => fn ($query) => $query
                ->with(['pitch', 'homeRegistration.team', 'awayRegistration.team', 'playerStats.teamMember.team', 'spiritScores'])
                ->orderByRaw('case when scheduled_at is null then 1 else 0 end')
                ->orderBy('scheduled_at')
                ->orderBy('match_number'),
        ])->loadCount(['registrations', 'matches', 'pitches']);

        $scheduleTz = SmallFixedRoundRobinDayOneSchedule::tournamentTimezone($tournament);

        $scheduleDateOptions = $tournament->matches
            ->filter(fn ($match) => $match->scheduled_at !== null)
            ->groupBy(fn ($match): string => $this->matchLocalScheduleDateKey($match->scheduled_at, $scheduleTz))
            ->map(function ($matches, string $date) use ($scheduleTz): array {
                $first = $matches->sortBy(fn (TournamentMatch $m): int => (int) ($m->scheduled_at?->getTimestamp() ?? 0))->first();

                return [
                    'value' => $date,
                    'label' => $first?->scheduled_at?->timezone($scheduleTz)->format('j M') ?? $date,
                ];
            })
            ->sortKeys()
            ->values();

        $scheduleStageOptions = collect([
            ['value' => 'group', 'label' => 'Group'],
            ['value' => 'bracket', 'label' => 'Bracket'],
        ])->filter(function (array $option) use ($tournament): bool {
            return $tournament->matches
                ->contains(fn ($match) => $this->scheduleStageBucket($match->stage) === $option['value']);
        })->values();

        $requestedDate = trim($request->string('date')->toString());
        $stageFilter = $this->normalizeScheduleStage($request, $scheduleStageOptions);

        $dateFilter = $scheduleDateOptions->contains(fn (array $option) => $option['value'] === $requestedDate)
            ? $requestedDate
            : 'all';

        $visibleMatches = $tournament->matches
            ->filter(function ($match) use ($dateFilter, $stageFilter, $scheduleTz): bool {
                if ($dateFilter !== 'all') {
                    $localKey = $this->matchLocalScheduleDateKey($match->scheduled_at, $scheduleTz);
                    if ($localKey === null || $localKey !== $dateFilter) {
                        return false;
                    }
                }

                if ($stageFilter !== 'all' && $this->scheduleStageBucket($match->stage) !== $stageFilter) {
                    return false;
                }

                return true;
            })
            ->values();

        $scheduleTimetable = $this->buildScheduleTimetable($visibleMatches, $scheduleTz);

        $statsSummary = [
            'completed_matches' => $tournament->matches->where('status', 'completed')->count(),
            'scheduled_matches' => $tournament->matches->where('status', 'scheduled')->count(),
            'live_matches' => $tournament->matches->where('status', 'live')->count(),
            'total_points' => $tournament->matches->reduce(
                fn (int $carry, $match) => $carry + (int) ($match->home_score ?? 0) + (int) ($match->away_score ?? 0),
                0,
            ),
        ];
        $statsDivision = $this->resolveStatsDivision($tournament->division);
        $statsGenderOptions = $this->buildStatsGenderOptions($statsDivision);
        $statsMetricOptions = collect([
            ['value' => 'goals', 'label' => 'Goals'],
            ['value' => 'assists', 'label' => 'Assists'],
            ['value' => 'blocks', 'label' => 'Blocks'],
            ['value' => 'total_offense', 'label' => 'Total O'],
        ]);
        $statsFilters = [
            'gender' => $this->normalizeStatsGender($request, $statsGenderOptions),
            'metric' => $this->normalizeStatsMetric($request, $statsMetricOptions),
            'search' => trim($request->string('search')->toString()),
        ];
        $statsLeaderboard = $this->paginateStatsLeaderboard($this->buildTournamentStatsLeaderboard(
            $tournament,
            $statsFilters['gender'],
            $statsFilters['metric'],
            $statsFilters['search'],
        ), $request);

        $groupMatches = $tournament->matches
            ->filter(fn ($match) => $this->scheduleStageBucket($match->stage) === 'group')
            ->values();

        $bracketMatches = $tournament->matches
            ->filter(fn ($match) => $this->scheduleStageBucket($match->stage) === 'bracket')
            ->values();

        $groupPools = $this->buildGroupSections($tournament, $groupMatches);

        $groupPoolOptions = $groupPools
            ->pluck('name')
            ->values();

        $requestedGroupPool = $this->normalizeGroupSectionName($request->string('pool')->toString());
        $groupFilter = $groupPoolOptions->contains($requestedGroupPool)
            ? $requestedGroupPool
            : $groupPoolOptions->first();

        $selectedGroupStandings = $groupFilter
            ? $this->buildGroupStandings($tournament, $groupFilter)
            : collect();

        $selectedGroupMatches = $groupFilter
            ? $groupMatches
                ->filter(fn ($match) => $this->matchBelongsToGroupSection($match, $groupFilter))
                ->values()
            : collect();

        $bracketFlights = $tournament->registrations
            ->filter(fn ($registration) => filled($registration->bracket_code) || filled($registration->bracket_rank))
            ->groupBy(fn ($registration) => $registration->bracket_code ?: 'Unassigned')
            ->map(function (Collection $registrations, string $code): array {
                $teams = $registrations
                    ->sort(fn ($left, $right) => [
                        $left->bracket_rank ?? 'ZZZ',
                        $left->seed_number ?? PHP_INT_MAX,
                        $left->team->name,
                        $left->id,
                    ] <=> [
                        $right->bracket_rank ?? 'ZZZ',
                        $right->seed_number ?? PHP_INT_MAX,
                        $right->team->name,
                        $right->id,
                    ])
                    ->values();

                return [
                    'code' => $code,
                    'label' => $this->formatBracketFlightLabel($code, $teams),
                    'teams' => $teams,
                ];
            })
            ->sort(fn (array $left, array $right) => $left['code'] <=> $right['code'])
            ->values();

        $bracketFlightOptions = $bracketFlights
            ->pluck('code')
            ->values();

        $requestedBracketFlight = trim($request->string('flight')->toString());
        $bracketFilter = $bracketFlightOptions->contains($requestedBracketFlight)
            ? $requestedBracketFlight
            : $bracketFlightOptions->first();

        $selectedBracketMatches = $bracketFilter
            ? $bracketMatches
                ->filter(fn ($match) => $this->matchBelongsToBracketFlight($match, $bracketFilter))
                ->values()
            : $bracketMatches;

        $bracketBoard = $this->buildBracketBoard($selectedBracketMatches);
        $spiritSort = [
            'column' => $this->normalizeSpiritSort($request),
        ];
        $spiritSort['direction'] = $this->normalizeSpiritDirection($request, $spiritSort['column']);
        $spiritDirectory = $this->sortSpiritDirectory(
            $this->buildSpiritDirectory($tournament),
            $spiritSort['column'],
            $spiritSort['direction'],
        );

        $crewSections = $tournament->crewMembers
            ->groupBy(fn ($crewMember) => $crewMember->category)
            ->map(function (Collection $members, string $category): array {
                return [
                    'category' => $category,
                    'members' => $members->values(),
                ];
            })
            ->values();

        $crewDirectory = $tournament->registrations
            ->map(function ($registration): array {
                return [
                    'registration' => $registration,
                    'team' => $registration->team,
                    'leaders' => $registration->team->members
                        ->whereIn('role', ['captain', 'spirit_captain'])
                        ->values(),
                ];
            })
            ->filter(fn (array $entry) => $entry['leaders']->isNotEmpty())
            ->values();

        $mvpFilters = [
            'gender' => $statsFilters['gender'],
        ];
        $mvpLeaderboard = $this->buildTournamentMvpLeaderboard($tournament, $mvpFilters['gender']);
        $standings = $this->buildStandings($tournament);

        return view('tournaments.show', [
            'tournament' => $tournament,
            'activeTab' => $activeTab,
            'scheduleFilters' => [
                'date' => $dateFilter,
                'stage' => $stageFilter,
            ],
            'scheduleOptions' => [
                'dates' => $scheduleDateOptions,
                'stages' => $scheduleStageOptions,
            ],
            'visibleMatches' => $visibleMatches,
            'scheduleTimetable' => $scheduleTimetable,
            'statsSummary' => $statsSummary,
            'statsDivision' => $statsDivision,
            'statsFilters' => $statsFilters,
            'statsOptions' => [
                'gender' => $statsGenderOptions,
                'metrics' => $statsMetricOptions,
            ],
            'statsLeaderboard' => $statsLeaderboard,
            'showCaptains' => $showCaptains,
            'groupMatches' => $groupMatches,
            'bracketMatches' => $bracketMatches,
            'groupPools' => $groupPools,
            'groupFilter' => $groupFilter,
            'selectedGroupStandings' => $selectedGroupStandings,
            'selectedGroupMatches' => $selectedGroupMatches,
            'bracketFlights' => $bracketFlights,
            'bracketFilter' => $bracketFilter,
            'selectedBracketMatches' => $selectedBracketMatches,
            'bracketBoard' => $bracketBoard,
            'spiritDirectory' => $spiritDirectory,
            'spiritSort' => $spiritSort,
            'crewSections' => $crewSections,
            'crewDirectory' => $crewDirectory,
            'mvpFilters' => $mvpFilters,
            'mvpLeaderboard' => $mvpLeaderboard,
            'standings' => $standings,
            'scheduleDisplayTimezone' => $scheduleTz,
        ]);
    }

    /**
     * Calendar date (Y-m-d) for grouping public schedule filters in the tournament timezone.
     */
    protected function matchLocalScheduleDateKey(?CarbonInterface $scheduledAt, string $tournamentTimezone): ?string
    {
        if ($scheduledAt === null) {
            return null;
        }

        return $scheduledAt->copy()->timezone($tournamentTimezone)->toDateString();
    }

    /**
     * Build a timetable view for the current public schedule selection.
     *
     * @return array{columns: Collection<int, array<string, mixed>>, days: Collection<int, array<string, mixed>>}
     */
    protected function buildScheduleTimetable(Collection $matches, string $tournamentTimezone): array
    {
        $scheduledMatches = $matches
            ->filter(fn ($match) => $match->scheduled_at)
            ->values();

        $columns = $scheduledMatches
            ->map(function ($match): array {
                return [
                    'key' => $this->scheduleTimetablePitchKey($match),
                    'name' => $match->pitch?->name ?: 'Field TBD',
                    'location' => $match->pitch?->location,
                    'sort_order' => $match->pitch?->sort_order ?? PHP_INT_MAX,
                    'pitch_id' => $match->pitch?->id,
                ];
            })
            ->unique('key')
            ->sort(fn (array $left, array $right) => [
                $left['sort_order'],
                $left['name'],
                $left['pitch_id'] ?? PHP_INT_MAX,
            ] <=> [
                $right['sort_order'],
                $right['name'],
                $right['pitch_id'] ?? PHP_INT_MAX,
            ])
            ->values();

        $days = $scheduledMatches
            ->groupBy(fn ($match): string => $this->matchLocalScheduleDateKey($match->scheduled_at, $tournamentTimezone))
            ->map(function (Collection $dayMatches, string $date) use ($columns, $tournamentTimezone): array {
                $slotStarts = $dayMatches
                    ->map(fn ($match) => $match->scheduled_at)
                    ->sortBy(fn ($scheduledAt) => $scheduledAt->getTimestamp())
                    ->unique(fn ($scheduledAt) => $scheduledAt->format('Y-m-d H:i:s'))
                    ->values();

                $slots = $slotStarts
                    ->map(function ($slotStart, int $index) use ($slotStarts, $dayMatches, $columns): array {
                        $slotMatches = $dayMatches
                            ->filter(fn ($match) => $match->scheduled_at?->equalTo($slotStart))
                            ->values();

                        return [
                            'starts_at' => $slotStart,
                            'ends_at' => $this->resolveScheduleTimetableSlotEnd($slotStart, $slotStarts, $index),
                            'cells' => $columns
                                ->map(function (array $column) use ($slotMatches): array {
                                    return [
                                        'column' => $column,
                                        'matches' => $slotMatches
                                            ->filter(fn ($match) => $this->scheduleTimetablePitchKey($match) === $column['key'])
                                            ->values(),
                                    ];
                                })
                                ->values(),
                        ];
                    })
                    ->values();

                return [
                    'value' => $date,
                    'label' => $dayMatches
                        ->sortBy(fn ($match) => $match->scheduled_at?->getTimestamp())
                        ->first()
                        ?->scheduled_at
                        ?->timezone($tournamentTimezone)->format('j M, D') ?? $date,
                    'slots' => $slots,
                ];
            })
            ->sortBy('value')
            ->values();

        return [
            'columns' => $columns,
            'days' => $days,
        ];
    }

    /**
     * Build a public spirit summary table for each registered team.
     */
    protected function buildSpiritDirectory(Tournament $tournament): Collection
    {
        $tournament->loadMissing(['matches.spiritScores']);

        return $tournament->registrations
            ->map(function ($registration) use ($tournament): array {
                $leaders = $registration->team->members
                    ->where('role', 'spirit_captain')
                    ->values();

                $teamMatches = $tournament->matches
                    ->filter(function ($match) use ($registration): bool {
                        return $match->home_registration_id === $registration->id
                            || $match->away_registration_id === $registration->id;
                    })
                    ->values();

                $playedMatches = $teamMatches
                    ->filter(fn ($match) => in_array($match->status, ['completed', 'live'], true))
                    ->values();

                $ratedMatches = $playedMatches
                    ->sort(function ($left, $right): int {
                        return [
                            $left->scheduled_at?->getTimestamp() ?? PHP_INT_MAX,
                            $left->match_number ?? PHP_INT_MAX,
                        ] <=> [
                            $right->scheduled_at?->getTimestamp() ?? PHP_INT_MAX,
                            $right->match_number ?? PHP_INT_MAX,
                        ];
                    })
                    ->map(function ($match) use ($registration): array {
                        $isHome = $match->home_registration_id === $registration->id;
                        $opponent = $isHome
                            ? $match->awayRegistration?->team
                            : $match->homeRegistration?->team;
                        $stageLabel = $this->scheduleStageBucket($match->stage) === 'group'
                            ? 'Group Game'
                            : str($match->stage)->replace('_', ' ')->headline()->toString();

                        return [
                            'match' => $match,
                            'opponent' => $opponent,
                            'date_label' => $match->scheduled_at?->format('d M Y') ?: 'Date TBD',
                            'time_label' => $match->scheduled_at?->format('H:i') ?: 'TBD',
                            'stage_label' => $stageLabel,
                            'round_label' => $match->round_label,
                            'status_label' => 'Rated',
                        ];
                    })
                    ->values();

                $receivedScores = $playedMatches
                    ->sort(function ($left, $right): int {
                        return [
                            $left->scheduled_at?->getTimestamp() ?? PHP_INT_MAX,
                            $left->match_number ?? PHP_INT_MAX,
                        ] <=> [
                            $right->scheduled_at?->getTimestamp() ?? PHP_INT_MAX,
                            $right->match_number ?? PHP_INT_MAX,
                        ];
                    })
                    ->map(function ($match) use ($registration): ?array {
                        $isHome = $match->home_registration_id === $registration->id;
                        $opponent = $isHome
                            ? $match->awayRegistration?->team
                            : $match->homeRegistration?->team;
                        $score = $this->extractReceivedSpiritScore($match, $isHome);

                        if (! $score) {
                            return null;
                        }

                        return [
                            'match' => $match,
                            'given_by' => $opponent?->name ?: 'Opponent TBD',
                            'rules' => $score['rules'],
                            'fouls' => $score['fouls'],
                            'fair' => $score['fair'],
                            'attitude' => $score['attitude'],
                            'communication' => $score['communication'],
                            'total' => $score['total'],
                        ];
                    })
                    ->filter()
                    ->values();

                $latestMatch = $playedMatches
                    ->sort(function ($left, $right): int {
                        return [
                            $right->scheduled_at?->getTimestamp() ?? 0,
                            $right->match_number ?? 0,
                        ] <=> [
                            $left->scheduled_at?->getTimestamp() ?? 0,
                            $left->match_number ?? 0,
                        ];
                    })
                    ->first();

                return [
                    'registration' => $registration,
                    'team' => $registration->team,
                    'leaders' => $leaders,
                    'games_played' => $playedMatches->count(),
                    'games_rated' => $receivedScores->count(),
                    'games_rated_label' => $receivedScores->count().'/'.$playedMatches->count(),
                    'rating_percentage' => $playedMatches->isNotEmpty()
                        ? round(($receivedScores->count() / $playedMatches->count()) * 100)
                        : 0,
                    'overall_spirit_score' => round((float) ($receivedScores->avg('total') ?? 0), 2),
                    'avg_rules' => round((float) ($receivedScores->avg('rules') ?? 0), 2),
                    'avg_fouls' => round((float) ($receivedScores->avg('fouls') ?? 0), 2),
                    'avg_fair' => round((float) ($receivedScores->avg('fair') ?? 0), 2),
                    'avg_attitude' => round((float) ($receivedScores->avg('attitude') ?? 0), 2),
                    'avg_communication' => round((float) ($receivedScores->avg('communication') ?? 0), 2),
                    'country_flag' => $this->countryFlagEmoji($registration->team->country_name),
                    'completed_matches' => $teamMatches->where('status', 'completed')->count(),
                    'upcoming_matches' => $teamMatches->where('status', 'scheduled')->count(),
                    'latest_match' => $latestMatch,
                    'rated_matches' => $ratedMatches,
                    'received_scores' => $receivedScores,
                ];
            })
            ->filter(fn (array $entry) => $entry['games_played'] > 0 || $entry['received_scores']->isNotEmpty())
            ->values();
    }

    /**
     * Normalize the requested tournament spirit sort column.
     */
    protected function normalizeSpiritSort(Request $request): string
    {
        $sort = trim($request->string('spirit_sort')->toString());

        return in_array($sort, ['team', 'games_rated', 'overall_spirit_score', 'avg_rules', 'avg_fouls', 'avg_fair', 'avg_attitude', 'avg_communication'], true)
            ? $sort
            : 'overall_spirit_score';
    }

    /**
     * Normalize the requested tournament spirit sort direction.
     */
    protected function normalizeSpiritDirection(Request $request, string $column): string
    {
        $direction = trim($request->string('spirit_direction')->toString());
        $defaultDirection = $column === 'team' ? 'asc' : 'desc';

        return in_array($direction, ['asc', 'desc'], true)
            ? $direction
            : $defaultDirection;
    }

    /**
     * Sort the tournament spirit ranking table using a public column.
     */
    protected function sortSpiritDirectory(Collection $spiritDirectory, string $column, string $direction): Collection
    {
        $sortOrder = match ($column) {
            'team' => ['team', 'overall_spirit_score', 'games_rated'],
            'games_rated' => ['games_rated', 'overall_spirit_score', 'team'],
            'avg_rules' => ['avg_rules', 'overall_spirit_score', 'games_rated', 'team'],
            'avg_fouls' => ['avg_fouls', 'overall_spirit_score', 'games_rated', 'team'],
            'avg_fair' => ['avg_fair', 'overall_spirit_score', 'games_rated', 'team'],
            'avg_attitude' => ['avg_attitude', 'overall_spirit_score', 'games_rated', 'team'],
            'avg_communication' => ['avg_communication', 'overall_spirit_score', 'games_rated', 'team'],
            default => ['overall_spirit_score', 'games_rated', 'avg_communication', 'team'],
        };

        return $spiritDirectory
            ->sort(function (array $left, array $right) use ($sortOrder, $direction): int {
                foreach ($sortOrder as $field) {
                    if ($field === 'team') {
                        $comparison = ($left['team']->name ?? '') <=> ($right['team']->name ?? '');
                    } else {
                        $comparison = $left[$field] <=> $right[$field];
                    }

                    if ($comparison !== 0) {
                        return $direction === 'asc' ? $comparison : -$comparison;
                    }
                }

                return 0;
            })
            ->values();
    }

    /**
     * Extract a public spirit-score breakdown from persisted {@see MatchSpiritScore} rows
     * or legacy JSON embedded in {@see TournamentMatch::$notes}.
     *
     * The match notes may contain JSON with one of several common shapes, for example:
     * - {"spirit_scores":{"home_received":{...},"away_received":{...}}}
     * - {"spirit":{"home":{...},"away":{...}}}
     * - {"spirit_scores":{"12":{...},"13":{...}}} keyed by registration id
     *
     * @return array{rules:int,fouls:int,fair:int,attitude:int,communication:int,total:int}|null
     */
    protected function extractReceivedSpiritScore(TournamentMatch $match, bool $isHome): ?array
    {
        $scoredTeamId = $isHome
            ? $match->homeRegistration?->team_id
            : $match->awayRegistration?->team_id;

        if ($scoredTeamId) {
            $record = $match->relationLoaded('spiritScores')
                ? $match->spiritScores->firstWhere('scored_team_id', (int) $scoredTeamId)
                : MatchSpiritScore::query()
                    ->where('match_id', $match->id)
                    ->where('scored_team_id', $scoredTeamId)
                    ->first();

            if ($record instanceof MatchSpiritScore) {
                return [
                    'rules' => $record->knowledge_rules_score,
                    'fouls' => $record->fouls_body_contact_score,
                    'fair' => $record->fair_mindedness_score,
                    'attitude' => $record->positive_attitude_score,
                    'communication' => $record->communication_respect_score,
                    'total' => $record->total_score,
                ];
            }
        }

        if (! filled($match->notes)) {
            return null;
        }

        $payload = json_decode($match->notes, true);

        if (! is_array($payload)) {
            return null;
        }

        $scopes = array_filter([
            data_get($payload, 'spirit_scores'),
            data_get($payload, 'spirit'),
            $payload,
        ], 'is_array');

        $keys = $isHome
            ? ['home_received', 'home', 'home_team', (string) $match->home_registration_id]
            : ['away_received', 'away', 'away_team', (string) $match->away_registration_id];

        foreach ($scopes as $scope) {
            foreach ($keys as $key) {
                $candidate = data_get($scope, $key);

                if (! is_array($candidate)) {
                    continue;
                }

                $normalized = $this->normalizeSpiritScorePayload($candidate);

                if ($normalized) {
                    return $normalized;
                }
            }
        }

        return null;
    }

    /**
     * Normalize a spirit-score payload into the public five-column rubric.
     *
     * @return array{rules:int,fouls:int,fair:int,attitude:int,communication:int,total:int}|null
     */
    protected function normalizeSpiritScorePayload(array $payload): ?array
    {
        $rules = $this->extractSpiritMetricValue($payload, ['rules', 'knowledge', 'knowledge_and_use']);
        $fouls = $this->extractSpiritMetricValue($payload, ['fouls', 'fouls_and_body', 'body_contact']);
        $fair = $this->extractSpiritMetricValue($payload, ['fair', 'fair_mindedness', 'fairness']);
        $attitude = $this->extractSpiritMetricValue($payload, ['attitude', 'attit', 'positive_attitude', 'self_control']);
        $communication = $this->extractSpiritMetricValue($payload, ['communication', 'comm']);

        if ($rules === null && $fouls === null && $fair === null && $attitude === null && $communication === null) {
            return null;
        }

        return [
            'rules' => $rules ?? 0,
            'fouls' => $fouls ?? 0,
            'fair' => $fair ?? 0,
            'attitude' => $attitude ?? 0,
            'communication' => $communication ?? 0,
            'total' => (int) (($rules ?? 0) + ($fouls ?? 0) + ($fair ?? 0) + ($attitude ?? 0) + ($communication ?? 0)),
        ];
    }

    /**
     * Extract a numeric spirit-metric value from a payload using known aliases.
     */
    protected function extractSpiritMetricValue(array $payload, array $keys): ?int
    {
        foreach ($keys as $key) {
            $value = data_get($payload, $key);

            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    /**
     * Build the visible group-phase pills for the public tournament page.
     */
    protected function buildGroupSections(Tournament $tournament, Collection $groupMatches): Collection
    {
        $sections = [];

        foreach ($tournament->registrations as $registration) {
            $sectionName = $this->normalizeGroupSectionName($registration->pool_name);

            if (! $sectionName) {
                continue;
            }

            $sections[$sectionName] = [
                'name' => $sectionName,
                'label' => $this->formatPublicGroupSectionLabel($sectionName),
            ];
        }

        foreach ($groupMatches as $match) {
            $sectionName = $this->groupSectionNameForMatch($match);

            if (! $sectionName) {
                continue;
            }

            $sections[$sectionName] ??= [
                'name' => $sectionName,
                'label' => $this->formatPublicGroupSectionLabel($sectionName),
            ];
        }

        return collect($sections)
            ->sort(fn (array $left, array $right): int => $this->groupSectionSortKey($left['name']) <=> $this->groupSectionSortKey($right['name']))
            ->values();
    }

    /**
     * Build standings for a single group section.
     */
    protected function buildGroupStandings(Tournament $tournament, string $poolName): Collection
    {
        $poolName = $this->normalizeGroupSectionName($poolName);

        if (! $poolName) {
            return collect();
        }

        $relevantGroupMatches = $tournament->matches
            ->filter(fn ($match) => $this->matchBelongsToGroupSection($match, $poolName))
            ->values();

        $registrationIds = $tournament->registrations
            ->filter(fn ($registration) => $this->normalizeGroupSectionName($registration->pool_name) === $poolName)
            ->pluck('id')
            ->merge($relevantGroupMatches->flatMap(fn ($match) => [
                $match->home_registration_id,
                $match->away_registration_id,
            ]))
            ->filter()
            ->unique()
            ->values();

        $poolRegistrations = $tournament->registrations
            ->whereIn('id', $registrationIds)
            ->sort(fn ($left, $right) => [
                $left->seed_number ?? PHP_INT_MAX,
                $left->team->name,
                $left->id,
            ] <=> [
                $right->seed_number ?? PHP_INT_MAX,
                $right->team->name,
                $right->id,
            ])
            ->values();

        $rows = $poolRegistrations
            ->mapWithKeys(function ($registration): array {
                return [
                    $registration->id => [
                        'registration' => $registration,
                        'team' => $registration->team,
                        'seed_number' => $registration->seed_number,
                        'played' => 0,
                        'wins' => 0,
                        'losses' => 0,
                        'ties' => 0,
                        'points' => 0,
                        'goal_difference' => 0,
                        'goals_for' => 0,
                        'goals_against' => 0,
                        'form' => [],
                    ],
                ];
            })
            ->all();

        $completedGroupMatches = $relevantGroupMatches
            ->filter(function ($match): bool {
                return $match->status === 'completed'
                    && $match->home_score !== null
                    && $match->away_score !== null;
            })
            ->sort(function ($left, $right): int {
                return [
                    $left->scheduled_at?->getTimestamp() ?? PHP_INT_MAX,
                    $left->match_number ?? PHP_INT_MAX,
                ] <=> [
                    $right->scheduled_at?->getTimestamp() ?? PHP_INT_MAX,
                    $right->match_number ?? PHP_INT_MAX,
                ];
            })
            ->values();

        foreach ($completedGroupMatches as $match) {
            $homeId = $match->home_registration_id;
            $awayId = $match->away_registration_id;

            if (! isset($rows[$homeId], $rows[$awayId])) {
                continue;
            }

            $rows[$homeId]['played']++;
            $rows[$awayId]['played']++;

            $rows[$homeId]['goals_for'] += (int) $match->home_score;
            $rows[$homeId]['goals_against'] += (int) $match->away_score;
            $rows[$awayId]['goals_for'] += (int) $match->away_score;
            $rows[$awayId]['goals_against'] += (int) $match->home_score;

            if ($match->home_score > $match->away_score) {
                $rows[$homeId]['wins']++;
                $rows[$homeId]['points'] += 3;
                $rows[$homeId]['form'][] = 'win';

                $rows[$awayId]['losses']++;
                $rows[$awayId]['form'][] = 'loss';
            } elseif ($match->home_score < $match->away_score) {
                $rows[$awayId]['wins']++;
                $rows[$awayId]['points'] += 3;
                $rows[$awayId]['form'][] = 'win';

                $rows[$homeId]['losses']++;
                $rows[$homeId]['form'][] = 'loss';
            } else {
                $rows[$homeId]['ties']++;
                $rows[$awayId]['ties']++;
                $rows[$homeId]['points']++;
                $rows[$awayId]['points']++;
                $rows[$homeId]['form'][] = 'tie';
                $rows[$awayId]['form'][] = 'tie';
            }
        }

        return collect($rows)
            ->map(function (array $row): array {
                $row['goal_difference'] = $row['goals_for'] - $row['goals_against'];
                $row['form'] = collect($row['form'])
                    ->reverse()
                    ->take(4)
                    ->values()
                    ->all();

                return $row;
            })
            ->sort(function (array $left, array $right): int {
                foreach (['points', 'goal_difference', 'goals_for', 'wins'] as $metric) {
                    $comparison = $right[$metric] <=> $left[$metric];

                    if ($comparison !== 0) {
                        return $comparison;
                    }
                }

                return ($left['seed_number'] ?? PHP_INT_MAX) <=> ($right['seed_number'] ?? PHP_INT_MAX);
            })
            ->values();
    }

    /**
     * Normalize a public group section key from a request value or stored label.
     */
    protected function normalizeGroupSectionName(?string $sectionName): ?string
    {
        $sectionName = str($sectionName ?? '')
            ->replace(['_', '-'], ' ')
            ->squish()
            ->toString();

        if ($sectionName === '') {
            return null;
        }

        if (preg_match('/^(?:POOL|GROUP)\s+([A-Z0-9]+)$/i', $sectionName, $matches) === 1) {
            return 'POOL '.strtoupper($matches[1]);
        }

        $explicitSection = $this->extractExplicitGroupSectionName($sectionName);

        return $explicitSection ?: str($sectionName)->headline()->toString();
    }

    /**
     * Extract a named non-bracket group section from a stage or round label.
     */
    protected function extractExplicitGroupSectionName(?string $value): ?string
    {
        $value = str($value ?? '')
            ->replace(['_', '-'], ' ')
            ->squish()
            ->toString();

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(?:POOL|GROUP)\s+([A-Z0-9]+)$/i', $value, $matches) === 1) {
            return 'POOL '.strtoupper($matches[1]);
        }

        $lowerValue = str($value)->lower();

        return match (true) {
            $lowerValue->contains('reseed') => 'Reseeding',
            $lowerValue->contains('consolation') => 'Consolation',
            default => null,
        };
    }

    /**
     * Build a public-facing label for a group section pill or badge.
     */
    protected function formatPublicGroupSectionLabel(?string $sectionName): string
    {
        $sectionName = $this->normalizeGroupSectionName($sectionName);

        if (! $sectionName) {
            return 'Unassigned';
        }

        if (preg_match('/^POOL\s+([A-Z0-9]+)$/', $sectionName, $matches) === 1) {
            return 'Group '.strtoupper($matches[1]);
        }

        return $sectionName;
    }

    /**
     * Resolve the group section key for a group-like public match.
     */
    protected function groupSectionNameForMatch(TournamentMatch $match): ?string
    {
        $homePool = $this->normalizeGroupSectionName($match->homeRegistration?->pool_name);
        $awayPool = $this->normalizeGroupSectionName($match->awayRegistration?->pool_name);
        $explicitSection = $this->extractExplicitGroupSectionName($match->round_label)
            ?: $this->extractExplicitGroupSectionName($match->stage);

        if ($explicitSection) {
            return $explicitSection;
        }

        if ($homePool && $homePool === $awayPool) {
            return $homePool;
        }

        return $homePool
            ?: $awayPool;
    }

    /**
     * Sort group sections so base pools appear first, followed by extra phases.
     *
     * @return array{int, string}
     */
    protected function groupSectionSortKey(string $sectionName): array
    {
        if (preg_match('/^POOL\s+([A-Z0-9]+)$/', $sectionName, $matches) === 1) {
            return [0, strtoupper($matches[1])];
        }

        $lowerSection = str($sectionName)->lower()->toString();

        return match ($lowerSection) {
            'reseeding' => [1, $lowerSection],
            'consolation' => [2, $lowerSection],
            default => [3, $lowerSection],
        };
    }

    /**
     * Determine whether a public group-stage match belongs to the requested section.
     */
    protected function matchBelongsToGroupSection(TournamentMatch $match, string $poolName): bool
    {
        if ($this->scheduleStageBucket($match->stage) !== 'group') {
            return false;
        }

        return $this->groupSectionNameForMatch($match) === $this->normalizeGroupSectionName($poolName);
    }

    /**
     * Determine whether a public bracket-stage match belongs to the requested flight.
     */
    protected function matchBelongsToBracketFlight(TournamentMatch $match, string $flightCode): bool
    {
        if ($this->scheduleStageBucket($match->stage) !== 'bracket') {
            return false;
        }

        $homeCode = $match->homeRegistration?->bracket_code;
        $awayCode = $match->awayRegistration?->bracket_code;

        if ($homeCode === $flightCode || $awayCode === $flightCode) {
            return true;
        }

        return filled($match->round_label) && str($match->round_label)->upper()->contains(str($flightCode)->upper());
    }

    /**
     * Build a pill label for a bracket flight using the registered seed range.
     */
    protected function formatBracketFlightLabel(string $code, Collection $registrations): string
    {
        $baseLabel = $code === 'Unassigned'
            ? 'Open Flight'
            : str($code)->replace(['_', '-'], ' ')->headline()->toString();

        $seedNumbers = $registrations
            ->pluck('seed_number')
            ->filter(fn ($seed) => $seed !== null)
            ->sort()
            ->values();

        if ($seedNumbers->isEmpty()) {
            return $baseLabel;
        }

        $firstSeed = $seedNumbers->first();
        $lastSeed = $seedNumbers->last();
        $rangeLabel = $firstSeed === $lastSeed
            ? (string) $firstSeed
            : "{$firstSeed}-{$lastSeed}";

        return "{$baseLabel} - {$rangeLabel}";
    }

    /**
     * Build a knockout board with explicit tree slots for each round card.
     *
     * @return array{columns: Collection<int, array<string, mixed>>, placements: Collection<int, array<string, mixed>>, base_rows: int, total_rows: int}
     */
    protected function buildBracketBoard(Collection $matches): array
    {
        $cards = $matches
            ->map(function (TournamentMatch $match): array {
                $classification = $this->classifyBracketMatch($match);

                return [
                    'match' => $match,
                    'column_key' => $classification['column_key'],
                    'column_label' => $classification['column_label'],
                    'column_order' => $classification['column_order'],
                    'is_placement' => $classification['is_placement'],
                ];
            })
            ->sort(function (array $left, array $right): int {
                return [
                    $left['column_order'],
                    $left['is_placement'] ? 1 : 0,
                    $left['match']->scheduled_at?->getTimestamp() ?? PHP_INT_MAX,
                    $left['match']->match_number ?? PHP_INT_MAX,
                ] <=> [
                    $right['column_order'],
                    $right['is_placement'] ? 1 : 0,
                    $right['match']->scheduled_at?->getTimestamp() ?? PHP_INT_MAX,
                    $right['match']->match_number ?? PHP_INT_MAX,
                ];
            })
            ->values();

        $columns = $cards
            ->filter(fn (array $card) => ! $card['is_placement'])
            ->groupBy('column_key')
            ->map(function (Collection $group, string $key): array {
                $firstCard = $group->first();

                return [
                    'key' => $key,
                    'label' => $firstCard['column_label'],
                    'order' => $firstCard['column_order'],
                    'cards' => $group
                        ->sort(function (array $left, array $right): int {
                            return [
                                $left['match']->scheduled_at?->getTimestamp() ?? PHP_INT_MAX,
                                $left['match']->match_number ?? PHP_INT_MAX,
                            ] <=> [
                                $right['match']->scheduled_at?->getTimestamp() ?? PHP_INT_MAX,
                                $right['match']->match_number ?? PHP_INT_MAX,
                            ];
                        })
                        ->values(),
                ];
            })
            ->sortBy('order')
            ->values();

        $placements = $cards
            ->filter(fn (array $card) => $card['is_placement'])
            ->sort(function (array $left, array $right): int {
                return [
                    $left['match']->scheduled_at?->getTimestamp() ?? PHP_INT_MAX,
                    $left['match']->match_number ?? PHP_INT_MAX,
                ] <=> [
                    $right['match']->scheduled_at?->getTimestamp() ?? PHP_INT_MAX,
                    $right['match']->match_number ?? PHP_INT_MAX,
                ];
            })
            ->values();

        if ($placements->isNotEmpty() && ! $columns->contains(fn (array $column) => $column['key'] === 'finals')) {
            $columns->push([
                'key' => 'finals',
                'label' => 'Finals',
                'order' => 30,
                'cards' => collect(),
            ]);
        }

        $columns = $columns
            ->sortBy('order')
            ->values();

        $firstColumn = $columns->first();
        $baseColumnCardCount = max((int) ($firstColumn ? $firstColumn['cards']->count() : 0), 1);
        $baseRows = max(($baseColumnCardCount * 2) - 1, 1);

        $positionedColumns = $columns
            ->values()
            ->map(function (array $column, int $columnIndex) use ($columns): array {
                $step = 2 ** $columnIndex;
                $positionedCards = $column['cards']
                    ->values()
                    ->map(function (array $card, int $cardIndex) use ($step): array {
                        $card['slot'] = (($cardIndex * 2) + 1) * $step;

                        return $card;
                    })
                    ->values();

                $connectors = collect();

                if ($columnIndex < ($columns->count() - 1)) {
                    for ($cardIndex = 0; $cardIndex < $positionedCards->count(); $cardIndex += 2) {
                        $upperCard = $positionedCards->get($cardIndex);
                        $lowerCard = $positionedCards->get($cardIndex + 1);

                        if (! $upperCard || ! $lowerCard) {
                            continue;
                        }

                        $connectors->push([
                            'from_slot' => $upperCard['slot'],
                            'to_slot' => $lowerCard['slot'],
                        ]);
                    }
                }

                $column['cards'] = $positionedCards;
                $column['connectors'] = $connectors;

                return $column;
            })
            ->values();

        $positionedPlacements = $placements
            ->values()
            ->map(function (array $card, int $index) use ($baseRows): array {
                $card['slot'] = $baseRows + 2 + ($index * 2);

                return $card;
            })
            ->values();

        $totalRows = max(
            $baseRows,
            (int) ($positionedPlacements->max('slot') ?? 0)
        );

        return [
            'columns' => $positionedColumns,
            'placements' => $positionedPlacements,
            'base_rows' => $baseRows,
            'total_rows' => max($totalRows, 1),
        ];
    }

    /**
     * Resolve a public bracket match into a board column and placement bucket.
     *
     * @return array{column_key: string, column_label: string, column_order: int, is_placement: bool}
     */
    protected function classifyBracketMatch(TournamentMatch $match): array
    {
        $stage = str($match->stage)->lower()->toString();
        $roundLabel = str($match->round_label)->lower()->toString();
        $isPlacement = str($stage)->contains(['placement', 'consolation'])
            || str($roundLabel)->contains(['bronze', 'place', '3rd', '5th', '7th', '9th']);

        if (str($stage)->contains('quarter') || preg_match('/\bqf\b/i', $match->round_label ?? '') === 1 || str($roundLabel)->contains('quarter')) {
            return [
                'column_key' => 'quarterfinals',
                'column_label' => 'Quarterfinals',
                'column_order' => 10,
                'is_placement' => false,
            ];
        }

        if (str($stage)->contains('semi') || preg_match('/\bsf\b/i', $match->round_label ?? '') === 1 || str($roundLabel)->contains('semi')) {
            return [
                'column_key' => 'semifinals',
                'column_label' => 'Semifinals',
                'column_order' => 20,
                'is_placement' => false,
            ];
        }

        if ($isPlacement) {
            return [
                'column_key' => 'finals',
                'column_label' => 'Finals',
                'column_order' => 40,
                'is_placement' => true,
            ];
        }

        if (str($stage)->contains('final') || str($roundLabel)->contains(['final', 'championship'])) {
            return [
                'column_key' => 'finals',
                'column_label' => 'Finals',
                'column_order' => 30,
                'is_placement' => false,
            ];
        }

        return [
            'column_key' => 'elimination',
            'column_label' => 'Bracket',
            'column_order' => 15,
            'is_placement' => false,
        ];
    }

    /**
     * Infer the end label for a public timetable slot.
     */
    protected function resolveScheduleTimetableSlotEnd($slotStart, Collection $slotStarts, int $index)
    {
        $nextSlotStart = $slotStarts->get($index + 1);

        if ($nextSlotStart && $nextSlotStart->greaterThan($slotStart)) {
            $slotEnd = $nextSlotStart->subMinutes(10);

            if ($slotEnd->greaterThan($slotStart)) {
                return $slotEnd;
            }
        }

        return $slotStart->addHour();
    }

    /**
     * Normalize a pitch into a reusable timetable column key.
     */
    protected function scheduleTimetablePitchKey(TournamentMatch $match): string
    {
        return $match->pitch
            ? 'pitch-'.$match->pitch->id
            : 'pitch-tbd';
    }

    /**
     * Build a tournament-wide MVP leaderboard from published player stats.
     */
    protected function buildTournamentMvpLeaderboard(Tournament $tournament, string $genderFilter = 'all'): Collection
    {
        return $tournament->matches
            ->flatMap(fn ($match) => $match->playerStats)
            ->groupBy('team_member_id')
            ->map(function (Collection $stats): ?array {
                $member = $stats->first()?->teamMember;
                $team = $member?->team;

                if (! $member || ! $team) {
                    return null;
                }

                $goals = $stats->sum('goals');
                $assists = $stats->sum('assists');
                $blocks = $stats->sum('blocks');
                $normalizedGender = $this->normalizePlayerGender($member->gender);

                return [
                    'member' => $member,
                    'team' => $team,
                    'display_name' => $member->name,
                    'gender' => $normalizedGender,
                    'gender_label' => $this->formatPlayerGenderLabel($member->gender, $normalizedGender),
                    'goals' => $goals,
                    'assists' => $assists,
                    'blocks' => $blocks,
                    'matches_played' => $stats->pluck('match_id')->unique()->count(),
                    'impact' => ($goals * 3) + ($assists * 2) + $blocks,
                ];
            })
            ->filter()
            ->filter(function (array $candidate) use ($genderFilter): bool {
                if ($candidate['impact'] <= 0) {
                    return false;
                }

                if ($genderFilter !== 'all' && $candidate['gender'] !== $genderFilter) {
                    return false;
                }

                return true;
            })
            ->sort(function (array $left, array $right): int {
                foreach (['impact', 'goals', 'assists', 'blocks', 'matches_played'] as $metric) {
                    $comparison = $right[$metric] <=> $left[$metric];

                    if ($comparison !== 0) {
                        return $comparison;
                    }
                }

                return $left['display_name'] <=> $right['display_name'];
            })
            ->values();
    }

    /**
     * Normalize the public-facing division label for the stats leaderboard.
     *
     * @return array{value: string, label: string}
     */
    protected function resolveStatsDivision(?string $division): array
    {
        $normalized = str($division ?? '')->lower()->trim()->toString();

        return match (true) {
            in_array($normalized, ['mix', 'mixed'], true) => [
                'value' => 'mixed',
                'label' => 'Mixed',
            ],
            in_array($normalized, ['men', 'male'], true) => [
                'value' => 'men',
                'label' => 'Men',
            ],
            in_array($normalized, ['women', 'woman', 'female'], true) => [
                'value' => 'women',
                'label' => 'Women',
            ],
            filled($division) => [
                'value' => str($normalized ?: 'open')->slug()->toString() ?: 'open',
                'label' => str($division)->headline()->toString(),
            ],
            default => [
                'value' => 'open',
                'label' => 'Open',
            ],
        };
    }

    /**
     * Build the gender chip options used in the public stats tab.
     *
     * @param  array{value: string, label: string}  $statsDivision
     * @return Collection<int, array{value: string, label: string}>
     */
    protected function buildStatsGenderOptions(array $statsDivision): Collection
    {
        if ($statsDivision['value'] === 'men') {
            return collect([
                ['value' => 'men', 'label' => 'Men'],
            ]);
        }

        if ($statsDivision['value'] === 'women') {
            return collect([
                ['value' => 'women', 'label' => 'Women'],
            ]);
        }

        if ($statsDivision['value'] === 'mixed') {
            return collect([
                ['value' => 'all', 'label' => 'Mix (All)'],
                ['value' => 'men', 'label' => 'Mix (Men)'],
                ['value' => 'women', 'label' => 'Mix (Women)'],
            ]);
        }

        return collect([
            ['value' => 'all', 'label' => 'All'],
            ['value' => 'men', 'label' => 'Men'],
            ['value' => 'women', 'label' => 'Women'],
        ]);
    }

    /**
     * Build the player leaderboard used by the public stats tab.
     */
    protected function buildTournamentStatsLeaderboard(
        Tournament $tournament,
        string $genderFilter,
        string $metric,
        string $search
    ): Collection {
        $needle = str($search)->lower()->trim()->toString();
        $sortOrder = $this->statsLeaderboardSortOrder($metric);

        return $tournament->matches
            ->flatMap(fn ($match) => $match->playerStats)
            ->groupBy('team_member_id')
            ->map(function (Collection $stats): ?array {
                $member = $stats->first()?->teamMember;
                $team = $member?->team;

                if (! $member || ! $team) {
                    return null;
                }

                $goals = (int) $stats->sum('goals');
                $assists = (int) $stats->sum('assists');
                $blocks = (int) $stats->sum('blocks');

                return [
                    'member' => $member,
                    'team' => $team,
                    'display_name' => $this->formatStatsPlayerName($member),
                    'gender' => $this->normalizePlayerGender($member->gender),
                    'goals' => $goals,
                    'assists' => $assists,
                    'blocks' => $blocks,
                    'total_offense' => $goals + $assists,
                    'matches_played' => $stats->pluck('match_id')->unique()->count(),
                    'search_index' => str(collect([
                        $member->name,
                        $member->nickname,
                        $team->name,
                    ])->filter()->implode(' '))->lower()->toString(),
                ];
            })
            ->filter()
            ->filter(function (array $player) use ($genderFilter, $needle): bool {
                if ($player['goals'] + $player['assists'] + $player['blocks'] <= 0) {
                    return false;
                }

                if ($genderFilter !== 'all' && $player['gender'] !== $genderFilter) {
                    return false;
                }

                if ($needle !== '' && ! str_contains($player['search_index'], $needle)) {
                    return false;
                }

                return true;
            })
            ->sort(function (array $left, array $right) use ($sortOrder): int {
                foreach ($sortOrder as $metricKey) {
                    $comparison = $right[$metricKey] <=> $left[$metricKey];

                    if ($comparison !== 0) {
                        return $comparison;
                    }
                }

                return [$left['display_name'], $left['team']->name] <=> [$right['display_name'], $right['team']->name];
            })
            ->values()
            ->map(function (array $player, int $index): array {
                return [
                    ...$player,
                    'rank' => $index + 1,
                ];
            });
    }

    /**
     * Paginate the ranked public stats leaderboard.
     */
    protected function paginateStatsLeaderboard(Collection $leaderboard, Request $request): LengthAwarePaginator
    {
        $perPage = 20;
        $page = max($request->integer('page', 1), 1);

        return (new LengthAwarePaginator(
            $leaderboard->forPage($page, $perPage)->values(),
            $leaderboard->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'pageName' => 'page',
            ],
        ))->appends(collect($request->query())->except('page')->all());
    }

    /**
     * Resolve tie-break sort priority for the selected stats metric.
     *
     * @return list<string>
     */
    protected function statsLeaderboardSortOrder(string $metric): array
    {
        return match ($metric) {
            'assists' => ['assists', 'total_offense', 'goals', 'blocks', 'matches_played'],
            'blocks' => ['blocks', 'total_offense', 'goals', 'assists', 'matches_played'],
            'total_offense' => ['total_offense', 'goals', 'assists', 'blocks', 'matches_played'],
            default => ['goals', 'total_offense', 'assists', 'blocks', 'matches_played'],
        };
    }

    /**
     * Render the public player name label used in stats tables.
     *
     * Registration tier is sometimes stored in {@see TeamMember::$nickname} (see static roster seeders).
     * Those values must not be shown as a parenthetical suffix on the public leaderboard.
     */
    protected function formatStatsPlayerName($member): string
    {
        $nickname = $member->nickname;

        if (filled($nickname) && ! $this->nicknameIsRegistrationTierLabel($nickname)) {
            return "{$member->name} ({$nickname})";
        }

        return $member->name;
    }

    /**
     * Whether the member nickname should be hidden on public stats (roster / registration tier only).
     */
    protected function nicknameIsRegistrationTierLabel(string $nickname): bool
    {
        $normalized = str($nickname)->lower()->trim()->toString();

        if ($normalized === '') {
            return false;
        }

        if (in_array($normalized, ['full', 'lite', 'organizer', 'partial', 'guest', 'maybe'], true)) {
            return true;
        }

        return str_starts_with($normalized, 'walk in');
    }

    /**
     * Normalize roster gender values into leaderboard filter buckets.
     */
    protected function normalizePlayerGender(?string $gender): string
    {
        $normalized = str($gender ?? '')->lower()->trim()->toString();

        return match (true) {
            in_array($normalized, ['man', 'men', 'male', 'boy'], true) => 'men',
            in_array($normalized, ['woman', 'women', 'female', 'girl'], true) => 'women',
            default => 'unknown',
        };
    }

    /**
     * Present a roster gender value in the public leaderboard tables.
     */
    protected function formatPlayerGenderLabel(?string $gender, string $normalizedGender): string
    {
        if (filled($gender)) {
            return str($gender)->headline()->toString();
        }

        return match ($normalizedGender) {
            'men' => 'Male',
            'women' => 'Female',
            default => 'Not listed',
        };
    }

    /**
     * Map common tournament countries to emoji flags for public team cards.
     */
    protected function countryFlagEmoji(?string $countryName): string
    {
        return [
            'philippines' => '🇵🇭',
            'malaysia' => '🇲🇾',
            'singapore' => '🇸🇬',
            'south korea' => '🇰🇷',
            'korea' => '🇰🇷',
            'japan' => '🇯🇵',
        ][str($countryName ?? '')->lower()->trim()->toString()] ?? '';
    }

    /**
     * Build an overall standings table from completed public results.
     */
    protected function buildStandings(Tournament $tournament): Collection
    {
        $divisionLabel = $this->resolveStatsDivision($tournament->division)['label'];

        $rows = $tournament->registrations
            ->mapWithKeys(function ($registration): array {
                return [
                    $registration->id => [
                        'registration' => $registration,
                        'team' => $registration->team,
                        'division_label' => '',
                        'country_flag' => '',
                        'seed_number' => $registration->seed_number,
                        'played' => 0,
                        'wins' => 0,
                        'losses' => 0,
                        'ties' => 0,
                        'points_for' => 0,
                        'points_against' => 0,
                        'diff' => 0,
                        'ranking_points' => 0,
                    ],
                ];
            })
            ->all();

        foreach ($tournament->matches as $match) {
            if (
                $match->status !== 'completed'
                || ! $match->homeRegistration
                || ! $match->awayRegistration
                || $match->home_score === null
                || $match->away_score === null
            ) {
                continue;
            }

            $homeId = $match->home_registration_id;
            $awayId = $match->away_registration_id;

            if (! isset($rows[$homeId], $rows[$awayId])) {
                continue;
            }

            $rows[$homeId]['played']++;
            $rows[$awayId]['played']++;

            $rows[$homeId]['points_for'] += (int) $match->home_score;
            $rows[$homeId]['points_against'] += (int) $match->away_score;
            $rows[$awayId]['points_for'] += (int) $match->away_score;
            $rows[$awayId]['points_against'] += (int) $match->home_score;

            if ($match->home_score > $match->away_score) {
                $rows[$homeId]['wins']++;
                $rows[$awayId]['losses']++;
            } elseif ($match->home_score < $match->away_score) {
                $rows[$awayId]['wins']++;
                $rows[$homeId]['losses']++;
            } else {
                $rows[$homeId]['ties']++;
                $rows[$awayId]['ties']++;
            }
        }

        return collect($rows)
            ->map(function (array $row) use ($divisionLabel): array {
                $row['diff'] = $row['points_for'] - $row['points_against'];
                $row['ranking_points'] = ($row['wins'] * 3) + $row['ties'];
                $row['division_label'] = $divisionLabel;
                $row['country_flag'] = $this->countryFlagEmoji($row['team']?->country_name);

                return $row;
            })
            ->sort(function (array $left, array $right): int {
                foreach (['ranking_points', 'wins', 'diff', 'points_for'] as $metric) {
                    $comparison = $right[$metric] <=> $left[$metric];

                    if ($comparison !== 0) {
                        return $comparison;
                    }
                }

                return ($left['seed_number'] ?? PHP_INT_MAX) <=> ($right['seed_number'] ?? PHP_INT_MAX);
            })
            ->values();
    }

    /**
     * Show a public tournament team profile page from the standings table.
     */
    public function showTeam(Request $request, Tournament $tournament, Team $team): View
    {
        abort_unless($tournament->is_public, 404);

        $profileView = $this->normalizeTeamProfileView($request);
        $playerStatsSort = [
            'column' => $this->normalizeTeamProfilePlayerStatSort($request),
        ];
        $playerStatsSort['direction'] = $this->normalizeTeamProfilePlayerStatDirection(
            $request,
            $playerStatsSort['column'],
        );
        $registration = $tournament->registrations()
            ->where('team_id', $team->id)
            ->with([
                'team' => fn ($query) => $query->with([
                    'members' => fn ($membersQuery) => $membersQuery
                        ->orderByRaw("case when role = 'captain' then 0 when role = 'spirit_captain' then 1 else 2 end")
                        ->orderBy('name'),
                ]),
            ])
            ->firstOrFail();

        $team = $registration->team;

        $matches = $tournament->matches()
            ->with([
                'pitch',
                'homeRegistration.team',
                'awayRegistration.team',
                'playerStats' => fn ($query) => $query
                    ->with('teamMember')
                    ->orderByDesc('goals')
                    ->orderByDesc('assists')
                    ->orderBy('id'),
                'scoreLogs' => fn ($query) => $query
                    ->with(['registration.team', 'scorer', 'assister'])
                    ->orderBy('sequence'),
            ])
            ->where(function (Builder $query) use ($registration): void {
                $query
                    ->where('home_registration_id', $registration->id)
                    ->orWhere('away_registration_id', $registration->id);
            })
            ->orderByRaw('case when scheduled_at is null then 1 else 0 end')
            ->orderBy('scheduled_at')
            ->orderBy('match_number')
            ->get();

        $playerStatsBase = $this->buildTournamentTeamPlayerStats($team, $matches);
        $playerStats = $this->sortTournamentTeamPlayerStats(
            $playerStatsBase,
            $playerStatsSort['column'],
            $playerStatsSort['direction'],
        );
        $statCharts = collect([
            $this->buildTeamStatDonut($playerStatsBase, 'goals', 'Goals'),
            $this->buildTeamStatDonut($playerStatsBase, 'assists', 'Assists'),
            $this->buildTeamStatDonut($playerStatsBase, 'total_offense', 'Total'),
        ]);
        $teamGames = $this->buildTournamentTeamGames($registration, $matches);
        $gamesPlayedSummary = $this->buildTournamentTeamGamesSummary($teamGames);
        $spiritProfile = $this->buildSpiritDirectory($tournament)
            ->first(fn (array $entry): bool => $entry['registration']->id === $registration->id);
        $malePlayers = $team->members
            ->filter(fn ($member): bool => $this->normalizePlayerGender($member->gender) === 'men')
            ->count();
        $femalePlayers = $team->members
            ->filter(fn ($member): bool => $this->normalizePlayerGender($member->gender) === 'women')
            ->count();

        return view('tournaments.teams.show', [
            'tournament' => $tournament,
            'team' => $team,
            'registration' => $registration,
            'profileView' => $profileView,
            'backLink' => route('tournaments.show', ['tournament' => $tournament, 'tab' => 'standings']),
            'statsDivision' => $this->resolveStatsDivision($tournament->division),
            'teamSummary' => [
                'total_players' => $team->members->count(),
                'male_players' => $malePlayers,
                'female_players' => $femalePlayers,
                'country_flag' => $this->countryFlagEmoji($team->country_name),
                'location' => $team->locationLabel(),
            ],
            'rosterDeadlinePassed' => $tournament->registration_deadline?->isPast() ?? false,
            'playerStatsSort' => $playerStatsSort,
            'playerStats' => $playerStats,
            'statCharts' => $statCharts,
            'teamGames' => $teamGames,
            'gamesPlayedSummary' => $gamesPlayedSummary,
            'spiritProfile' => $spiritProfile,
        ]);
    }

    /**
     * Normalize the requested team profile subsection.
     */
    protected function normalizeTeamProfileView(Request $request): string
    {
        $view = trim($request->string('view')->toString());

        return in_array($view, ['player-stats', 'games-played', 'spirit'], true)
            ? $view
            : 'player-stats';
    }

    /**
     * Normalize the requested team player-stats sort column.
     */
    protected function normalizeTeamProfilePlayerStatSort(Request $request): string
    {
        $sort = trim($request->string('sort')->toString());

        return in_array($sort, ['name', 'goals', 'assists', 'total_offense'], true)
            ? $sort
            : 'total_offense';
    }

    /**
     * Normalize the requested team player-stats sort direction.
     */
    protected function normalizeTeamProfilePlayerStatDirection(Request $request, string $column): string
    {
        $direction = trim($request->string('direction')->toString());
        $defaultDirection = $column === 'name' ? 'asc' : 'desc';

        return in_array($direction, ['asc', 'desc'], true)
            ? $direction
            : $defaultDirection;
    }

    /**
     * Build tournament-wide player totals for a specific team.
     */
    protected function buildTournamentTeamPlayerStats(Team $team, Collection $matches): Collection
    {
        return $matches
            ->flatMap(fn ($match) => $match->playerStats)
            ->filter(fn ($stat): bool => $stat->teamMember?->team_id === $team->id)
            ->groupBy('team_member_id')
            ->map(function (Collection $stats): ?array {
                $member = $stats->first()?->teamMember;

                if (! $member) {
                    return null;
                }

                $goals = (int) $stats->sum('goals');
                $assists = (int) $stats->sum('assists');
                $blocks = (int) $stats->sum('blocks');

                return [
                    'member' => $member,
                    'display_name' => $this->formatStatsPlayerName($member),
                    'goals' => $goals,
                    'assists' => $assists,
                    'blocks' => $blocks,
                    'total_offense' => $goals + $assists,
                ];
            })
            ->filter()
            ->filter(fn (array $player): bool => $player['goals'] > 0 || $player['assists'] > 0 || $player['blocks'] > 0)
            ->values();
    }

    /**
     * Sort a team's aggregated player table using the selected public column and direction.
     */
    protected function sortTournamentTeamPlayerStats(Collection $playerStats, string $column, string $direction): Collection
    {
        $sortOrder = match ($column) {
            'name' => ['display_name', 'total_offense', 'goals', 'assists'],
            'goals' => ['goals', 'total_offense', 'assists', 'display_name'],
            'assists' => ['assists', 'total_offense', 'goals', 'display_name'],
            default => ['total_offense', 'goals', 'assists', 'display_name'],
        };

        return $playerStats
            ->sort(function (array $left, array $right) use ($sortOrder, $direction): int {
                foreach ($sortOrder as $metric) {
                    $comparison = $metric === 'display_name'
                        ? ($left[$metric] <=> $right[$metric])
                        : ($left[$metric] <=> $right[$metric]);

                    if ($comparison !== 0) {
                        return $direction === 'asc' ? $comparison : -$comparison;
                    }
                }

                return 0;
            })
            ->values();
    }

    /**
     * Build a compact donut chart definition for team stat summaries.
     *
     * @return array{label: string, metric: string, total: int, gradient: string, segments: Collection<int, array{name: string, value: int, color: string, percentage: float}>}
     */
    protected function buildTeamStatDonut(Collection $playerStats, string $metric, string $label): array
    {
        $palette = ['#f9c74f', '#ff7a59', '#ff9f40', '#52c7d4', '#ef476f', '#2da8ff', '#52d2a8', '#8a5cf6', '#9ad65b', '#38bdf8', '#f472b6', '#f97316'];
        $segments = $playerStats
            ->filter(fn (array $player): bool => $player[$metric] > 0)
            ->sort(function (array $left, array $right) use ($metric): int {
                foreach ([$metric, 'total_offense', 'goals', 'assists'] as $field) {
                    $comparison = $right[$field] <=> $left[$field];

                    if ($comparison !== 0) {
                        return $comparison;
                    }
                }

                return $left['display_name'] <=> $right['display_name'];
            })
            ->values();
        $total = (int) $segments->sum($metric);

        if ($total === 0) {
            return [
                'label' => $label,
                'metric' => $metric,
                'total' => 0,
                'gradient' => 'conic-gradient(#e5e7eb 0deg 360deg)',
                'segments' => collect(),
            ];
        }

        $degrees = 0.0;
        $gradientParts = [];
        $segmentDefinitions = $segments->values()->map(function (array $player, int $index) use ($metric, $palette, $total, &$degrees, &$gradientParts): array {
            $color = $palette[$index % count($palette)];
            $value = (int) $player[$metric];
            $percentage = round(($value / $total) * 100, 1);
            $portion = ($value / $total) * 360;
            $start = $degrees;
            $degrees += $portion;
            $gradientParts[] = sprintf('%s %.2fdeg %.2fdeg', $color, $start, $degrees);

            return [
                'name' => $player['display_name'],
                'value' => $value,
                'color' => $color,
                'percentage' => $percentage,
            ];
        });

        if ($degrees < 360) {
            $gradientParts[] = sprintf('#e5e7eb %.2fdeg 360deg', $degrees);
        }

        return [
            'label' => $label,
            'metric' => $metric,
            'total' => $total,
            'gradient' => 'conic-gradient('.implode(', ', $gradientParts).')',
            'segments' => $segmentDefinitions,
        ];
    }

    /**
     * Build team-specific public match rows for the team profile page.
     */
    protected function buildTournamentTeamGames($registration, Collection $matches): Collection
    {
        return $matches
            ->map(function (TournamentMatch $match) use ($registration): array {
                $isHome = $match->home_registration_id === $registration->id;
                $homeTeam = $match->homeRegistration?->team;
                $awayTeam = $match->awayRegistration?->team;
                $opponent = $isHome
                    ? $awayTeam
                    : $homeTeam;
                $teamScore = $isHome ? $match->home_score : $match->away_score;
                $opponentScore = $isHome ? $match->away_score : $match->home_score;
                $stageBucket = $this->scheduleStageBucket($match->stage);
                $stageLabel = $stageBucket === 'group'
                    ? 'Group Game'
                    : str($match->stage)->replace('_', ' ')->headline()->toString();
                $statusLabel = match ($match->status) {
                    'completed' => 'Ended',
                    'live' => 'Live',
                    default => 'Scheduled',
                };
                $statusTone = match ($match->status) {
                    'completed' => 'bg-[#67d6b3] text-white',
                    'live' => 'bg-[#2f55b7] text-white',
                    default => 'bg-zinc-100 text-zinc-600',
                };
                $groupLabel = $stageBucket === 'group'
                    ? $this->formatTournamentTeamGameGroupLabel(
                        $match->round_label
                        ?: $match->homeRegistration?->pool_name
                        ?: $match->awayRegistration?->pool_name,
                    )
                    : null;
                $shortDateLabel = $match->scheduled_at
                    ? strtoupper($match->scheduled_at->format('j M'))
                    : 'DATE TBD';

                $resultLabel = match (true) {
                    $match->status !== 'completed' || $teamScore === null || $opponentScore === null => $statusLabel,
                    $teamScore > $opponentScore => 'Win',
                    $teamScore < $opponentScore => 'Loss',
                    default => 'Tie',
                };

                $resultTone = match ($resultLabel) {
                    'Win' => 'bg-[#e8f8f1] text-[#0f9f6e]',
                    'Loss' => 'bg-[#fff1f2] text-[#e11d48]',
                    'Tie' => 'bg-zinc-100 text-zinc-600',
                    'Live' => 'bg-[#2f55b7] text-white',
                    default => 'bg-zinc-100 text-zinc-600',
                };

                return [
                    'match' => $match,
                    'opponent' => $opponent,
                    'is_home_team' => $isHome,
                    'home_team' => $homeTeam,
                    'away_team' => $awayTeam,
                    'home_score' => $match->home_score,
                    'away_score' => $match->away_score,
                    'team_score' => $teamScore,
                    'opponent_score' => $opponentScore,
                    'scoreline' => $teamScore !== null && $opponentScore !== null ? "{$teamScore} - {$opponentScore}" : 'TBD',
                    'date_label' => $match->scheduled_at?->format('d M Y') ?: 'Date TBD',
                    'short_date_label' => $shortDateLabel,
                    'time_label' => $match->scheduled_at?->format('H:i') ?: 'TBD',
                    'stage_label' => $stageLabel,
                    'group_label' => $groupLabel,
                    'status_label' => $statusLabel,
                    'status_tone' => $statusTone,
                    'result_label' => $resultLabel,
                    'result_tone' => $resultTone,
                    'pitch_label' => $match->pitch?->name ?: 'Field TBD',
                ];
            })
            ->values();
    }

    /**
     * Build a public summary for the team games-played tab.
     *
     * @return array{has_completed_games: bool, wins: int, losses: int, ties: int, wins_percentage: float, losses_percentage: float, points_for: int, points_against: int, points_for_percentage: float, points_against_percentage: float}
     */
    protected function buildTournamentTeamGamesSummary(Collection $teamGames): array
    {
        $completedGames = $teamGames
            ->filter(fn (array $game): bool => $game['match']->status === 'completed' && $game['team_score'] !== null && $game['opponent_score'] !== null)
            ->values();

        $wins = $completedGames->where('result_label', 'Win')->count();
        $losses = $completedGames->where('result_label', 'Loss')->count();
        $ties = $completedGames->where('result_label', 'Tie')->count();
        $decisionTotal = max($wins + $losses, 1);
        $pointsFor = (int) $completedGames->sum('team_score');
        $pointsAgainst = (int) $completedGames->sum('opponent_score');
        $pointsTotal = max($pointsFor + $pointsAgainst, 1);

        return [
            'has_completed_games' => $completedGames->isNotEmpty(),
            'wins' => $wins,
            'losses' => $losses,
            'ties' => $ties,
            'wins_percentage' => $wins > 0 ? round(($wins / $decisionTotal) * 100, 2) : 0.0,
            'losses_percentage' => $losses > 0 ? round(($losses / $decisionTotal) * 100, 2) : 0.0,
            'points_for' => $pointsFor,
            'points_against' => $pointsAgainst,
            'points_for_percentage' => $pointsFor > 0 ? round(($pointsFor / $pointsTotal) * 100, 2) : 0.0,
            'points_against_percentage' => $pointsAgainst > 0 ? round(($pointsAgainst / $pointsTotal) * 100, 2) : 0.0,
        ];
    }

    /**
     * Format a public-facing pool label for the team games cards.
     */
    protected function formatTournamentTeamGameGroupLabel(?string $label): ?string
    {
        return filled($label)
            ? $this->formatPublicGroupSectionLabel($label)
            : null;
    }

    /**
     * Show a public match detail page from the tournament schedule.
     */
    public function showMatch(Request $request, Tournament $tournament, TournamentMatch $match): View
    {
        abort_unless($tournament->is_public && $match->tournament_id === $tournament->id, 404);

        $activeMatchTab = $this->normalizeMatchTab($request);

        $match->load([
            'tournament.creator',
            'pitch',
            'homeRegistration.team' => fn ($query) => $query->with([
                'members' => fn ($membersQuery) => $membersQuery
                    ->orderByRaw("case when role = 'captain' then 0 when role = 'spirit_captain' then 1 else 2 end")
                    ->orderBy('name'),
            ]),
            'awayRegistration.team' => fn ($query) => $query->with([
                'members' => fn ($membersQuery) => $membersQuery
                    ->orderByRaw("case when role = 'captain' then 0 when role = 'spirit_captain' then 1 else 2 end")
                    ->orderBy('name'),
            ]),
            'playerStats' => fn ($query) => $query
                ->with(['teamMember.team'])
                ->orderByDesc('goals')
                ->orderByDesc('assists')
                ->orderByDesc('blocks')
                ->orderBy('id'),
            'scoreLogs' => fn ($query) => $query
                ->with(['registration.team', 'scorer', 'assister'])
                ->orderBy('sequence'),
            'spiritScores',
        ]);

        $homeTeam = $match->homeRegistration?->team;
        $awayTeam = $match->awayRegistration?->team;

        $homeStats = $match->playerStats
            ->filter(fn ($stat) => $stat->teamMember?->team_id === $homeTeam?->id)
            ->values();

        $awayStats = $match->playerStats
            ->filter(fn ($stat) => $stat->teamMember?->team_id === $awayTeam?->id)
            ->values();

        $mvpCandidates = $match->playerStats
            ->map(function ($stat) {
                $impact = ((int) $stat->goals * 3) + ((int) $stat->assists * 2) + (int) $stat->blocks;

                return [
                    'stat' => $stat,
                    'impact' => $impact,
                ];
            })
            ->filter(fn (array $candidate) => $candidate['impact'] > 0)
            ->sortByDesc('impact')
            ->values();

        $teamLeadership = collect([
            'home' => $homeTeam?->members?->whereIn('role', ['captain', 'spirit_captain'])->values() ?? collect(),
            'away' => $awayTeam?->members?->whereIn('role', ['captain', 'spirit_captain'])->values() ?? collect(),
        ]);

        $teamSummaries = [
            'home' => [
                'goals' => $homeStats->sum('goals'),
                'assists' => $homeStats->sum('assists'),
                'blocks' => $homeStats->sum('blocks'),
            ],
            'away' => [
                'goals' => $awayStats->sum('goals'),
                'assists' => $awayStats->sum('assists'),
                'blocks' => $awayStats->sum('blocks'),
            ],
        ];

        $scoreBreakdown = $match->scoreLogs
            ->map(function ($log) use ($match): array {
                $isHomeScore = $log->team_registration_id === $match->home_registration_id;

                return [
                    'is_home_score' => $isHomeScore,
                    'minute' => $log->minute,
                    'score' => $log->home_score.' - '.$log->away_score,
                    'scorer' => $log->scorer?->name ?: $log->registration?->team?->name,
                    'assister' => $log->assister?->name,
                ];
            });

        $comparisonRows = collect([
            [
                'label' => 'Points',
                'home' => (int) ($match->home_score ?? 0),
                'away' => (int) ($match->away_score ?? 0),
            ],
            [
                'label' => 'Scoring Plays',
                'home' => $match->scoreLogs->where('team_registration_id', $match->home_registration_id)->count(),
                'away' => $match->scoreLogs->where('team_registration_id', $match->away_registration_id)->count(),
            ],
            [
                'label' => 'Assists Logged',
                'home' => $match->scoreLogs
                    ->where('team_registration_id', $match->home_registration_id)
                    ->whereNotNull('assist_team_member_id')
                    ->count(),
                'away' => $match->scoreLogs
                    ->where('team_registration_id', $match->away_registration_id)
                    ->whereNotNull('assist_team_member_id')
                    ->count(),
            ],
            [
                'label' => 'Blocks',
                'home' => $homeStats->sum('blocks'),
                'away' => $awayStats->sum('blocks'),
            ],
            [
                'label' => 'Scoring Players',
                'home' => $match->scoreLogs
                    ->where('team_registration_id', $match->home_registration_id)
                    ->pluck('team_member_id')
                    ->filter()
                    ->unique()
                    ->count(),
                'away' => $match->scoreLogs
                    ->where('team_registration_id', $match->away_registration_id)
                    ->pluck('team_member_id')
                    ->filter()
                    ->unique()
                    ->count(),
            ],
            [
                'label' => 'Leadership Tagged',
                'home' => $teamLeadership['home']->count(),
                'away' => $teamLeadership['away']->count(),
            ],
        ]);

        return view('tournaments.matches.show', [
            'tournament' => $tournament,
            'match' => $match,
            'activeMatchTab' => $activeMatchTab,
            'homeTeam' => $homeTeam,
            'awayTeam' => $awayTeam,
            'homeStats' => $homeStats,
            'awayStats' => $awayStats,
            'mvpCandidates' => $mvpCandidates,
            'teamLeadership' => $teamLeadership,
            'teamSummaries' => $teamSummaries,
            'scoreBreakdown' => $scoreBreakdown,
            'comparisonRows' => $comparisonRows,
            'resultsLocked' => $tournament->status !== 'completed',
            'backLink' => route('tournaments.show', array_filter([
                'tournament' => $tournament,
                'tab' => 'schedule',
                'date' => $match->scheduled_at
                    ? $this->matchLocalScheduleDateKey($match->scheduled_at, SmallFixedRoundRobinDayOneSchedule::tournamentTimezone($tournament))
                    : null,
                'stage' => $this->scheduleStageBucket($match->stage),
            ])),
            'matchStageBucket' => $this->scheduleStageBucket($match->stage),
        ]);
    }

    /**
     * Normalize the requested tournament detail tab.
     */
    protected function normalizeTab(Request $request): string
    {
        $tab = trim($request->string('tab')->toString());

        return in_array($tab, ['info', 'teams', 'schedule', 'pitches', 'stats', 'spirit', 'group', 'bracket', 'mvp', 'standings', 'crew'], true)
            ? $tab
            : 'schedule';
    }

    /**
     * Normalize the requested schedule stage filter.
     */
    protected function normalizeScheduleStage(Request $request, Collection $scheduleStageOptions): string
    {
        $requestedStage = trim($request->string('stage')->toString());

        return $scheduleStageOptions->contains(fn (array $option) => $option['value'] === $requestedStage)
            ? $requestedStage
            : 'all';
    }

    /**
     * Normalize the requested public stats gender filter.
     */
    protected function normalizeStatsGender(Request $request, Collection $statsGenderOptions): string
    {
        $requestedGender = trim($request->string('gender')->toString());
        $defaultGender = data_get($statsGenderOptions->first(), 'value', 'all');

        return $statsGenderOptions->contains(fn (array $option) => $option['value'] === $requestedGender)
            ? $requestedGender
            : $defaultGender;
    }

    /**
     * Normalize the requested public stats metric filter.
     */
    protected function normalizeStatsMetric(Request $request, Collection $statsMetricOptions): string
    {
        $requestedMetric = trim($request->string('metric')->toString());

        return $statsMetricOptions->contains(fn (array $option) => $option['value'] === $requestedMetric)
            ? $requestedMetric
            : 'goals';
    }

    /**
     * Normalize the requested public match detail tab.
     */
    protected function normalizeMatchTab(Request $request): string
    {
        $tab = trim($request->string('tab')->toString());

        return in_array($tab, ['summary', 'stats', 'spirit', 'mvp'], true)
            ? $tab
            : 'summary';
    }

    /**
     * Collapse internal match stages into public schedule buckets.
     */
    protected function scheduleStageBucket(?string $stage): string
    {
        $normalizedStage = str($stage ?? '')
            ->lower()
            ->replace('-', '_')
            ->toString();

        return in_array($normalizedStage, ['group', 'group_play', 'pool', 'pool_play', 'round_robin', 'reseeding', 'consolation', 'seeding', 'bracket_ranking', 'crossover'], true)
            || str($normalizedStage)->contains(['group', 'pool', 'round_robin', 'reseed', 'consolation', 'seeding', 'crossover', 'bracket_ranking'])
            ? 'group'
            : 'bracket';
    }

    /**
     * Apply public listing filters to the tournament query.
     */
    protected function applyFilters(Builder $query, Request $request): Builder
    {
        $search = trim($request->string('search')->toString());
        $type = trim($request->string('type')->toString());
        $month = $this->parseCalendarMonth($request->string('month')->toString());

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('venue', 'like', "%{$search}%")
                    ->orWhere('barangay', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('province', 'like', "%{$search}%")
                    ->orWhere('country_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($type !== '') {
            $query->where(function (Builder $builder) use ($type): void {
                $builder
                    ->where('division', $type)
                    ->orWhere('surface', $type)
                    ->orWhere('event_type', $type);
            });
        }

        foreach ([
            'country' => 'country_name',
            'division' => 'division',
            'surface' => 'surface',
            'event_type' => 'event_type',
        ] as $input => $column) {
            $value = trim($request->string($input)->toString());

            if ($value !== '') {
                $query->where($column, $value);
            }
        }

        if ($month) {
            $query = $this->applyCalendarMonthFilter($query, $month);
        }

        return $query;
    }

    /**
     * Apply a month-overlap constraint for the public board.
     */
    protected function applyCalendarMonthFilter(Builder $query, CarbonImmutable $month): Builder
    {
        $monthStart = $month->startOfMonth()->toDateTimeString();
        $monthEnd = $month->endOfMonth()->toDateTimeString();

        return $query->where(function (Builder $builder) use ($monthStart, $monthEnd): void {
            $builder
                ->where(function (Builder $range) use ($monthStart, $monthEnd): void {
                    $range
                        ->whereNotNull('starts_at')
                        ->where('starts_at', '<=', $monthEnd)
                        ->where(function (Builder $subquery) use ($monthStart): void {
                            $subquery
                                ->whereNull('ends_at')
                                ->orWhere('ends_at', '>=', $monthStart);
                        });
                })
                ->orWhere(function (Builder $singleDay) use ($monthStart, $monthEnd): void {
                    $singleDay
                        ->whereNull('starts_at')
                        ->whereNotNull('ends_at')
                        ->whereBetween('ends_at', [$monthStart, $monthEnd]);
                });
        });
    }

    /**
     * Normalize the requested public board period.
     */
    protected function normalizePeriod(Request $request): string
    {
        $period = trim($request->string('period')->toString());

        return in_array($period, ['upcoming', 'past'], true) ? $period : 'upcoming';
    }

    /**
     * Apply the requested time period to the public tournament query.
     */
    protected function applyPeriod(Builder $query, string $period, $today): Builder
    {
        if ($period === 'past') {
            return $query->whereNotNull('ends_at')
                ->where('ends_at', '<', $today);
        }

        return $query->where(function (Builder $builder) use ($today): void {
            $builder
                ->whereNull('ends_at')
                ->orWhere('ends_at', '>=', $today);
        });
    }

    /**
     * Apply the display order for the requested time period.
     */
    protected function applySort(Builder $query, string $period): Builder
    {
        if ($period === 'past') {
            return $query
                ->orderByRaw('case when ends_at is null then 1 else 0 end')
                ->orderByDesc('ends_at')
                ->orderByDesc('starts_at')
                ->orderBy('name');
        }

        return $query->upcomingFirst();
    }

    /**
     * Extract a sorted set of values for public listing filters.
     *
     * @return Collection<int, string>
     */
    protected function distinctValues(Builder $query, string $column)
    {
        return $query
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->select($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column);
    }

    /**
     * Normalize the requested public board display mode.
     */
    protected function normalizeBoardView(Request $request): string
    {
        $view = trim($request->string('view')->toString());

        return in_array($view, ['list', 'calendar'], true) ? $view : 'list';
    }

    /**
     * Normalize the year displayed in the month picker controls.
     */
    protected function normalizeCalendarPickerYear(Request $request, CarbonImmutable $selectedMonth): int
    {
        $requestedYear = (int) $request->integer('picker_year');

        if ($requestedYear >= 2000 && $requestedYear <= 2100) {
            return $requestedYear;
        }

        return $selectedMonth->year;
    }

    /**
     * Normalize the month shown in the public board calendar picker grid.
     */
    protected function normalizeCalendarPickerMonth(Request $request, int $pickerYear, CarbonImmutable $boardMonth): int
    {
        $requested = (int) $request->integer('picker_month');

        if ($requested >= 1 && $requested <= 12) {
            return $requested;
        }

        $parsedMonth = $this->parseCalendarMonth($request->string('month')->toString());
        if ($parsedMonth && $parsedMonth->year === $pickerYear) {
            return $parsedMonth->month;
        }

        if ($boardMonth->year === $pickerYear) {
            return $boardMonth->month;
        }

        return 1;
    }

    /**
     * Build the public tournament board calendar payload.
     *
     * @param  Collection<int, Tournament>  $tournaments
     * @return array{
     *     month: CarbonImmutable,
     *     month_label: string,
     *     previous_month: ?CarbonImmutable,
     *     next_month: ?CarbonImmutable,
     *     weeks: Collection<int, Collection<int, array<string, mixed>>>,
     *     selected_day: CarbonImmutable,
     *     selected_day_label: string,
     *     selected_day_events: Collection<int, array<string, mixed>>,
     *     selected_day_count: int,
     *     has_events: bool
     * }
     */
    protected function buildPublicBoardCalendar(Collection $tournaments, string $period, Request $request): array
    {
        $today = CarbonImmutable::today();
        $fallbackMonth = $today->startOfMonth();

        $availableMonths = $tournaments
            ->flatMap(fn (Tournament $tournament) => $this->calendarMonthsForTournament($tournament))
            ->unique(fn (CarbonImmutable $month) => $month->format('Y-m'))
            ->sortBy(fn (CarbonImmutable $month) => $month->getTimestamp())
            ->values();

        $requestedMonth = $this->parseCalendarMonth($request->string('month')->toString());

        $month = $availableMonths->contains(fn (CarbonImmutable $candidate) => $requestedMonth && $candidate->equalTo($requestedMonth))
            ? $requestedMonth
            : ($period === 'past'
                ? ($availableMonths->last() ?: $fallbackMonth)
                : ($availableMonths->first() ?: $fallbackMonth));

        $monthEnd = $month->endOfMonth();
        $eventsByDay = collect();

        foreach ($tournaments as $tournament) {
            $range = $this->calendarDateRangeForTournament($tournament);

            if (! $range) {
                continue;
            }

            $visibleStart = $range['start']->greaterThan($month) ? $range['start'] : $month;
            $visibleEnd = $range['end']->lessThan($monthEnd) ? $range['end'] : $monthEnd;

            if ($visibleStart->greaterThan($visibleEnd)) {
                continue;
            }

            $event = $this->buildCalendarEventEntry($tournament);

            for ($cursor = $visibleStart; $cursor->lessThanOrEqualTo($visibleEnd); $cursor = $cursor->addDay()) {
                $dayKey = $cursor->toDateString();
                $eventsByDay[$dayKey] ??= collect();
                $eventsByDay[$dayKey]->push($event);
            }
        }

        $eventsByDay = $eventsByDay
            ->map(function (Collection $events): Collection {
                return $events
                    ->sort(function (array $left, array $right): int {
                        return [
                            $left['country_sort'],
                            $left['start_timestamp'],
                            $left['name'],
                        ] <=> [
                            $right['country_sort'],
                            $right['start_timestamp'],
                            $right['name'],
                        ];
                    })
                    ->values();
            });

        $requestedDay = $this->parseCalendarDay($request->string('day')->toString());
        $selectedDay = $requestedDay && $requestedDay->isSameMonth($month)
            ? $requestedDay
            : null;

        if (! $selectedDay && $eventsByDay->isNotEmpty()) {
            $selectedDay = CarbonImmutable::parse((string) $eventsByDay->keys()->sort()->first());
        }

        $selectedDay ??= $month;
        $selectedDayEvents = $eventsByDay->get($selectedDay->toDateString(), collect());

        $gridStart = $month->startOfWeek(CarbonImmutable::MONDAY);
        $gridEnd = $monthEnd->endOfWeek(CarbonImmutable::SUNDAY);
        $weeks = collect();

        for ($cursor = $gridStart; $cursor->lessThanOrEqualTo($gridEnd);) {
            $week = collect();

            for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
                $dayKey = $cursor->toDateString();
                $dayEvents = $eventsByDay->get($dayKey, collect());

                $week->push([
                    'date' => $cursor,
                    'day_number' => $cursor->day,
                    'is_current_month' => $cursor->isSameMonth($month),
                    'is_today' => $cursor->equalTo($today),
                    'is_selected' => $cursor->equalTo($selectedDay),
                    'event_count' => $dayEvents->count(),
                    'dot_colors' => $dayEvents->pluck('dot_color')->take(6)->values(),
                    'hidden_event_count' => max(0, $dayEvents->count() - 6),
                ]);

                $cursor = $cursor->addDay();
            }

            $weeks->push($week);
        }

        $monthIndex = $availableMonths->search(fn (CarbonImmutable $candidate) => $candidate->equalTo($month));
        $previousMonth = $monthIndex !== false && $monthIndex > 0
            ? $availableMonths->get($monthIndex - 1)
            : null;
        $nextMonth = $monthIndex !== false && $monthIndex < ($availableMonths->count() - 1)
            ? $availableMonths->get($monthIndex + 1)
            : null;

        return [
            'month' => $month,
            'month_label' => $month->format('F Y'),
            'previous_month' => $previousMonth,
            'next_month' => $nextMonth,
            'weeks' => $weeks,
            'selected_day' => $selectedDay,
            'selected_day_label' => strtoupper($selectedDay->format('j F Y')),
            'selected_day_events' => $selectedDayEvents,
            'selected_day_count' => $selectedDayEvents->count(),
            'has_events' => $tournaments->isNotEmpty(),
        ];
    }

    /**
     * Parse a requested calendar month.
     */
    protected function parseCalendarMonth(?string $value): ?CarbonImmutable
    {
        $value = trim((string) $value);

        if (! preg_match('/^\d{4}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m', $value)->startOfMonth();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Parse a requested calendar day.
     */
    protected function parseCalendarDay(?string $value): ?CarbonImmutable
    {
        $value = trim((string) $value);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Expand a tournament into the months it occupies on the calendar.
     *
     * @return Collection<int, CarbonImmutable>
     */
    protected function calendarMonthsForTournament(Tournament $tournament): Collection
    {
        $range = $this->calendarDateRangeForTournament($tournament);

        if (! $range) {
            return collect();
        }

        $months = collect();

        for ($cursor = $range['start']->startOfMonth(); $cursor->lessThanOrEqualTo($range['end']->startOfMonth()); $cursor = $cursor->addMonth()) {
            $months->push($cursor);
        }

        return $months;
    }

    /**
     * Resolve the visible date range for a tournament card on the board.
     *
     * @return array{start: CarbonImmutable, end: CarbonImmutable}|null
     */
    protected function calendarDateRangeForTournament(Tournament $tournament): ?array
    {
        $start = $tournament->starts_at ? CarbonImmutable::parse($tournament->starts_at->toDateString()) : null;
        $end = $tournament->ends_at ? CarbonImmutable::parse($tournament->ends_at->toDateString()) : null;

        if (! $start && ! $end) {
            return null;
        }

        $start ??= $end;
        $end ??= $start;

        if ($end->lessThan($start)) {
            [$start, $end] = [$end, $start];
        }

        return [
            'start' => $start->startOfDay(),
            'end' => $end->startOfDay(),
        ];
    }

    /**
     * Build a lightweight event row for the public board calendar.
     *
     * @return array<string, mixed>
     */
    protected function buildCalendarEventEntry(Tournament $tournament): array
    {
        return [
            'name' => $tournament->name,
            'url' => route('tournaments.show', $tournament),
            'date_label' => $tournament->dateRangeLabel(),
            'country' => $tournament->country_name ?: 'Location to be announced',
            'country_sort' => $tournament->country_name ?: 'zzzz',
            'status' => str($tournament->status)->headline()->toString(),
            'tags' => $tournament->publicTags(),
            'dot_color' => $this->resolveCalendarEventColor($tournament),
            'start_timestamp' => $tournament->starts_at?->getTimestamp() ?? $tournament->ends_at?->getTimestamp() ?? PHP_INT_MAX,
        ];
    }

    /**
     * Pick a stable accent color for a tournament on the public calendar.
     */
    protected function resolveCalendarEventColor(Tournament $tournament): string
    {
        $palette = ['#ec4899', '#8b5cf6', '#3b82f6', '#14b8a6', '#f59e0b', '#ef4444'];
        $seed = crc32(implode('|', [
            $tournament->slug,
            $tournament->country_name,
            $tournament->division,
            $tournament->event_type,
        ]));

        return $palette[$seed % count($palette)];
    }
}
