@php
    use App\Support\AdminTournamentTabStatusPresentation;

    $dashboardTab = $dashboardTab ?? null;
    $dashboardMatches = collect($matches ?? $selectedTournament->matches ?? [])
        ->sortBy([
            fn ($left, $right) => ($left->scheduled_at?->getTimestamp() ?? PHP_INT_MAX) <=> ($right->scheduled_at?->getTimestamp() ?? PHP_INT_MAX),
            fn ($left, $right) => ($left->match_number ?? PHP_INT_MAX) <=> ($right->match_number ?? PHP_INT_MAX),
            fn ($left, $right) => $left->id <=> $right->id,
        ])
        ->values();
    $dashboardPageSize = $pageSize ?? 6;
    $showAddMatchButton = $showAddMatchButton ?? false;
    $showPublicLinks = $showPublicLinks ?? false;
    $manageScoringPitchScoped = $manageScoringPitchScoped ?? false;
    $dashboardTitle = $dashboardTitle ?? __('Games Dashboard');
    $dashboardIntro = $dashboardIntro ?? null;
    $dashboardEmptyMessage = $dashboardEmptyMessage ?? null;
    $tournamentMatchTotal = collect($selectedTournament->matches ?? [])->count();
    $totalGames = $dashboardMatches->count();
    $scheduledGames = $dashboardMatches->where('status', 'scheduled')->count();
    $liveGames = $dashboardMatches->where('status', 'live')->count();
    $completedGames = $dashboardMatches->where('status', 'completed')->count();
    $missingTeamsGames = $dashboardMatches
        ->filter(fn ($match): bool => ! $match->homeRegistration || ! $match->awayRegistration)
        ->count();

    $primaryChips = AdminTournamentTabStatusPresentation::gameDashboardPrimaryStatusChips($dashboardTab);

    $statusCards = [
        [
            'label' => __('Total Games'),
            'count' => $totalGames,
            'detail' => __('All scheduled tournament games'),
            'tone' => 'border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900',
            'filter' => 'all',
        ],
    ];

    foreach ($primaryChips as $chip) {
        $filter = $chip['filter'];
        $count = match ($filter) {
            'scheduled' => $scheduledGames,
            'live' => $liveGames,
            'completed' => $completedGames,
            default => 0,
        };
        $detail = match ($filter) {
            'scheduled' => __('Not started yet'),
            'live' => __('Currently in play'),
            'completed' => __('Scores can be reviewed'),
            default => '',
        };
        $tone = match ($filter) {
            'scheduled' => 'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-950',
            'live' => 'border-sky-200 bg-sky-50 dark:border-sky-900/70 dark:bg-sky-950/30',
            'completed' => 'border-emerald-200 bg-emerald-50 dark:border-emerald-900/70 dark:bg-emerald-950/30',
            default => 'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-950',
        };
        $statusCards[] = [
            'label' => $chip['label'],
            'count' => $count,
            'detail' => $detail,
            'tone' => $tone,
            'filter' => $filter,
        ];
    }

    if ($dashboardTab !== AdminTournamentTabStatusPresentation::TAB_QUARTER_FINAL) {
        $statusCards[] = [
            'label' => __('Need Teams'),
            'count' => $missingTeamsGames,
            'detail' => __('Missing home or away team'),
            'tone' => 'border-amber-200 bg-amber-50 dark:border-amber-900/70 dark:bg-amber-950/30',
            'filter' => 'needs-teams',
        ];
    }

    $isQuarterFinalDashboard = $dashboardTab === AdminTournamentTabStatusPresentation::TAB_QUARTER_FINAL;
@endphp

<section
    class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900"
    data-game-dashboard
    data-page-size="{{ $dashboardPageSize }}"
