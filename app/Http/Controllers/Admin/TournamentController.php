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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TournamentController extends Controller
{
    /**
     * Show the admin tournament setup page.
     */
    public function index(Request $request): View
    {
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
     * Show the dedicated live-scoring console for a tournament match.
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
     * Update live-scoring match status and operator notes.
     */
    public function updateMatchScoring(Request $request, Tournament $tournament, TournamentMatch $match): RedirectResponse
    {
        $this->ensureTournamentOwnsMatch($tournament, $match);

        $validator = Validator::make($request->all(), [
            'status' => ['required', Rule::in(['scheduled', 'live', 'completed'])],
            'notes' => ['nullable', 'string'],
        ]);

        $validator->after(function ($validator) use ($request, $match): void {
            $hasScoreLogs = $match->scoreLogs()->exists();
            $hasPublishedScoreline = $hasScoreLogs
                || (! is_null($match->home_score) && ! is_null($match->away_score));
            $status = $request->string('status')->toString();

            if ($status === 'scheduled' && $hasScoreLogs) {
                $validator->errors()->add('status', 'Clear the scoring timeline before moving the match back to scheduled.');
            }

            if ($status === 'completed' && ! $hasPublishedScoreline) {
                $validator->errors()->add('status', 'Completed matches require a scoreline or at least one scoring play.');
            }
        });

        $validated = $validator->validate();

        $match->update([
            'status' => $validated['status'],
            'notes' => $this->normalizeNullableString($validated['notes'] ?? null),
        ]);

        if ($match->scoreLogs()->exists()) {
            $this->syncMatchScoreTimeline($match->fresh());
        }

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
        $tab = $request->string('redirect_tab')->toString();

        return in_array($tab, ['overview', 'basic-info', 'teams', 'pitches', 'format', 'matches', 'crew', 'publish'], true)
            ? $tab
            : null;
    }
}
