<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Support\ManualRoundRobinSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoundRobinScheduleController extends Controller
{
    public const MANUAL_SLOT_NOTES_MARKER = '[[manual-rr-slot]]';

    public function store(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->authorizeSmallTournament($tournament);

        $tournamentId = (int) $tournament->id;
        $p = 'rr_slot';

        $validated = $request->validate([
            'day' => ['required', 'in:1,2'],
            "{$p}.round" => ['required', 'integer', 'min:1'],
            "{$p}.start_time" => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            "{$p}.end_time" => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            "{$p}.pitch1_pitch_id" => ['required', 'integer', Rule::exists('pitches', 'id')->where('tournament_id', $tournamentId)],
            "{$p}.pitch2_pitch_id" => ['required', 'integer', Rule::exists('pitches', 'id')->where('tournament_id', $tournamentId)],
            "{$p}.match1_match_number" => ['required', 'integer', 'min:1', Rule::unique('matches', 'match_number')->where('tournament_id', $tournamentId)],
            "{$p}.match2_match_number" => ['required', 'integer', 'min:1', Rule::unique('matches', 'match_number')->where('tournament_id', $tournamentId)],
            "{$p}.match1_home_registration_id" => ['required', 'integer', 'different:'.$p.'.match1_away_registration_id', Rule::exists('tournament_registrations', 'id')->where('tournament_id', $tournamentId)],
            "{$p}.match1_away_registration_id" => ['required', 'integer', Rule::exists('tournament_registrations', 'id')->where('tournament_id', $tournamentId)],
            "{$p}.match2_home_registration_id" => ['required', 'integer', 'different:'.$p.'.match2_away_registration_id', Rule::exists('tournament_registrations', 'id')->where('tournament_id', $tournamentId)],
            "{$p}.match2_away_registration_id" => ['required', 'integer', Rule::exists('tournament_registrations', 'id')->where('tournament_id', $tournamentId)],
            "{$p}.status" => ['nullable', 'string', Rule::in(['upcoming', 'live', 'completed'])],
            'redirect_route' => ['nullable', 'string', 'max:64'],
            'redirect_tab' => ['nullable', 'string', 'max:64'],
            'redirect_search' => ['nullable', 'string', 'max:255'],
        ]);

        $slot = $validated[$p];
        $day = (string) $validated['day'];

        if ((int) $slot['match1_match_number'] === (int) $slot['match2_match_number']) {
            throw ValidationException::withMessages([
                "{$p}.match2_match_number" => __('Game A and Game B must use different match numbers.'),
            ]);
        }

        $this->assertSlotTeamUniqueness($p, $slot);
        $this->assertNoDuplicatePairing($tournament, (int) $slot['match1_home_registration_id'], (int) $slot['match1_away_registration_id'], null, null, "{$p}.match1_home_registration_id");
        $this->assertNoDuplicatePairing($tournament, (int) $slot['match2_home_registration_id'], (int) $slot['match2_away_registration_id'], null, null, "{$p}.match2_home_registration_id");

        $tz = ManualRoundRobinSchedule::tournamentTimezone($tournament);
        $dayLocal = $day === '2'
            ? ManualRoundRobinSchedule::roundRobinDayTwoLocal($tournament)
            : ManualRoundRobinSchedule::roundRobinDayOneLocal($tournament);
        $dateIso = $dayLocal->format('Y-m-d');

        $start = CarbonImmutable::parse($dateIso.' '.$slot['start_time'].':00', $tz);
        $end = CarbonImmutable::parse($dateIso.' '.$slot['end_time'].':00', $tz);

        if (! $end->greaterThan($start)) {
            throw ValidationException::withMessages([
                "{$p}.end_time" => __('End time must be after start time.'),
            ]);
        }

        $matchStatus = ManualRoundRobinSchedule::mapRowUiStatusToMatchStatus((string) ($slot['status'] ?? 'upcoming'));
        $roundLabel = __('Round :round', ['round' => (int) $slot['round']]);
        $rowUlid = (string) Str::ulid();

        DB::transaction(function () use ($tournament, $slot, $start, $end, $matchStatus, $roundLabel, $rowUlid): void {
            foreach (
                [
                    [
                        'num' => (int) $slot['match1_match_number'],
                        'home' => (int) $slot['match1_home_registration_id'],
                        'away' => (int) $slot['match1_away_registration_id'],
                        'pitch' => (int) $slot['pitch1_pitch_id'],
                    ],
                    [
                        'num' => (int) $slot['match2_match_number'],
                        'home' => (int) $slot['match2_home_registration_id'],
                        'away' => (int) $slot['match2_away_registration_id'],
                        'pitch' => (int) $slot['pitch2_pitch_id'],
                    ],
                ] as $game
            ) {
                TournamentMatch::query()->create([
                    'tournament_id' => $tournament->id,
                    'pitch_id' => $game['pitch'],
                    'pitch_assigned_by' => null,
                    'home_registration_id' => $game['home'],
                    'away_registration_id' => $game['away'],
                    'stage' => 'round_robin',
                    'round_label' => $roundLabel,
                    'match_number' => $game['num'],
                    'scheduled_at' => $start->utc(),
                    'scheduled_ends_at' => $end->utc(),
                    'status' => $matchStatus,
                    'home_score' => null,
                    'away_score' => null,
                    'notes' => self::MANUAL_SLOT_NOTES_MARKER,
                    'schedule_slot_ulid' => $rowUlid,
                ]);
            }
        });

        $tournament->unsetRelation('matches');

        return redirect()
            ->route('admin.tournaments.index', $this->adminRoundRobinTabQuery($request, $tournament))
            ->with('status', 'round-robin-schedule-row-created');
    }

    public function update(Request $request, Tournament $tournament, string $slot): RedirectResponse
    {
        $this->authorizeSmallTournament($tournament);

        if (! $this->isValidSlotKey($slot)) {
            abort(404);
        }

        $tournament->loadMissing(['pitches' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')]);

        [$m1, $m2] = ManualRoundRobinSchedule::resolveSlotMatchesFromKey($tournament, $slot);

        if ($m1 === null || $m2 === null) {
            return redirect()
                ->route('admin.tournaments.index', $this->adminRoundRobinTabQuery($request, $tournament))
                ->withErrors(['schedule_row' => __('This schedule row is missing one of its games. Refresh the page or remove the row from the match list.')]);
        }

        $tournamentId = (int) $tournament->id;
        $p = 'rr_slot';

        $ignoreIds = array_values(array_filter([(int) $m1->id, (int) $m2->id], fn (int $id): bool => $id > 0));

        $validated = $request->validate([
            "{$p}.round" => ['required', 'integer', 'min:1'],
            "{$p}.start_time" => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            "{$p}.end_time" => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            "{$p}.pitch1_pitch_id" => ['required', 'integer', Rule::exists('pitches', 'id')->where('tournament_id', $tournamentId)],
            "{$p}.pitch2_pitch_id" => ['required', 'integer', Rule::exists('pitches', 'id')->where('tournament_id', $tournamentId)],
            "{$p}.match1_match_number" => [
                'required',
                'integer',
                'min:1',
                Rule::unique('matches', 'match_number')->where(function ($query) use ($tournamentId, $ignoreIds): void {
                    $query->where('tournament_id', $tournamentId);
                    if ($ignoreIds !== []) {
                        $query->whereNotIn('id', $ignoreIds);
                    }
                }),
            ],
            "{$p}.match2_match_number" => [
                'required',
                'integer',
                'min:1',
                Rule::unique('matches', 'match_number')->where(function ($query) use ($tournamentId, $ignoreIds): void {
                    $query->where('tournament_id', $tournamentId);
                    if ($ignoreIds !== []) {
                        $query->whereNotIn('id', $ignoreIds);
                    }
                }),
            ],
            "{$p}.match1_home_registration_id" => ['required', 'integer', 'different:'.$p.'.match1_away_registration_id', Rule::exists('tournament_registrations', 'id')->where('tournament_id', $tournamentId)],
            "{$p}.match1_away_registration_id" => ['required', 'integer', Rule::exists('tournament_registrations', 'id')->where('tournament_id', $tournamentId)],
            "{$p}.match2_home_registration_id" => ['required', 'integer', 'different:'.$p.'.match2_away_registration_id', Rule::exists('tournament_registrations', 'id')->where('tournament_id', $tournamentId)],
            "{$p}.match2_away_registration_id" => ['required', 'integer', Rule::exists('tournament_registrations', 'id')->where('tournament_id', $tournamentId)],
            "{$p}.status" => ['nullable', 'string', Rule::in(['upcoming', 'live', 'completed'])],
            "{$p}.pitch1_match_id" => ['required', 'integer', Rule::exists('matches', 'id')->where('tournament_id', $tournamentId)],
            "{$p}.pitch2_match_id" => ['required', 'integer', Rule::exists('matches', 'id')->where('tournament_id', $tournamentId)],
            'confirm_sensitive_changes' => ['nullable', 'boolean'],
            'redirect_route' => ['nullable', 'string', 'max:64'],
            'redirect_tab' => ['nullable', 'string', 'max:64'],
            'redirect_search' => ['nullable', 'string', 'max:255'],
        ]);

        $slotData = $validated[$p];

        $allowedIds = [(int) $m1->id, (int) $m2->id];

        if (
            ! in_array((int) $slotData['pitch1_match_id'], $allowedIds, true)
            || ! in_array((int) $slotData['pitch2_match_id'], $allowedIds, true)
            || (int) $slotData['pitch1_match_id'] === (int) $slotData['pitch2_match_id']
        ) {
            return redirect()
                ->route('admin.tournaments.index', $this->adminRoundRobinTabQuery($request, $tournament))
                ->withErrors(['schedule_row' => __('That edit form does not match this schedule row anymore. Refresh the page and try again.')]);
        }

        if ((int) $slotData['match1_match_number'] === (int) $slotData['match2_match_number']) {
            throw ValidationException::withMessages([
                "{$p}.match2_match_number" => __('Game A and Game B must use different match numbers.'),
            ]);
        }

        $this->assertSlotTeamUniqueness($p, $slotData);

        if (
            $this->slotHasSensitiveState($m1, $m2)
            && $this->slotStructuralChangeRequested($tournament, $m1, $m2, $slotData)
            && ! $request->boolean('confirm_sensitive_changes')
        ) {
            throw ValidationException::withMessages([
                "{$p}.confirm_sensitive_changes" => __('This row has scores, scoring activity, or is live/completed. Check “Confirm structural changes” below if you intend to change teams, game numbers, pitches, times, or round.'),
            ]);
        }

        $ignoreA = (int) $m1->id;
        $ignoreB = (int) $m2->id;

        $this->assertNoDuplicatePairing(
            $tournament,
            (int) $slotData['match1_home_registration_id'],
            (int) $slotData['match1_away_registration_id'],
            $ignoreA,
            $ignoreB,
            "{$p}.match1_home_registration_id",
        );
        $this->assertNoDuplicatePairing(
            $tournament,
            (int) $slotData['match2_home_registration_id'],
            (int) $slotData['match2_away_registration_id'],
            $ignoreA,
            $ignoreB,
            "{$p}.match2_home_registration_id",
        );

        $tz = ManualRoundRobinSchedule::tournamentTimezone($tournament);
        $anchor = $m1 ?? $m2;

        if ($anchor === null || $anchor->scheduled_at === null) {
            return redirect()
                ->route('admin.tournaments.index', $this->adminRoundRobinTabQuery($request, $tournament))
                ->withErrors(['schedule_row' => __('This schedule row is missing a start time in the database.')]);
        }

        $dayLocal = $anchor->scheduled_at->timezone($tz)->startOfDay();
        $dateIso = $dayLocal->format('Y-m-d');

        $start = CarbonImmutable::parse($dateIso.' '.$slotData['start_time'].':00', $tz);
        $end = CarbonImmutable::parse($dateIso.' '.$slotData['end_time'].':00', $tz);

        if (! $end->greaterThan($start)) {
            throw ValidationException::withMessages([
                "{$p}.end_time" => __('End time must be after start time.'),
            ]);
        }

        $matchStatus = ManualRoundRobinSchedule::mapRowUiStatusToMatchStatus((string) ($slotData['status'] ?? 'upcoming'));
        $roundLabel = __('Round :round', ['round' => (int) $slotData['round']]);

        $updates = [
            [
                'match' => $m1,
                'num' => (int) $slotData['match1_match_number'],
                'home' => (int) $slotData['match1_home_registration_id'],
                'away' => (int) $slotData['match1_away_registration_id'],
                'pitch' => (int) $slotData['pitch1_pitch_id'],
            ],
            [
                'match' => $m2,
                'num' => (int) $slotData['match2_match_number'],
                'home' => (int) $slotData['match2_home_registration_id'],
                'away' => (int) $slotData['match2_away_registration_id'],
                'pitch' => (int) $slotData['pitch2_pitch_id'],
            ],
        ];

        DB::transaction(function () use ($updates, $start, $end, $matchStatus, $roundLabel): void {
            foreach ($updates as $row) {
                $match = $row['match'];

                if (! $match instanceof TournamentMatch) {
                    continue;
                }

                $match->forceFill([
                    'pitch_id' => $row['pitch'],
                    'home_registration_id' => $row['home'],
                    'away_registration_id' => $row['away'],
                    'round_label' => $roundLabel,
                    'match_number' => $row['num'],
                    'scheduled_at' => $start->utc(),
                    'scheduled_ends_at' => $end->utc(),
                    'status' => $matchStatus,
                ])->save();
            }
        });

        $tournament->unsetRelation('matches');

        return redirect()
            ->route('admin.tournaments.index', $this->adminRoundRobinTabQuery($request, $tournament))
            ->with('status', 'round-robin-schedule-row-updated');
    }

    public function destroy(Request $request, Tournament $tournament, string $slot): RedirectResponse
    {
        $this->authorizeSmallTournament($tournament);

        if (! $this->isValidSlotKey($slot)) {
            abort(404);
        }

        [$m1, $m2] = ManualRoundRobinSchedule::resolveSlotMatchesFromKey($tournament, $slot);
        $group = collect([$m1, $m2])->filter();

        if ($group->isEmpty()) {
            return redirect()
                ->route('admin.tournaments.index', $this->adminRoundRobinTabQuery($request, $tournament))
                ->withErrors(['schedule_row' => __('Could not find that schedule row.')]);
        }

        if (! ManualRoundRobinSchedule::slotRowIsRemovable($group)) {
            return redirect()
                ->route('admin.tournaments.index', $this->adminRoundRobinTabQuery($request, $tournament))
                ->withErrors(['schedule_row' => __('Cannot remove this schedule row because one or more games already has scores or is in progress.')]);
        }

        DB::transaction(function () use ($group): void {
            foreach ($group as $match) {
                $match->delete();
            }
        });

        $tournament->unsetRelation('matches');

        return redirect()
            ->route('admin.tournaments.index', $this->adminRoundRobinTabQuery($request, $tournament))
            ->with('status', 'round-robin-schedule-row-deleted');
    }

    public function restore(Request $request, Tournament $tournament, string $slot): RedirectResponse
    {
        $this->authorizeSmallTournament($tournament);

        if (! $this->isValidSlotKey($slot)) {
            abort(404);
        }

        $matches = collect(ManualRoundRobinSchedule::resolveTrashedSlotMatchesFromKey($tournament, $slot))->filter();

        if ($matches->isEmpty()) {
            return redirect()
                ->route('admin.tournaments.index', $this->adminRoundRobinTabQuery($request, $tournament))
                ->withErrors(['schedule_row' => __('Could not find removed games to restore for that schedule row.')]);
        }

        foreach ($matches as $match) {
            $match->restore();
        }

        $tournament->unsetRelation('matches');

        return redirect()
            ->route('admin.tournaments.index', $this->adminRoundRobinTabQuery($request, $tournament))
            ->with('status', 'round-robin-schedule-row-restored');
    }

    public function updateRowStatus(Request $request, Tournament $tournament, string $slot): RedirectResponse
    {
        $this->authorizeSmallTournament($tournament);

        if (! $this->isValidSlotKey($slot)) {
            abort(404);
        }

        $matches = $this->matchesForSlotStatusKey($tournament, $slot);

        if ($matches->isEmpty()) {
            return redirect()
                ->route('admin.tournaments.index', $this->adminRoundRobinTabQuery($request, $tournament))
                ->withErrors(['status' => __('Could not find games for this schedule row.')]);
        }

        $request->merge([
            'status' => $this->normalizeRowStatusInput($request->string('status')->toString()),
        ]);

        $validator = Validator::make($request->all(), [
            'status' => ['required', Rule::in(['scheduled', 'live', 'completed'])],
        ]);

        $validator->after(function ($validator) use ($request, $matches): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $status = $request->string('status')->toString();

            foreach ($matches as $match) {
                if (! $match instanceof TournamentMatch) {
                    continue;
                }

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
        $status = $validated['status'];

        DB::transaction(function () use ($matches, $status): void {
            foreach ($matches as $match) {
                if ($match instanceof TournamentMatch) {
                    $match->update(['status' => $status]);
                }
            }
        });

        $tournament->unsetRelation('matches');

        return redirect()
            ->route('admin.tournaments.index', $this->adminRoundRobinTabQuery($request, $tournament))
            ->with('status', 'match-status-updated');
    }

    protected function authorizeSmallTournament(Tournament $tournament): void
    {
        if ($tournament->registrations()->count() >= TournamentController::MINIMUM_BRACKET_TEAM_COUNT) {
            abort(404);
        }
    }

    protected function isValidSlotKey(string $slot): bool
    {
        return preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $slot) === 1
            || preg_match('/^m\d+-m\d+$/', $slot) === 1;
    }

    /**
     * @param  array<string, mixed>  $slot
     */
    protected function assertSlotTeamUniqueness(string $prefix, array $slot): void
    {
        $ids = array_values(array_filter([
            (int) ($slot['match1_home_registration_id'] ?? 0),
            (int) ($slot['match1_away_registration_id'] ?? 0),
            (int) ($slot['match2_home_registration_id'] ?? 0),
            (int) ($slot['match2_away_registration_id'] ?? 0),
        ], fn (int $id): bool => $id > 0));

        if (count($ids) !== count(array_unique($ids))) {
            throw ValidationException::withMessages([
                "{$prefix}.match1_home_registration_id" => __('The same team cannot appear twice in one time slot.'),
            ]);
        }
    }

    protected function assertNoDuplicatePairing(
        Tournament $tournament,
        int $homeRegistrationId,
        int $awayRegistrationId,
        ?int $ignoreMatchIdA,
        ?int $ignoreMatchIdB,
        string $errorKey,
    ): void {
        if ($homeRegistrationId <= 0 || $awayRegistrationId <= 0 || $homeRegistrationId === $awayRegistrationId) {
            return;
        }

        $ignore = array_values(array_filter([$ignoreMatchIdA, $ignoreMatchIdB], fn (?int $id): bool => $id !== null && $id > 0));

        $exists = TournamentMatch::query()
            ->where('tournament_id', $tournament->id)
            ->where('stage', 'round_robin')
            ->where(static function ($q) use ($homeRegistrationId, $awayRegistrationId): void {
                $q->where(static function ($q2) use ($homeRegistrationId, $awayRegistrationId): void {
                    $q2->where('home_registration_id', $homeRegistrationId)
                        ->where('away_registration_id', $awayRegistrationId);
                })->orWhere(static function ($q2) use ($homeRegistrationId, $awayRegistrationId): void {
                    $q2->where('home_registration_id', $awayRegistrationId)
                        ->where('away_registration_id', $homeRegistrationId);
                });
            })
            ->when($ignore !== [], fn ($q) => $q->whereNotIn('id', $ignore))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                $errorKey => __('That team pairing already exists in another round robin match. Each pairing should normally appear only once.'),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function adminRoundRobinTabQuery(Request $request, Tournament $tournament): array
    {
        $search = trim((string) $request->input('redirect_search', ''));

        return collect([
            'tournament' => $tournament->id,
            'tab' => 'round-robin',
            'search' => $search !== '' ? $search : null,
        ])->filter(fn (mixed $value): bool => $value !== null && $value !== '')->all();
    }

    /**
     * @return Collection<int, TournamentMatch>
     */
    protected function matchesForSlotStatusKey(Tournament $tournament, string $slotKey): Collection
    {
        if (preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $slotKey) === 1) {
            return TournamentMatch::query()
                ->where('tournament_id', $tournament->id)
                ->where('stage', 'round_robin')
                ->where('schedule_slot_ulid', $slotKey)
                ->orderBy('match_number')
                ->orderBy('id')
                ->get();
        }

        if (preg_match('/^m(\d+)-m(\d+)$/', $slotKey, $m) === 1) {
            return TournamentMatch::query()
                ->where('tournament_id', $tournament->id)
                ->where('stage', 'round_robin')
                ->whereIn('id', [(int) $m[1], (int) $m[2]])
                ->orderBy('match_number')
                ->orderBy('id')
                ->get();
        }

        return collect();
    }

    protected function normalizeRowStatusInput(string $status): string
    {
        $trimmed = trim($status);

        return match (strtolower($trimmed)) {
            'upcoming' => TournamentMatch::STATUS_SCHEDULED,
            'live' => TournamentMatch::STATUS_LIVE,
            'completed' => TournamentMatch::STATUS_COMPLETED,
            default => $trimmed,
        };
    }

    protected function slotHasSensitiveState(TournamentMatch $m1, TournamentMatch $m2): bool
    {
        foreach ([$m1, $m2] as $match) {
            if (in_array($match->status, [TournamentMatch::STATUS_LIVE, TournamentMatch::STATUS_COMPLETED], true)) {
                return true;
            }

            if ($match->home_score !== null || $match->away_score !== null) {
                return true;
            }

            if ($match->scoreLogs()->exists() || $match->playerStats()->exists() || $match->spiritScores()->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $slotData
     */
    protected function slotStructuralChangeRequested(Tournament $tournament, TournamentMatch $m1, TournamentMatch $m2, array $slotData): bool
    {
        $newRound = (int) $slotData['round'];

        foreach ([$m1, $m2] as $match) {
            if (ManualRoundRobinSchedule::parseRoundNumberFromLabel($match->round_label) !== $newRound) {
                return true;
            }
        }

        $pairs = [
            [(int) $m1->home_registration_id, (int) $slotData['match1_home_registration_id']],
            [(int) $m1->away_registration_id, (int) $slotData['match1_away_registration_id']],
            [(int) $m2->home_registration_id, (int) $slotData['match2_home_registration_id']],
            [(int) $m2->away_registration_id, (int) $slotData['match2_away_registration_id']],
            [(int) $m1->pitch_id, (int) $slotData['pitch1_pitch_id']],
            [(int) $m2->pitch_id, (int) $slotData['pitch2_pitch_id']],
            [(int) $m1->match_number, (int) $slotData['match1_match_number']],
            [(int) $m2->match_number, (int) $slotData['match2_match_number']],
        ];

        foreach ($pairs as [$before, $after]) {
            if ($before !== $after) {
                return true;
            }
        }

        $tz = ManualRoundRobinSchedule::tournamentTimezone($tournament);
        $anchor = $m1->scheduled_at !== null ? $m1 : $m2;

        if ($anchor->scheduled_at === null) {
            return true;
        }

        $dayLocal = $anchor->scheduled_at->timezone($tz)->startOfDay();
        $dateIso = $dayLocal->format('Y-m-d');
        $newStart = CarbonImmutable::parse($dateIso.' '.$slotData['start_time'].':00', $tz)->utc();
        $newEnd = CarbonImmutable::parse($dateIso.' '.$slotData['end_time'].':00', $tz)->utc();

        foreach ([$m1, $m2] as $match) {
            if (! $match->scheduled_at?->equalTo($newStart) || ! $match->scheduled_ends_at?->equalTo($newEnd)) {
                return true;
            }
        }

        return false;
    }
}