>
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ $dashboardTitle }}</h2>
            @if (filled($dashboardIntro))
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                    {{ $dashboardIntro }}
                </p>
            @else
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                    {{ $manageScoringPitchScoped
                        ? __('Games on your assigned fields and on fields without an assigned scorekeeper appear here. Open Manage Scoring to enter or update the score sheet.')
                        : __('Use this view to find a game, check its status, and open the score sheet.') }}
                </p>
            @endif
        </div>

        @if ($showAddMatchButton)
            <flux:modal.trigger name="setup-add-match-modal-{{ $selectedTournament->id }}">
                <flux:button variant="primary" :disabled="(!$canCreateMatches)">
                    {{ __('Add Match') }}
                </flux:button>
            </flux:modal.trigger>
        @endif
    </div>

    <div @class([
        'mt-5 grid gap-3 sm:grid-cols-2',
        'xl:grid-cols-5' => ! $isQuarterFinalDashboard,
        'xl:grid-cols-4' => $isQuarterFinalDashboard,
    ])>
        @foreach ($statusCards as $card)
            <button
                type="button"
                class="rounded-xl border px-4 py-3 text-left transition hover:-translate-y-0.5 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-[#2f55b7]/40 {{ $card['tone'] }}"
                data-game-dashboard-filter="{{ $card['filter'] }}"
                aria-pressed="{{ $card['filter'] === 'all' ? 'true' : 'false' }}"
            >
                <div class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500 dark:text-zinc-400">{{ $card['label'] }}</div>
                <div class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-white">{{ $card['count'] }}</div>
                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $card['detail'] }}</div>
            </button>
        @endforeach
    </div>

    <div class="mt-5 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <label class="block w-full text-sm font-medium text-zinc-700 lg:max-w-md dark:text-zinc-300">
            <span class="sr-only">{{ __('Search games') }}</span>
            <input
                type="search"
                placeholder="{{ __('Search team, pitch, stage, status') }}"
                class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                data-game-dashboard-search
            >
        </label>

        <div class="text-sm text-zinc-500 dark:text-zinc-400">
            <span data-game-dashboard-visible-count>{{ $totalGames }}</span>
            {{ __('shown') }}
            <span class="mx-1">/</span>
            {{ trans_choice('{0} 0 total games|{1} :count total game|[2,*] :count total games', $totalGames, ['count' => $totalGames]) }}
        </div>
    </div>

    <div class="mt-4 grid gap-3 lg:grid-cols-2" data-game-dashboard-list>
        @forelse ($dashboardMatches as $match)
            @php
                $homeName = $match->homeRegistration?->team?->name ?? __('TBD');
                $awayName = $match->awayRegistration?->team?->name ?? __('TBD');
                $statusLabel = AdminTournamentTabStatusPresentation::statusBadgeLabel(
                    (string) $match->status,
                    $dashboardTab,
                );
                $statusTone = match ($match->status) {
                    'completed' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
                    'live' => 'bg-sky-100 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300',
                    default => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200',
                };
            @endphp

            <article
                class="rounded-xl border border-neutral-200 bg-zinc-50 p-4 dark:border-neutral-700 dark:bg-zinc-950"
                data-game-dashboard-item
                data-game-status="{{ $match->status }}"
                data-needs-teams="{{ (! $match->homeRegistration || ! $match->awayRegistration) ? 'true' : 'false' }}"
            >
                <div class="flex flex-col gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-md px-2.5 py-1 text-xs font-semibold {{ $statusTone }}">{{ $statusLabel }}</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Game #:value', ['value' => $match->match_number ?? '-']) }}</span>
                        </div>

                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            <div class="{{ \App\Support\MatchTeamBoxResultPresentation::teamBoxClasses($match, 'home') }}">
                                <div class="text-[10px] font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-400">{{ __('Home') }}</div>
                                <div class="mt-1 truncate text-base font-semibold text-zinc-900 dark:text-white">{{ $homeName }}</div>
                                @if (! is_null($match->home_score) && ! is_null($match->away_score))
                                    <div class="mt-2 text-2xl font-bold tabular-nums text-zinc-900 dark:text-white">{{ $match->home_score }}</div>
                                @endif
                            </div>
                            <div class="{{ \App\Support\MatchTeamBoxResultPresentation::teamBoxClasses($match, 'away') }}">
                                <div class="text-[10px] font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-400">{{ __('Away') }}</div>
                                <div class="mt-1 truncate text-base font-semibold text-zinc-900 dark:text-white">{{ $awayName }}</div>
                                @if (! is_null($match->home_score) && ! is_null($match->away_score))
                                    <div class="mt-2 text-2xl font-bold tabular-nums text-zinc-900 dark:text-white">{{ $match->away_score }}</div>
                                @endif
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm text-zinc-600 dark:text-zinc-300">
                            <span>{{ str($match->stage)->replace('_', ' ')->headline() }}</span>
                            @if ($match->round_label)
                                <span>{{ $match->round_label }}</span>
                            @endif
                            <span>{{ __('Pitch: :value', ['value' => $match->pitch?->name ?? 'Unassigned']) }}</span>
                            <span>{{ __('Time: :value', ['value' => $match->scheduled_at?->format('M j, Y g:i A') ?? 'TBD']) }}</span>
                        </div>
                        @if (! is_null($match->home_score) && ! is_null($match->away_score))
                            <div class="mt-2 text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Final: :home - :away', ['home' => $match->home_score, 'away' => $match->away_score]) }}</div>
                        @else
                            <div class="mt-2 text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('No score yet') }}</div>
                        @endif
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    @if ($canEnterScores && $match->homeRegistration && $match->awayRegistration)
                        <a
                            href="{{ route('admin.tournaments.matches.scoring', ['tournament' => $selectedTournament, 'match' => $match]) }}"
                            wire:navigate
                            class="inline-flex items-center justify-center rounded-lg border border-[#c8d7f8] bg-[#e9f0ff] px-3 py-2 text-sm font-medium text-[#2f55b7] transition hover:border-[#9fb7f2] hover:bg-[#dce8ff]"
                        >
                            {{ $manageScoringPitchScoped ? __('Manage Scoring') : __('Open Score Sheet') }}
                        </a>
                    @elseif (! $match->homeRegistration || ! $match->awayRegistration)
                        <span class="inline-flex items-center justify-center rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-700 dark:border-amber-900/70 dark:bg-amber-950/30 dark:text-amber-300">
                            {{ __('Needs both teams') }}
                        </span>
                    @else
                        <span class="inline-flex items-center justify-center rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm font-medium text-zinc-500 dark:border-neutral-700 dark:bg-zinc-900 dark:text-zinc-400">
                            {{ __('View only') }}
                        </span>
                    @endif

                    @if ($showPublicLinks)
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
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-neutral-300 p-4 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                @if (filled($dashboardEmptyMessage))
                    {{ $dashboardEmptyMessage }}
                @elseif ($manageScoringPitchScoped && $tournamentMatchTotal > 0)
                    <p class="font-medium text-zinc-900 dark:text-zinc-100">
                        {{ __('No games are available for you to score in this tournament.') }}
                    </p>
                    <p class="mt-2">
                        {{ __('Games must be placed on a playing field. You can score games on fields assigned to you, or on fields with no assigned scorekeeper. Games on fields reserved for another scorekeeper are hidden.') }}
                    </p>
                @else
                    {{ __('No games have been added yet.') }}
                @endif
            </div>
        @endforelse
    </div>

    @if ($dashboardMatches->isNotEmpty())
        <div class="mt-4 hidden rounded-xl border border-dashed border-neutral-300 p-4 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300" data-game-dashboard-empty>
            {{ __('No games match your search.') }}
        </div>

        <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-neutral-200 pt-4 text-sm text-zinc-500 dark:border-neutral-700 dark:text-zinc-400" data-game-dashboard-pagination>
            <span data-game-dashboard-page-label></span>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="rounded-md border border-neutral-200 px-3 py-1 font-medium text-zinc-700 transition hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-neutral-700 dark:text-zinc-200 dark:hover:bg-zinc-800"
                    data-game-dashboard-prev
                >
                    {{ __('Prev') }}
                </button>

                <button
                    type="button"
                    class="rounded-md border border-neutral-200 px-3 py-1 font-medium text-zinc-700 transition hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-neutral-700 dark:text-zinc-200 dark:hover:bg-zinc-800"
                    data-game-dashboard-next
                >
                    {{ __('Next') }}
                </button>
            </div>
        </div>
    @endif
