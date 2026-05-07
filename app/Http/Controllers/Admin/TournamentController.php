<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MatchPlayerStat;
use App\Models\MatchScoreLog;
use App\Models\Pitch;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Tournament;
use App\Models\TournamentCrew;
use App\Models\TournamentMatch;
use App\Models\TournamentRegistration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

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

        $selectedTournament = $request->filled('tournament')
            ? Tournament::query()->find($request->integer('tournament'))
            : null;

        $selectedTournament?->load([
            'pitches',
            'registrations' => fn ($query) => $query
                ->with('team')
                ->orderByRaw('case when seed_number is null then 1 else 0 end')
                ->orderBy('seed_number')
                ->orderBy('id'),
            'matches' => fn ($query) => $query
                ->with(['pitch', 'homeRegistration.team', 'awayRegistration.team'])
                ->orderByRaw('case when scheduled_at is null then 1 else 0 end')
                ->orderBy('scheduled_at')
                ->orderBy('match_number')
                ->orderBy('id'),
            'crewMembers' => fn ($query) => $query
                ->orderBy('category')
                ->orderBy('sort_order')
                ->orderBy('name'),
        ]);

        $availableTeams = Team::query()
            ->withCount('members')
            ->orderBy('name')
            ->get();

        return view('admin.tournaments.index', [
            'selectedTournament' => $selectedTournament,
            'availableTeams' => $availableTeams,
            'totalTournamentCount' => Tournament::query()->count(),
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
                'tab' => $this->resolveTournamentRedirectTab($request) ?? 'overview',
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
        ]);
    }

    /**
     * Update manual match status, scoreline, and operator notes.
     */
    public function updateMatchScoring(Request $request, Tournament $tournament, TournamentMatch $match): RedirectResponse
    {
        $this->ensureTournamentOwnsMatch($tournament, $match);

        $validator = Validator::make($request->all(), [
            'status' => ['required', Rule::in(['scheduled', 'live', 'completed'])],
            'home_score' => ['nullable', 'integer', 'min:0', 'max:999'],
            'away_score' => ['nullable', 'integer', 'min:0', 'max:999'],
            'notes' => ['nullable', 'string'],
        ]);

        $validator->after(function ($validator) use ($request, $match): void {
            $hasScoreLogs = $match->scoreLogs()->exists();
            $status = $request->string('status')->toString();
            $hasHomeScore = $request->filled('home_score');
            $hasAwayScore = $request->filled('away_score');

            if ($hasHomeScore xor $hasAwayScore) {
                $validator->errors()->add('home_score', 'Both scores are required when recording a result.');
                $validator->errors()->add('away_score', 'Both scores are required when recording a result.');
            }

            if ($status === 'scheduled' && $hasScoreLogs) {
                $validator->errors()->add('status', 'Clear the scoring timeline before moving the match back to scheduled.');
            }

        });

        $validated = $validator->validate();
        $scoresVisible = $validated['status'] === 'completed'
            && array_key_exists('home_score', $validated)
            && array_key_exists('away_score', $validated)
            && $validated['home_score'] !== null
            && $validated['away_score'] !== null;
        $matchUpdates = [
            'status' => $validated['status'],
            'notes' => $this->normalizeNullableString($validated['notes'] ?? null),
        ];

        if ($scoresVisible) {
            $matchUpdates['home_score'] = $validated['home_score'];
            $matchUpdates['away_score'] = $validated['away_score'];
        }

        $match->update($matchUpdates);

        return redirect()
            ->route('admin.tournaments.matches.scoring', [
                'tournament' => $tournament,
                'match' => $match,
            ])
            ->with('status', 'match-scoring-updated');
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

            if ($match->status === 'scheduled') {
                TournamentMatch::query()
                    ->whereKey($match->id)
                    ->update(['status' => 'live']);

                $match->status = 'live';
            }

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
            'Scores can only be entered after the match is completed.',
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
        ]);

        Pitch::create([
            ...$validated,
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
        ]);

        $pitch->update([
            'name' => $validated['name'],
            'location' => $this->normalizeNullableString($validated['location'] ?? null),
            'sort_order' => $validated['sort_order'],
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
                    'bracket_rank' => null,
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
     * Legacy endpoint retained so stale clients cannot auto-generate round robin matches.
     */
    public function generateRoundRobinMatches(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tournament_id' => ['required', 'integer', 'exists:tournaments,id'],
        ]);

        return redirect()
            ->route(
                $this->resolveTournamentRedirectRoute($request),
                $this->resolveTournamentRedirectParameters($request, $validated['tournament_id']),
            )
            ->withErrors([
                'round_robin' => 'Round robin matches must be created manually. Use Add Match Manually to build the schedule.',
            ]);
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
        });

        $validated = $validator->validate();

        $scoresVisible = $validated['status'] !== 'scheduled'
            && array_key_exists('home_score', $validated)
            && array_key_exists('away_score', $validated)
            && $validated['home_score'] !== null
            && $validated['away_score'] !== null;

        TournamentMatch::create([
            'tournament_id' => $validated['tournament_id'],
            'pitch_id' => $validated['pitch_id'] ?? null,
            'home_registration_id' => $validated['home_registration_id'],
            'away_registration_id' => $validated['away_registration_id'],
            'stage' => $this->normalizeNullableString($validated['stage']) ?? 'group',
            'round_label' => $this->normalizeNullableString($validated['round_label'] ?? null),
            'match_number' => $validated['match_number'] ?? null,
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

        $validator->after(function ($validator) use ($request, $tournamentId): void {
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
        });

        $validated = $validator->validate();

        $match->update([
            'pitch_id' => $validated['pitch_id'] ?? null,
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
     * Delete a tournament match. Cascades to score logs and player stats
     * via the underlying schema relationships.
     */
    public function destroyMatch(Request $request, TournamentMatch $match): RedirectResponse
    {
        $tournamentId = $match->tournament_id;

        DB::transaction(function () use ($match): void {
            $match->delete();
        });

        return redirect()
            ->route(
                $this->resolveTournamentRedirectRoute($request),
                $this->resolveTournamentRedirectParameters($request, $tournamentId),
            )
            ->with('status', 'match-deleted');
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
        $value = $this->normalizeNullableString($value);

        if (! $value) {
            return null;
        }

        if (preg_match('/^[A-Za-z]+$/', $value) === 1) {
            return 'Bracket '.Str::upper($value);
        }

        if (str_starts_with(Str::lower($value), 'bracket ')) {
            $suffix = trim(Str::after($value, ' '));

            return 'Bracket '.Str::upper($suffix);
        }

        return Str::of($value)->squish()->title()->toString();
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
                    'registrations' => $registrations->values(),
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
        ]);
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

        if ($tab === 'basic-info') {
            return 'round-robin';
        }

        return in_array($tab, ['overview', 'round-robin', 'teams', 'pitches', 'format', 'matches', 'crew', 'publish'], true)
            ? $tab
            : null;
    }
}
