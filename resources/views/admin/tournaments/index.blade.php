@php
    $bracketTeamLimit = \App\Http\Controllers\Admin\TournamentController::BRACKET_TEAM_LIMIT;
    $minimumBracketTeamCount = \App\Http\Controllers\Admin\TournamentController::MINIMUM_BRACKET_TEAM_COUNT;
    $user = auth()->user();
    $isAdmin = $user->isAdmin();

    $adminTabs = [
        ['key' => 'overview', 'label' => __('Seeding')],
        ['key' => 'round-robin', 'label' => __('Round Robin')],
        ['key' => 'teams', 'label' => __('Bracket Ranking')],
        ['key' => 'pitches', 'label' => __('Crossover')],
        ['key' => 'format', 'label' => __('Pooling')],
        ['key' => 'matches', 'label' => __('Quarter Final')],
        ['key' => 'crew', 'label' => __('Semi Finals')],
        ['key' => 'publish', 'label' => __('Championship')],
    ];
    $adminTabKeys = array_column($adminTabs, 'key');
    $requestedTab = request()->string('tab')->toString();
    $requestedTab = $requestedTab === 'basic-info' ? 'round-robin' : $requestedTab;
    $selectedTab = in_array($requestedTab, $adminTabKeys, true) ? $requestedTab : 'overview';

    $pitchModalTournamentId = old('pitch_tournament_id')
        ? (int) old('pitch_tournament_id')
        : null;
    $registerModalTournamentId = old('registration_tournament_id')
        ? (int) old('registration_tournament_id')
        : null;
    $matchModalTournamentId = old('match_tournament_id')
        ? (int) old('match_tournament_id')
        : null;
    $crewModalTournamentId = old('crew_tournament_id')
        ? (int) old('crew_tournament_id')
        : null;

    $showPitchModal = $selectedTournament && $pitchModalTournamentId === $selectedTournament->id;
    $showRegisterModal = $selectedTournament && $registerModalTournamentId === $selectedTournament->id;
    $showMatchModal = $selectedTournament && $matchModalTournamentId === $selectedTournament->id;
    $showCrewModal = $selectedTournament && $crewModalTournamentId === $selectedTournament->id;
    $seedOrderBracketModalCode = ($seedOrderBracket = trim((string) old('seed_order_bracket_code', ''))) !== ''
        ? $seedOrderBracket
        : null;

    $teamCount = $selectedTournament?->registrations?->count() ?? 0;
    $pitchCount = $selectedTournament?->pitches?->count() ?? 0;
    $matchCount = $selectedTournament?->matches?->count() ?? 0;
    $crewCount = $selectedTournament?->crewMembers?->count() ?? 0;
    $seededRegistrations = collect($selectedTournament?->registrations ?? [])
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
    $seededBracketGroups = $seededRegistrations
        ->filter(fn ($registration): bool => filled($registration->bracket_code))
        ->groupBy('bracket_code')
        ->sortKeys()
        ->map(function ($registrations, string $code): array {
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
    $unassignedSeededCount = $seededRegistrations
        ->filter(fn ($registration): bool => blank($registration->bracket_code))
        ->count();
    $roundRobinMatches = collect($selectedTournament?->matches ?? [])
        ->filter(fn ($match): bool => $match->stage === 'round_robin')
        ->values();
    $hasBracketThreshold = $teamCount >= $minimumBracketTeamCount;
    $canCreateMatches = $selectedTournament ? $teamCount >= 2 : false;
    $firstScorableMatch = $selectedTournament?->matches?->first(
        fn ($match): bool => $match->homeRegistration && $match->awayRegistration
    );

    $readinessChecks = $selectedTournament && $isAdmin
        ? [
            [
                'label' => __('Basic info complete'),
                'ready' => filled($selectedTournament->name)
                    && filled($selectedTournament->venue)
                    && filled($selectedTournament->province)
                    && filled($selectedTournament->city)
                    && filled($selectedTournament->barangay),
                'detail' => $selectedTournament->addressLabel() ?: __('Location to be announced'),
            ],
            [
                'label' => __('Teams registered'),
                'ready' => $teamCount > 0,
                'detail' => trans_choice('{0} No teams yet|{1} :count team ready|[2,*] :count teams ready', $teamCount, ['count' => $teamCount]),
            ],
            [
                'label' => __('Pitches added'),
                'ready' => $pitchCount > 0,
                'detail' => trans_choice('{0} No pitches yet|{1} :count pitch ready|[2,*] :count pitches ready', $pitchCount, ['count' => $pitchCount]),
            ],
            [
                'label' => __('Matches scheduled'),
                'ready' => $matchCount > 0,
                'detail' => trans_choice('{0} No matches yet|{1} :count match scheduled|[2,*] :count matches scheduled', $matchCount, ['count' => $matchCount]),
            ],
            [
                'label' => __('Public preview'),
                'ready' => (bool) $selectedTournament->is_public,
                'detail' => $selectedTournament->is_public ? __('Published on the public board') : __('Still private'),
            ],
        ]
        : [];

    $frisbeeStageOptions = [
        ['value' => 'seeding', 'label' => __('Day 0 - Seeding')],
        ['value' => 'round_robin', 'label' => __('Day 1 - Round Robin')],
        ['value' => 'bracket_ranking', 'label' => __('Day 1 - Bracket Ranking')],
        ['value' => 'crossover', 'label' => __('Day 1 - Crossover')],
        ['value' => 'pooling', 'label' => __('Day 1 - Pooling')],
        ['value' => 'quarterfinal', 'label' => __('Day 2 - Quarter Finals')],
        ['value' => 'semifinal', 'label' => __('Day 2 - Semi-Finals')],
        ['value' => 'championship', 'label' => __('Day 2 - Championship')],
    ];
@endphp

<x-layouts::app :title="$isAdmin ? __('Tournament Setup') : __('Score Matches')">
    <div class="space-y-6">
        <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <flux:heading size="xl">{{ $isAdmin ? __('Admin Tournament Setup') : __('Tournament Scoring Console') }}</flux:heading>
            <flux:text class="mt-2 max-w-3xl">
                {{ $isAdmin
                    ? __('Phase 1 admin tools now use a tabbed setup workspace: quick actions stay in modals, while the larger setup flow stays on-page.')
                    : __('Open a tournament from the directory to review the match list and launch live scoring without full tournament setup access.') }}
            </flux:text>

            @if (session('status'))
                <div class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-950/40 dark:text-green-300">
                    @switch(session('status'))
                        @case('tournament-created')
                            {{ __('Tournament created successfully.') }}
                            @break
                        @case('tournament-updated')
                            {{ __('Tournament updated successfully.') }}
                            @break
                        @case('pitch-created')
                            {{ __('Pitch added successfully.') }}
                            @break
                        @case('pitch-updated')
                            {{ __('Pitch updated successfully.') }}
                            @break
                        @case('pitch-deleted')
                            {{ __('Pitch deleted successfully.') }}
                            @break
                        @case('registration-created')
                            {{ __('Team registered successfully.') }}
                            @break
                        @case('registrations-seeded')
                            {{ $hasBracketThreshold
                                ? __('Teams seeded successfully. Brackets now use :count teams each, and extra teams remain unassigned.', ['count' => $bracketTeamLimit])
                                : __('Teams seeded successfully. Brackets start only at :count total teams, so all teams remain unassigned for now.', ['count' => $minimumBracketTeamCount]) }}
                            @break
                        @case('registrations-seeding-skipped')
                            {{ __('No registered teams were available for seeding.') }}
                            @break
                        @case('registrations-seeding-updated')
                            {{ __('Manual seeding updated successfully.') }}
                            @break
                        @case('round-robin-generated')
                            {{ __('Round robin matches generated successfully.') }}
                            @break
                        @case('match-created')
                            {{ __('Match added successfully.') }}
                            @break
                        @case('match-updated')
                            {{ __('Match updated successfully.') }}
                            @break
                        @case('match-deleted')
                            {{ __('Match deleted successfully.') }}
                            @break
                        @case('crew-created')
                            {{ __('Crew member added successfully.') }}
                            @break
                        @default
                            {{ __('Saved.') }}
                    @endswitch
                </div>
            @endif
        </section>

        @if ($isAdmin && ! $selectedTournament)
            <section id="create-tournament" class="scroll-mt-6 rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                <div class="mb-4">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Create Tournament') }}</h2>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ __('This is the starting point for seeding, match scheduling, and pool generation.') }}</p>
                </div>

                @include('admin.tournaments.partials.form', [
                    'action' => route('admin.tournaments.store'),
                    'submitLabel' => __('Create Tournament'),
                    'hiddenFields' => [
                        'redirect_tab' => 'overview',
                    ],
                ])
            </section>
        @endif

        @if ($selectedTournament)
            @if ($isAdmin)
                <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500 dark:text-zinc-400">
                                {{ __('Tournament Setup Workspace') }}
                            </div>
                            <flux:heading size="xl" class="mt-2">{{ $selectedTournament->name }}</flux:heading>
                            <flux:text class="mt-2 max-w-3xl">
                                {{ $selectedTournament->venue }}
                                @if ($selectedTournament->dateRangeLabel())
                                    {{ ' | '.$selectedTournament->dateRangeLabel() }}
                                @endif
                                @if ($selectedTournament->timezone)
                                    {{ ' | '.$selectedTournament->timezone }}
                                @endif
                            </flux:text>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <span class="inline-flex items-center rounded-full border border-neutral-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-700 dark:border-neutral-700 dark:bg-zinc-950 dark:text-zinc-200">
                                    {{ __('Status: :status', ['status' => str($selectedTournament->status)->headline()]) }}
                                </span>
                                <span class="inline-flex items-center rounded-full border border-neutral-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-700 dark:border-neutral-700 dark:bg-zinc-950 dark:text-zinc-200">
                                    {{ $selectedTournament->is_public ? __('Public') : __('Private') }}
                                </span>
                                <span class="inline-flex items-center rounded-full border border-neutral-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-700 dark:border-neutral-700 dark:bg-zinc-950 dark:text-zinc-200">
                                    {{ __('Teams: :count', ['count' => $teamCount]) }}
                                </span>
                                <span class="inline-flex items-center rounded-full border border-neutral-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-700 dark:border-neutral-700 dark:bg-zinc-950 dark:text-zinc-200">
                                    {{ __('Matches: :count', ['count' => $matchCount]) }}
                                </span>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <a
                                href="{{ route('admin.tournaments.list') }}"
                                wire:navigate
                                class="inline-flex items-center justify-center rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-zinc-700 transition hover:border-neutral-400 hover:bg-zinc-100 dark:border-neutral-700 dark:text-zinc-200 dark:hover:bg-zinc-800"
                            >
                                {{ __('Back to Tournaments') }}
                            </a>

                            @if ($selectedTournament->is_public)
                                <a
                                    href="{{ route('tournaments.show', $selectedTournament) }}"
                                    target="_blank"
                                    rel="noreferrer"
                                    class="inline-flex items-center justify-center rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-zinc-700 transition hover:border-neutral-400 hover:bg-zinc-100 dark:border-neutral-700 dark:text-zinc-200 dark:hover:bg-zinc-800"
                                >
                                    {{ __('Preview Public Page') }}
                                </a>
                            @endif

                            @if ($firstScorableMatch)
                                <a
                                    href="{{ route('admin.tournaments.matches.scoring', ['tournament' => $selectedTournament, 'match' => $firstScorableMatch]) }}"
                                    wire:navigate
                                    class="inline-flex items-center justify-center rounded-lg border border-[#c8d7f8] bg-[#e9f0ff] px-4 py-2 text-sm font-medium text-[#2f55b7] transition hover:border-[#9fb7f2] hover:bg-[#dce8ff]"
                                >
                                    {{ __('Open Scoring') }}
                                </a>
                            @endif
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-neutral-200 bg-white p-3 dark:border-neutral-700 dark:bg-zinc-900">
                    <nav class="flex gap-2 overflow-x-auto">
                        @foreach ($adminTabs as $tab)
                            <a
                                href="{{ route('admin.tournaments.index', ['tournament' => $selectedTournament->id, 'tab' => $tab['key']]) }}"
                                wire:navigate
                                class="inline-flex shrink-0 items-center rounded-lg border px-4 py-2 text-sm font-medium transition {{ $selectedTab === $tab['key']
                                    ? 'border-[#2f55b7] bg-[#eef4ff] text-[#2f55b7] dark:border-sky-400 dark:bg-sky-950/30 dark:text-sky-200'
                                    : 'border-neutral-200 text-zinc-700 hover:border-neutral-400 hover:bg-zinc-100 dark:border-neutral-700 dark:text-zinc-200 dark:hover:bg-zinc-800' }}"
                            >
                                {{ $tab['label'] }}
                            </a>
                        @endforeach
                    </nav>
                </section>

                @if ($selectedTab === 'overview')
                    <section class="space-y-6">
                        <div data-seeding-overview-container>
                            @include('admin.tournaments.partials.seeding-overview', [
                                'selectedTournament' => $selectedTournament,
                                'teamCount' => $teamCount,
                                'seededBracketGroups' => $seededBracketGroups,
                                'unassignedSeededCount' => $unassignedSeededCount,
                                'minimumBracketTeamCount' => $minimumBracketTeamCount,
                                'bracketTeamLimit' => $bracketTeamLimit,
                                'seedOrderBracketModalCode' => $seedOrderBracketModalCode,
                                'asyncStatusMessage' => null,
                            ])
                        </div>

                        <script data-navigate-once>
                            (() => {
                                if (window.__distrackAsyncSeedingBound) {
                                    return;
                                }

                                window.__distrackAsyncSeedingBound = true;

                                document.addEventListener('submit', async (event) => {
                                    const form = event.target;

                                    if (!(form instanceof HTMLFormElement) || !form.matches('[data-seeding-randomize-form]')) {
                                        return;
                                    }

                                    const container = form.closest('[data-seeding-overview-container]')
                                        ?? document.querySelector('[data-seeding-overview-container]');

                                    if (!(container instanceof HTMLElement)) {
                                        return;
                                    }

                                    event.preventDefault();

                                    const submitButtons = Array.from(form.querySelectorAll('button, [type="submit"]'))
                                        .filter((element) => element instanceof HTMLButtonElement || element instanceof HTMLInputElement);

                                    submitButtons.forEach((button) => {
                                        button.dataset.originalDisabled = button.disabled ? 'true' : 'false';
                                        button.disabled = true;
                                    });

                                    try {
                                        const response = await fetch(form.action, {
                                            method: form.method || 'POST',
                                            headers: {
                                                'Accept': 'application/json',
                                                'X-Requested-With': 'XMLHttpRequest',
                                            },
                                            body: new FormData(form),
                                        });

                                        if (!response.ok) {
                                            throw new Error(`Randomize request failed with status ${response.status}.`);
                                        }

                                        const payload = await response.json();

                                        if (typeof payload.overview_html !== 'string') {
                                            throw new Error('Missing seeding overview HTML in response.');
                                        }

                                        container.innerHTML = payload.overview_html;
                                    } catch (error) {
                                        console.error(error);

                                        submitButtons.forEach((button) => {
                                            button.disabled = button.dataset.originalDisabled === 'true';
                                        });

                                        form.submit();
                                    }
                                });
                            })();
                        </script>
                    </section>
                @elseif ($selectedTab === 'round-robin')
                    <section class="space-y-6">
                        <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Pitches') }}</h2>
                                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ __('Add at least one pitch before generating the round robin schedule. Available pitches are assigned to matches in order.') }}
                                    </p>
                                </div>

                                <flux:modal.trigger name="setup-add-pitch-modal-{{ $selectedTournament->id }}">
                                    <flux:button variant="primary">
                                        {{ __('Add Pitch') }}
                                    </flux:button>
                                </flux:modal.trigger>
                            </div>

                            <div class="mt-4 flex items-center justify-between gap-4">
                                <div class="text-sm text-zinc-600 dark:text-zinc-300">
                                    {{ __('Pitches available for this tournament') }}
                                </div>
                                <span class="rounded-md border border-neutral-200 px-3 py-1 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                                    {{ trans_choice('{0} No pitches|{1} :count pitch|[2,*] :count pitches', $pitchCount, ['count' => $pitchCount]) }}
                                </span>
                            </div>

                            <div class="mt-3 space-y-3">
                                @forelse ($selectedTournament->pitches as $pitch)
                                    <div class="rounded-xl border border-neutral-200 bg-zinc-50 p-4 dark:border-neutral-700 dark:bg-zinc-950">
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <div class="font-semibold text-zinc-900 dark:text-white">{{ $pitch->name }}</div>
                                                <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                                    {{ $pitch->location ?: __('No location provided') }}
                                                </div>
                                            </div>
                                            <div class="text-right text-xs text-zinc-500 dark:text-zinc-400">
                                                {{ __('Order: :order', ['order' => $pitch->sort_order]) }}
                                            </div>
                                        </div>

                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <flux:modal.trigger name="setup-edit-pitch-modal-{{ $pitch->id }}">
                                                <flux:button variant="ghost" size="sm">
                                                    {{ __('Edit') }}
                                                </flux:button>
                                            </flux:modal.trigger>

                                            <form
                                                method="POST"
                                                action="{{ route('admin.tournaments.pitches.destroy', ['pitch' => $pitch->id]) }}"
                                                onsubmit="return confirm('{{ __('Delete this pitch? Matches scheduled on it will lose the pitch assignment.') }}')"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="redirect_route" value="admin.tournaments.index">
                                                <input type="hidden" name="redirect_tab" value="round-robin">

                                                <button
                                                    type="submit"
                                                    class="rounded-full border border-red-200 px-3 py-1 text-[11px] font-medium text-red-600 transition hover:border-red-300 hover:bg-red-50 dark:border-red-900/70 dark:text-red-300 dark:hover:bg-red-950/40"
                                                >
                                                    {{ __('Delete') }}
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @empty
                                    <div class="rounded-xl border border-dashed border-neutral-300 p-4 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                                        {{ __('No pitches yet. Add a pitch first so the round robin generator has somewhere to schedule the matches.') }}
                                    </div>
                                @endforelse
                            </div>
                        </section>

                        <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Round Robin') }}</h2>
                                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ __('Generate the opening bracket matchups here. The current brackets are used as the round robin groups, and available pitches are assigned automatically in order.') }}
                                    </p>
                                </div>

                                <form method="POST" action="{{ route('admin.tournaments.matches.round-robin.generate') }}">
                                    @csrf
                                    <input type="hidden" name="tournament_id" value="{{ $selectedTournament->id }}">
                                    <input type="hidden" name="redirect_route" value="admin.tournaments.index">
                                    <input type="hidden" name="redirect_tab" value="round-robin">

                                    <flux:button
                                        type="submit"
                                        variant="primary"
                                        :disabled="$seededBracketGroups->isEmpty() || $pitchCount === 0"
                                    >
                                        {{ $roundRobinMatches->isNotEmpty() ? __('Regenerate Round Robin') : __('Generate Round Robin') }}
                                    </flux:button>
                                </form>
                            </div>

                            @if ($errors->has('round_robin'))
                                <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
                                    {{ $errors->first('round_robin') }}
                                </div>
                            @endif

                            <div class="mt-5 grid gap-3 md:grid-cols-4">
                                <div class="rounded-xl border border-neutral-200 bg-zinc-50 px-4 py-3 dark:border-neutral-700 dark:bg-zinc-950">
                                    <div class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500 dark:text-zinc-400">{{ __('Brackets') }}</div>
                                    <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-white">{{ $seededBracketGroups->count() }}</div>
                                </div>
                                <div class="rounded-xl border border-neutral-200 bg-zinc-50 px-4 py-3 dark:border-neutral-700 dark:bg-zinc-950">
                                    <div class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500 dark:text-zinc-400">{{ __('Teams') }}</div>
                                    <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-white">{{ $teamCount }}</div>
                                </div>
                                <div class="rounded-xl border border-neutral-200 bg-zinc-50 px-4 py-3 dark:border-neutral-700 dark:bg-zinc-950">
                                    <div class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500 dark:text-zinc-400">{{ __('Pitches') }}</div>
                                    <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-white">{{ $pitchCount }}</div>
                                </div>
                                <div class="rounded-xl border border-neutral-200 bg-zinc-50 px-4 py-3 dark:border-neutral-700 dark:bg-zinc-950">
                                    <div class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500 dark:text-zinc-400">{{ __('Matches') }}</div>
                                    <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-white">{{ $roundRobinMatches->count() }}</div>
                                </div>
                            </div>

                            <div class="mt-4 rounded-xl border border-dashed border-neutral-300 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-neutral-700 dark:bg-zinc-950 dark:text-zinc-300">
                                {{ __('This uses the current seeded brackets. For the sample setup, the generated round robin matches will rotate through the :count available pitches.', ['count' => $pitchCount]) }}
                            </div>

                            @if ($seededBracketGroups->isNotEmpty())
                                <div class="mt-4 grid gap-3 md:grid-cols-2">
                                    @foreach ($seededBracketGroups as $group)
                                        <div class="rounded-xl border border-neutral-200 bg-zinc-50 px-4 py-4 dark:border-neutral-700 dark:bg-zinc-950">
                                            <div class="font-semibold text-zinc-900 dark:text-white">{{ $group['code'] }}</div>
                                            <div class="mt-2 space-y-1 text-sm text-zinc-600 dark:text-zinc-300">
                                                @foreach ($group['registrations'] as $registration)
                                                    <div>{{ ($registration->seed_number ?? '-') . ' - ' . $registration->team->name }}</div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if ($pitchCount === 0 || $seededBracketGroups->isEmpty())
                                <p class="mt-4 text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ __('Add at least one pitch and make sure teams are already assigned to brackets before generating round robin matches.') }}
                                </p>
                            @endif
                        </section>

                        <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Round Robin Schedule') }}</h2>
                                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ __('Mix the auto generator with manual matches. Add a single match yourself when you need a custom matchup or pitch assignment.') }}
                                    </p>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-md border border-neutral-200 px-3 py-1 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                                        {{ trans_choice('{0} No matches yet|{1} :count match|[2,*] :count matches', $roundRobinMatches->count(), ['count' => $roundRobinMatches->count()]) }}
                                    </span>

                                    <flux:modal.trigger name="setup-add-round-robin-match-modal-{{ $selectedTournament->id }}">
                                        <flux:button variant="primary" :disabled="! $canCreateMatches">
                                            {{ __('Add Match Manually') }}
                                        </flux:button>
                                    </flux:modal.trigger>
                                </div>
                            </div>

                            @unless ($canCreateMatches)
                                <p class="mb-3 text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ __('Register at least two teams before creating a manual round robin match.') }}
                                </p>
                            @endunless

                            <div class="space-y-3">
                                @forelse ($roundRobinMatches as $match)
                                    <div class="rounded-xl border border-neutral-200 bg-zinc-50 p-4 dark:border-neutral-700 dark:bg-zinc-950">
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <div class="font-semibold text-zinc-900 dark:text-white">
                                                    {{ $match->homeRegistration?->team->name ?? __('TBD') }}
                                                    {{ __('vs') }}
                                                    {{ $match->awayRegistration?->team->name ?? __('TBD') }}
                                                </div>
                                                <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                                    {{ $match->round_label ?: __('Round Robin') }}
                                                </div>
                                                @if ($match->scheduled_at)
                                                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                                        {{ __('Scheduled :time', ['time' => $match->scheduled_at->format('M j, Y g:i A')]) }}
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="text-right text-xs text-zinc-500 dark:text-zinc-400">
                                                <div>{{ __('Match #: :value', ['value' => $match->match_number ?? '-']) }}</div>
                                                <div class="mt-1">{{ __('Pitch: :value', ['value' => $match->pitch?->name ?? '-']) }}</div>
                                            </div>
                                        </div>

                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <flux:modal.trigger name="setup-edit-round-robin-match-modal-{{ $match->id }}">
                                                <flux:button variant="ghost" size="sm">
                                                    {{ __('Edit') }}
                                                </flux:button>
                                            </flux:modal.trigger>

                                            <form
                                                method="POST"
                                                action="{{ route('admin.tournaments.matches.destroy', ['match' => $match->id]) }}"
                                                onsubmit="return confirm('{{ __('Delete this round robin match? This cannot be undone.') }}')"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="redirect_route" value="admin.tournaments.index">
                                                <input type="hidden" name="redirect_tab" value="round-robin">

                                                <button
                                                    type="submit"
                                                    class="rounded-full border border-red-200 px-3 py-1 text-[11px] font-medium text-red-600 transition hover:border-red-300 hover:bg-red-50 dark:border-red-900/70 dark:text-red-300 dark:hover:bg-red-950/40"
                                                >
                                                    {{ __('Delete') }}
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @empty
                                    <div class="rounded-xl border border-dashed border-neutral-300 p-4 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                                        {{ __('No round robin matches yet. Use Generate Round Robin for an automated bracket sweep, or add matches manually one at a time.') }}
                                    </div>
                                @endforelse
                            </div>
                        </section>

                    </section>
                @elseif ($selectedTab === 'teams')
                    <section class="space-y-6">
                        <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Teams') }}</h2>
                                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ __('Review registered teams, manage seeding metadata, and use the quick modal to add another team without leaving the page.') }}
                                    </p>
                                </div>

                                <flux:modal.trigger name="setup-register-team-modal-{{ $selectedTournament->id }}">
                                    <flux:button variant="primary">
                                        {{ __('Register Team') }}
                                    </flux:button>
                                </flux:modal.trigger>
                            </div>
                        </section>

                        <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                            <div class="mb-4 flex items-center justify-between gap-4">
                                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Registered Teams') }}</h2>
                                <span class="rounded-md border border-neutral-200 px-3 py-1 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                                    {{ trans_choice('{0} No teams|{1} :count team|[2,*] :count teams', $teamCount, ['count' => $teamCount]) }}
                                </span>
                            </div>

                            <div class="space-y-3">
                                @forelse ($selectedTournament->registrations as $registration)
                                    <div class="rounded-xl border border-neutral-200 bg-zinc-50 p-4 dark:border-neutral-700 dark:bg-zinc-950">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <div class="font-semibold text-zinc-900 dark:text-white">{{ $registration->team->name }}</div>
                                                <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                                    {{ __('Status: :status', ['status' => str($registration->status)->headline()]) }}
                                                </div>
                                            </div>
                                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                                {{ __('Seed: :seed', ['seed' => $registration->seed_number ?? '-']) }}
                                            </div>
                                        </div>
                                        <div class="mt-3 flex flex-wrap gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                            <span>{{ __('Bracket: :value', ['value' => $registration->bracket_code ?? '-']) }}</span>
                                            <span>{{ __('Pool: :value', ['value' => $registration->pool_name ?? '-']) }}</span>
                                        </div>
                                    </div>
                                @empty
                                    <div class="rounded-xl border border-dashed border-neutral-300 p-4 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                                        {{ __('No teams registered to this tournament yet.') }}
                                    </div>
                                @endforelse
                            </div>
                        </section>
                    </section>
                @elseif ($selectedTab === 'pitches')
                    <section class="space-y-6">
                        <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Pitches') }}</h2>
                                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ __('Keep the pitch list inline for reference while the add form stays in a small modal.') }}
                                    </p>
                                </div>

                                <flux:modal.trigger name="setup-add-pitch-modal-{{ $selectedTournament->id }}">
                                    <flux:button variant="primary">
                                        {{ __('Add Pitch') }}
                                    </flux:button>
                                </flux:modal.trigger>
                            </div>
                        </section>

                        <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                            <div class="mb-4 flex items-center justify-between gap-4">
                                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Pitches for :tournament', ['tournament' => $selectedTournament->name]) }}</h2>
                                <span class="rounded-md border border-neutral-200 px-3 py-1 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                                    {{ trans_choice('{0} No pitches|{1} :count pitch|[2,*] :count pitches', $pitchCount, ['count' => $pitchCount]) }}
                                </span>
                            </div>

                            <div class="space-y-3">
                                @forelse ($selectedTournament->pitches as $pitch)
                                    <div class="rounded-xl border border-neutral-200 bg-zinc-50 p-4 dark:border-neutral-700 dark:bg-zinc-950">
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <div class="font-semibold text-zinc-900 dark:text-white">{{ $pitch->name }}</div>
                                                <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                                    {{ $pitch->location ?: __('No location provided') }}
                                                </div>
                                                <div class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                                    {{ __('Order: :order', ['order' => $pitch->sort_order]) }}
                                                </div>
                                            </div>

                                            <div class="flex flex-wrap gap-2">
                                                <flux:modal.trigger name="setup-edit-pitch-modal-{{ $pitch->id }}">
                                                    <flux:button variant="ghost" size="sm">
                                                        {{ __('Edit') }}
                                                    </flux:button>
                                                </flux:modal.trigger>

                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.tournaments.pitches.destroy', ['pitch' => $pitch->id]) }}"
                                                    onsubmit="return confirm('{{ __('Delete this pitch? Matches scheduled on it will lose the pitch assignment.') }}')"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="redirect_route" value="admin.tournaments.index">
                                                    <input type="hidden" name="redirect_tab" value="pitches">

                                                    <button
                                                        type="submit"
                                                        class="rounded-full border border-red-200 px-3 py-1 text-[11px] font-medium text-red-600 transition hover:border-red-300 hover:bg-red-50 dark:border-red-900/70 dark:text-red-300 dark:hover:bg-red-950/40"
                                                    >
                                                        {{ __('Delete') }}
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="rounded-xl border border-dashed border-neutral-300 p-4 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                                        {{ __('No pitches have been added yet.') }}
                                    </div>
                                @endforelse
                            </div>
                        </section>
                    </section>
                @elseif ($selectedTab === 'format')
                    @include('admin.tournaments.partials.frisbee-format-workflow', [
                        'tournament' => $selectedTournament,
                    ])
                @elseif ($selectedTab === 'matches')
                    <section class="space-y-6">
                        <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Matches') }}</h2>
                                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ __('Scheduling remains on-page while the quick add flow stays in a modal. Live scoring still opens as a dedicated workflow.') }}
                                    </p>
                                </div>

                                <flux:modal.trigger name="setup-add-match-modal-{{ $selectedTournament->id }}">
                                    <flux:button variant="primary" :disabled="! $canCreateMatches">
                                        {{ __('Add Match') }}
                                    </flux:button>
                                </flux:modal.trigger>
                            </div>

                            @unless ($canCreateMatches)
                                <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ __('Register at least two teams before creating a match.') }}
                                </p>
                            @endunless

                            <div class="mt-4 rounded-xl border border-neutral-200 bg-zinc-50 p-4 dark:border-neutral-700 dark:bg-zinc-950">
                                <div class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Frisbee Stage Keys') }}</div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($frisbeeStageOptions as $option)
                                        <span class="inline-flex items-center rounded-full border border-neutral-200 bg-white px-3 py-1 text-xs font-medium text-zinc-700 dark:border-neutral-700 dark:bg-zinc-900 dark:text-zinc-300">
                                            {{ $option['label'] }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        </section>

                        <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                            <div class="mb-4">
                                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Match Schedule') }}</h2>
                            </div>

                            <div class="space-y-3">
                                @forelse ($selectedTournament->matches as $match)
                                    <div class="rounded-xl border border-neutral-200 bg-zinc-50 p-4 dark:border-neutral-700 dark:bg-zinc-950">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <div class="font-semibold text-zinc-900 dark:text-white">
                                                    {{ $match->homeRegistration?->team->name ?? __('TBD') }}
                                                    {{ __('vs') }}
                                                    {{ $match->awayRegistration?->team->name ?? __('TBD') }}
                                                </div>
                                                <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                                    {{ str($match->stage)->replace('_', ' ')->headline() }}
                                                    @if ($match->round_label)
                                                        {{ ' | '.$match->round_label }}
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="text-right text-xs text-zinc-500 dark:text-zinc-400">
                                                <div>{{ __('Status: :status', ['status' => str($match->status)->headline()]) }}</div>
                                                @if (! is_null($match->home_score) && ! is_null($match->away_score))
                                                    <div class="mt-1">{{ $match->home_score }} - {{ $match->away_score }}</div>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="mt-3 flex flex-wrap gap-3 text-xs text-zinc-500 dark:text-zinc-400">
                                            <span>{{ __('Match #: :value', ['value' => $match->match_number ?? '-']) }}</span>
                                            <span>{{ __('Pitch: :value', ['value' => $match->pitch?->name ?? '-']) }}</span>
                                            <span>{{ __('Time: :value', ['value' => $match->scheduled_at?->format('M j, Y g:i A') ?? 'TBD']) }}</span>
                                        </div>

                                        <div class="mt-4 flex flex-wrap gap-2">
                                            @if ($match->homeRegistration && $match->awayRegistration)
                                                <a
                                                    href="{{ route('admin.tournaments.matches.scoring', ['tournament' => $selectedTournament, 'match' => $match]) }}"
                                                    wire:navigate
                                                    class="inline-flex items-center justify-center rounded-lg border border-[#c8d7f8] bg-[#e9f0ff] px-3 py-2 text-sm font-medium text-[#2f55b7] transition hover:border-[#9fb7f2] hover:bg-[#dce8ff]"
                                                >
                                                    {{ __('Live Scoring') }}
                                                </a>
                                            @endif

                                            @if ($selectedTournament->is_public)
                                                <a
                                                    href="{{ route('tournaments.matches.show', ['tournament' => $selectedTournament, 'match' => $match]) }}"
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    class="inline-flex items-center justify-center rounded-lg border border-neutral-300 px-3 py-2 text-sm font-medium text-zinc-700 transition hover:border-neutral-400 hover:bg-zinc-100 dark:border-neutral-700 dark:text-zinc-200 dark:hover:bg-zinc-800"
                                                >
                                                    {{ __('Public Match') }}
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <div class="rounded-xl border border-dashed border-neutral-300 p-4 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                                        {{ __('No matches have been added yet.') }}
                                    </div>
                                @endforelse
                            </div>
                        </section>
                    </section>
                @elseif ($selectedTab === 'crew')
                    <section class="space-y-6">
                        <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Crew') }}</h2>
                                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ __('Publish the staff directory here while keeping the add form inside a modal.') }}
                                    </p>
                                </div>

                                <flux:modal.trigger name="setup-add-crew-modal-{{ $selectedTournament->id }}">
                                    <flux:button variant="primary">
                                        {{ __('Add Crew Member') }}
                                    </flux:button>
                                </flux:modal.trigger>
                            </div>
                        </section>

                        <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                            <div class="mb-4 flex items-center justify-between gap-4">
                                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Crew Directory') }}</h2>
                                <span class="rounded-md border border-neutral-200 px-3 py-1 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                                    {{ trans_choice('{0} No crew entries|{1} :count crew entry|[2,*] :count crew entries', $crewCount, ['count' => $crewCount]) }}
                                </span>
                            </div>

                            <div class="space-y-3">
                                @forelse ($selectedTournament->crewMembers as $crewMember)
                                    <div class="rounded-xl border border-neutral-200 bg-zinc-50 p-4 dark:border-neutral-700 dark:bg-zinc-950">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <div class="font-semibold text-zinc-900 dark:text-white">{{ $crewMember->name }}</div>
                                                <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                                    {{ $crewMember->category }}
                                                    @if ($crewMember->title)
                                                        {{ ' | '.$crewMember->title }}
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                                {{ __('Order: :order', ['order' => $crewMember->sort_order]) }}
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="rounded-xl border border-dashed border-neutral-300 p-4 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                                        {{ __('No crew entries have been added yet.') }}
                                    </div>
                                @endforelse
                            </div>
                        </section>
                    </section>
                @elseif ($selectedTab === 'publish')
                    <section class="space-y-6">
                        <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Publish & Preview') }}</h2>
                                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ __('Review the public-facing snapshot and confirm the tournament is ready before pushing it live.') }}
                                    </p>
                                </div>

                                <a
                                    href="{{ route('admin.tournaments.index', ['tournament' => $selectedTournament->id, 'tab' => 'round-robin']) }}"
                                    wire:navigate
                                    class="inline-flex items-center justify-center rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-zinc-700 transition hover:border-neutral-400 hover:bg-zinc-100 dark:border-neutral-700 dark:text-zinc-200 dark:hover:bg-zinc-800"
                                >
                                    {{ __('Edit Round Robin') }}
                                </a>
                            </div>

                            <div class="mt-5 grid gap-3 md:grid-cols-2">
                                @foreach ($readinessChecks as $check)
                                    <div class="rounded-xl border border-neutral-200 bg-zinc-50 px-4 py-3 dark:border-neutral-700 dark:bg-zinc-950">
                                        <div class="flex items-center gap-3">
                                            <span class="h-2.5 w-2.5 rounded-full {{ $check['ready'] ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                                            <div class="font-medium text-zinc-900 dark:text-white">{{ $check['label'] }}</div>
                                        </div>
                                        <div class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $check['detail'] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </section>

                        @include('admin.tournaments.partials.public-profile-snapshot', [
                            'tournament' => $selectedTournament,
                        ])
                    </section>
                @endif

                @include('admin.tournaments.partials.setup-add-pitch-modal', [
                    'tournament' => $selectedTournament,
                    'show' => $showPitchModal,
                    'redirectTab' => in_array($selectedTab, ['round-robin', 'pitches'], true) ? $selectedTab : 'pitches',
                ])

                @foreach ($selectedTournament->pitches as $pitch)
                    @include('admin.tournaments.partials.setup-edit-pitch-modal', [
                        'tournament' => $selectedTournament,
                        'pitch' => $pitch,
                        'redirectTab' => in_array($selectedTab, ['round-robin', 'pitches'], true) ? $selectedTab : 'pitches',
                    ])
                @endforeach

                @include('admin.tournaments.partials.setup-register-team-modal', [
                    'tournament' => $selectedTournament,
                    'availableTeams' => $availableTeams,
                    'show' => $showRegisterModal,
                ])

                @include('admin.tournaments.partials.setup-add-match-modal', [
                    'tournament' => $selectedTournament,
                    'show' => $showMatchModal,
                    'stageOptions' => $frisbeeStageOptions,
                ])

                @include('admin.tournaments.partials.setup-add-round-robin-match-modal', [
                    'tournament' => $selectedTournament,
                ])

                @foreach ($roundRobinMatches as $match)
                    @include('admin.tournaments.partials.setup-edit-round-robin-match-modal', [
                        'tournament' => $selectedTournament,
                        'match' => $match,
                    ])
                @endforeach

                @include('admin.tournaments.partials.setup-add-crew-modal', [
                    'tournament' => $selectedTournament,
                    'show' => $showCrewModal,
                ])
            @else
                <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <flux:heading size="xl">{{ __('Tournament Scoring Console') }}</flux:heading>
                            <flux:text class="mt-2 max-w-3xl">
                                {{ __('Open the match list below and launch live scoring without full tournament setup access.') }}
                            </flux:text>
                            <div class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $selectedTournament->name }}
                                @if ($selectedTournament->dateRangeLabel())
                                    {{ ' | '.$selectedTournament->dateRangeLabel() }}
                                @endif
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <a
                                href="{{ route('admin.tournaments.list') }}"
                                wire:navigate
                                class="inline-flex items-center justify-center rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-zinc-700 transition hover:border-neutral-400 hover:bg-zinc-100 dark:border-neutral-700 dark:text-zinc-200 dark:hover:bg-zinc-800"
                            >
                                {{ __('Back to Tournaments') }}
                            </a>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                    <div class="mb-4">
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Matches Ready for Scoring') }}</h2>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ __('Use the live scoring link on each match card to record scorers, assisters, minutes, and automatic totals.') }}</p>
                    </div>

                    <div class="space-y-3">
                        @forelse ($selectedTournament->matches as $match)
                            <div class="rounded-xl border border-neutral-200 bg-zinc-50 p-4 dark:border-neutral-700 dark:bg-zinc-950">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-semibold text-zinc-900 dark:text-white">
                                            {{ $match->homeRegistration?->team->name ?? __('TBD') }}
                                            {{ __('vs') }}
                                            {{ $match->awayRegistration?->team->name ?? __('TBD') }}
                                        </div>
                                        <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                            {{ str($match->stage)->replace('_', ' ')->headline() }}
                                            @if ($match->round_label)
                                                {{ ' | '.$match->round_label }}
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-right text-xs text-zinc-500 dark:text-zinc-400">
                                        <div>{{ __('Status: :status', ['status' => str($match->status)->headline()]) }}</div>
                                        @if (! is_null($match->home_score) && ! is_null($match->away_score))
                                            <div class="mt-1">{{ $match->home_score }} - {{ $match->away_score }}</div>
                                        @endif
                                    </div>
                                </div>

                                <div class="mt-3 flex flex-wrap gap-3 text-xs text-zinc-500 dark:text-zinc-400">
                                    <span>{{ __('Match #: :value', ['value' => $match->match_number ?? '-']) }}</span>
                                    <span>{{ __('Pitch: :value', ['value' => $match->pitch?->name ?? '-']) }}</span>
                                    <span>{{ __('Time: :value', ['value' => $match->scheduled_at?->format('M j, Y g:i A') ?? 'TBD']) }}</span>
                                </div>

                                <div class="mt-4 flex flex-wrap gap-2">
                                    @if ($match->homeRegistration && $match->awayRegistration)
                                        <a
                                            href="{{ route('admin.tournaments.matches.scoring', ['tournament' => $selectedTournament, 'match' => $match]) }}"
                                            wire:navigate
                                            class="inline-flex items-center justify-center rounded-lg border border-[#c8d7f8] bg-[#e9f0ff] px-3 py-2 text-sm font-medium text-[#2f55b7] transition hover:border-[#9fb7f2] hover:bg-[#dce8ff]"
                                        >
                                            {{ __('Live Scoring') }}
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-neutral-300 p-4 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                                {{ __('No matches have been added yet.') }}
                            </div>
                        @endforelse
                    </div>
                </section>
            @endif
        @else
            <section class="rounded-xl border border-dashed border-neutral-300 bg-zinc-50 p-6 text-sm text-zinc-600 dark:border-neutral-700 dark:bg-zinc-900 dark:text-zinc-300">
                @if ($totalTournamentCount > 0)
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <div class="font-medium text-zinc-900 dark:text-white">{{ $isAdmin ? __('No tournament selected for setup.') : __('No tournament selected for scoring.') }}</div>
                            <p class="mt-1">
                                {{ $isAdmin
                                    ? __('Open the Tournaments tab, then click Setup on the tournament you want to manage here.')
                                    : __('Open the Tournaments tab, then click Score Matches on the tournament you need to work on.') }}
                            </p>
                        </div>

                        <a
                            href="{{ route('admin.tournaments.list') }}"
                            wire:navigate
                            class="inline-flex items-center justify-center rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-zinc-700 transition hover:border-neutral-400 hover:bg-zinc-100 dark:border-neutral-700 dark:text-zinc-200 dark:hover:bg-zinc-800"
                        >
                            {{ __('Open Tournaments') }}
                        </a>
                    </div>
                @else
                    {{ $isAdmin
                        ? __('Create a tournament above to unlock pitch setup, team registration, and the rest of the setup workflow.')
                        : __('An administrator needs to create a tournament before live scoring can begin.') }}
                @endif
            </section>
        @endif
    </div>
</x-layouts::app>
