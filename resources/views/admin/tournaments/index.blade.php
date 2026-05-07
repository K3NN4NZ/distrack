@php
    $bracketTeamLimit = \App\Http\Controllers\Admin\TournamentController::BRACKET_TEAM_LIMIT;
    $minimumBracketTeamCount = \App\Http\Controllers\Admin\TournamentController::MINIMUM_BRACKET_TEAM_COUNT;
    $user = auth()->user();
    $isAdmin = $user->isAdmin();
    $canEnterScores = $user->canEnterScores();

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
    $roundRobinMatchesByPitch = $roundRobinMatches
        ->groupBy(fn ($match): string => $match->pitch_id ? (string) $match->pitch_id : 'unassigned')
        ->map(fn ($matches) => $matches->values());
    $unassignedRoundRobinMatches = $roundRobinMatchesByPitch->get('unassigned', collect());
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
                    : __('Open a tournament from the directory to review the match list and enter final scores without full tournament setup access.') }}
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
                        @case('bracket-ranking-applied')
                            {{ __('Bracket ranks saved from round robin standings.') }}
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

                            @if ($canEnterScores && $firstScorableMatch)
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
                        @include('admin.tournaments.partials.game-score-dashboard', [
                            'selectedTournament' => $selectedTournament,
                            'canEnterScores' => $canEnterScores,
                            'canCreateMatches' => $canCreateMatches,
                            'showAddMatchButton' => false,
                            'showPublicLinks' => $selectedTournament->is_public,
                        ])

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
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Round Robin') }}</h2>
                                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ __('Robins are created manually. Use the seeded brackets below as a guide, then add each pairing yourself.') }}
                                    </p>
                                </div>
                            </div>

                            @if ($errors->has('round_robin'))
                                <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
                                    {{ $errors->first('round_robin') }}
                                </div>
                            @endif

                            <div class="mt-5 flex gap-3 overflow-x-auto pb-1">
                                <div class="min-w-[160px] flex-1 rounded-2xl border border-neutral-200 bg-gradient-to-br from-white to-zinc-50 px-4 py-4 shadow-sm dark:border-neutral-700 dark:from-zinc-900 dark:to-zinc-950">
                                    <div class="text-xs font-semibold uppercase tracking-[0.16em] text-zinc-500 dark:text-zinc-400">{{ __('Brackets') }}</div>
                                    <div class="mt-3 text-3xl font-semibold text-zinc-900 dark:text-white">{{ $seededBracketGroups->count() }}</div>
                                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Seed groups ready for manual pairing') }}</div>
                                </div>
                                <div class="min-w-[160px] flex-1 rounded-2xl border border-neutral-200 bg-gradient-to-br from-white to-zinc-50 px-4 py-4 shadow-sm dark:border-neutral-700 dark:from-zinc-900 dark:to-zinc-950">
                                    <div class="text-xs font-semibold uppercase tracking-[0.16em] text-zinc-500 dark:text-zinc-400">{{ __('Teams') }}</div>
                                    <div class="mt-3 text-3xl font-semibold text-zinc-900 dark:text-white">{{ $teamCount }}</div>
                                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Registered teams in this setup') }}</div>
                                </div>
                                <div class="min-w-[160px] flex-1 rounded-2xl border border-neutral-200 bg-gradient-to-br from-white to-zinc-50 px-4 py-4 shadow-sm dark:border-neutral-700 dark:from-zinc-900 dark:to-zinc-950">
                                    <div class="text-xs font-semibold uppercase tracking-[0.16em] text-zinc-500 dark:text-zinc-400">{{ __('Pitches') }}</div>
                                    <div class="mt-3 text-3xl font-semibold text-zinc-900 dark:text-white">{{ $pitchCount }}</div>
                                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Available fields for scheduling') }}</div>
                                </div>
                                <div class="min-w-[160px] flex-1 rounded-2xl border border-neutral-200 bg-gradient-to-br from-white to-zinc-50 px-4 py-4 shadow-sm dark:border-neutral-700 dark:from-zinc-900 dark:to-zinc-950">
                                    <div class="text-xs font-semibold uppercase tracking-[0.16em] text-zinc-500 dark:text-zinc-400">{{ __('Robins') }}</div>
                                    <div class="mt-3 text-3xl font-semibold text-zinc-900 dark:text-white">{{ $roundRobinMatches->count() }}</div>
                                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Round robin games already added') }}</div>
                                </div>
                            </div>

                            <div class="mt-4 rounded-xl border border-dashed border-neutral-300 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-neutral-700 dark:bg-zinc-950 dark:text-zinc-300">
                                {{ __('Use the current seeded brackets below as a reference while creating the round robin schedule manually.') }}
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

                            @if ($seededBracketGroups->isEmpty())
                                <p class="mt-4 text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ __('Teams are not seeded into brackets yet. You can still add manual robins, but the bracket reference list will stay empty until seeding is done.') }}
                                </p>
                            @endif
                        </section>

                        @php
                            $assignedRoundRobinMatches = $roundRobinMatches->filter(fn ($match) => $match->pitch_id);
                            $totalRobinCount = $roundRobinMatches->count();
                            $completedRobinCount = $roundRobinMatches->filter(fn ($match) => $match->status === 'completed')->count();
                            $liveRobinCount = $roundRobinMatches->filter(fn ($match) => $match->status === 'live')->count();
                            $scheduledRobinCount = $roundRobinMatches->filter(fn ($match) => $match->status === 'scheduled')->count();
                        @endphp

                        <section class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-zinc-900">
                            <div class="flex flex-col gap-4 border-b border-neutral-200 bg-gradient-to-br from-zinc-50 to-white px-6 py-5 sm:flex-row sm:items-start sm:justify-between dark:border-neutral-700 dark:from-zinc-900 dark:to-zinc-900">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-[#2f55b7]/10 text-[#2f55b7] dark:bg-[#2f55b7]/20 dark:text-blue-300">
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <rect x="3" y="4" width="18" height="16" rx="2" />
                                                <path d="M3 10h18" />
                                                <path d="M9 4v16" />
                                                <path d="M15 4v16" />
                                            </svg>
                                        </span>
                                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Round Robin Board') }}</h2>
                                    </div>
                                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ __('Day 1 robins grouped by pitch.') }}
                                    </p>

                                    <div class="mt-4 flex flex-wrap gap-2 text-xs">
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-neutral-200 bg-white px-3 py-1 font-medium text-zinc-700 dark:border-neutral-700 dark:bg-zinc-950 dark:text-zinc-200">
                                            <span class="h-1.5 w-1.5 rounded-full bg-zinc-400"></span>
                                            {{ trans_choice('{0} No robins|{1} :count robin total|[2,*] :count robins total', $totalRobinCount, ['count' => $totalRobinCount]) }}
                                        </span>
                                        @if ($scheduledRobinCount > 0)
                                            <span class="inline-flex items-center gap-1.5 rounded-full border border-blue-200 bg-blue-50 px-3 py-1 font-medium text-blue-700 dark:border-blue-900/60 dark:bg-blue-950/30 dark:text-blue-200">
                                                <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                                                {{ __(':count scheduled', ['count' => $scheduledRobinCount]) }}
                                            </span>
                                        @endif
                                        @if ($liveRobinCount > 0)
                                            <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 font-medium text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200">
                                                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-500"></span>
                                                {{ __(':count live', ['count' => $liveRobinCount]) }}
                                            </span>
                                        @endif
                                        @if ($completedRobinCount > 0)
                                            <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 font-medium text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-200">
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                                {{ __(':count completed', ['count' => $completedRobinCount]) }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <flux:modal.trigger name="setup-add-pitch-modal-{{ $selectedTournament->id }}">
                                        <flux:button variant="ghost">
                                            {{ __('Add Pitch') }}
                                        </flux:button>
                                    </flux:modal.trigger>

                                    <flux:modal.trigger name="setup-add-round-robin-match-modal-{{ $selectedTournament->id }}">
                                        <flux:button variant="primary" :disabled="! $canCreateMatches">
                                            {{ __('Add Robin') }}
                                        </flux:button>
                                    </flux:modal.trigger>
                                </div>
                            </div>

                            <div class="min-h-[26rem] overflow-x-auto bg-zinc-50/40 px-6 py-8 dark:bg-zinc-950/40">
                                <div class="mx-auto mb-10 flex max-w-xs items-center gap-3">
                                    <span class="h-px flex-1 bg-gradient-to-r from-transparent to-neutral-300 dark:to-neutral-700"></span>
                                    <span class="rounded-full bg-zinc-900 px-4 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-white dark:bg-white dark:text-zinc-900">
                                        {{ __('Day 1') }}
                                    </span>
                                    <span class="h-px flex-1 bg-gradient-to-l from-transparent to-neutral-300 dark:to-neutral-700"></span>
                                </div>

                                @if ($selectedTournament->pitches->isNotEmpty())
                                    @php
                                        $pitchesList = $selectedTournament->pitches;
                                        $pitchCount = $pitchesList->count();
                                        $timeSlotKey = function ($scheduledAt) {
                                            return $scheduledAt ? $scheduledAt->format('Y-m-d H:i') : 'unscheduled';
                                        };

                                        $timeSlotMap = [];
                                        $matchesByTimeAndPitch = [];

                                        foreach ($assignedRoundRobinMatches as $assignedMatch) {
                                            $slotKey = $timeSlotKey($assignedMatch->scheduled_at);
                                            $timeSlotMap[$slotKey] = $assignedMatch->scheduled_at;
                                            $matchesByTimeAndPitch[$slotKey][$assignedMatch->pitch_id][] = $assignedMatch;
                                        }

                                        if (empty($timeSlotMap)) {
                                            $timeSlotMap = ['unscheduled' => null];
                                        }

                                        uksort($timeSlotMap, function ($a, $b) {
                                            if ($a === 'unscheduled') return 1;
                                            if ($b === 'unscheduled') return -1;
                                            return strcmp($a, $b);
                                        });

                                        $gridTemplate = '7rem repeat('.max(1, $pitchCount).', minmax(12rem, 1fr))';
                                    @endphp

                                    <div class="min-w-[64rem]">
                                        <div class="grid gap-3" style="grid-template-columns: {{ $gridTemplate }};">
                                            <div></div>
                                            @foreach ($pitchesList as $pitch)
                                                @php
                                                    $pitchLabel = str($pitch->name)->lower()->startsWith('pitch') ? $pitch->name : __('Pitch :name', ['name' => $pitch->name]);
                                                    $pitchMatchCount = $roundRobinMatchesByPitch->get((string) $pitch->id, collect())->count();
                                                @endphp
                                                <div class="flex items-center justify-between gap-2 rounded-xl border border-neutral-200 bg-white px-3 py-2 dark:border-neutral-700 dark:bg-zinc-900">
                                                    <div class="flex min-w-0 items-center gap-2">
                                                        <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-[#2f55b7]/10 text-[#2f55b7] dark:bg-[#2f55b7]/20 dark:text-blue-300">
                                                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                                <path d="M12 21s-7-6.5-7-12a7 7 0 1 1 14 0c0 5.5-7 12-7 12Z" />
                                                                <circle cx="12" cy="9" r="2.5" />
                                                            </svg>
                                                        </span>
                                                        <div class="min-w-0">
                                                            <div class="truncate text-xs font-semibold uppercase tracking-wider text-zinc-900 dark:text-white">{{ $pitchLabel }}</div>
                                                            <div class="text-[10px] text-zinc-500 dark:text-zinc-400">
                                                                {{ trans_choice('{0} No robins|{1} :count robin|[2,*] :count robins', $pitchMatchCount, ['count' => $pitchMatchCount]) }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <flux:modal.trigger name="setup-add-round-robin-match-modal-{{ $selectedTournament->id }}-pitch-{{ $pitch->id }}">
                                                        <button
                                                            type="button"
                                                            @disabled(! $canCreateMatches)
                                                            class="inline-flex shrink-0 items-center gap-1 rounded-md border border-neutral-200 bg-white px-1.5 py-0.5 text-[10px] font-medium text-zinc-700 transition hover:border-[#2f55b7] hover:text-[#2f55b7] disabled:cursor-not-allowed disabled:opacity-60 dark:border-neutral-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:border-blue-400 dark:hover:text-blue-300"
                                                        >
                                                            <svg class="h-2.5 w-2.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                                <path d="M10 4a.75.75 0 0 1 .75.75v4.5h4.5a.75.75 0 0 1 0 1.5h-4.5v4.5a.75.75 0 0 1-1.5 0v-4.5h-4.5a.75.75 0 0 1 0-1.5h4.5v-4.5A.75.75 0 0 1 10 4Z" />
                                                            </svg>
                                                            {{ __('Add') }}
                                                        </button>
                                                    </flux:modal.trigger>
                                                </div>
                                            @endforeach
                                        </div>

                                        <div class="mt-3 grid gap-3" style="grid-template-columns: {{ $gridTemplate }};">
                                            @foreach ($timeSlotMap as $slotKey => $slotDateTime)
                                                <div class="flex items-center justify-end pr-2 text-right">
                                                    @if ($slotDateTime)
                                                        <div>
                                                            <div class="text-sm font-semibold text-zinc-900 dark:text-white">
                                                                {{ $slotDateTime->format('g:i A') }}
                                                            </div>
                                                            <div class="text-[10px] uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                                                {{ $slotDateTime->format('M j') }}
                                                            </div>
                                                        </div>
                                                    @else
                                                        <span class="rounded-full bg-zinc-200 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                                                            {{ __('TBD') }}
                                                        </span>
                                                    @endif
                                                </div>

                                                @foreach ($pitchesList as $pitch)
                                                    @php
                                                        $cellMatches = $matchesByTimeAndPitch[$slotKey][$pitch->id] ?? [];
                                                    @endphp

                                                    <div class="flex flex-col gap-2">
                                                        @forelse ($cellMatches as $match)
                                                            @php
                                                                $homeReg = $match->homeRegistration;
                                                                $awayReg = $match->awayRegistration;
                                                                $homeName = $homeReg?->team?->name ?? __('TBD');
                                                                $awayName = $awayReg?->team?->name ?? __('TBD');
                                                                $homeSeed = $homeReg?->seed_number;
                                                                $awaySeed = $awayReg?->seed_number;
                                                                $matchStatus = $match->status ?? 'scheduled';
                                                                $hasResult = $matchStatus === 'completed' && $match->home_score !== null && $match->away_score !== null;
                                                                $homeWins = $hasResult && $match->home_score > $match->away_score;
                                                                $awayWins = $hasResult && $match->away_score > $match->home_score;
                                                                $statusStyles = match ($matchStatus) {
                                                                    'completed' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
                                                                    'live' => 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
                                                                    default => 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300',
                                                                };
                                                                $statusLabel = str($matchStatus)->headline();
                                                                $robinLabel = $match->round_label ?: __('Robin :number', ['number' => $match->match_number ?? '—']);
                                                            @endphp

                                                            <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white transition hover:border-[#2f55b7]/60 hover:shadow-md dark:border-neutral-700 dark:bg-zinc-950 dark:hover:border-blue-400/60">
                                                                <div class="flex items-center justify-between gap-2 border-b border-neutral-200 bg-zinc-50/60 px-3 py-1.5 dark:border-neutral-700 dark:bg-zinc-900/60">
                                                                    <span class="truncate text-[11px] font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                                                        {{ $robinLabel }}
                                                                    </span>
                                                                    <span class="inline-flex shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider {{ $statusStyles }}">
                                                                        @if ($matchStatus === 'live')
                                                                            <span class="mr-1 h-1.5 w-1.5 animate-pulse self-center rounded-full bg-amber-500"></span>
                                                                        @endif
                                                                        {{ $statusLabel }}
                                                                    </span>
                                                                </div>

                                                                <div class="px-3 py-3">
                                                                    <div class="flex items-center justify-between gap-2 {{ $homeWins ? 'font-semibold text-zinc-900 dark:text-white' : 'text-zinc-700 dark:text-zinc-200' }}">
                                                                        <div class="flex min-w-0 items-center gap-2">
                                                                            @if ($homeSeed)
                                                                                <span class="inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-md bg-[#2f55b7]/10 px-1 text-[10px] font-bold text-[#2f55b7] dark:bg-[#2f55b7]/20 dark:text-blue-300">
                                                                                    {{ $homeSeed }}
                                                                                </span>
                                                                            @endif
                                                                            <span class="truncate text-sm">{{ $homeName }}</span>
                                                                        </div>
                                                                        @if ($hasResult)
                                                                            <span class="shrink-0 text-base font-bold tabular-nums {{ $homeWins ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-500 dark:text-zinc-400' }}">{{ $match->home_score }}</span>
                                                                        @endif
                                                                    </div>

                                                                    <div class="my-1 flex items-center gap-2">
                                                                        <span class="h-px flex-1 bg-neutral-200 dark:bg-neutral-700"></span>
                                                                        <span class="text-[10px] font-bold uppercase tracking-[0.15em] text-zinc-400 dark:text-zinc-500">{{ __('vs') }}</span>
                                                                        <span class="h-px flex-1 bg-neutral-200 dark:bg-neutral-700"></span>
                                                                    </div>

                                                                    <div class="flex items-center justify-between gap-2 {{ $awayWins ? 'font-semibold text-zinc-900 dark:text-white' : 'text-zinc-700 dark:text-zinc-200' }}">
                                                                        <div class="flex min-w-0 items-center gap-2">
                                                                            @if ($awaySeed)
                                                                                <span class="inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-md bg-amber-500/10 px-1 text-[10px] font-bold text-amber-700 dark:bg-amber-500/20 dark:text-amber-300">
                                                                                    {{ $awaySeed }}
                                                                                </span>
                                                                            @endif
                                                                            <span class="truncate text-sm">{{ $awayName }}</span>
                                                                        </div>
                                                                        @if ($hasResult)
                                                                            <span class="shrink-0 text-base font-bold tabular-nums {{ $awayWins ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-500 dark:text-zinc-400' }}">{{ $match->away_score }}</span>
                                                                        @endif
                                                                    </div>
                                                                </div>

                                                                <div class="flex gap-1 border-t border-neutral-200 bg-zinc-50/60 px-2 py-1.5 dark:border-neutral-700 dark:bg-zinc-900/60">
                                                                    @if ($canEnterScores && $homeReg && $awayReg)
                                                                        <a
                                                                            href="{{ route('admin.tournaments.matches.scoring', ['tournament' => $selectedTournament, 'match' => $match]) }}"
                                                                            wire:navigate
                                                                            class="inline-flex flex-1 items-center justify-center gap-1 rounded-md bg-[#2f55b7] px-2 py-1.5 text-[11px] font-semibold text-white transition hover:bg-[#244591]"
                                                                        >
                                                                            <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                                                <path d="M10 2a.75.75 0 0 1 .75.75v.5h2.5a1.75 1.75 0 0 1 1.75 1.75V16.5A1.75 1.75 0 0 1 13.25 18.25H6.75A1.75 1.75 0 0 1 5 16.5V5a1.75 1.75 0 0 1 1.75-1.75h2.5v-.5A.75.75 0 0 1 10 2ZM7.5 8.5a.75.75 0 0 0 0 1.5h5a.75.75 0 0 0 0-1.5h-5Zm0 3a.75.75 0 0 0 0 1.5h5a.75.75 0 0 0 0-1.5h-5Z" />
                                                                            </svg>
                                                                            {{ __('Score') }}
                                                                        </a>
                                                                    @endif

                                                                    <flux:modal.trigger name="setup-edit-round-robin-match-modal-{{ $match->id }}">
                                                                        <button type="button" class="inline-flex flex-1 items-center justify-center gap-1 rounded-md border border-neutral-300 bg-white px-2 py-1.5 text-[11px] font-semibold text-zinc-700 transition hover:border-neutral-400 hover:bg-zinc-100 dark:border-neutral-700 dark:bg-zinc-950 dark:text-zinc-200 dark:hover:bg-zinc-800">
                                                                            <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                                                <path d="M2.695 14.763l-1.262 3.154a.5.5 0 0 0 .65.65l3.155-1.262a4 4 0 0 0 1.343-.885L17.5 5.5a2.121 2.121 0 0 0-3-3L3.58 13.42a4 4 0 0 0-.885 1.343Z" />
                                                                            </svg>
                                                                            {{ __('Edit') }}
                                                                        </button>
                                                                    </flux:modal.trigger>
                                                                </div>
                                                            </div>
                                                        @empty
                                                            <flux:modal.trigger name="setup-add-round-robin-match-modal-{{ $selectedTournament->id }}-pitch-{{ $pitch->id }}">
                                                                <button
                                                                    type="button"
                                                                    @disabled(! $canCreateMatches)
                                                                    class="flex h-full min-h-24 w-full flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-neutral-300 px-2 py-4 text-center text-[10px] text-zinc-400 transition hover:border-[#2f55b7] hover:bg-[#2f55b7]/5 hover:text-[#2f55b7] disabled:cursor-not-allowed disabled:opacity-60 dark:border-neutral-700 dark:text-zinc-500 dark:hover:border-blue-400 dark:hover:bg-blue-500/5 dark:hover:text-blue-300"
                                                                >
                                                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                                        <path d="M10 4a.75.75 0 0 1 .75.75v4.5h4.5a.75.75 0 0 1 0 1.5h-4.5v4.5a.75.75 0 0 1-1.5 0v-4.5h-4.5a.75.75 0 0 1 0-1.5h4.5v-4.5A.75.75 0 0 1 10 4Z" />
                                                                    </svg>
                                                                </button>
                                                            </flux:modal.trigger>
                                                        @endforelse
                                                    </div>
                                                @endforeach
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    <div class="flex min-h-72 flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-neutral-300 bg-white px-6 py-10 text-center dark:border-neutral-700 dark:bg-zinc-900">
                                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <path d="M12 21s-7-6.5-7-12a7 7 0 1 1 14 0c0 5.5-7 12-7 12Z" />
                                                <circle cx="12" cy="9" r="2.5" />
                                            </svg>
                                        </span>
                                        <div>
                                            <div class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('No pitches yet') }}</div>
                                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ __('Add a pitch to start building the Day 1 board.') }}</p>
                                        </div>
                                        <flux:modal.trigger name="setup-add-pitch-modal-{{ $selectedTournament->id }}">
                                            <flux:button variant="primary" size="sm">
                                                {{ __('Add Pitch') }}
                                            </flux:button>
                                        </flux:modal.trigger>
                                    </div>
                                @endif
                            </div>

                            @if ($unassignedRoundRobinMatches->isNotEmpty())
                                <div class="border-t border-amber-200 bg-amber-50/70 px-6 py-4 dark:border-amber-900/70 dark:bg-amber-950/20">
                                    <div class="flex items-center gap-2">
                                        <svg class="h-4 w-4 text-amber-600 dark:text-amber-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 6a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 6Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
                                        </svg>
                                        <div class="text-sm font-semibold text-amber-900 dark:text-amber-100">
                                            {{ __('Unassigned Robins') }}
                                            <span class="ml-1 text-xs font-normal text-amber-700 dark:text-amber-300">
                                                ({{ trans_choice('{1} :count robin needs a pitch|[2,*] :count robins need a pitch', $unassignedRoundRobinMatches->count(), ['count' => $unassignedRoundRobinMatches->count()]) }})
                                            </span>
                                        </div>
                                    </div>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach ($unassignedRoundRobinMatches as $match)
                                            <flux:modal.trigger name="setup-edit-round-robin-match-modal-{{ $match->id }}">
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center gap-2 rounded-lg border border-amber-200 bg-white px-3 py-2 text-sm text-zinc-800 transition hover:border-amber-400 hover:bg-amber-50 dark:border-amber-900/70 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-amber-950/40"
                                                >
                                                    <span class="text-[11px] font-semibold uppercase tracking-wider text-amber-700 dark:text-amber-300">
                                                        {{ $match->round_label ?: __('Robin :number', ['number' => $match->match_number ?? '—']) }}
                                                    </span>
                                                    <span class="font-medium">{{ $match->homeRegistration?->team->name ?? __('TBD') }}</span>
                                                    <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('vs') }}</span>
                                                    <span class="font-medium">{{ $match->awayRegistration?->team->name ?? __('TBD') }}</span>
                                                </button>
                                            </flux:modal.trigger>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </section>

                    </section>
                @elseif ($selectedTab === 'teams')
                    <section class="space-y-6">
                        @if ($bracketRankingPreview ?? null)
                            <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Bracket ranking') }}</h2>
                                        <p class="mt-1 max-w-3xl text-sm text-zinc-600 dark:text-zinc-300">
                                            {{ __('After round robin, ranks are computed from completed games (stage: round robin) within each bracket. Apply writes A1, A2, … onto each team for the public bracket tab and scheduling.') }}
                                        </p>
                                    </div>
                                    @if ($isAdmin && ! empty($bracketRankingPreview['brackets']))
                                        <form
                                            method="POST"
                                            action="{{ route('admin.tournaments.bracket-ranking.apply', $selectedTournament) }}"
                                            class="shrink-0"
                                        >
                                            @csrf
                                            <input type="hidden" name="redirect_route" value="admin.tournaments.index">
                                            <input type="hidden" name="redirect_tab" value="teams">
                                            <flux:button type="submit" variant="primary">
                                                {{ __('Apply ranks to teams') }}
                                            </flux:button>
                                        </form>
                                    @endif
                                </div>

                                @if (! empty($bracketRankingPreview['empty_reason']))
                                    <div class="mt-4 rounded-lg border border-dashed border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-950/60 dark:text-zinc-300">
                                        {{ $bracketRankingPreview['empty_reason'] }}
                                    </div>
                                @else
                                    <div class="mt-5 space-y-8">
                                        @foreach ($bracketRankingPreview['brackets'] as $b)
                                            <div>
                                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">{{ $b['code'] }}</h3>
                                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                                        {{ trans_choice('{1} :count completed game|[2,*] :count completed games', $b['completed_matches'], ['count' => $b['completed_matches']]) }}
                                                        ·
                                                        {{ trans_choice('{1} :count team|[2,*] :count teams', $b['registered_teams'], ['count' => $b['registered_teams']]) }}
                                                    </span>
                                                </div>
                                                <div class="mt-3 overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-700">
                                                    <table class="min-w-[40rem] w-full text-left text-sm">
                                                        <thead class="border-b border-neutral-200 bg-zinc-50 text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:border-neutral-700 dark:bg-zinc-900 dark:text-zinc-400">
                                                            <tr>
                                                                <th class="px-4 py-2.5">{{ __('Rank') }}</th>
                                                                <th class="px-4 py-2.5">{{ __('Team') }}</th>
                                                                <th class="px-4 py-2.5">{{ __('Pld') }}</th>
                                                                <th class="px-4 py-2.5">{{ __('W') }}</th>
                                                                <th class="px-4 py-2.5">{{ __('L') }}</th>
                                                                <th class="px-4 py-2.5">{{ __('D') }}</th>
                                                                <th class="px-4 py-2.5">{{ __('Pts') }}</th>
                                                                <th class="px-4 py-2.5">{{ __('GD') }}</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                                                            @foreach ($b['standings'] as $row)
                                                                <tr class="bg-white dark:bg-zinc-900">
                                                                    <td class="px-4 py-2.5 font-semibold text-zinc-900 dark:text-white">{{ $row['proposed_rank'] }}</td>
                                                                    <td class="px-4 py-2.5 text-zinc-800 dark:text-zinc-100">{{ $row['team_name'] }}</td>
                                                                    <td class="px-4 py-2.5 text-zinc-600 dark:text-zinc-300">{{ $row['played'] }}</td>
                                                                    <td class="px-4 py-2.5 text-zinc-600 dark:text-zinc-300">{{ $row['wins'] }}</td>
                                                                    <td class="px-4 py-2.5 text-zinc-600 dark:text-zinc-300">{{ $row['losses'] }}</td>
                                                                    <td class="px-4 py-2.5 text-zinc-600 dark:text-zinc-300">{{ $row['ties'] }}</td>
                                                                    <td class="px-4 py-2.5 text-zinc-600 dark:text-zinc-300">{{ $row['points'] }}</td>
                                                                    <td class="px-4 py-2.5 text-zinc-600 dark:text-zinc-300">{{ $row['goal_difference'] }}</td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                                @if ($b['completed_matches'] === 0)
                                                    <p class="mt-2 text-xs text-amber-700 dark:text-amber-300">
                                                        {{ __('No completed round robin games for this bracket yet—ranks default to seed order.') }}
                                                    </p>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </section>
                        @endif

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
                                            <span>{{ __('RR rank: :value', ['value' => $registration->bracket_rank ?? '-']) }}</span>
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
                        @include('admin.tournaments.partials.game-score-dashboard', [
                            'selectedTournament' => $selectedTournament,
                            'canEnterScores' => $canEnterScores,
                            'canCreateMatches' => $canCreateMatches,
                            'showAddMatchButton' => true,
                            'showPublicLinks' => $selectedTournament->is_public,
                        ])
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

                @foreach ($selectedTournament->pitches as $pitch)
                    @include('admin.tournaments.partials.setup-add-round-robin-match-modal', [
                        'tournament' => $selectedTournament,
                        'pitch' => $pitch,
                    ])
                @endforeach

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
                                {{ __('Open the match list below and enter final scores without full tournament setup access.') }}
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

                @include('admin.tournaments.partials.game-score-dashboard', [
                    'selectedTournament' => $selectedTournament,
                    'canEnterScores' => $canEnterScores,
                    'canCreateMatches' => $canCreateMatches,
                    'showAddMatchButton' => false,
                    'showPublicLinks' => false,
                ])
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
                        : __('An administrator needs to create a tournament before score entry can begin.') }}
                @endif
            </section>
        @endif
    </div>
</x-layouts::app>