</section>

@once
    <script>
        (() => {
            if (window.__distrackGameDashboardsBound) {
                return;
            }

            window.__distrackGameDashboardsBound = true;

            const setupGameDashboards = () => {
                document.querySelectorAll('[data-game-dashboard]').forEach((dashboard) => {
                    if (dashboard.dataset.ready === 'true') {
                        return;
                    }

                    dashboard.dataset.ready = 'true';

                    const items = Array.from(dashboard.querySelectorAll('[data-game-dashboard-item]'));
                    const searchInput = dashboard.querySelector('[data-game-dashboard-search]');
                    const emptyState = dashboard.querySelector('[data-game-dashboard-empty]');
                    const pagination = dashboard.querySelector('[data-game-dashboard-pagination]');
                    const visibleCount = dashboard.querySelector('[data-game-dashboard-visible-count]');
                    const pageLabel = dashboard.querySelector('[data-game-dashboard-page-label]');
                    const previousButton = dashboard.querySelector('[data-game-dashboard-prev]');
                    const nextButton = dashboard.querySelector('[data-game-dashboard-next]');
                    const filterButtons = Array.from(dashboard.querySelectorAll('[data-game-dashboard-filter]'));
                    const pageSize = Number(dashboard.dataset.pageSize || 6);
                    let currentPage = 1;
                    let activeFilter = 'all';

                    const syncFilterButtons = () => {
                        filterButtons.forEach((button) => {
                            const isActive = button.dataset.gameDashboardFilter === activeFilter;

                            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                            button.classList.toggle('ring-2', isActive);
                            button.classList.toggle('ring-[#2f55b7]/50', isActive);
                            button.classList.toggle('shadow-sm', isActive);
                        });
                    };

                    const render = () => {
                        const query = (searchInput?.value ?? '').trim().toLowerCase();
                        const filteredItems = items.filter((item) => {
                            const matchesSearch = item.textContent.toLowerCase().includes(query);
                            const matchesFilter = activeFilter === 'all'
                                || (activeFilter === 'needs-teams' && item.dataset.needsTeams === 'true')
                                || item.dataset.gameStatus === activeFilter;

                            return matchesSearch && matchesFilter;
                        });
                        const totalPages = Math.max(1, Math.ceil(filteredItems.length / pageSize));

                        currentPage = Math.min(currentPage, totalPages);

                        const start = (currentPage - 1) * pageSize;
                        const end = start + pageSize;

                        items.forEach((item) => {
                            item.hidden = true;
                        });

                        filteredItems.forEach((item, index) => {
                            item.hidden = index < start || index >= end;
                        });

                        if (visibleCount) {
                            visibleCount.textContent = filteredItems.length;
                        }

                        if (emptyState) {
                            emptyState.classList.toggle('hidden', filteredItems.length > 0);
                        }

                        if (pagination) {
                            pagination.hidden = filteredItems.length <= pageSize;
                        }

                        if (pageLabel) {
                            pageLabel.textContent = `Page ${currentPage} of ${totalPages}`;
                        }

                        if (previousButton) {
                            previousButton.disabled = currentPage === 1;
                        }

                        if (nextButton) {
                            nextButton.disabled = currentPage === totalPages;
                        }

                        syncFilterButtons();
                    };

                    filterButtons.forEach((button) => {
                        button.addEventListener('click', () => {
                            activeFilter = button.dataset.gameDashboardFilter || 'all';
                            currentPage = 1;
                            render();
                        });
                    });

                    searchInput?.addEventListener('input', () => {
                        currentPage = 1;
                        render();
                    });

                    previousButton?.addEventListener('click', () => {
                        currentPage = Math.max(1, currentPage - 1);
                        render();
                    });

                    nextButton?.addEventListener('click', () => {
                        currentPage += 1;
                        render();
                    });

                    render();
                });
            };

            document.addEventListener('DOMContentLoaded', setupGameDashboards);
            document.addEventListener('livewire:navigated', setupGameDashboards);
            setupGameDashboards();
        })();
    </script>
@endonce
