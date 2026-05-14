<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MatchPlayerStat;
use App\Models\MatchScoreLog;
use App\Models\MatchSpiritScore;
use App\Models\Pitch;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Tournament;
use App\Models\TournamentCrew;
use App\Models\TournamentMatch;
use App\Models\TournamentRegistration;
use App\Models\User;
use App\Services\BracketRankingService;
use App\Support\BracketCodes;
use App\Support\SmallDayTwoKnockoutBracket;
use App\Support\SmallFixedRoundRobinDayOneSchedule;
use App\Support\SmallFixedRoundRobinDayTwoSchedule;
use App\Support\SmallTournamentTeamStanding;
use App\Support\TournamentPooling;
use App\Support\TournamentReportBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use iio\libmergepdf\Merger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class TournamentController extends Controller
{
    public const BRACKET_TEAM_LIMIT = 5;

    public const MINIMUM_BRACKET_COUNT = 2;

    public const MINIMUM_BRACKET_TEAM_COUNT = self::BRACKET_TEAM_LIMIT * self::MINIMUM_BRACKET_COUNT;

    /**
     * Show the admin tournament setup page.
     */
    public function index(Request $request): View|RedirectResponse
    {
        $requestedTab = $request->string('tab')->toString();
        $normalizedRequestedTab = $this->normalizeTournamentTab($requestedTab);

        if ($requestedTab !== '' && $normalizedRequestedTab !== null && $normalizedRequestedTab !== $requestedTab) {
            return redirect()->route('admin.tournaments.index', collect($request->query())
                ->put('tab', $normalizedRequestedTab)
                ->all());
        }

        if (! $request->user()->isAdmin() && $request->filled('tab')) {
            return redirect()->route(
                'admin.tournaments.index',
                collect($request->query())
                    ->except('tab')
                    ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
                    ->all(),
            );
        }

        $selectedTournament = $request->filled('tournament')
            ? Tournament::query()->find($request->integer('tournament'))
            : null;

        $selectedTournament?->load([
            'pitches',
            'registrations' => fn ($query) => $query
                ->with([
                    'team' => fn ($teamQuery) => $teamQuery->with([
                        'members' => fn ($membersQuery) => $membersQuery
                            ->orderByRaw("case role when 'captain' then 0 when 'spirit_captain' then 1 else 2 end")
                            ->orderBy('name')
                            ->orderBy('id'),
                    ]),
                ])
                ->orderByRaw('case when seed_number is null then 1 else 0 end')
                ->orderBy('seed_number')
                ->orderBy('id'),
            'matches' => fn ($query) => $query
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

        if ($selectedTournament !== null) {
            $registrationCount = $selectedTournament->registrations->count();
            $normalizedTabForThreshold = $this->normalizeTournamentTab($request->string('tab')->toString());

            if (
                $registrationCount < self::MINIMUM_BRACKET_TEAM_COUNT
                && $normalizedTabForThreshold !== null
                && in_array($normalizedTabForThreshold, ['bracket-ranking', 'crossover', 'pooling'], true)
            ) {
                return redirect()->route(
                    'admin.tournaments.index',
                    collect($request->query())
                        ->put('tab', 'games-dashboard')
                        ->all(),
                );
            }

            if (
                $registrationCount >= self::MINIMUM_BRACKET_TEAM_COUNT
                && $normalizedTabForThreshold === 'team-standing'
            ) {
                return redirect()->route(
                    'admin.tournaments.index',
                    collect($request->query())
                        ->put('tab', 'games-dashboard')
                        ->all(),
                );
            }

            if (
                $registrationCount < self::MINIMUM_BRACKET_TEAM_COUNT
                && $normalizedTabForThreshold !== null
                && in_array($normalizedTabForThreshold, ['quarter-final', 'championship'], true)
            ) {
                SmallDayTwoKnockoutBracket::sync($selectedTournament);
                $selectedTournament->unsetRelation('matches');
                $selectedTournament->load([
                    'matches' => fn ($query) => $query
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
            }
        }

        $availableTeams = Team::query()
            ->withCount('members')
            ->orderBy('name')
            ->get();

        $bracketRankingPreview = $selectedTournament
            ? app(BracketRankingService::class)->preview($selectedTournament)
            : null;

        $scorekeeperPitchManagedMatches = null;
        if ($selectedTournament && $request->user()->isScorekeeper()) {
            $userId = (int) $request->user()->id;

            $scorekeeperPitchManagedMatches = collect($selectedTournament->matches ?? [])
                ->filter(function (TournamentMatch $match) use ($userId): bool {
                    if ($match->pitch_id === null) {
                        return false;
                    }

                    $pitch = $match->pitch;

                    return $pitch instanceof Pitch && $pitch->allowsScorekeeperUserId($userId);
                })
                ->values();
        }

        $pitchAssignmentScorekeepers = $request->user()->isAdmin()
            ? User::query()
                ->where('role', User::ROLE_SCOREKEEPER)
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'name'])
            : collect();

        $tournamentReport = null;
        if ($selectedTournament && $this->normalizeTournamentTab($request->string('tab')->toString()) === 'report') {
            $tournamentReport = app(TournamentReportBuilder::class)->build($selectedTournament);
        }

        return view('admin.tournaments.index', [
            'selectedTournament' => $selectedTournament,
            'availableTeams' => $availableTeams,
            'totalTournamentCount' => Tournament::query()->count(),
            'bracketRankingPreview' => $bracketRankingPreview,
            'scorekeeperPitchManagedMatches' => $scorekeeperPitchManagedMatches,
            'pitchAssignmentScorekeepers' => $pitchAssignmentScorekeepers,
            'tournamentReport' => $tournamentReport,
        ]);
    }

    /**
     * Show the admin tournament directory page.
     */
    public function list(Request $request): View
    {
        $availableTeams = Team::query()
            ->withCount('members')
            ->orderBy('name')
            ->get();

        return view('admin.tournaments.list', [
            ...$this->tournamentListViewData($request),
            'availableTeams' => $availableTeams,
        ]);
    }

    /**
     * Create a new tournament shell.
     */
    public function storeTournament(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->tournamentRules());

        $tournament = Tournament::create([
            'slug' => $this->uniqueSlug($validated['name']),
            'created_by' => $request->user()->id,
            ...$this->buildTournamentPayload($validated, $request),
        ]);

        return redirect()
            ->route('admin.tournaments.index', [
                'tournament' => $tournament->id,
                'tab' => $this->resolveTournamentRedirectTab($request) ?? 'games-dashboard',
            ])
            ->with('status', 'tournament-created');
    }

    /**
     * Update the selected tournament shell.
     */
    public function updateTournament(Request $request, Tournament $tournament): RedirectResponse
    {
        $prefix = 'edit_';
        $validated = $request->validate($this->tournamentRules($prefix));

        $tournament->update($this->buildTournamentPayload($validated, $request, $prefix));

        return redirect()
            ->route(
                $this->resolveTournamentRedirectRoute($request),
                $this->resolveTournamentRedirectParameters($request, $tournament),
            )
            ->with('status', 'tournament-updated');
    }

    /**
     * Delete the selected tournament and its dependent setup data.
     */
    public function destroyTournament(Request $request, Tournament $tournament): RedirectResponse
    {
        DB::transaction(function () use ($tournament): void {
            $tournament->delete();
        });

        return redirect()
            ->route('admin.tournaments.list', collect([
                'search' => $this->normalizeNullableString($request->string('redirect_search')->toString()),
            ])->filter()->all())
            ->with('status', 'tournament-deleted');
    }

    /**
     * Redirect shorthand URLs (/admin/tournaments/matches/{match}) to the canonical scoring route.
     */
    public function redirectToMatchScoring(TournamentMatch $match): RedirectResponse
    {
        $tournament = Tournament::query()->findOrFail($match->tournament_id);

        return redirect()->route('admin.tournaments.matches.scoring', [
            'tournament' => $tournament,
            'match' => $match,
        ]);
    }

    /**
     * Show the dedicated score entry page for a tournament match.
     */
    public function showMatchScoring(Request $request, Tournament $tournament, TournamentMatch $match): View
    {
        $this->ensureTournamentOwnsMatch($tournament, $match);

        $match = $this->loadMatchScoringContext($match);

        return view('admin.tournaments.scoring', [
            'tournament' => $tournament,
            'match' => $match,
            'memberDirectory' => $this->buildMatchMemberDirectory($match),
            'matchHasManualScorelineWithoutLog' => $this->matchHasManualScorelineWithoutLog($match),
            'spiritScoresByScoredTeamId' => $match->spiritScores->keyBy('scored_team_id'),
        ]);
    }

    /**
     * Persist spirit-of-the-game scoresheets for both teams (one row per scored team).
     */
    public function storeMatchSpiritScores(Request $request, Tournament $tournament, TournamentMatch $match): RedirectResponse
    {
        $this->ensureTournamentOwnsMatch($tournament, $match);

        if ($match->status !== 'completed') {
            return redirect()
                ->route('admin.tournaments.matches.scoring', [
                    'tournament' => $tournament,
                    'match' => $match,
                ])
                ->withErrors([
                    'spirit' => SmallFixedRoundRobinDayOneSchedule::scoringRequiresCompletedScheduleMessage($match),
                ]);
        }

        $match->loadMissing([
            'homeRegistration.team.members',
            'awayRegistration.team.members',
        ]);

        $homeTeam = $match->homeRegistration?->team;
        $awayTeam = $match->awayRegistration?->team;

        if (! $homeTeam || ! $awayTeam) {
            return redirect()
                ->route('admin.tournaments.matches.scoring', [
                    'tournament' => $tournament,
                    'match' => $match,
                ])
                ->withErrors(['spirit' => __('This match needs both registered teams before spirit scores can be saved.')]);
        }

        $scoreField = ['required', 'integer', Rule::in([1, 2, 3])];

        $validated = $request->validate([
            'spirit.home.knowledge_rules_score' => $scoreField,
            'spirit.home.fouls_body_contact_score' => $scoreField,
            'spirit.home.fair_mindedness_score' => $scoreField,
            'spirit.home.positive_attitude_score' => $scoreField,
            'spirit.home.communication_respect_score' => $scoreField,
            'spirit.away.knowledge_rules_score' => $scoreField,
            'spirit.away.fouls_body_contact_score' => $scoreField,
            'spirit.away.fair_mindedness_score' => $scoreField,
            'spirit.away.positive_attitude_score' => $scoreField,
            'spirit.away.communication_respect_score' => $scoreField,
            'spirit.home.notes' => ['nullable', 'string', 'max:1000'],
            'spirit.away.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($tournament, $match, $homeTeam, $awayTeam, $validated): void {
            $this->upsertMatchSpiritScoreRecord(
                $tournament,
                $match,
                $homeTeam,
                $awayTeam,
                $validated['spirit']['home'],
                $this->spiritCaptainMember($homeTeam),
            );

            $this->upsertMatchSpiritScoreRecord(
                $tournament,
                $match,
                $awayTeam,
                $homeTeam,
                $validated['spirit']['away'],
                $this->spiritCaptainMember($awayTeam),
            );
        });

        return redirect()
            ->route('admin.tournaments.matches.scoring', [
                'tournament' => $tournament,
                'match' => $match,
            ])
            ->with('status', 'spirit-saved');
    }

    /**
     * Auto-save spirit scores for a single team (JSON). Used by the scoring page debounced fetch.
     */
    public function patchMatchSpiritScores(Request $request, Tournament $tournament, TournamentMatch $match): JsonResponse
    {
        $this->ensureTournamentOwnsMatch($tournament, $match);

        if ($match->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => SmallFixedRoundRobinDayOneSchedule::scoringRequiresCompletedScheduleMessage($match),
            ], 422);
        }

        $match->loadMissing([
            'homeRegistration.team.members',
            'awayRegistration.team.members',
        ]);

        $homeTeam = $match->homeRegistration?->team;
        $awayTeam = $match->awayRegistration?->team;

        if (! $homeTeam || ! $awayTeam) {
            return response()->json([
                'success' => false,
                'message' => __('This match needs both registered teams before spirit scores can be saved.'),
            ], 422);
        }

        $criterionRule = ['nullable', 'integer', Rule::in([1, 2, 3])];

        try {
            $validated = $request->validate([
                'scored_team_id' => ['required', 'integer', Rule::in([$homeTeam->id, $awayTeam->id])],
                'scoring_team_id' => ['required', 'integer', Rule::in([$homeTeam->id, $awayTeam->id])],
                'knowledge_rules_score' => $criterionRule,
                'fouls_body_contact_score' => $criterionRule,
                'fair_mindedness_score' => $criterionRule,
                'positive_attitude_score' => $criterionRule,
                'communication_respect_score' => $criterionRule,
                'notes' => ['nullable', 'string', 'max:1000'],
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => __('Error saving'),
                'errors' => $exception->errors(),
            ], 422);
        }

        $scoredTeamId = (int) $validated['scored_team_id'];
        $scoringTeamId = (int) $validated['scoring_team_id'];
        $expectedScoringTeamId = $scoredTeamId === $homeTeam->id ? $awayTeam->id : $homeTeam->id;

        if ($scoringTeamId !== $expectedScoringTeamId) {
            return response()->json([
                'success' => false,
                'message' => __('Invalid spirit score pairing for this match.'),
            ], 422);
        }

        $scoredTeam = $scoredTeamId === $homeTeam->id ? $homeTeam : $awayTeam;
        $scoringTeam = $scoringTeamId === $homeTeam->id ? $homeTeam : $awayTeam;

        $payload = collect($validated)
            ->only([
                'knowledge_rules_score',
                'fouls_body_contact_score',
                'fair_mindedness_score',
                'positive_attitude_score',
                'communication_respect_score',
                'notes',
            ])
            ->all();

        $record = DB::transaction(function () use ($tournament, $match, $scoredTeam, $scoringTeam, $payload): ?MatchSpiritScore {
            return $this->upsertMatchSpiritScoreRecord(
                $tournament,
                $match,
                $scoredTeam,
                $scoringTeam,
                $payload,
                $this->spiritCaptainMember($scoredTeam),
            );
        });

        $match->unsetRelation('spiritScores');
        $match->load('spiritScores');

        return response()->json([
            'success' => true,
            'message' => __('Saved'),
            'total_score' => $record?->total_score,
            'criteria' => $record ? [
                'knowledge_rules_score' => $record->knowledge_rules_score,
                'fouls_body_contact_score' => $record->fouls_body_contact_score,
                'fair_mindedness_score' => $record->fair_mindedness_score,
                'positive_attitude_score' => $record->positive_attitude_score,
                'communication_respect_score' => $record->communication_respect_score,
            ] : null,
        ]);
    }

    /**
     * Stream a printable match PDF (game score + spirit scoresheet) for any tournament match.
     *
     * Completed games append a spirit scoresheet page only when spirit criteria have been entered.
     * Non-completed games always append the spirit scoresheet template after the match sheet; both sheets
     * omit saved numeric scores in the PDF (printable blank templates — database values are unchanged).
     *
     * Uses A4 landscape with two cut-out panels per page, and the same layout for all stages (round robin,
     * knockout, placement, etc.) and all match IDs — there is no per-stage or per-match Blade branch here.
     */
    public function exportMatchScoringPdf(Request $request, Tournament $tournament, TournamentMatch $match)
    {
        $this->ensureTournamentOwnsMatch($tournament, $match);

        $match = $this->loadMatchScoringContext($match);

        $homeTeam = $match->homeRegistration?->team;
        $awayTeam = $match->awayRegistration?->team;

        $homePlayerStats = $match->playerStats
            ->filter(fn ($stat) => $stat->teamMember?->team_id === $homeTeam?->id)
            ->values();
        $awayPlayerStats = $match->playerStats
            ->filter(fn ($stat) => $stat->teamMember?->team_id === $awayTeam?->id)
            ->values();

        $spiritScoresByScoredTeamId = $match->spiritScores->keyBy('scored_team_id');
        $hasSpiritScoreData = $spiritScoresByScoredTeamId->contains(
            fn (MatchSpiritScore $row): bool => $this->matchSpiritScoreRowHasData($row),
        );
        $mergeSpiritPortrait = ! $match->isCompletedMatchStatus() || $hasSpiritScoreData;

        $normalizedStatus = strtolower((string) $match->status);

        $matchStatusLabel = match ($normalizedStatus) {
            'scheduled', 'upcoming', 'pending' => __('Upcoming'),
            'live', 'in_progress' => __('Live'),
            'completed' => __('Completed'),
            default => (string) str($match->status)->headline(),
        };

        $isCompleted = $match->isCompletedMatchStatus();

        $winnerLabel = null;
        if ($isCompleted
            && $match->home_score !== null
            && $match->away_score !== null
            && $homeTeam
            && $awayTeam
        ) {
            if ((int) $match->home_score > (int) $match->away_score) {
                $winnerLabel = $homeTeam->name;
            } elseif ((int) $match->away_score > (int) $match->home_score) {
                $winnerLabel = $awayTeam->name;
            } else {
                $winnerLabel = __('Draw');
            }
        }

        $gamePdf = Pdf::loadView('admin.tournaments.matches.pdf', [
            'tournament' => $tournament,
            'match' => $match,
            'homeTeam' => $homeTeam,
            'awayTeam' => $awayTeam,
            'homePlayerStats' => $homePlayerStats,
            'awayPlayerStats' => $awayPlayerStats,
            'matchStatusLabel' => $matchStatusLabel,
            'winnerLabel' => $winnerLabel,
            'isCompleted' => $isCompleted,
        ]);
        $gamePdf->setPaper('a4', 'landscape');
        $gamePdf->setOption('isRemoteEnabled', true);

        $gameBinary = $gamePdf->output();

        if ($mergeSpiritPortrait) {
            $spiritPdf = Pdf::loadView('admin.tournaments.matches.spirit-pdf', [
                'tournament' => $tournament,
                'match' => $match,
                'homeTeam' => $homeTeam,
                'awayTeam' => $awayTeam,
                'spiritScoresByScoredTeamId' => $spiritScoresByScoredTeamId,
                'matchStatusLabel' => $matchStatusLabel,
                'isCompleted' => $isCompleted,
            ]);
            $spiritPdf->setPaper('a4', 'landscape');
            $spiritPdf->setOption('isRemoteEnabled', true);

            $merger = new Merger;
            $merger->addRaw($gameBinary);
            $merger->addRaw($spiritPdf->output());
            $binary = $merger->merge();
        } else {
            $binary = $gameBinary;
        }

        $filename = 'match-'.(filled($match->match_number) ? $match->match_number : $match->id).'-score.pdf';
        $disposition = $request->boolean('preview')
            ? 'inline; filename="'.$filename.'"'
            : 'attachment; filename="'.$filename.'"';

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition,
        ]);
    }

    /**
     * Download a spirit-only PDF (scoresheet) for the match when {@see MatchSpiritScore} rows exist.
     */
    public function exportMatchSpiritScoringPdf(Tournament $tournament, TournamentMatch $match)
    {
        $this->ensureTournamentOwnsMatch($tournament, $match);

        $match = $this->loadMatchScoringContext($match);

        if ($match->spiritScores->filter(fn (MatchSpiritScore $row): bool => $this->matchSpiritScoreRowHasData($row))->isEmpty()) {
            abort(404, __('No spirit scores available yet.'));
        }

        $homeTeam = $match->homeRegistration?->team;
        $awayTeam = $match->awayRegistration?->team;

        $spiritScoresByScoredTeamId = $match->spiritScores->keyBy('scored_team_id');

        $normalizedStatus = strtolower((string) $match->status);
        $matchStatusLabel = match ($normalizedStatus) {
            'scheduled', 'upcoming', 'pending' => __('Upcoming'),
            'live', 'in_progress' => __('Live'),
            'completed' => __('Completed'),
            default => (string) str($match->status)->headline(),
        };

        $filename = sprintf('tournament-%d-match-%d-spirit-score.pdf', $tournament->id, $match->id);

        $pdf = Pdf::loadView('admin.tournaments.matches.spirit-pdf', [
            'tournament' => $tournament,
            'match' => $match,
            'homeTeam' => $homeTeam,
            'awayTeam' => $awayTeam,
            'spiritScoresByScoredTeamId' => $spiritScoresByScoredTeamId,
            'matchStatusLabel' => $matchStatusLabel,
            'isCompleted' => $match->isCompletedMatchStatus(),
        ]);
        $pdf->setPaper('a4', 'landscape');
        $pdf->setOption('isRemoteEnabled', true);

        return $pdf->download($filename);
    }

    /**
     * Record a scoring play and auto-sync the visible scoreline plus player totals.
     */
    public function storeMatchScoreLog(Request $request, Tournament $tournament, TournamentMatch $match): RedirectResponse
    {
        $this->ensureTournamentOwnsMatch($tournament, $match);

        $registrationOptions = TournamentRegistration::query()
            ->with('team:id')
            ->whereIn('id', array_filter([
                $match->home_registration_id,
                $match->away_registration_id,
            ]))
            ->get()
            ->keyBy('id');

        $validator = Validator::make($request->all(), [
            'team_registration_id' => ['required', 'integer'],
            'team_member_id' => ['required', 'integer', 'exists:team_members,id'],
            'assist_team_member_id' => ['nullable', 'integer', 'different:team_member_id', 'exists:team_members,id'],
            'minute' => ['nullable', 'integer', 'min:0', 'max:999'],
            'replace_manual_scoreline' => ['sometimes', 'accepted'],
        ]);

        $validator->after(function ($validator) use ($request, $match, $registrationOptions): void {
            if ($match->status !== 'completed') {
                $validator->errors()->add(
                    'team_registration_id',
                    SmallFixedRoundRobinDayOneSchedule::scoringRequiresCompletedScheduleMessage($match),
                );

                return;
            }

            if ($registrationOptions->count() < 2) {
                $validator->errors()->add('team_registration_id', 'This match needs both registered teams before live scoring can start.');

                return;
            }

            $teamRegistrationId = $request->integer('team_registration_id');
            $selectedRegistration = $registrationOptions->get($teamRegistrationId);

            if (! $selectedRegistration) {
                $validator->errors()->add('team_registration_id', 'Choose either the home or away team for the scoring play.');

                return;
            }

            if (! $this->registrationOwnsMember($selectedRegistration, $request->integer('team_member_id'))) {
                $validator->errors()->add('team_member_id', 'The selected scorer is not part of that team registration.');
            }

            if ($request->filled('assist_team_member_id')
                && ! $this->registrationOwnsMember($selectedRegistration, $request->integer('assist_team_member_id'))
            ) {
                $validator->errors()->add('assist_team_member_id', 'The selected assister must belong to the same team as the scorer.');
            }

            if ($this->matchHasManualScorelineWithoutLog($match) && ! $request->boolean('replace_manual_scoreline')) {
                $validator->errors()->add(
                    'replace_manual_scoreline',
                    'Confirm that live scoring should replace the current manual scoreline before adding the first play.',
                );
            }
        });

        $validated = $validator->validate();

        DB::transaction(function () use ($match, $validated): void {
            MatchScoreLog::create([
                'match_id' => $match->id,
                'sequence' => ((int) $match->scoreLogs()->max('sequence')) + 1,
                'team_registration_id' => $validated['team_registration_id'],
                'team_member_id' => $validated['team_member_id'],
                'assist_team_member_id' => $validated['assist_team_member_id'] ?? null,
                'minute' => $validated['minute'] ?? null,
                'home_score' => 0,
                'away_score' => 0,
            ]);

            $this->syncMatchScoreTimeline($match);
            $this->syncMatchPlayerStatsFromScoreLogs($match);
        });

        return redirect()
            ->route('admin.tournaments.matches.scoring', [
                'tournament' => $tournament,
                'match' => $match,
            ])
            ->with('status', 'score-play-added');
    }

    /**
     * Remove a scoring play and rebuild the match timeline plus derived totals.
     */
    public function destroyMatchScoreLog(
        Request $request,
        Tournament $tournament,
        TournamentMatch $match,
        MatchScoreLog $scoreLog,
    ): RedirectResponse {
        $this->ensureTournamentOwnsMatch($tournament, $match);
        abort_unless($scoreLog->match_id === $match->id, 404);

        if ($match->status !== 'completed') {
            return redirect()
                ->route('admin.tournaments.matches.scoring', [
                    'tournament' => $tournament,
                    'match' => $match,
                ])
                ->withErrors([
                    'score_log' => SmallFixedRoundRobinDayOneSchedule::scoringRequiresCompletedScheduleMessage($match),
                ]);
        }

        DB::transaction(function () use ($match, $scoreLog): void {
            $scoreLog->delete();
            $this->syncMatchScoreTimeline($match);
            $this->syncMatchPlayerStatsFromScoreLogs($match);
        });

        return redirect()
            ->route('admin.tournaments.matches.scoring', [
                'tournament' => $tournament,
                'match' => $match,
            ])
            ->with('status', 'score-play-deleted');
    }

    /**
     * Auto-save a single player's goal/assist/block tally for a match.
     */
    public function updateMatchPlayerStat(Request $request, Tournament $tournament, TournamentMatch $match): JsonResponse
    {
        $this->ensureTournamentOwnsMatch($tournament, $match);

        abort_unless(
            $match->status === 'completed',
            422,
            SmallFixedRoundRobinDayOneSchedule::scoringRequiresCompletedScheduleMessage($match),
        );

        $validated = $request->validate([
            'team_member_id' => ['required', 'integer', 'exists:team_members,id'],
            'field' => ['required', 'string', Rule::in(['goals', 'assists', 'blocks'])],
            'value' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $teamMember = TeamMember::query()->findOrFail($validated['team_member_id']);

        $homeTeamId = $match->homeRegistration?->team_id;
        $awayTeamId = $match->awayRegistration?->team_id;

        abort_unless(
            in_array($teamMember->team_id, array_filter([$homeTeamId, $awayTeamId]), true),
            422,
            'Team member does not belong to either registered team.',
        );

        $value = (int) ($validated['value'] ?? 0);

        DB::transaction(function () use ($match, $teamMember, $validated, $value): void {
            $stat = MatchPlayerStat::query()->firstOrNew([
                'match_id' => $match->id,
                'team_member_id' => $teamMember->id,
            ]);

            $stat->goals = $stat->goals ?? 0;
            $stat->assists = $stat->assists ?? 0;
            $stat->blocks = $stat->blocks ?? 0;
            $stat->{$validated['field']} = $value;

            if ($stat->goals === 0 && $stat->assists === 0 && $stat->blocks === 0) {
                if ($stat->exists) {
                    $stat->delete();
                }
            } else {
                $stat->save();
            }

            $this->syncMatchScoreFromPlayerStats($match);
        });

        $match->refresh()->load('playerStats');

        return response()->json([
            'ok' => true,
            'home_score' => $match->home_score ?? 0,
            'away_score' => $match->away_score ?? 0,
        ]);
    }

    /**
     * Recompute the visible scoreline from current player goal totals per side.
     */
    protected function syncMatchScoreFromPlayerStats(TournamentMatch $match): void
    {
        $homeTeamId = $match->homeRegistration?->team_id;
        $awayTeamId = $match->awayRegistration?->team_id;

        $stats = MatchPlayerStat::query()
            ->with('teamMember:id,team_id')
            ->where('match_id', $match->id)
            ->get();

        $homeScore = $stats
            ->filter(fn ($stat) => $stat->teamMember?->team_id === $homeTeamId)
            ->sum('goals');

        $awayScore = $stats
            ->filter(fn ($stat) => $stat->teamMember?->team_id === $awayTeamId)
            ->sum('goals');

        $match->forceFill([
            'home_score' => (int) $homeScore,
            'away_score' => (int) $awayScore,
        ])->save();

        $match->loadMissing('tournament');
        $tournament = $match->tournament;
        if ($tournament !== null && $tournament->registrations()->count() < self::MINIMUM_BRACKET_TEAM_COUNT) {
            SmallDayTwoKnockoutBracket::syncAfterResultChange($tournament, $match);
        }
    }

    /**
     * Add a pitch to a selected tournament.
     */
    public function storePitch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tournament_id' => ['required', 'integer', 'exists:tournaments,id'],
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:1'],
            'scorekeeper_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn (Builder $query): Builder => $query->where('role', User::ROLE_SCOREKEEPER)),
            ],
        ]);

        Pitch::create([
            'tournament_id' => $validated['tournament_id'],
            'name' => $validated['name'],
            'location' => $this->normalizeNullableString($validated['location'] ?? null),
            'sort_order' => $validated['sort_order'],
            'scorekeeper_user_id' => $validated['scorekeeper_user_id'] ?? null,
            'is_active' => true,
        ]);

        return redirect()
            ->route(
                $this->resolveTournamentRedirectRoute($request),
                $this->resolveTournamentRedirectParameters($request, $validated['tournament_id']),
            )
            ->with('status', 'pitch-created');
    }

    /**
     * Update an existing pitch attached to a tournament.
     */
    public function updatePitch(Request $request, Pitch $pitch): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:1'],
            'scorekeeper_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn (Builder $query): Builder => $query->where('role', User::ROLE_SCOREKEEPER)),
            ],
        ]);

        $pitch->update([
            'name' => $validated['name'],
            'location' => $this->normalizeNullableString($validated['location'] ?? null),
            'sort_order' => $validated['sort_order'],
            'scorekeeper_user_id' => $validated['scorekeeper_user_id'] ?? null,
        ]);

        return redirect()
            ->route(
                $this->resolveTournamentRedirectRoute($request),
                $this->resolveTournamentRedirectParameters($request, $pitch->tournament_id),
            )
            ->with('status', 'pitch-updated');
    }

    /**
     * Delete an existing pitch from a tournament. Matches that referenced
     * the pitch keep their schedule but lose the pitch assignment.
     */
    public function destroyPitch(Request $request, Pitch $pitch): RedirectResponse
    {
        $tournamentId = $pitch->tournament_id;

        $pitch->delete();

        return redirect()
            ->route(
                $this->resolveTournamentRedirectRoute($request),
                $this->resolveTournamentRedirectParameters($request, $tournamentId),
            )
            ->with('status', 'pitch-deleted');
    }

    /**
     * Register one or more existing teams into a selected tournament.
     *
     * Accepts either a single `team_id` (legacy modal flow) or an array of
     * `team_ids` for bulk registration from the tournament directory.
     */
    public function storeRegistration(Request $request): RedirectResponse
    {
        $tournamentId = $request->integer('tournament_id');
        $isBulk = $request->has('team_ids');

        if ($isBulk) {
            $validated = $request->validate(
                [
                    'tournament_id' => ['required', 'integer', 'exists:tournaments,id'],
                    'team_ids' => ['required', 'array', 'min:1'],
                    'team_ids.*' => [
                        'integer',
                        'distinct',
                        'exists:teams,id',
                        Rule::unique('tournament_registrations', 'team_id')->where(
                            fn ($query) => $query->where('tournament_id', $tournamentId),
                        ),
                    ],
                ],
                [
                    'team_ids.required' => 'Select at least one team to register.',
                    'team_ids.min' => 'Select at least one team to register.',
                    'team_ids.*.distinct' => 'Each team can only be selected once.',
                    'team_ids.*.unique' => 'One or more selected teams are already registered in this tournament.',
                ],
            );

            $teamIds = collect($validated['team_ids'])
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all();
        } else {
            $validated = $request->validate(
                [
                    'tournament_id' => ['required', 'integer', 'exists:tournaments,id'],
                    'team_id' => [
                        'required',
                        'integer',
                        'exists:teams,id',
                        Rule::unique('tournament_registrations')->where(
                            fn ($query) => $query->where('tournament_id', $tournamentId),
                        ),
                    ],
                ],
                [
                    'team_id.unique' => 'This team is already registered in the selected tournament.',
                ],
            );

            $teamIds = [(int) $validated['team_id']];
        }

        DB::transaction(function () use ($validated, $teamIds): void {
            foreach ($teamIds as $teamId) {
                TournamentRegistration::create([
                    'tournament_id' => $validated['tournament_id'],
                    'team_id' => $teamId,
                    'status' => 'pending',
                    'seed_number' => null,
                    'bracket_code' => null,
                    'bracket_rank' => null,
                    'pool_name' => null,
                ]);
            }
        });

        return redirect()
            ->route(
                $this->resolveTournamentRedirectRoute($request),
                $this->resolveTournamentRedirectParameters($request, $validated['tournament_id']),
            )
            ->with('status', count($teamIds) > 1 ? 'registrations-created' : 'registration-created');
    }

    /**
     * Auto-assign sequential seeds and compose brackets only when two full groups can be formed.
     */
    public function seedRegistrations(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'tournament_id' => ['required', 'integer', 'exists:tournaments,id'],
        ]);

        $registrations = TournamentRegistration::query()
            ->where('tournament_id', $validated['tournament_id'])
            ->get()
            ->shuffle()
            ->values();

        if ($registrations->isEmpty()) {
            return $this->buildSeedRegistrationsResponse(
                request: $request,
                tournamentId: $validated['tournament_id'],
                status: 'registrations-seeding-skipped',
            );
        }

        $fullBracketTeamCount = $registrations->count() >= self::MINIMUM_BRACKET_TEAM_COUNT
            ? intdiv($registrations->count(), self::BRACKET_TEAM_LIMIT) * self::BRACKET_TEAM_LIMIT
            : 0;

        $bracketCount = $fullBracketTeamCount > 0
            ? intdiv($fullBracketTeamCount, self::BRACKET_TEAM_LIMIT)
            : 0;

        $bracketSlots = $bracketCount > 0
            ? collect(range(0, $bracketCount - 1))
                ->flatMap(fn (int $bracketIndex): array => array_fill(
                    0,
                    self::BRACKET_TEAM_LIMIT,
                    'Bracket '.$this->alphabeticalBracketLabel($bracketIndex),
                ))
                ->shuffle()
                ->values()
            : collect();

        DB::transaction(function () use ($bracketSlots, $fullBracketTeamCount, $registrations): void {
            foreach ($registrations as $index => $registration) {
                $seedNumber = $index + 1;
                $bracketCode = null;

                if ($index < $fullBracketTeamCount) {
                    $bracketCode = $bracketSlots->get($index);
                }

                $registration->update([
                    'seed_number' => $seedNumber,
                    'bracket_code' => $bracketCode,
                    'bracket_rank' => null,
                    'pool_name' => null,
                ]);
            }
        });

        return $this->buildSeedRegistrationsResponse(
            request: $request,
            tournamentId: $validated['tournament_id'],
            status: 'registrations-seeded',
        );
    }

    /**
     * Manually update seed and bracket assignments per team registration.
     */
    public function updateRegistrationSeeding(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'tournament_id' => ['required', 'integer', 'exists:tournaments,id'],
            'registrations' => ['required', 'array', 'min:1'],
            'registrations.*.id' => ['required', 'integer', 'exists:tournament_registrations,id'],
            'registrations.*.seed_number' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'registrations.*.bracket_code' => ['nullable', 'string', 'max:255'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            $tournamentId = $request->integer('tournament_id');
            $registrations = collect($request->input('registrations', []));

            $registrationIds = $registrations
                ->pluck('id')
                ->filter(fn ($id) => $id !== null && $id !== '')
                ->map(fn ($id) => (int) $id)
                ->values();

            if ($registrationIds->isEmpty()) {
                return;
            }

            $ownedRegistrationIds = TournamentRegistration::query()
                ->where('tournament_id', $tournamentId)
                ->whereIn('id', $registrationIds)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id);

            foreach ($registrationIds as $index => $registrationId) {
                if (! $ownedRegistrationIds->contains($registrationId)) {
                    $validator->errors()->add("registrations.{$index}.id", 'This team registration does not belong to the selected tournament.');
                }
            }

            $currentRegistrations = TournamentRegistration::query()
                ->where('tournament_id', $tournamentId)
                ->get()
                ->keyBy('id');

            $finalSeedAssignments = $currentRegistrations
                ->mapWithKeys(fn (TournamentRegistration $registration): array => [
                    $registration->id => $registration->seed_number,
                ]);

            foreach ($registrations as $registrationData) {
                if (! isset($registrationData['id'])) {
                    continue;
                }

                $finalSeedAssignments[(int) $registrationData['id']] = filled($registrationData['seed_number'] ?? null)
                    ? (int) $registrationData['seed_number']
                    : null;
            }

            $duplicateSeeds = $finalSeedAssignments
                ->filter(fn ($seed): bool => $seed !== null)
                ->countBy()
                ->filter(fn (int $count): bool => $count > 1);

            if ($duplicateSeeds->isNotEmpty()) {
                $validator->errors()->add('registrations', 'Seed numbers must be unique per tournament.');
            }

            $finalBracketAssignments = $currentRegistrations
                ->mapWithKeys(fn (TournamentRegistration $registration): array => [
                    $registration->id => $this->normalizeBracketCode($registration->bracket_code),
                ]);

            foreach ($registrations as $registrationData) {
                if (! isset($registrationData['id'])) {
                    continue;
                }

                $finalBracketAssignments[(int) $registrationData['id']] = $this->normalizeBracketCode(
                    $registrationData['bracket_code'] ?? null,
                );
            }

            $bracketSizes = $finalBracketAssignments
                ->filter()
                ->countBy();

            $overflowingBrackets = $bracketSizes
                ->filter(fn (int $count): bool => $count > self::BRACKET_TEAM_LIMIT);

            $incompleteBrackets = $bracketSizes
                ->filter(fn (int $count): bool => $count < self::BRACKET_TEAM_LIMIT);

            $activeBracketCount = $bracketSizes->count();
            $missingBracketCount = $activeBracketCount > 0 && $activeBracketCount < self::MINIMUM_BRACKET_COUNT;

            if (
                $duplicateSeeds->isEmpty()
                && $overflowingBrackets->isEmpty()
                && $incompleteBrackets->isEmpty()
                && ! $missingBracketCount
            ) {
                return;
            }

            if ($overflowingBrackets->isNotEmpty()) {
                $validator->errors()->add(
                    'registrations',
                    'Each bracket can only contain up to '.self::BRACKET_TEAM_LIMIT.' teams.',
                );
            }

            if ($incompleteBrackets->isNotEmpty()) {
                $validator->errors()->add(
                    'registrations',
                    'Each bracket must contain exactly '.self::BRACKET_TEAM_LIMIT.' teams. Leave extra teams without a bracket until a full bracket can be formed.',
                );
            }

            if ($missingBracketCount) {
                $validator->errors()->add(
                    'registrations',
                    'Bracket play starts only when at least '.self::MINIMUM_BRACKET_TEAM_COUNT.' teams are available, so you need at least '.self::MINIMUM_BRACKET_COUNT.' full brackets.',
                );
            }

            foreach ($registrations as $index => $registrationData) {
                $submittedSeed = filled($registrationData['seed_number'] ?? null)
                    ? (int) $registrationData['seed_number']
                    : null;
                $normalizedBracket = $this->normalizeBracketCode($registrationData['bracket_code'] ?? null);

                if ($submittedSeed !== null && $duplicateSeeds->has((string) $submittedSeed)) {
                    $validator->errors()->add(
                        "registrations.{$index}.seed_number",
                        "Seed {$submittedSeed} is already assigned to another team in this tournament.",
                    );
                }

                if (! $normalizedBracket || ! $overflowingBrackets->has($normalizedBracket)) {
                    if (! $normalizedBracket || ! $incompleteBrackets->has($normalizedBracket)) {
                        if (! $normalizedBracket || ! $missingBracketCount) {
                            continue;
                        }

                        $validator->errors()->add(
                            "registrations.{$index}.bracket_code",
                            'At least '.self::MINIMUM_BRACKET_COUNT.' full brackets are required before teams can be assigned to bracket play.',
                        );

                        continue;
                    }

                    $validator->errors()->add(
                        "registrations.{$index}.bracket_code",
                        "{$normalizedBracket} currently has {$incompleteBrackets[$normalizedBracket]} teams. Each bracket must contain exactly ".self::BRACKET_TEAM_LIMIT.' teams.',
                    );

                    continue;
                }

                $validator->errors()->add(
                    "registrations.{$index}.bracket_code",
                    "{$normalizedBracket} can only contain up to ".self::BRACKET_TEAM_LIMIT.' teams.',
                );
            }
        });

        $validated = $validator->validate();

        DB::transaction(function () use ($validated): void {
            foreach ($validated['registrations'] as $registrationData) {
                $registration = TournamentRegistration::query()
                    ->where('tournament_id', $validated['tournament_id'])
                    ->findOrFail($registrationData['id']);

                $registration->update([
                    'seed_number' => $registrationData['seed_number'] ?? null,
                    'bracket_code' => $this->normalizeBracketCode($registrationData['bracket_code'] ?? null),
                ]);
            }
        });

        return redirect()
            ->route(
                $this->resolveTournamentRedirectRoute($request),
                $this->resolveTournamentRedirectParameters($request, $validated['tournament_id']),
            )
            ->with('status', 'registrations-seeding-updated');
    }

    /**
     * Assign bracket_rank (e.g. A1, A2) from completed round robin results per bracket.
     */
    public function applyBracketRanking(Request $request, Tournament $tournament): RedirectResponse
    {
        app(BracketRankingService::class)->apply($tournament);

        return redirect()
            ->route(
                $this->resolveTournamentRedirectRoute($request),
                $this->resolveTournamentRedirectParameters($request, $tournament->id),
            )
            ->with('status', 'bracket-ranking-applied');
    }

    /**
     * Create crossover games from a single source of truth: {@see TournamentRegistration}
     * rows that have both {@see TournamentRegistration::$bracket_rank} and a normalizable
     * {@see TournamentRegistration::$bracket_code} (set via bracket ranking / seeding).
     *
     * Pairing rule (one rule only): for each adjacent bracket pair (A+B, C+D, …) sorted by
     * normalized bracket code, order teams by rank order within each bracket, then mirror:
     * left rank #1 vs right rank #N, left #2 vs right #N−1, … (classic crossover / “strength vs strength tail”).
     *
     * Idempotency: re-running skips team pairings that already exist; stale **auto-generated**
     * scheduled shells (see {@see TournamentMatch::CROSSOVER_AUTO_GENERATED_MARKER}) are removed
     * when rankings change. Manual crossover games (no marker) are never deleted here.
     */
    public function generateCrossoverSchedule(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tournament_id' => ['required', 'integer', 'exists:tournaments,id'],
        ]);

        $tournamentId = (int) $validated['tournament_id'];

        $rankedWithRankOnly = TournamentRegistration::query()
            ->where('tournament_id', $tournamentId)
            ->get()
            ->filter(fn (TournamentRegistration $registration): bool => filled(trim((string) ($registration->bracket_rank ?? ''))));

        if ($rankedWithRankOnly->isEmpty()) {
            return redirect()
                ->route(
                    $this->resolveTournamentRedirectRoute($request),
                    $this->resolveTournamentRedirectParameters($request, $tournamentId),
                )
                ->withErrors([
                    'crossover' => 'Apply bracket ranking first so every crossover team has a rank (for example A1, B4).',
                ]);
        }

        $rankMissingBracket = $rankedWithRankOnly
            ->filter(fn (TournamentRegistration $registration): bool => ! filled(BracketCodes::normalize($registration->bracket_code ?? null)));

        if ($rankMissingBracket->isNotEmpty()) {
            return redirect()
                ->route(
                    $this->resolveTournamentRedirectRoute($request),
                    $this->resolveTournamentRedirectParameters($request, $tournamentId),
                )
                ->withErrors([
                    'crossover' => 'Every ranked team must belong to a bracket (set bracket code) before generating crossover games. Teams with a rank but no bracket were excluded from pairing.',
                ]);
        }

        $rankedRegistrations = $rankedWithRankOnly
            ->filter(fn (TournamentRegistration $registration): bool => filled(BracketCodes::normalize($registration->bracket_code ?? null)))
            ->values();

        if ($rankedRegistrations->isEmpty()) {
            return redirect()
                ->route(
                    $this->resolveTournamentRedirectRoute($request),
                    $this->resolveTournamentRedirectParameters($request, $tournamentId),
                )
                ->withErrors([
                    'crossover' => 'Crossover needs ranked teams with a valid bracket code.',
                ]);
        }

        $groupedByBracket = $rankedRegistrations
            ->groupBy(fn (TournamentRegistration $registration): string => BracketCodes::normalize($registration->bracket_code ?? null) ?? '')
            ->filter(fn (Collection $_registrations, string $bracketCode): bool => $bracketCode !== '');

        $sortedBracketCodes = $groupedByBracket->keys()->sort()->values();

        if ($sortedBracketCodes->count() < 2) {
            return redirect()
                ->route(
                    $this->resolveTournamentRedirectRoute($request),
                    $this->resolveTournamentRedirectParameters($request, $tournamentId),
                )
                ->withErrors([
                    'crossover' => 'Crossover needs at least two seeded brackets that each have bracket ranks.',
                ]);
        }

        if ($sortedBracketCodes->count() % 2 !== 0) {
            return redirect()
                ->route(
                    $this->resolveTournamentRedirectRoute($request),
                    $this->resolveTournamentRedirectParameters($request, $tournamentId),
                )
                ->withErrors([
                    'crossover' => 'Automatic crossover expects an even number of ranked brackets (for example Bracket A and Bracket B, or four brackets pairing A+B then C+D).',
                ]);
        }

        foreach (range(0, $sortedBracketCodes->count() - 2, 2) as $pairOffset) {
            $codeLeft = $sortedBracketCodes[$pairOffset];
            $codeRight = $sortedBracketCodes[$pairOffset + 1];
            if ($groupedByBracket[$codeLeft]->count() !== $groupedByBracket[$codeRight]->count()) {
                return redirect()
                    ->route(
                        $this->resolveTournamentRedirectRoute($request),
                        $this->resolveTournamentRedirectParameters($request, $tournamentId),
                    )
                    ->withErrors([
                        'crossover' => "{$codeLeft} has {$groupedByBracket[$codeLeft]->count()} ranked teams while {$codeRight} has {$groupedByBracket[$codeRight]->count()}; counts must match to build mirror crossover games.",
                    ]);
            }
        }

        $pitches = Pitch::query()
            ->where('tournament_id', $tournamentId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $expectedPairings = [];

        foreach (range(0, $sortedBracketCodes->count() - 2, 2) as $pairOffset) {
            $codeLeft = $sortedBracketCodes[$pairOffset];
            $codeRight = $sortedBracketCodes[$pairOffset + 1];

            $leftSide = $groupedByBracket[$codeLeft]
                ->sort(function (TournamentRegistration $left, TournamentRegistration $right): int {
                    return [
                        $this->crossoverBracketRankOrderingValue((string) $left->bracket_rank),
                        $left->id,
                    ] <=> [
                        $this->crossoverBracketRankOrderingValue((string) $right->bracket_rank),
                        $right->id,
                    ];
                })
                ->values();

            $rightSide = $groupedByBracket[$codeRight]
                ->sort(function (TournamentRegistration $left, TournamentRegistration $right): int {
                    return [
                        $this->crossoverBracketRankOrderingValue((string) $left->bracket_rank),
                        $left->id,
                    ] <=> [
                        $this->crossoverBracketRankOrderingValue((string) $right->bracket_rank),
                        $right->id,
                    ];
                })
                ->values();

            $n = $leftSide->count();

            if ($rightSide->count() !== $n || $n < 1) {
                continue;
            }

            $shortLeft = BracketCodes::rankPrefixFromCode($codeLeft);
            $shortRight = BracketCodes::rankPrefixFromCode($codeRight);
            $bracketPairGameOrdinal = 0;

            for ($index = 0; $index < $n; $index++) {
                $homeRegistration = $leftSide[$index];
                $awayRegistration = $rightSide[$n - $index - 1];
                $bracketPairGameOrdinal++;

                $expectedPairings[] = [
                    'pair_key' => $this->crossoverTeamPairKey((int) $homeRegistration->team_id, (int) $awayRegistration->team_id),
                    'home_registration_id' => (int) $homeRegistration->id,
                    'away_registration_id' => (int) $awayRegistration->id,
                    'round_label' => "Cross · {$shortLeft} vs {$shortRight} #{$bracketPairGameOrdinal}",
                ];
            }
        }

        $expectedPairings = $this->dedupeCrossoverExpectedPairings($expectedPairings);

        if ($expectedPairings === []) {
            return redirect()
                ->route(
                    $this->resolveTournamentRedirectRoute($request),
                    $this->resolveTournamentRedirectParameters($request, $tournamentId),
                )
                ->withErrors([
                    'crossover' => 'No crossover pairings could be built from the current bracket data.',
                ]);
        }

        $expectedPairKeys = collect($expectedPairings)
            ->pluck('pair_key')
            ->flip()
            ->all();

        $nextMatchNumber = TournamentMatch::nextMatchNumberForTournament($tournamentId);

        $createdCount = 0;
        $skippedDuplicates = 0;
        $crossoverPitchAssignedByUserId = $request->user()->id;

        $autoNotes = TournamentMatch::CROSSOVER_AUTO_GENERATED_MARKER;

        DB::transaction(function () use (
            $tournamentId,
            $pitches,
            $expectedPairings,
            $expectedPairKeys,
            &$nextMatchNumber,
            &$createdCount,
            &$skippedDuplicates,
            $crossoverPitchAssignedByUserId,
            $autoNotes,
        ): void {
            $existingMatches = TournamentMatch::query()
                ->where('tournament_id', $tournamentId)
                ->where('stage', 'crossover')
                ->withCount(['scoreLogs', 'playerStats'])
                ->with(['homeRegistration:id,team_id', 'awayRegistration:id,team_id'])
                ->orderBy('match_number')
                ->get();

            $matchesByPair = [];

            foreach ($existingMatches as $existingMatch) {
                $teamPairKey = $this->crossoverTeamPairKeyForMatch($existingMatch);

                if ($teamPairKey === null) {
                    continue;
                }

                $matchesByPair[$teamPairKey] ??= collect();
                $matchesByPair[$teamPairKey]->push($existingMatch);
            }

            foreach ($matchesByPair as $pairKey => $pairMatches) {
                $regenerableMatches = $pairMatches
                    ->filter(fn (TournamentMatch $match): bool => $this->isRegenerableAutoCrossoverMatch($match))
                    ->values();

                if (isset($expectedPairKeys[$pairKey])) {
                    $regenerableMatches
                        ->slice(1)
                        ->each(fn (TournamentMatch $match): bool => (bool) $match->delete());

                    continue;
                }

                $regenerableMatches
                    ->each(fn (TournamentMatch $match): bool => (bool) $match->delete());
            }

            $existingPairKeys = TournamentMatch::query()
                ->where('tournament_id', $tournamentId)
                ->where('stage', 'crossover')
                ->whereNotNull('home_registration_id')
                ->whereNotNull('away_registration_id')
                ->with(['homeRegistration:id,team_id', 'awayRegistration:id,team_id'])
                ->get()
                ->map(fn (TournamentMatch $match): ?string => $this->crossoverTeamPairKeyForMatch($match))
                ->filter()
                ->unique()
                ->mapWithKeys(fn (string $pairKey): array => [$pairKey => true])
                ->all();

            $pitchAssignments = 0;

            foreach ($expectedPairings as $pairing) {
                if (isset($existingPairKeys[$pairing['pair_key']])) {
                    $skippedDuplicates++;

                    continue;
                }

                $pitchId = $pitches->isEmpty()
                    ? null
                    : $pitches->get($pitchAssignments++ % max($pitches->count(), 1))?->id;

                TournamentMatch::create([
                    'tournament_id' => $tournamentId,
                    'pitch_id' => $pitchId,
                    'pitch_assigned_by' => $pitchId !== null ? $crossoverPitchAssignedByUserId : null,
                    'home_registration_id' => $pairing['home_registration_id'],
                    'away_registration_id' => $pairing['away_registration_id'],
                    'stage' => 'crossover',
                    'round_label' => $pairing['round_label'],
                    'match_number' => $nextMatchNumber++,
                    'scheduled_at' => null,
                    'status' => 'scheduled',
                    'home_score' => null,
                    'away_score' => null,
                    'notes' => $autoNotes,
                ]);

                $existingPairKeys[$pairing['pair_key']] = true;
                $createdCount++;
            }
        });

        $this->syncCrossoverPoolingAssignments($tournamentId);

        return redirect()
            ->route(
                $this->resolveTournamentRedirectRoute($request),
                $this->resolveTournamentRedirectParameters($request, $tournamentId),
            )
            ->with('status', 'crossover-schedule-generated')
            ->with('crossover_matches_created', $createdCount)
            ->with('crossover_matches_skipped_duplicates', $skippedDuplicates);
    }

    /**
     * @param  list<array{pair_key: string, home_registration_id: int, away_registration_id: int, round_label: string}>  $expectedPairings
     * @return list<array{pair_key: string, home_registration_id: int, away_registration_id: int, round_label: string}>
     */
    protected function dedupeCrossoverExpectedPairings(array $expectedPairings): array
    {
        $seen = [];
        $out = [];

        foreach ($expectedPairings as $row) {
            if (isset($seen[$row['pair_key']])) {
                continue;
            }

            $seen[$row['pair_key']] = true;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Numeric suffix used to order ranks like A5 before A11 when applicable.
     */
    protected function crossoverBracketRankOrderingValue(string $bracketRank): int
    {
        $bracketRank = trim($bracketRank);

        if ($bracketRank === '') {
            return PHP_INT_MAX;
        }

        if (preg_match('/(\d+)\s*$/', $bracketRank, $matches) === 1) {
            return (int) $matches[1];
        }

        return PHP_INT_MAX;
    }

    /**
     * Create Quarter Final matches from finalized Pool A / Pool B columns (classic crossover: A# vs B(count−#+1)).
     */
    public function generateQuarterFinalMatches(Request $request, Tournament $tournament): RedirectResponse
    {
        if ($tournament->registrations()->count() < self::MINIMUM_BRACKET_TEAM_COUNT) {
            $tab = $this->normalizeTournamentTab($request->string('redirect_tab')->toString()) ?? 'quarter-final';

            return redirect()
                ->route('admin.tournaments.index', [
                    'tournament' => $tournament->id,
                    'tab' => $tab,
                ])
                ->withErrors([
                    'quarter_final' => __('Knockout for this size is managed on the Quarter Finals tab (Day 2 bracket). Pooling-based generation applies only once you reach :count teams.', ['count' => self::MINIMUM_BRACKET_TEAM_COUNT]),
                ]);
        }

        $crossoverMatches = TournamentMatch::query()
            ->where('tournament_id', $tournament->id)
            ->where('stage', 'crossover')
            ->with(['homeRegistration.team', 'awayRegistration.team'])
            ->orderBy('match_number')
            ->orderBy('id')
            ->get();

        $built = TournamentPooling::buildPoolingAssignments($tournament, $crossoverMatches);

        $tab = $this->normalizeTournamentTab($request->string('redirect_tab')->toString()) ?? 'quarter-final';

        $roundRobinSchedule = SmallTournamentTeamStanding::roundRobinScheduleCompletion($tournament);
        if ($roundRobinSchedule['total'] > 0 && ! $roundRobinSchedule['is_complete']) {
            return redirect()
                ->route('admin.tournaments.index', [
                    'tournament' => $tournament->id,
                    'tab' => $tab,
                ])
                ->withErrors([
                    'quarter_final' => __('Finish every Round Robin game before generating Quarter Finals. Pairings use final standings once all pool games are complete.'),
                ]);
        }

        if (! TournamentPooling::poolingFinalizedForQuarterFinalGeneration($built)) {
            return redirect()
                ->route('admin.tournaments.index', [
                    'tournament' => $tournament->id,
                    'tab' => $tab,
                ])
                ->withErrors([
                    'quarter_final' => __('Cannot generate Quarter Finals yet. Please finalize Pool A and Pool B first.'),
                ]);
        }

        $columns = TournamentPooling::quarterFinalBracketColumnsRegistrationIds($built);

        if ($columns === null) {
            return redirect()
                ->route('admin.tournaments.index', [
                    'tournament' => $tournament->id,
                    'tab' => $tab,
                ])
                ->withErrors([
                    'quarter_final' => __('Could not read Pool A and Pool B qualifiers. Open the Pooling tab and ensure every slot has a team.'),
                ]);
        }

        [$poolAIds, $poolBIds] = $columns;
        $pairCount = min(count($poolAIds), count($poolBIds));

        if ($pairCount < 1) {
            return redirect()
                ->route('admin.tournaments.index', [
                    'tournament' => $tournament->id,
                    'tab' => $tab,
                ])
                ->withErrors([
                    'quarter_final' => __('Each pool needs at least one qualified team to build Quarter Final pairings.'),
                ]);
        }

        $marker = TournamentMatch::QUARTER_FINAL_AUTO_GENERATED_MARKER;

        DB::transaction(function () use ($tournament, $poolAIds, $poolBIds, $pairCount, $marker): void {
            TournamentMatch::query()
                ->where('tournament_id', $tournament->id)
                ->where('stage', 'quarterfinal')
                ->where('notes', 'like', '%'.$marker.'%')
                ->delete();

            $nextNum = TournamentMatch::nextMatchNumberForTournament($tournament->id);

            for ($i = 0; $i < $pairCount; $i++) {
                $homeId = $poolAIds[$i];
                $awayId = $poolBIds[$pairCount - 1 - $i];

                TournamentMatch::create([
                    'tournament_id' => $tournament->id,
                    'pitch_id' => null,
                    'pitch_assigned_by' => null,
                    'home_registration_id' => $homeId,
                    'away_registration_id' => $awayId,
                    'stage' => 'quarterfinal',
                    'round_label' => __('Quarter Final · Game :n', ['n' => $i + 1]),
                    'match_number' => $nextNum,
                    'scheduled_at' => null,
                    'status' => 'scheduled',
                    'home_score' => null,
                    'away_score' => null,
                    'notes' => $marker,
                ]);

                $nextNum++;
            }
        });

        return redirect()
            ->route('admin.tournaments.index', [
                'tournament' => $tournament->id,
                'tab' => $tab,
            ])
            ->with('status', 'quarter-final-generated')
            ->with('quarter_final_matches_created', $pairCount);
    }

    /**
     * Generate one round-robin schedule per seeded bracket and assign each
     * bracket to the next available pitch.
     */
    public function generateRoundRobinMatches(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tournament_id' => ['required', 'integer', 'exists:tournaments,id'],
        ]);

        $tournamentId = (int) $validated['tournament_id'];
        $pitches = Pitch::query()
            ->where('tournament_id', $tournamentId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($pitches->isEmpty()) {
            return redirect()
                ->route(
                    $this->resolveTournamentRedirectRoute($request),
                    $this->resolveTournamentRedirectParameters($request, $tournamentId),
                )
                ->withErrors([
                    'round_robin' => 'Add Pitch 1 and Pitch 2 first before generating the bracket round robin schedule.',
                ]);
        }

        $bracketGroups = TournamentRegistration::query()
            ->with('team')
            ->where('tournament_id', $tournamentId)
            ->whereNotNull('bracket_code')
            ->get()
            ->groupBy(fn (TournamentRegistration $registration): string => $this->normalizeBracketCode($registration->bracket_code) ?? '')
            ->filter(fn (Collection $registrations, string $bracketCode): bool => $bracketCode !== '' && $registrations->count() >= 2)
            ->sortKeys()
            ->values();

        if ($bracketGroups->isEmpty()) {
            return redirect()
                ->route(
                    $this->resolveTournamentRedirectRoute($request),
                    $this->resolveTournamentRedirectParameters($request, $tournamentId),
                )
                ->withErrors([
                    'round_robin' => 'Seed teams into brackets first. Round robin is generated inside each bracket only.',
                ]);
        }

        $createdCount = 0;
        $assignedCount = 0;
        $updatedCount = 0;

        DB::transaction(function () use ($tournamentId, $pitches, $bracketGroups, &$createdCount, &$assignedCount, &$updatedCount): void {
            $existingMatches = TournamentMatch::query()
                ->where('tournament_id', $tournamentId)
                ->where('stage', 'round_robin')
                ->get();

            $matchesByPair = [];

            foreach ($existingMatches as $match) {
                if (! $match->home_registration_id || ! $match->away_registration_id) {
                    continue;
                }

                $matchesByPair[$this->roundRobinPairKey((int) $match->home_registration_id, (int) $match->away_registration_id)] = $match;
            }

            $nextMatchNumber = TournamentMatch::nextMatchNumberForTournament($tournamentId);

            foreach ($bracketGroups as $bracketIndex => $registrations) {
                $pitch = $pitches->values()->get($bracketIndex % $pitches->count());
                $bracketCode = $this->normalizeBracketCode($registrations->first()->bracket_code) ?? 'Bracket';
                $sortedRegistrations = $registrations
                    ->sort(fn (TournamentRegistration $left, TournamentRegistration $right): int => [
                        $left->seed_number ?? PHP_INT_MAX,
                        $left->team?->name ?? '',
                        $left->id,
                    ] <=> [
                        $right->seed_number ?? PHP_INT_MAX,
                        $right->team?->name ?? '',
                        $right->id,
                    ])
                    ->values();
                $bracketMatchNumber = 1;

                for ($homeIndex = 0; $homeIndex < $sortedRegistrations->count(); $homeIndex++) {
                    for ($awayIndex = $homeIndex + 1; $awayIndex < $sortedRegistrations->count(); $awayIndex++) {
                        $homeRegistration = $sortedRegistrations->get($homeIndex);
                        $awayRegistration = $sortedRegistrations->get($awayIndex);
                        $pairKey = $this->roundRobinPairKey($homeRegistration->id, $awayRegistration->id);
                        $roundLabel = "{$bracketCode} Match {$bracketMatchNumber}";
                        $existingMatch = $matchesByPair[$pairKey] ?? null;

                        if ($existingMatch) {
                            $updates = [];

                            if (! $existingMatch->pitch_id) {
                                $updates['pitch_id'] = $pitch->id;
                            }

                            if (! $existingMatch->round_label) {
                                $updates['round_label'] = $roundLabel;
                            }

                            if (! $existingMatch->match_number) {
                                $updates['match_number'] = $nextMatchNumber++;
                            }

                            if ($updates !== []) {
                                $existingMatch->update($updates);
                                $updatedCount++;

                                if (array_key_exists('pitch_id', $updates)) {
                                    $assignedCount++;
                                }
                            }
                        } else {
                            $match = TournamentMatch::create([
                                'tournament_id' => $tournamentId,
                                'pitch_id' => $pitch->id,
                                'home_registration_id' => $homeRegistration->id,
                                'away_registration_id' => $awayRegistration->id,
                                'stage' => 'round_robin',
                                'round_label' => $roundLabel,
                                'match_number' => $nextMatchNumber++,
                                'status' => 'completed',
                                'home_score' => 0,
                                'away_score' => 0,
                            ]);

                            $matchesByPair[$pairKey] = $match;
                            $createdCount++;
                        }

                        $bracketMatchNumber++;
                    }
                }
            }
        });

        return redirect()
            ->route(
                $this->resolveTournamentRedirectRoute($request),
                $this->resolveTournamentRedirectParameters($request, $tournamentId),
            )
            ->with('status', 'round-robin-generated')
            ->with('round_robin_created', $createdCount)
            ->with('round_robin_assigned', $assignedCount)
            ->with('round_robin_updated', $updatedCount);
    }

    /**
     * Add a match schedule or result entry to a selected tournament.
     */
    public function storeMatch(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'tournament_id' => ['required', 'integer', 'exists:tournaments,id'],
            'pitch_id' => ['nullable', 'integer', 'exists:pitches,id'],
            'home_registration_id' => ['required', 'integer', 'exists:tournament_registrations,id'],
            'away_registration_id' => ['required', 'integer', 'different:home_registration_id', 'exists:tournament_registrations,id'],
            'stage' => ['required', 'string', 'max:50'],
            'round_label' => ['nullable', 'string', 'max:255'],
            'match_number' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'scheduled_at' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['scheduled', 'live', 'completed'])],
            'home_score' => ['nullable', 'integer', 'min:0', 'max:999'],
            'away_score' => ['nullable', 'integer', 'min:0', 'max:999'],
            'notes' => ['nullable', 'string'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            $tournamentId = $request->integer('tournament_id');
            $stage = $this->normalizeNullableString($request->string('stage')->toString()) ?? 'group';

            if ($request->filled('pitch_id')
                && ! Pitch::query()
                    ->whereKey($request->integer('pitch_id'))
                    ->where('tournament_id', $tournamentId)
                    ->exists()
            ) {
                $validator->errors()->add('pitch_id', 'The selected pitch does not belong to this tournament.');
            }

            foreach (['home_registration_id', 'away_registration_id'] as $field) {
                if (! TournamentRegistration::query()
                    ->whereKey($request->integer($field))
                    ->where('tournament_id', $tournamentId)
                    ->exists()
                ) {
                    $validator->errors()->add($field, 'The selected team registration does not belong to this tournament.');
                }
            }

            $hasHomeScore = $request->filled('home_score');
            $hasAwayScore = $request->filled('away_score');

            if ($hasHomeScore xor $hasAwayScore) {
                $validator->errors()->add('home_score', 'Both scores are required when recording a result.');
                $validator->errors()->add('away_score', 'Both scores are required when recording a result.');
            }

            if ($request->string('status')->toString() === 'completed' && (! $hasHomeScore || ! $hasAwayScore)) {
                $validator->errors()->add('status', 'Completed matches must include both home and away scores.');
            }

            if ($stage === 'round_robin') {
                $homeRegistrationId = $request->integer('home_registration_id');
                $awayRegistrationId = $request->integer('away_registration_id');
                $selectedBracketCode = $this->normalizeNullableString($request->string('round_robin_bracket_code')->toString());

                $selectedRegistrations = TournamentRegistration::query()
                    ->whereIn('id', [$homeRegistrationId, $awayRegistrationId])
                    ->get()
                    ->keyBy('id');

                $homeRegistration = $selectedRegistrations->get($homeRegistrationId);
                $awayRegistration = $selectedRegistrations->get($awayRegistrationId);

                if ($homeRegistration && $awayRegistration) {
                    if ($this->normalizeBracketCode($homeRegistration->bracket_code) !== $this->normalizeBracketCode($awayRegistration->bracket_code)) {
                        $validator->errors()->add('away_registration_id', 'Round robin teams must come from the same bracket.');
                    }

                    if ($selectedBracketCode !== null
                        && $this->normalizeBracketCode($homeRegistration->bracket_code) !== $this->normalizeBracketCode($selectedBracketCode)
                    ) {
                        $validator->errors()->add('round_robin_bracket_code', 'The selected bracket does not match the chosen teams.');
                    }
                }

                if ($homeRegistrationId > 0 && $awayRegistrationId > 0) {
                    $duplicateRoundRobinMatchExists = TournamentMatch::query()
                        ->where('tournament_id', $tournamentId)
                        ->where('stage', 'round_robin')
                        ->where(function (Builder $query) use ($homeRegistrationId, $awayRegistrationId): void {
                            $query
                                ->where(function (Builder $pairQuery) use ($homeRegistrationId, $awayRegistrationId): void {
                                    $pairQuery
                                        ->where('home_registration_id', $homeRegistrationId)
                                        ->where('away_registration_id', $awayRegistrationId);
                                })
                                ->orWhere(function (Builder $pairQuery) use ($homeRegistrationId, $awayRegistrationId): void {
                                    $pairQuery
                                        ->where('home_registration_id', $awayRegistrationId)
                                        ->where('away_registration_id', $homeRegistrationId);
                                });
                        })
                        ->exists();

                    if ($duplicateRoundRobinMatchExists) {
                        $validator->errors()->add('away_registration_id', 'This round robin matchup has already been scheduled.');
                    }
                }

            }

            if ($stage === 'crossover') {
                $homeRegistrationId = $request->integer('home_registration_id');
                $awayRegistrationId = $request->integer('away_registration_id');

                $selectedRegistrations = TournamentRegistration::query()
                    ->whereIn('id', [$homeRegistrationId, $awayRegistrationId])
                    ->get()
                    ->keyBy('id');

                $homeRegistration = $selectedRegistrations->get($homeRegistrationId);
                $awayRegistration = $selectedRegistrations->get($awayRegistrationId);

                if ($homeRegistration && $awayRegistration) {
                    $homeRank = trim((string) ($homeRegistration->bracket_rank ?? ''));
                    $awayRank = trim((string) ($awayRegistration->bracket_rank ?? ''));

                    if ($homeRank === '' || $awayRank === '') {
                        $validator->errors()->add('home_registration_id', 'Crossover teams must have a bracket rank from Bracket Ranking (e.g. A1, B2).');
                    }

                    $homeBracket = $this->normalizeBracketCode($homeRegistration->bracket_code);
                    $awayBracket = $this->normalizeBracketCode($awayRegistration->bracket_code);

                    if ($homeBracket === null || $awayBracket === null || $homeBracket === $awayBracket) {
                        $validator->errors()->add('away_registration_id', 'Crossover pairs must be from two different brackets.');
                    }

                    if ($homeRegistrationId > 0 && $awayRegistrationId > 0
                        && $this->crossoverDuplicatePairExists($tournamentId, $homeRegistrationId, $awayRegistrationId)
                    ) {
                        $validator->errors()->add('away_registration_id', 'This crossover matchup has already been scheduled.');
                    }
                }
            }
        });

        $validated = $validator->validate();

        $scoresVisible = $validated['status'] !== 'scheduled'
            && array_key_exists('home_score', $validated)
            && array_key_exists('away_score', $validated)
            && $validated['home_score'] !== null
            && $validated['away_score'] !== null;

        $newPitchId = $validated['pitch_id'] ?? null;

        $tournamentIdForMatch = (int) $validated['tournament_id'];
        $resolvedMatchNumber = array_key_exists('match_number', $validated) && $validated['match_number'] !== null
            ? (int) $validated['match_number']
            : TournamentMatch::nextMatchNumberForTournament($tournamentIdForMatch);

        TournamentMatch::create([
            'tournament_id' => $validated['tournament_id'],
            'pitch_id' => $newPitchId,
            'pitch_assigned_by' => $newPitchId !== null ? $request->user()->id : null,
            'home_registration_id' => $validated['home_registration_id'],
            'away_registration_id' => $validated['away_registration_id'],
            'stage' => $this->normalizeNullableString($validated['stage']) ?? 'group',
            'round_label' => $this->normalizeNullableString($validated['round_label'] ?? null),
            'match_number' => $resolvedMatchNumber,
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'status' => $validated['status'],
            'home_score' => $scoresVisible ? $validated['home_score'] : null,
            'away_score' => $scoresVisible ? $validated['away_score'] : null,
            'notes' => $this->normalizeNullableString($validated['notes'] ?? null),
        ]);

        return redirect()
            ->route(
                $this->resolveTournamentRedirectRoute($request),
                $this->resolveTournamentRedirectParameters($request, $validated['tournament_id']),
            )
            ->with('status', 'match-created');
    }

    /**
     * Update an existing tournament match. Used by the manual round robin
     * setup flow so admins can adjust teams, pitch, schedule, and round
     * label per match without regenerating the whole bracket.
     */
    public function updateMatch(Request $request, TournamentMatch $match): RedirectResponse
    {
        $tournamentId = $match->tournament_id;

        $validator = Validator::make($request->all(), [
            'pitch_id' => ['nullable', 'integer', 'exists:pitches,id'],
            'home_registration_id' => ['required', 'integer', 'exists:tournament_registrations,id'],
            'away_registration_id' => ['required', 'integer', 'different:home_registration_id', 'exists:tournament_registrations,id'],
            'round_label' => ['nullable', 'string', 'max:255'],
            'match_number' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        $validator->after(function ($validator) use ($request, $tournamentId, $match): void {
            if ($request->filled('pitch_id')
                && ! Pitch::query()
                    ->whereKey($request->integer('pitch_id'))
                    ->where('tournament_id', $tournamentId)
                    ->exists()
            ) {
                $validator->errors()->add('pitch_id', 'The selected pitch does not belong to this tournament.');
            }

            foreach (['home_registration_id', 'away_registration_id'] as $field) {
                if (! TournamentRegistration::query()
                    ->whereKey($request->integer($field))
                    ->where('tournament_id', $tournamentId)
                    ->exists()
                ) {
                    $validator->errors()->add($field, 'The selected team registration does not belong to this tournament.');
                }
            }

            if ($match->stage === 'crossover') {
                $homeRegistrationId = $request->integer('home_registration_id');
                $awayRegistrationId = $request->integer('away_registration_id');

                $selectedRegistrations = TournamentRegistration::query()
                    ->whereIn('id', [$homeRegistrationId, $awayRegistrationId])
                    ->get()
                    ->keyBy('id');

                $homeRegistration = $selectedRegistrations->get($homeRegistrationId);
                $awayRegistration = $selectedRegistrations->get($awayRegistrationId);

                if ($homeRegistration && $awayRegistration) {
                    $homeRank = trim((string) ($homeRegistration->bracket_rank ?? ''));
                    $awayRank = trim((string) ($awayRegistration->bracket_rank ?? ''));

                    if ($homeRank === '' || $awayRank === '') {
                        $validator->errors()->add('home_registration_id', 'Crossover teams must have a bracket rank from Bracket Ranking.');
                    }

                    $homeBracket = $this->normalizeBracketCode($homeRegistration->bracket_code);
                    $awayBracket = $this->normalizeBracketCode($awayRegistration->bracket_code);

                    if ($homeBracket === null || $awayBracket === null || $homeBracket === $awayBracket) {
                        $validator->errors()->add('away_registration_id', 'Crossover pairs must be from two different brackets.');
                    }

                    if ($homeRegistrationId > 0 && $awayRegistrationId > 0
                        && $this->crossoverDuplicatePairExists($tournamentId, $homeRegistrationId, $awayRegistrationId, $match->id)
                    ) {
                        $validator->errors()->add('away_registration_id', 'This crossover matchup already exists.');
                    }
                }
            }
        });

        $validated = $validator->validate();

        $newPitchId = $validated['pitch_id'] ?? null;
        $pitchChanged = (string) ($match->pitch_id ?? '') !== (string) ($newPitchId ?? '');
        $pitchAssignedBy = $match->pitch_assigned_by;
        if ($pitchChanged) {
            $pitchAssignedBy = $newPitchId !== null ? $request->user()->id : null;
        }

        $match->update([
            'pitch_id' => $newPitchId,
            'pitch_assigned_by' => $pitchAssignedBy,
            'home_registration_id' => $validated['home_registration_id'],
            'away_registration_id' => $validated['away_registration_id'],
            'round_label' => $this->normalizeNullableString($validated['round_label'] ?? null),
            'match_number' => $validated['match_number'] ?? null,
            'scheduled_at' => $validated['scheduled_at'] ?? null,
        ]);

        return redirect()
            ->route(
                $this->resolveTournamentRedirectRoute($request),
                $this->resolveTournamentRedirectParameters($request, $tournamentId),
            )
            ->with('status', 'match-updated');
    }

    /**
     * Clear pitch assignment for a scheduled crossover match so it returns to the unassigned list.
     */
    public function unassignCrossoverMatchPitch(Request $request, TournamentMatch $match): RedirectResponse
    {
        abort_unless($match->stage === 'crossover', 404);

        $tournamentId = $match->tournament_id;

        if (! filled($match->pitch_id)) {
            return redirect()
                ->route(
                    $this->resolveTournamentRedirectRoute($request),
                    $this->resolveTournamentRedirectParameters($request, $tournamentId),
                )
                ->withErrors(['crossover' => __('This crossover game is not assigned to a field.')]);
        }

        if ($match->status !== 'scheduled' || $match->scoreLogs()->exists()) {
            return redirect()
                ->route(
                    $this->resolveTournamentRedirectRoute($request),
                    $this->resolveTournamentRedirectParameters($request, $tournamentId),
                )
                ->withErrors(['crossover' => __('Removing a field assignment is only allowed for scheduled crossover games with no scoring activity.')]);
        }

        $match->update([
            'pitch_id' => null,
            'pitch_assigned_by' => null,
        ]);

        return redirect()
            ->route(
                $this->resolveTournamentRedirectRoute($request),
                $this->resolveTournamentRedirectParameters($request, $tournamentId),
            )
            ->with('status', 'crossover-pitch-unassigned');
    }

    /**
     * Delete a tournament match. Cascades to score logs and player stats
     * via the underlying schema relationships.
     */
    public function destroyMatch(Request $request, TournamentMatch $match): RedirectResponse
    {
        if ($request->filled('tournament_id')) {
            abort_unless(
                (int) $request->input('tournament_id') === (int) $match->tournament_id,
                403,
            );
        }

        $tournamentId = $match->tournament_id;
        $wasCrossoverMatch = $match->stage === 'crossover';

        DB::transaction(function () use ($match): void {
            $match->delete();
        });

        if ($wasCrossoverMatch) {
            $this->syncCrossoverPoolingAssignments($tournamentId);
        }

        return redirect()
            ->route(
                $this->resolveTournamentRedirectRoute($request),
                $this->resolveTournamentRedirectParameters($request, $tournamentId),
            )
            ->with('status', 'match-deleted');
    }

    /**
     * Persist the fixed Day 1 round robin grid (Pitch 1 & Pitch 2) for tournaments below the bracket threshold.
     *
     * Day 2 is upserted alongside Day 1 so the admin only needs one sync action; Day 2 failures surface
     * as a separate error key so Day 1 remains useful even when the Day 2 roster is incomplete.
     */
    public function syncSmallDayOneRoundRobinSchedule(Request $request, Tournament $tournament): RedirectResponse
    {
        try {
            SmallFixedRoundRobinDayOneSchedule::sync($tournament);
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('admin.tournaments.index', $this->adminRoundRobinTabQuery($request, $tournament))
                ->withErrors(['small_day1_schedule' => $exception->getMessage()]);
        }

        try {
            SmallFixedRoundRobinDayTwoSchedule::sync($tournament);
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('admin.tournaments.index', $this->adminRoundRobinTabQuery($request, $tournament))
                ->with('status', 'small-day1-schedule-synced')
                ->withErrors(['small_day2_schedule' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.tournaments.index', $this->adminRoundRobinTabQuery($request, $tournament))
            ->with('status', 'small-day1-schedule-synced');
    }

    /**
     * Persist the fixed Day 2 round robin grid (Pitch 1 & Pitch 2) for tournaments below the bracket threshold.
     */
    public function syncSmallDayTwoRoundRobinSchedule(Request $request, Tournament $tournament): RedirectResponse
    {
        try {
            SmallFixedRoundRobinDayTwoSchedule::sync($tournament);
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('admin.tournaments.index', $this->adminRoundRobinTabQuery($request, $tournament))
                ->withErrors(['small_day2_schedule' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.tournaments.index', $this->adminRoundRobinTabQuery($request, $tournament))
            ->with('status', 'small-day2-schedule-synced');
    }

    /**
     * Quick status changes from the small-tournament fixed Day 1 round robin table,
     * or from Day 2 knockout bracket matches (games 37–48) identified by {@see SmallDayTwoKnockoutBracket}.
     */
    public function updateRoundRobinMatchStatus(Request $request, Tournament $tournament, TournamentMatch $match): RedirectResponse
    {
        $this->ensureTournamentOwnsMatch($tournament, $match);

        $belowBracketThreshold = $tournament->registrations()->count() < self::MINIMUM_BRACKET_TEAM_COUNT;
        $isSmallKnockoutBracket = SmallDayTwoKnockoutBracket::isSmallDayTwoKnockoutScheduleRow($match);

        if ($isSmallKnockoutBracket) {
            abort_unless($belowBracketThreshold, 404);
        } else {
            abort_unless($match->stage === 'round_robin', 404);

            if (! $belowBracketThreshold) {
                return redirect()
                    ->route(
                        $this->resolveTournamentRedirectRoute($request),
                        $this->resolveTournamentRedirectParameters($request, $tournament),
                    )
                    ->withErrors(['status' => __('This quick status control is only available for tournaments below the bracket threshold.')]);
            }
        }

        $bracketStatuses = ['scheduled', 'live', 'completed'];
        $roundRobinStatuses = ['scheduled', 'live', 'completed'];

        $validator = Validator::make($request->all(), [
            'status' => [
                'required',
                Rule::in($isSmallKnockoutBracket ? $bracketStatuses : $roundRobinStatuses),
            ],
        ]);

        $validator->after(function ($validator) use ($request, $match, $isSmallKnockoutBracket): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $status = $request->string('status')->toString();

            if ($status === 'scheduled' && $match->scoreLogs()->exists()) {
                $validator->errors()->add('status', __('Clear the scoring timeline before moving the match back to scheduled.'));
            }

            if ($status === 'completed') {
                $hasBothSides = $match->home_registration_id !== null && $match->away_registration_id !== null;
                // Small Day 2 knockout cards (games 37–48, bracket marker) enter player-based scores only after the
                // row is Completed — same bootstrap as quarter finals without a seeded scoreline. Requiring scores
                // here blocks Ranking Path / placement games (41–42) and any fresh knockout row from ever opening scoring.
                if ($hasBothSides && ($match->home_score === null || $match->away_score === null) && ! $isSmallKnockoutBracket) {
                    $validator->errors()->add('status', __('Completed matches must include both home and away scores.'));
                }

                if (! $hasBothSides && ! $isSmallKnockoutBracket) {
                    $validator->errors()->add('status', __('Assign both teams before marking this match completed.'));
                }
            }
        });

        $validated = $validator->validate();

        $match->update([
            'status' => $validated['status'],
        ]);

        if ($isSmallKnockoutBracket) {
            SmallDayTwoKnockoutBracket::syncAfterResultChange($tournament, $match);
        }

        return redirect()
            ->route(
                $this->resolveTournamentRedirectRoute($request),
                $this->resolveTournamentRedirectParameters($request, $tournament),
            )
            ->with('status', 'match-status-updated');
    }

    /**
     * Apply one status to both Pitch 1 & Pitch 2 games for a fixed Day 1 time-slot row (same round).
     */
    public function updateSmallDayOneRoundRobinSlotStatus(Request $request, Tournament $tournament): RedirectResponse
    {
        if ($tournament->registrations()->count() >= self::MINIMUM_BRACKET_TEAM_COUNT) {
            return redirect()
                ->route('admin.tournaments.index', $this->adminRoundRobinTabQuery($request, $tournament))
                ->withErrors(['status' => __('This row status control is only available for tournaments below the bracket threshold.')]);
        }

        $request->merge([
            'status' => $this->normalizeSmallDayOneSlotStatusInput($request->string('status')->toString()),
        ]);

        $markerPrefix = SmallFixedRoundRobinDayOneSchedule::MARKER_PREFIX;

        $validator = Validator::make($request->all(), [
            'round' => ['required', 'integer', 'min:1', 'max:12'],
            'status' => ['required', Rule::in(['scheduled', 'live', 'completed'])],
        ]);

        $validator->after(function ($validator) use ($request, $tournament, $markerPrefix): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $round = (int) $request->input('round');
            $status = $request->string('status')->toString();
            $gameNumbers = [($round * 2) - 1, $round * 2];

            $matches = TournamentMatch::query()
                ->where('tournament_id', $tournament->id)
                ->where('stage', 'round_robin')
                ->whereIn('match_number', $gameNumbers)
                ->where('notes', 'like', '%'.$markerPrefix.'%')
                ->orderBy('match_number')
                ->get();

            if ($matches->isEmpty()) {
                $validator->errors()->add('status', __('Sync the Day 1 schedule before setting row status.'));

                return;
            }

            foreach ($matches as $match) {
                if ($status === 'scheduled' && $match->scoreLogs()->exists()) {
                    $validator->errors()->add(
                        'status',
                        __('Clear the scoring timeline on game :num before moving this row back to upcoming.', ['num' => $match->match_number]),
                    );

                    return;
                }
            }
        });

        if ($validator->fails()) {
            return redirect()
                ->route('admin.tournaments.index', $this->adminRoundRobinTabQuery($request, $tournament))
                ->withErrors($validator);
        }

        $validated = $validator->validated();

        $round = (int) $validated['round'];
        $status = $validated['status'];
        $gameNumbers = [($round * 2) - 1, $round * 2];

        $matches = TournamentMatch::query()
            ->where('tournament_id', $tournament->id)
            ->where('stage', 'round_robin')
            ->whereIn('match_number', $gameNumbers)
            ->where('notes', 'like', '%'.$markerPrefix.'%')
            ->orderBy('match_number')
            ->get();

        DB::transaction(function () use ($matches, $status): void {
            foreach ($matches as $match) {
                $match->update(['status' => $status]);
            }
        });

        return redirect()
            ->route('admin.tournaments.index', $this->adminRoundRobinTabQuery($request, $tournament))
            ->with('status', 'match-status-updated');
    }

    /**
     * Apply one status to both Pitch 1 & Pitch 2 games for a fixed Day 2 time-slot row (same round).
     */
    public function updateSmallDayTwoRoundRobinSlotStatus(Request $request, Tournament $tournament): RedirectResponse
    {
        if ($tournament->registrations()->count() >= self::MINIMUM_BRACKET_TEAM_COUNT) {
            return redirect()
                ->route('admin.tournaments.index', $this->adminRoundRobinTabQuery($request, $tournament))
                ->withErrors(['status' => __('This row status control is only available for tournaments below the bracket threshold.')]);
        }

        $request->merge([
            'status' => $this->normalizeSmallDayOneSlotStatusInput($request->string('status')->toString()),
        ]);

        $markerPrefix = SmallFixedRoundRobinDayTwoSchedule::MARKER_PREFIX;
        $firstRound = SmallFixedRoundRobinDayTwoSchedule::FIRST_ROUND_NUMBER;
        $lastRound = SmallFixedRoundRobinDayTwoSchedule::lastScheduledRoundNumber($tournament);
        $firstGame = SmallFixedRoundRobinDayTwoSchedule::FIRST_GAME_NUMBER;

        $validator = Validator::make($request->all(), [
            'round' => ['required', 'integer', 'min:'.$firstRound, 'max:'.max($firstRound, $lastRound)],
            'status' => ['required', Rule::in(['scheduled', 'live', 'completed'])],
        ]);

        $validator->after(function ($validator) use ($request, $tournament, $markerPrefix, $firstRound, $firstGame): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $round = (int) $request->input('round');
            $status = $request->string('status')->toString();
            $slotIndex = $round - $firstRound;
            $gameNumbers = [$firstGame + ($slotIndex * 2), $firstGame + ($slotIndex * 2) + 1];

            $matches = TournamentMatch::query()
                ->where('tournament_id', $tournament->id)
                ->where('stage', 'round_robin')
                ->whereIn('match_number', $gameNumbers)
                ->where('notes', 'like', '%'.$markerPrefix.'%')
                ->orderBy('match_number')
                ->get();

            if ($matches->isEmpty()) {
                $validator->errors()->add('status', __('Sync the Day 2 schedule before setting row status.'));

                return;
            }

            foreach ($matches as $match) {
                if ($status === 'scheduled' && $match->scoreLogs()->exists()) {
                    $validator->errors()->add(
                        'status',
                        __('Clear the scoring timeline on game :num before moving this row back to upcoming.', ['num' => $match->match_number]),
                    );

                    return;
                }
            }
        });

        if ($validator->fails()) {
            return redirect()
                ->route('admin.tournaments.index', $this->adminRoundRobinTabQuery($request, $tournament))
                ->withErrors($validator);
        }

        $validated = $validator->validated();

        $round = (int) $validated['round'];
        $status = $validated['status'];
        $slotIndex = $round - $firstRound;
        $gameNumbers = [$firstGame + ($slotIndex * 2), $firstGame + ($slotIndex * 2) + 1];

        $matches = TournamentMatch::query()
            ->where('tournament_id', $tournament->id)
            ->where('stage', 'round_robin')
            ->whereIn('match_number', $gameNumbers)
            ->where('notes', 'like', '%'.$markerPrefix.'%')
            ->orderBy('match_number')
            ->get();

        DB::transaction(function () use ($matches, $status): void {
            foreach ($matches as $match) {
                $match->update(['status' => $status]);
            }
        });

        return redirect()
            ->route('admin.tournaments.index', $this->adminRoundRobinTabQuery($request, $tournament))
            ->with('status', 'match-status-updated');
    }

    /**
     * Query string for admin tournament setup opened on the Round Robin tab (fixed Day 1 flows).
     *
     * @return array<string, mixed>
     */
    protected function adminRoundRobinTabQuery(Request $request, Tournament $tournament): array
    {
        return collect([
            'tournament' => $tournament->id,
            'tab' => 'round-robin',
            'search' => $this->normalizeNullableString($request->string('redirect_search')->toString()),
        ])->filter(fn (mixed $value): bool => $value !== null && $value !== '')->all();
    }

    /**
     * Map UI labels (Upcoming / Live / Completed) to persisted match status values.
     */
    protected function normalizeSmallDayOneSlotStatusInput(string $status): string
    {
        $trimmed = trim($status);

        return match (strtolower($trimmed)) {
            'upcoming' => 'scheduled',
            'live' => 'live',
            'completed' => 'completed',
            default => $trimmed,
        };
    }

    /**
     * Add a public crew entry to a selected tournament.
     */
    public function storeCrew(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tournament_id' => ['required', 'integer', 'exists:tournaments,id'],
            'category' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'photo' => ['nullable', Rule::imageFile(allowSvg: true)->max(2048)],
            'photo_path' => ['nullable', 'string', 'max:2048'],
            'sort_order' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        TournamentCrew::create([
            'tournament_id' => $validated['tournament_id'],
            'category' => $this->normalizeNullableString($validated['category']) ?? 'Crew',
            'title' => $this->normalizeNullableString($validated['title'] ?? null),
            'name' => $validated['name'],
            'photo_path' => $request->file('photo')?->store('tournament-crews', 'public')
                ?? $this->normalizeNullableString($validated['photo_path'] ?? null),
            'sort_order' => $validated['sort_order'],
        ]);

        return redirect()
            ->route(
                $this->resolveTournamentRedirectRoute($request),
                $this->resolveTournamentRedirectParameters($request, $validated['tournament_id']),
            )
            ->with('status', 'crew-created');
    }

    /**
     * Generate a unique slug for a tournament name.
     */
    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'tournament';
        $slug = $base;
        $suffix = 2;

        while (Tournament::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * Validation rules shared by tournament create and edit forms.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function tournamentRules(string $prefix = ''): array
    {
        $field = fn (string $name): string => $prefix.$name;

        return [
            $field('name') => ['required', 'string', 'max:255'],
            $field('venue') => ['required', 'string', 'max:255'],
            $field('description') => ['nullable', 'string'],
            $field('registration_deadline') => ['nullable', 'date'],
            $field('starts_at') => ['nullable', 'date'],
            $field('ends_at') => ['nullable', 'date'],
            $field('status') => ['required', Rule::in(['draft', 'registration', 'live', 'completed'])],
            $field('country_name') => ['required', 'string', 'max:255'],
            $field('province_code') => ['required', 'string'],
            $field('province') => ['required', 'string', 'max:255'],
            $field('city_code') => ['required', 'string'],
            $field('city') => ['required', 'string', 'max:255'],
            $field('barangay_code') => ['required', 'string'],
            $field('barangay') => ['required', 'string', 'max:255'],
            $field('timezone') => ['nullable', 'string', 'max:120'],
            $field('venue_google_map_link') => ['nullable', 'string', 'max:2048'],
            $field('thumbnail_path') => ['nullable', 'string', 'max:2048'],
            $field('event_type') => ['nullable', 'string', 'max:50'],
            $field('division') => ['nullable', 'string', 'max:50'],
            $field('surface') => ['nullable', 'string', 'max:50'],
            $field('round_robin_advancing_count') => ['nullable', 'integer', 'min:1', 'max:255'],
            $field('info_labels') => ['nullable', 'array'],
            $field('info_labels.*') => ['nullable', 'string', 'max:120'],
            $field('info_values') => ['nullable', 'array'],
            $field('info_values.*') => ['nullable', 'string', 'max:500'],
            $field('organizer_labels') => ['nullable', 'array'],
            $field('organizer_labels.*') => ['nullable', 'string', 'max:120'],
            $field('organizer_values') => ['nullable', 'array'],
            $field('organizer_values.*') => ['nullable', 'string', 'max:500'],
            $field('link_labels') => ['nullable', 'array'],
            $field('link_labels.*') => ['nullable', 'string', 'max:120'],
            $field('link_urls') => ['nullable', 'array'],
            $field('link_urls.*') => ['nullable', 'string', 'max:2048'],
            $field('is_public') => ['nullable', 'boolean'],
        ];
    }

    /**
     * Build a normalized tournament payload from validated input.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function buildTournamentPayload(array $validated, Request $request, string $prefix = ''): array
    {
        $field = fn (string $name): string => $prefix.$name;

        return [
            'name' => $validated[$field('name')],
            'venue' => $validated[$field('venue')],
            'description' => $this->normalizeNullableString($validated[$field('description')] ?? null),
            'registration_deadline' => $validated[$field('registration_deadline')] ?? null,
            'starts_at' => $validated[$field('starts_at')] ?? null,
            'ends_at' => $validated[$field('ends_at')] ?? null,
            'status' => $validated[$field('status')],
            'country_name' => $this->normalizeNullableString($validated[$field('country_name')]) ?? 'Philippines',
            'province' => $this->normalizeNullableString($validated[$field('province')] ?? null),
            'city' => $this->normalizeNullableString($validated[$field('city')] ?? null),
            'barangay' => $this->normalizeNullableString($validated[$field('barangay')] ?? null),
            'timezone' => $this->normalizeNullableString($validated[$field('timezone')] ?? null),
            'venue_google_map_link' => $this->normalizeNullableString($validated[$field('venue_google_map_link')] ?? null),
            'thumbnail_path' => $this->normalizeNullableString($validated[$field('thumbnail_path')] ?? null),
            'event_type' => $this->normalizeNullableString($validated[$field('event_type')] ?? null),
            'division' => $this->normalizeNullableString($validated[$field('division')] ?? null),
            'surface' => $this->normalizeNullableString($validated[$field('surface')] ?? null),
            'round_robin_advancing_count' => isset($validated[$field('round_robin_advancing_count')]) && $validated[$field('round_robin_advancing_count')] !== null && $validated[$field('round_robin_advancing_count')] !== ''
                ? max(1, min(255, (int) $validated[$field('round_robin_advancing_count')]))
                : null,
            'info_items' => $this->compileInfoItems(
                $validated[$field('info_labels')] ?? [],
                $validated[$field('info_values')] ?? [],
            ),
            'organizer_items' => $this->compileInfoItems(
                $validated[$field('organizer_labels')] ?? [],
                $validated[$field('organizer_values')] ?? [],
            ),
            'link_items' => $this->compileLinkItems(
                $validated[$field('link_labels')] ?? [],
                $validated[$field('link_urls')] ?? [],
            ),
            'is_public' => $request->boolean($field('is_public')),
        ];
    }

    /**
     * Normalize optional text fields before persistence.
     */
    protected function normalizeNullableString(?string $value, bool $uppercase = false): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return $uppercase ? Str::upper($value) : $value;
    }

    /**
     * Convert a zero-based bracket index into A, B, ... Z, AA, AB, ...
     */
    protected function alphabeticalBracketLabel(int $index): string
    {
        $label = '';
        $index++;

        while ($index > 0) {
            $index--;
            $label = chr(65 + ($index % 26)).$label;
            $index = intdiv($index, 26);
        }

        return $label;
    }

    /**
     * Normalize bracket labels so short inputs like "A" become "Bracket A".
     */
    protected function normalizeBracketCode(?string $value): ?string
    {
        return BracketCodes::normalize($value);
    }

    /**
     * Build a stable key for a round-robin pair regardless of home/away side.
     */
    protected function roundRobinPairKey(int $leftRegistrationId, int $rightRegistrationId): string
    {
        $ids = [$leftRegistrationId, $rightRegistrationId];
        sort($ids);

        return implode('-', $ids);
    }

    /**
     * Stable key for a crossover pairing using team IDs (order-independent).
     */
    protected function crossoverTeamPairKey(int $teamIdA, int $teamIdB): string
    {
        $ids = [$teamIdA, $teamIdB];
        sort($ids);

        return $ids[0].'-'.$ids[1];
    }

    /**
     * Team pair key for an existing crossover match, or null if sides are incomplete.
     */
    protected function crossoverTeamPairKeyForMatch(TournamentMatch $match): ?string
    {
        $match->loadMissing([
            'homeRegistration:id,team_id',
            'awayRegistration:id,team_id',
        ]);

        $homeTeamId = $match->homeRegistration?->team_id;
        $awayTeamId = $match->awayRegistration?->team_id;

        if ($homeTeamId === null || $awayTeamId === null) {
            return null;
        }

        return $this->crossoverTeamPairKey((int) $homeTeamId, (int) $awayTeamId);
    }

    /**
     * Whether an identical crossover pairing already exists (either home/away orientation).
     * Uses team IDs so the same matchup cannot be re-added under swapped registration sides.
     */
    protected function crossoverDuplicatePairExists(int $tournamentId, int $homeRegistrationId, int $awayRegistrationId, ?int $exceptMatchId = null): bool
    {
        $homeTeamId = TournamentRegistration::query()->whereKey($homeRegistrationId)->value('team_id');
        $awayTeamId = TournamentRegistration::query()->whereKey($awayRegistrationId)->value('team_id');

        if ($homeTeamId === null || $awayTeamId === null) {
            return false;
        }

        $proposedKey = $this->crossoverTeamPairKey((int) $homeTeamId, (int) $awayTeamId);

        $query = TournamentMatch::query()
            ->where('tournament_id', $tournamentId)
            ->where('stage', 'crossover')
            ->whereNotNull('home_registration_id')
            ->whereNotNull('away_registration_id')
            ->with(['homeRegistration:id,team_id', 'awayRegistration:id,team_id']);

        if ($exceptMatchId !== null) {
            $query->whereKeyNot($exceptMatchId);
        }

        return $query->get()->contains(function (TournamentMatch $match) use ($proposedKey): bool {
            return $this->crossoverTeamPairKeyForMatch($match) === $proposedKey;
        });
    }

    /**
     * Auto-generated crossover shells can be safely rebuilt when rankings change.
     */
    protected function isRegenerableAutoCrossoverMatch(TournamentMatch $match): bool
    {
        if ($match->stage !== 'crossover' || $match->status !== 'scheduled') {
            return false;
        }

        if ($match->home_score !== null || $match->away_score !== null) {
            return false;
        }

        if ((int) ($match->score_logs_count ?? 0) > 0 || (int) ($match->player_stats_count ?? 0) > 0) {
            return false;
        }

        $notes = trim((string) ($match->notes ?? ''));

        if (str_contains($notes, TournamentMatch::CROSSOVER_AUTO_GENERATED_MARKER)) {
            return true;
        }

        // Legacy rows created before the notes marker existed (round label only).
        return $notes === ''
            && preg_match('/^Cross\\s+(?:·|Â·)\\s+.+\\s+vs\\s+.+\\s+#\\d+$/u', trim((string) $match->round_label)) === 1;
    }

    /**
     * Build the current pool placement snapshot from crossover results (optional {@see Tournament::$pooling_rules} overrides).
     *
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
    protected function buildCrossoverPoolingSnapshot(int $tournamentId): array
    {
        $tournament = Tournament::query()->find($tournamentId);

        $crossoverMatches = TournamentMatch::query()
            ->where('tournament_id', $tournamentId)
            ->where('stage', 'crossover')
            ->with(['homeRegistration.team', 'awayRegistration.team'])
            ->orderBy('match_number')
            ->orderBy('id')
            ->get();

        return TournamentPooling::buildPersistenceSnapshot($tournament, $crossoverMatches);
    }

    /**
     * Persist Pool A / Pool B assignments derived from crossover results.
     *
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
    protected function syncCrossoverPoolingAssignments(int $tournamentId): array
    {
        $snapshot = $this->buildCrossoverPoolingSnapshot($tournamentId);

        $tournament = Tournament::query()->find($tournamentId);

        if ($snapshot['participant_ids'] === []) {
            return $snapshot;
        }

        if ($tournament !== null && ($tournament->pooling_mode ?? TournamentPooling::POOLING_MODE_AUTO) === TournamentPooling::POOLING_MODE_MANUAL) {
            return $snapshot;
        }

        TournamentRegistration::query()
            ->where('tournament_id', $tournamentId)
            ->whereIn('id', $snapshot['participant_ids'])
            ->update(['pool_name' => null]);

        foreach ($snapshot['assignments'] as $registrationId => $poolName) {
            TournamentRegistration::query()
                ->where('tournament_id', $tournamentId)
                ->whereKey($registrationId)
                ->update(['pool_name' => $poolName]);
        }

        return $snapshot;
    }

    /**
     * JSON pooling board for admins (crossover-derived assignments).
     */
    public function poolingBoard(Request $request, Tournament $tournament): JsonResponse
    {
        $crossoverMatches = TournamentMatch::query()
            ->where('tournament_id', $tournament->id)
            ->where('stage', 'crossover')
            ->with(['homeRegistration.team', 'awayRegistration.team'])
            ->orderBy('match_number')
            ->orderBy('id')
            ->get();

        $built = TournamentPooling::buildPoolingAssignments($tournament, $crossoverMatches);

        return response()->json(TournamentPooling::toApiPayload($built));
    }

    /**
     * Apply automatic diagram pooling and persist registration pool tags.
     */
    public function applyAutomaticPooling(Request $request, Tournament $tournament): RedirectResponse
    {
        $tournament->update([
            'pooling_mode' => TournamentPooling::POOLING_MODE_AUTO,
            'pooling_manual_slots' => null,
        ]);

        $this->syncCrossoverPoolingAssignments($tournament->id);

        return redirect()
            ->route('admin.tournaments.index', [
                'tournament' => $tournament->id,
                'tab' => $this->normalizeTournamentTab($request->string('redirect_tab')->toString()) ?? 'pooling',
            ])
            ->with('status', 'pooling-auto-applied');
    }

    /**
     * Save manual Pool A / Pool B assignments (validated partition of crossover teams).
     */
    public function saveManualPooling(Request $request, Tournament $tournament): RedirectResponse
    {
        $crossoverMatches = TournamentMatch::query()
            ->where('tournament_id', $tournament->id)
            ->where('stage', 'crossover')
            ->with(['homeRegistration.team', 'awayRegistration.team'])
            ->orderBy('match_number')
            ->orderBy('id')
            ->get();

        if (! TournamentPooling::isCrossoverScheduleFullyResolved($crossoverMatches)) {
            return redirect()
                ->route('admin.tournaments.index', [
                    'tournament' => $tournament->id,
                    'tab' => $this->normalizeTournamentTab($request->string('redirect_tab')->toString()) ?? 'pooling',
                ])
                ->withErrors([
                    'pooling' => __('Finish every crossover game with a decisive score before saving manual pooling.'),
                ]);
        }

        try {
            TournamentPooling::validateManualPoolSelections(
                $tournament,
                $request->input('pool_a_registration_ids', []),
                $request->input('pool_b_registration_ids', []),
                $crossoverMatches,
            );
        } catch (ValidationException $exception) {
            return redirect()
                ->route('admin.tournaments.index', [
                    'tournament' => $tournament->id,
                    'tab' => $this->normalizeTournamentTab($request->string('redirect_tab')->toString()) ?? 'pooling',
                ])
                ->withErrors($exception->errors());
        }

        $poolAIds = TournamentPooling::dedupeRegistrationIdsPreserveOrder($request->input('pool_a_registration_ids', []));
        $poolBIds = TournamentPooling::dedupeRegistrationIdsPreserveOrder($request->input('pool_b_registration_ids', []));

        $participantIds = TournamentPooling::qualifiedCrossoverRegistrationIds($crossoverMatches);

        DB::transaction(function () use ($tournament, $poolAIds, $poolBIds, $participantIds): void {
            $tournament->update([
                'pooling_mode' => TournamentPooling::POOLING_MODE_MANUAL,
                'pooling_manual_slots' => [
                    'pool_a' => $poolAIds,
                    'pool_b' => $poolBIds,
                ],
            ]);

            TournamentRegistration::query()
                ->where('tournament_id', $tournament->id)
                ->whereIn('id', $participantIds)
                ->update(['pool_name' => null]);

            foreach ($poolAIds as $registrationId) {
                TournamentRegistration::query()
                    ->where('tournament_id', $tournament->id)
                    ->whereKey($registrationId)
                    ->update(['pool_name' => 'POOL A']);
            }

            foreach ($poolBIds as $registrationId) {
                TournamentRegistration::query()
                    ->where('tournament_id', $tournament->id)
                    ->whereKey($registrationId)
                    ->update(['pool_name' => 'POOL B']);
            }
        });

        return redirect()
            ->route('admin.tournaments.index', [
                'tournament' => $tournament->id,
                'tab' => $this->normalizeTournamentTab($request->string('redirect_tab')->toString()) ?? 'pooling',
            ])
            ->with('status', 'pooling-manual-saved');
    }

    /**
     * Clear saved pool assignments for crossover participants: manual snapshot, pooling mode, and POOL A/B tags.
     * Does not delete crossover matches or field assignments.
     */
    public function clearPoolingAssignments(Request $request, Tournament $tournament): RedirectResponse
    {
        $crossoverMatches = TournamentMatch::query()
            ->where('tournament_id', $tournament->id)
            ->where('stage', 'crossover')
            ->orderBy('match_number')
            ->orderBy('id')
            ->get();

        $participantIds = TournamentPooling::qualifiedCrossoverRegistrationIds($crossoverMatches);

        DB::transaction(function () use ($tournament, $participantIds): void {
            $tournament->update([
                'pooling_mode' => TournamentPooling::POOLING_MODE_AUTO,
                'pooling_manual_slots' => null,
            ]);

            if ($participantIds !== []) {
                TournamentRegistration::query()
                    ->where('tournament_id', $tournament->id)
                    ->whereIn('id', $participantIds)
                    ->update(['pool_name' => null]);
            }
        });

        return redirect()
            ->route('admin.tournaments.index', [
                'tournament' => $tournament->id,
                'tab' => $this->normalizeTournamentTab($request->string('redirect_tab')->toString()) ?? 'pooling',
            ])
            ->with('status', 'pooling-assignments-cleared');
    }

    /**
     * Remove playing-field assignments from crossover games that are still scheduled with no scoring activity.
     * Matches with live/completed status or score logs are left unchanged.
     */
    public function clearCrossoverPitchAssignments(Request $request, Tournament $tournament): RedirectResponse
    {
        $cleared = TournamentMatch::query()
            ->where('tournament_id', $tournament->id)
            ->where('stage', 'crossover')
            ->whereNotNull('pitch_id')
            ->where('status', 'scheduled')
            ->whereDoesntHave('scoreLogs')
            ->update([
                'pitch_id' => null,
                'pitch_assigned_by' => null,
            ]);

        if ($cleared === 0) {
            return redirect()
                ->route('admin.tournaments.index', [
                    'tournament' => $tournament->id,
                    'tab' => $this->normalizeTournamentTab($request->string('redirect_tab')->toString()) ?? 'crossover',
                ])
                ->withErrors([
                    'crossover' => __('No eligible crossover games were assigned to a field. Only scheduled games with no scoring activity can be cleared.'),
                ]);
        }

        return redirect()
            ->route('admin.tournaments.index', [
                'tournament' => $tournament->id,
                'tab' => $this->normalizeTournamentTab($request->string('redirect_tab')->toString()) ?? 'crossover',
            ])
            ->with('status', 'crossover-pitch-assignments-cleared')
            ->with('crossover_pitch_assignments_cleared_count', $cleared);
    }

    /**
     * Build the response after auto-seeding, with JSON support for in-place UI updates.
     */
    protected function buildSeedRegistrationsResponse(
        Request $request,
        int $tournamentId,
        string $status,
    ): RedirectResponse|JsonResponse {
        if (! $request->expectsJson()) {
            return redirect()
                ->route(
                    $this->resolveTournamentRedirectRoute($request),
                    $this->resolveTournamentRedirectParameters($request, $tournamentId),
                )
                ->with('status', $status);
        }

        $viewData = $this->buildSeedingOverviewViewData(
            Tournament::query()->findOrFail($tournamentId),
        );

        $message = $this->resolveSeedRegistrationsStatusMessage($status, $viewData['teamCount']);
        $viewData['asyncStatusMessage'] = $message;

        return response()->json([
            'status' => $status,
            'message' => $message,
            'overview_html' => view('admin.tournaments.partials.seeding-overview', $viewData)->render(),
        ]);
    }

    /**
     * Build the seeding overview data shared by the overview page and async refreshes.
     *
     * @return array<string, mixed>
     */
    protected function buildSeedingOverviewViewData(
        Tournament $tournament,
        ?string $seedOrderBracketModalCode = null,
        ?string $asyncStatusMessage = null,
    ): array {
        $tournament->load([
            'registrations' => fn ($query) => $query
                ->with('team')
                ->orderByRaw('case when seed_number is null then 1 else 0 end')
                ->orderBy('seed_number')
                ->orderBy('id'),
        ]);

        $teamCount = $tournament->registrations->count();
        $seededRegistrations = collect($tournament->registrations)
            ->sort(fn ($left, $right) => [
                $left->seed_number ?? PHP_INT_MAX,
                $left->team?->name ?? '',
                $left->id,
            ] <=> [
                $right->seed_number ?? PHP_INT_MAX,
                $right->team?->name ?? '',
                $right->id,
            ])
            ->values();

        $seededBracketGroups = $this->buildSeededBracketGroups($seededRegistrations);

        return [
            'selectedTournament' => $tournament,
            'teamCount' => $teamCount,
            'tournamentSeedOrderRegistrations' => $seededRegistrations,
            'seededBracketGroups' => $seededBracketGroups,
            'unassignedSeededCount' => $seededRegistrations
                ->filter(fn ($registration): bool => blank($registration->bracket_code))
                ->count(),
            'minimumBracketTeamCount' => self::MINIMUM_BRACKET_TEAM_COUNT,
            'bracketTeamLimit' => self::BRACKET_TEAM_LIMIT,
            'seedOrderBracketModalCode' => $seedOrderBracketModalCode,
            'asyncStatusMessage' => $asyncStatusMessage,
        ];
    }

    /**
     * @param  Collection<int, TournamentRegistration>  $seededRegistrations
     * @return Collection<int, array{code: string, count: int, registrations: Collection<int, TournamentRegistration>, seed_range: string|null}>
     */
    protected function buildSeededBracketGroups(Collection $seededRegistrations): Collection
    {
        return $seededRegistrations
            ->filter(fn ($registration): bool => filled($registration->bracket_code))
            ->groupBy('bracket_code')
            ->sortKeys()
            ->map(function (Collection $registrations, string $code): array {
                $seedNumbers = $registrations
                    ->pluck('seed_number')
                    ->filter(fn ($seed): bool => $seed !== null)
                    ->sort()
                    ->values();

                $firstSeed = $seedNumbers->first();
                $lastSeed = $seedNumbers->last();

                return [
                    'code' => $code,
                    'count' => $registrations->count(),
                    'registrations' => $registrations
                        ->sort(fn ($left, $right) => [
                            $left->seed_number ?? PHP_INT_MAX,
                            $left->id,
                        ] <=> [
                            $right->seed_number ?? PHP_INT_MAX,
                            $right->id,
                        ])
                        ->values(),
                    'seed_range' => $seedNumbers->isEmpty()
                        ? null
                        : ($firstSeed === $lastSeed ? (string) $firstSeed : "{$firstSeed}-{$lastSeed}"),
                ];
            })
            ->values();
    }

    protected function resolveSeedRegistrationsStatusMessage(string $status, int $teamCount): string
    {
        return match ($status) {
            'registrations-seeded' => $teamCount >= self::MINIMUM_BRACKET_TEAM_COUNT
                ? 'Teams seeded successfully. Brackets now use '.self::BRACKET_TEAM_LIMIT.' teams each, and extra teams remain unassigned.'
                : 'Teams seeded successfully. Brackets start only at '.self::MINIMUM_BRACKET_TEAM_COUNT.' total teams, so all teams remain unassigned for now.',
            'registrations-seeding-skipped' => 'No registered teams were available for seeding.',
            default => 'Saved.',
        };
    }

    /**
     * Normalize dynamic public info rows before persistence.
     *
     * @param  array<int, string|null>  $labels
     * @param  array<int, string|null>  $values
     * @return list<array{label: string, value: string}>
     */
    protected function compileInfoItems(array $labels, array $values): array
    {
        $totalRows = max(count($labels), count($values));

        return collect(range(0, max($totalRows - 1, -1)))
            ->map(function (int $index) use ($labels, $values): ?array {
                $label = $this->normalizeNullableString($labels[$index] ?? null);
                $value = $this->normalizeNullableString($values[$index] ?? null);

                if (! $label || ! $value) {
                    return null;
                }

                return [
                    'label' => $label,
                    'value' => $value,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Normalize dynamic public link rows before persistence.
     *
     * @param  array<int, string|null>  $labels
     * @param  array<int, string|null>  $urls
     * @return list<array{label: string, href: string}>
     */
    protected function compileLinkItems(array $labels, array $urls): array
    {
        $totalRows = max(count($labels), count($urls));

        return collect(range(0, max($totalRows - 1, -1)))
            ->map(function (int $index) use ($labels, $urls): ?array {
                $label = $this->normalizeNullableString($labels[$index] ?? null);
                $href = $this->normalizeNullableString($urls[$index] ?? null);

                if (! $label || ! $href) {
                    return null;
                }

                return [
                    'label' => $label,
                    'href' => $href,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Confirm the requested match belongs to the selected tournament.
     */
    protected function ensureTournamentOwnsMatch(Tournament $tournament, TournamentMatch $match): void
    {
        abort_unless($match->tournament_id === $tournament->id, 404);
    }

    /**
     * Load the relations needed by the admin live-scoring console.
     */
    protected function loadMatchScoringContext(TournamentMatch $match): TournamentMatch
    {
        return $match->load([
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
            'scoreLogs' => fn ($query) => $query
                ->with(['registration.team', 'scorer', 'assister'])
                ->orderBy('sequence')
                ->orderBy('id'),
            'playerStats' => fn ($query) => $query
                ->with('teamMember')
                ->orderByDesc('goals')
                ->orderByDesc('assists')
                ->orderByDesc('blocks')
                ->orderBy('id'),
            'spiritScores',
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    protected function upsertMatchSpiritScoreRecord(
        Tournament $tournament,
        TournamentMatch $match,
        Team $scoredTeam,
        Team $scoringTeam,
        array $input,
        ?TeamMember $spiritCaptain,
    ): ?MatchSpiritScore {
        $criteriaKeys = [
            'knowledge_rules_score',
            'fouls_body_contact_score',
            'fair_mindedness_score',
            'positive_attitude_score',
            'communication_respect_score',
        ];

        $criteria = [];
        foreach ($criteriaKeys as $key) {
            if (! array_key_exists($key, $input)) {
                throw new InvalidArgumentException("Missing spirit criterion: {$key}");
            }

            $raw = $input[$key];
            if ($raw === '' || $raw === null) {
                $criteria[$key] = null;
            } else {
                $criteria[$key] = (int) $raw;
            }
        }

        $hasAny = collect($criteria)->contains(fn ($value) => $value !== null);

        $query = MatchSpiritScore::query()
            ->where('match_id', $match->id)
            ->where('scored_team_id', $scoredTeam->id);

        if (! $hasAny) {
            $query->delete();

            return null;
        }

        $total = array_sum(array_values(array_filter($criteria, fn ($value) => $value !== null)));

        $notes = $this->normalizeNullableString($input['notes'] ?? null);

        return MatchSpiritScore::query()->updateOrCreate(
            [
                'match_id' => $match->id,
                'scored_team_id' => $scoredTeam->id,
            ],
            [
                'tournament_id' => $tournament->id,
                'scoring_team_id' => $scoringTeam->id,
                'spirit_captain_id' => $spiritCaptain?->id,
                ...$criteria,
                'total_score' => $total,
                'notes' => $notes,
            ],
        );
    }

    protected function matchSpiritScoreRowHasData(MatchSpiritScore $row): bool
    {
        return $row->knowledge_rules_score !== null
            || $row->fouls_body_contact_score !== null
            || $row->fair_mindedness_score !== null
            || $row->positive_attitude_score !== null
            || $row->communication_respect_score !== null;
    }

    protected function spiritCaptainMember(?Team $team): ?TeamMember
    {
        return $team?->members?->firstWhere('role', 'spirit_captain');
    }

    /**
     * Build a roster directory keyed by registration id for Alpine-driven scorer selectors.
     *
     * @return array<int, array<int, array{id: int, name: string, role: string}>>
     */
    protected function buildMatchMemberDirectory(TournamentMatch $match): array
    {
        return collect([
            $match->homeRegistration,
            $match->awayRegistration,
        ])->filter()
            ->mapWithKeys(function (TournamentRegistration $registration): array {
                return [
                    $registration->id => $registration->team?->members
                        ?->map(fn (TeamMember $member): array => [
                            'id' => $member->id,
                            'name' => $member->name,
                            'role' => (string) $member->role,
                        ])
                        ->values()
                        ->all() ?? [],
                ];
            })
            ->all();
    }

    /**
     * Determine whether the current match still only has a manual scoreline.
     */
    protected function matchHasManualScorelineWithoutLog(TournamentMatch $match): bool
    {
        return $match->scoreLogs()->doesntExist()
            && ! is_null($match->home_score)
            && ! is_null($match->away_score);
    }

    /**
     * Check if a roster member belongs to the team registered in the scoring play.
     */
    protected function registrationOwnsMember(TournamentRegistration $registration, int $memberId): bool
    {
        return TeamMember::query()
            ->whereKey($memberId)
            ->where('team_id', $registration->team_id)
            ->exists();
    }

    /**
     * Recalculate the ordered scoring timeline and the match's visible scoreline.
     */
    protected function syncMatchScoreTimeline(TournamentMatch $match): void
    {
        $logs = MatchScoreLog::query()
            ->where('match_id', $match->id)
            ->orderBy('sequence')
            ->orderBy('id')
            ->get();

        if ($logs->isEmpty()) {
            $match->forceFill([
                'home_score' => $match->status === 'scheduled' ? null : 0,
                'away_score' => $match->status === 'scheduled' ? null : 0,
            ])->save();

            return;
        }

        $homeScore = 0;
        $awayScore = 0;

        foreach ($logs as $index => $log) {
            if ($log->team_registration_id === $match->home_registration_id) {
                $homeScore++;
            } elseif ($log->team_registration_id === $match->away_registration_id) {
                $awayScore++;
            }

            $expectedSequence = $index + 1;

            if (
                $log->sequence !== $expectedSequence
                || $log->home_score !== $homeScore
                || $log->away_score !== $awayScore
            ) {
                $log->forceFill([
                    'sequence' => $expectedSequence,
                    'home_score' => $homeScore,
                    'away_score' => $awayScore,
                ])->save();
            }
        }

        $match->forceFill([
            'home_score' => $homeScore,
            'away_score' => $awayScore,
        ])->save();
    }

    /**
     * Rebuild goal and assist totals from the scoring timeline while preserving block counts.
     */
    protected function syncMatchPlayerStatsFromScoreLogs(TournamentMatch $match): void
    {
        $scoreLogs = MatchScoreLog::query()
            ->where('match_id', $match->id)
            ->get();

        $existingStats = MatchPlayerStat::query()
            ->where('match_id', $match->id)
            ->get()
            ->keyBy('team_member_id');

        $goalsByMember = $scoreLogs
            ->whereNotNull('team_member_id')
            ->groupBy('team_member_id')
            ->map(fn ($logs) => $logs->count());

        $assistsByMember = $scoreLogs
            ->whereNotNull('assist_team_member_id')
            ->groupBy('assist_team_member_id')
            ->map(fn ($logs) => $logs->count());

        $memberIds = $existingStats->keys()
            ->merge($goalsByMember->keys())
            ->merge($assistsByMember->keys())
            ->unique()
            ->filter();

        foreach ($memberIds as $memberId) {
            $existingStat = $existingStats->get($memberId);
            $goals = (int) ($goalsByMember->get($memberId) ?? 0);
            $assists = (int) ($assistsByMember->get($memberId) ?? 0);
            $blocks = (int) ($existingStat?->blocks ?? 0);

            if ($goals === 0 && $assists === 0 && $blocks === 0) {
                $existingStat?->delete();

                continue;
            }

            MatchPlayerStat::query()->updateOrCreate(
                [
                    'match_id' => $match->id,
                    'team_member_id' => $memberId,
                ],
                [
                    'goals' => $goals,
                    'assists' => $assists,
                    'blocks' => $blocks,
                ],
            );
        }
    }

    /**
     * Shared tournament list data for admin setup and directory pages.
     *
     * @return array<string, mixed>
     */
    protected function tournamentListViewData(Request $request): array
    {
        $search = trim($request->string('search')->toString());

        $baseQuery = Tournament::query();

        $tournaments = $this->applyTournamentListFilters(
            query: clone $baseQuery,
            search: $search,
        )
            ->with([
                'registrations' => fn ($query) => $query
                    ->select(['id', 'tournament_id', 'team_id', 'status', 'seed_number'])
                    ->with('team:id,name')
                    ->orderByRaw('case when seed_number is null then 1 else 0 end')
                    ->orderBy('seed_number')
                    ->orderBy('id'),
            ])
            ->withCount(['pitches', 'registrations', 'matches', 'crewMembers'])
            ->latest()
            ->get();

        return [
            'tournaments' => $tournaments,
            'listFilters' => [
                'search' => $search,
            ],
            'tournamentListQueryParams' => collect([
                'search' => $search !== '' ? $search : null,
            ])->filter()->all(),
            'totalTournamentCount' => (clone $baseQuery)->count(),
        ];
    }

    /**
     * Apply search and filter constraints to the admin tournament directory.
     */
    protected function applyTournamentListFilters(Builder $query, string $search): Builder
    {
        return $query
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $like = '%'.$search.'%';

                    $searchQuery
                        ->where('name', 'like', $like)
                        ->orWhere('venue', 'like', $like)
                        ->orWhere('country_name', 'like', $like)
                        ->orWhere('province', 'like', $like)
                        ->orWhere('city', 'like', $like)
                        ->orWhere('barangay', 'like', $like)
                        ->orWhere('event_type', 'like', $like)
                        ->orWhere('division', 'like', $like)
                        ->orWhere('surface', 'like', $like)
                        ->orWhere('status', 'like', $like);
                });
            });
    }

    /**
     * Resolve the route used after tournament profile updates.
     */
    protected function resolveTournamentRedirectRoute(Request $request): string
    {
        $route = $request->string('redirect_route')->toString();

        return in_array($route, ['admin.tournaments.index', 'admin.tournaments.list'], true)
            ? $route
            : 'admin.tournaments.index';
    }

    /**
     * Resolve redirect parameters for the admin tournament flows.
     *
     * @return array<string, mixed>
     */
    protected function resolveTournamentRedirectParameters(Request $request, Tournament|int $tournament): array
    {
        $tournamentId = $tournament instanceof Tournament
            ? $tournament->id
            : $tournament;

        return collect([
            'tournament' => $tournamentId,
            'search' => $this->normalizeNullableString($request->string('redirect_search')->toString()),
            'tab' => $this->resolveTournamentRedirectTab($request),
        ])->filter(fn (mixed $value): bool => $value !== null && $value !== '')->all();
    }

    /**
     * Resolve the setup tab used after admin tournament actions.
     */
    protected function resolveTournamentRedirectTab(Request $request): ?string
    {
        return $this->normalizeTournamentTab($request->string('redirect_tab')->toString());
    }

    protected function normalizeTournamentTab(?string $tab): ?string
    {
        $tab = $this->normalizeNullableString($tab);
        $tab = $tab !== null ? trim($tab, "\"' ") : null;

        if ($tab === 'basic-info') {
            return 'round-robin';
        }

        if ($tab === 'teams') {
            return 'bracket-ranking';
        }

        if ($tab === 'pitches') {
            return 'crossover';
        }

        if ($tab === 'format') {
            return 'pooling';
        }

        if ($tab === 'matches') {
            return 'quarter-final';
        }

        if ($tab === 'quater-final') {
            return 'quarter-final';
        }

        // Legacy admin bookmarks for the removed Crew tab → Semi Finals.
        if ($tab === 'crew' || $tab === 'event-crew') {
            return 'semi-finals';
        }

        // Legacy Championship tab query param.
        if ($tab === 'publish') {
            return 'championship';
        }

        return in_array($tab, ['games-dashboard', 'overview', 'round-robin', 'team-standing', 'bracket-ranking', 'crossover', 'pooling', 'quarter-final', 'semi-finals', 'championship', 'report'], true)
            ? $tab
            : null;
    }
}
