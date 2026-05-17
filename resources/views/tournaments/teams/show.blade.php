@extends('layouts.public', ['title' => $team->name])

@section('content')
    @php
        $teamLogo = $team->logoUrl();
        $teamBadge = function ($listedTeam) {
            if (! $listedTeam) {
                return 'TBD';
            }

            return str($listedTeam->name)
                ->explode(' ')
                ->take(2)
                ->map(fn ($word) => str($word)->substr(0, 1))
                ->implode('');
        };
        $teamViewUrl = function (string $view) use ($tournament, $team, $playerStatsSort) {
            $defaultDirection = $playerStatsSort['column'] === 'name' ? 'asc' : 'desc';

            return route('tournaments.teams.show', array_filter([
                'tournament' => $tournament,
                'team' => $team,
                'view' => $view !== 'player-stats' ? $view : null,
                'sort' => $playerStatsSort['column'] !== 'total_offense' ? $playerStatsSort['column'] : null,
                'direction' => $playerStatsSort['direction'] !== $defaultDirection ? $playerStatsSort['direction'] : null,
            ]));
        };
        $playerStatsSortUrl = function (string $column) use ($tournament, $team, $playerStatsSort) {
            $defaultDirection = $column === 'name' ? 'asc' : 'desc';
            $direction = $playerStatsSort['column'] === $column
                ? ($playerStatsSort['direction'] === 'asc' ? 'desc' : 'asc')
                : $defaultDirection;

            return route('tournaments.teams.show', array_filter([
                'tournament' => $tournament,
                'team' => $team,
                'view' => 'player-stats',
                'sort' => $column !== 'total_offense' ? $column : null,
                'direction' => $direction !== $defaultDirection ? $direction : null,
            ]));
        };
    @endphp

    <div class="mx-auto max-w-5xl space-y-7">
        <a href="{{ $backLink }}" class="inline-flex items-center gap-2 text-[1.05rem] font-medium text-zinc-900 transition hover:text-[#2f55b7]" wire:navigate>
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M11.78 4.22a.75.75 0 0 1 0 1.06L7.56 9.5h8.69a.75.75 0 0 1 0 1.5H7.56l4.22 4.22a.75.75 0 1 1-1.06 1.06l-5.5-5.5a.75.75 0 0 1 0-1.06l5.5-5.5a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" />
            </svg>
            Back
        </a>

        <section class="rounded-[1rem] border border-zinc-200 bg-white px-6 py-8 shadow-sm sm:px-10">
            <div class="flex flex-col items-center text-center">
                <div class="flex h-36 w-36 items-center justify-center overflow-hidden rounded-full border-[6px] border-[#ff5b63] bg-white shadow-[inset_0_0_0_6px_rgba(255,91,99,0.12)]">
                    @if ($teamLogo)
                        <img src="{{ $teamLogo }}" alt="{{ $team->name }}" class="h-full w-full object-cover">
                    @else
                        <svg class="h-20 w-20 text-[#ff5b63]" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"></circle>
                            <path d="M12 6.5l1.7 3.45 3.8.56-2.75 2.68.65 3.81L12 15.2l-3.4 1.8.65-3.81L6.5 10.5l3.8-.56L12 6.5Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"></path>
                        </svg>
                    @endif
                </div>

                <h1 class="mt-6 text-4xl font-semibold tracking-tight text-zinc-950">{{ $team->name }}</h1>

                @if ($teamSummary['location'])
                    <div class="mt-2 text-lg text-zinc-400">
                        {{ $teamSummary['location'] }}
                        @if ($teamSummary['country_flag'])
                            <span class="ms-1">{{ $teamSummary['country_flag'] }}</span>
                        @endif
                    </div>
                @endif

                <div class="mt-3 text-[1.1rem] text-zinc-950">
                    Total Players: {{ $teamSummary['total_players'] }}
                    <span class="text-[#4c8bf7]">({{ $teamSummary['male_players'] }} MMP</span>,
                    <span class="text-[#ff5b9b]">{{ $teamSummary['female_players'] }} FMP)</span>
                </div>
            </div>
        </section>

        @if ($rosterDeadlinePassed)
            <div class="rounded-[1rem] border-2 border-[#f5c400] bg-[#fff8bf] px-5 py-4 text-[1.05rem] leading-8 text-zinc-700">
                Roster deadline has passed.
                <br>
                Please contact the organiser
            </div>
        @endif

        <div class="flex flex-wrap justify-center gap-2">
            @foreach ([
                'player-stats' => 'Player Stats',
                'games-played' => 'Games Played',
                'spirit' => 'Spirit',
            ] as $viewKey => $viewLabel)
                <a
                    href="{{ $teamViewUrl($viewKey) }}"
                    class="rounded-full border px-7 py-3 text-[1.05rem] font-semibold transition {{ $profileView === $viewKey
                        ? 'border-[#2f55b7] bg-[#2f55b7] text-white'
                        : 'border-zinc-300 bg-white text-zinc-900 hover:bg-zinc-50' }}"
                    wire:navigate
                >
                    {{ $viewLabel }}
                </a>
            @endforeach
        </div>

        @if ($profileView === 'player-stats')
            <section class="space-y-8">
                <div>
                    <h2 class="text-[2rem] font-medium tracking-tight text-zinc-950">Overall Stats Summary</h2>

                    <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                        @foreach ($statCharts as $chart)
                            @php
                                $chartSegments = $chart['segments']->values();
                                $segmentOffset = 0;
                            @endphp

                            <div
                                x-data="{
                                    segments: @js($chartSegments->all()),
                                    metricLabel: @js(str($chart['label'])->lower()->toString()),
                                    activeSegment: null,
                                    tooltipX: 88,
                                    tooltipY: 32,
                                    activate(index, event) {
                                        this.activeSegment = this.segments[index];
                                        this.move(event);
                                    },
                                    move(event) {
                                        if (!this.$refs.surface) {
                                            return;
                                        }

                                        const bounds = this.$refs.surface.getBoundingClientRect();
                                        const localX = event.clientX - bounds.left;
                                        const localY = event.clientY - bounds.top;

                                        this.tooltipX = Math.min(Math.max(localX, 18), bounds.width - 18);
                                        this.tooltipY = Math.min(Math.max(localY, 18), bounds.height - 18);
                                    },
                                    clear() {
                                        this.activeSegment = null;
                                    },
                                }"
                                class="flex h-full flex-col items-center rounded-[1rem] border border-zinc-200 bg-white px-6 py-8 text-center shadow-sm"
                                data-stat-donut
                                data-chart-metric="{{ $chart['metric'] }}"
                            >
                                <h3 class="text-xl font-semibold tracking-tight text-zinc-950 sm:text-2xl">{{ $chart['label'] }}</h3>

                                <div x-ref="surface" class="relative mx-auto mt-6 h-40 w-40 shrink-0 sm:h-44 sm:w-44">
                                    <div
                                        x-show="activeSegment"
                                        style="display: none;"
                                        class="pointer-events-none absolute z-20 min-w-[10rem] rounded-[0.9rem] bg-zinc-900 px-3 py-2 text-left text-sm text-white shadow-[0_16px_36px_rgba(15,23,42,0.28)]"
                                        :style="`left:${tooltipX}px; top:${Math.max(tooltipY - 14, 18)}px; transform: translate(-50%, -100%);`"
                                    >
                                        <div class="font-semibold leading-tight" x-text="activeSegment ? activeSegment.name : ''"></div>
                                        <div class="mt-1 flex items-center gap-2 text-[0.78rem] text-zinc-200">
                                            <span class="inline-flex h-2.5 w-2.5 rounded-full" :style="`background:${activeSegment ? activeSegment.color : '#fff'}`"></span>
                                            <span x-text="activeSegment ? `${activeSegment.value} ${metricLabel} (${activeSegment.percentage}%)` : ''"></span>
                                        </div>
                                    </div>

                                    <svg viewBox="0 0 120 120" class="h-full w-full drop-shadow-[0_8px_18px_rgba(15,23,42,0.06)]" role="img" aria-label="{{ $chart['label'] }} distribution">
                                        <circle
                                            cx="60"
                                            cy="60"
                                            r="35"
                                            fill="none"
                                            stroke="#f1f5f9"
                                            stroke-width="16"
                                        ></circle>

                                        @foreach ($chartSegments as $segment)
                                            @php
                                                $portion = $chart['total'] > 0 ? ($segment['value'] / $chart['total']) * 100 : 0;
                                                $gap = min(1.4, $portion * 0.2);
                                                $dash = max($portion - $gap, 0.25);
                                                $currentOffset = $segmentOffset;
                                                $segmentOffset += $portion;
                                            @endphp

                                            <circle
                                                cx="60"
                                                cy="60"
                                                r="35"
                                                fill="none"
                                                stroke="{{ $segment['color'] }}"
                                                stroke-width="16"
                                                stroke-linecap="butt"
                                                pathLength="100"
                                                stroke-dasharray="{{ number_format($dash, 3, '.', '') }} 100"
                                                stroke-dashoffset="{{ number_format(-$currentOffset, 3, '.', '') }}"
                                                transform="rotate(-90 60 60)"
                                                class="cursor-pointer transition duration-150 hover:opacity-90"
                                                @mouseenter="activate({{ $loop->index }}, $event)"
                                                @mousemove="move($event)"
                                                @mouseleave="clear()"
                                            >
                                                <title>{{ $segment['name'] }}: {{ $segment['value'] }} {{ str($chart['label'])->lower() }} ({{ $segment['percentage'] }}%)</title>
                                            </circle>
                                        @endforeach
                                    </svg>

                                    <div class="pointer-events-none absolute inset-[26%] rounded-full bg-white shadow-[inset_0_0_0_1px_rgba(226,232,240,0.9)]"></div>
                                </div>

                                <p class="mt-6 text-center">
                                    <span class="text-sm font-semibold uppercase tracking-[0.12em] text-zinc-400">Total</span>
                                    <span class="mt-1 block text-2xl font-bold tabular-nums text-zinc-950 sm:text-[1.75rem]">{{ $chart['total'] }}</span>
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="overflow-hidden rounded-[1rem] border border-zinc-200 bg-white">
                    <div class="grid grid-cols-[minmax(0,1.6fr)_4rem_4rem_4rem] items-center gap-3 border-b border-zinc-200 bg-zinc-50 px-5 py-4 text-sm font-semibold text-zinc-900">
                        @foreach ([
                            ['key' => 'name', 'label' => 'Name', 'align' => 'justify-start'],
                            ['key' => 'goals', 'label' => 'G', 'align' => 'justify-center'],
                            ['key' => 'assists', 'label' => 'A', 'align' => 'justify-center'],
                            ['key' => 'total_offense', 'label' => 'T', 'align' => 'justify-center'],
                        ] as $header)
                            @php
                                $isActiveSort = $playerStatsSort['column'] === $header['key'];
                            @endphp

                            <a
                                href="{{ $playerStatsSortUrl($header['key']) }}"
                                class="flex items-center gap-1.5 {{ $header['align'] }} transition {{ $isActiveSort ? 'text-zinc-950' : 'text-zinc-700 hover:text-zinc-950' }}"
                                wire:navigate.preserve-scroll
                            >
                                <span>{{ $header['label'] }}</span>
                                <svg
                                    class="h-3.5 w-3.5 transition {{ $isActiveSort && $playerStatsSort['direction'] === 'asc' ? 'rotate-180' : '' }}"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    aria-hidden="true"
                                >
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                                </svg>
                            </a>
                        @endforeach
                    </div>

                    @forelse ($playerStats as $player)
                        <div class="grid grid-cols-[minmax(0,1.6fr)_4rem_4rem_4rem] items-center gap-3 border-b border-zinc-200 px-5 py-4 text-[1.05rem] text-zinc-900 last:border-b-0">
                            <div class="min-w-0 truncate">{{ $player['display_name'] }}</div>
                            <div class="text-center">{{ $player['goals'] }}</div>
                            <div class="text-center">{{ $player['assists'] }}</div>
                            <div class="text-center">{{ $player['total_offense'] }}</div>
                        </div>
                    @empty
                        <div class="px-5 py-10 text-center text-sm text-zinc-600">
                            No published player stats are available for this team yet.
                        </div>
                    @endforelse
                </div>
            </section>
        @elseif ($profileView === 'games-played')
            <section class="space-y-6">
                @if ($gamesPlayedSummary['has_completed_games'])
                    <div class="space-y-6">
                        <div class="space-y-2.5">
                            <div class="text-center text-[2rem] font-semibold tracking-tight text-zinc-950">Win vs Loss</div>

                            <div class="overflow-hidden rounded-full bg-zinc-100">
                                <div class="flex h-6 w-full">
                                    <div class="h-full bg-[#52c7cb]" style="width: {{ $gamesPlayedSummary['wins_percentage'] }}%"></div>
                                    <div class="h-full bg-[#ff7a59]" style="width: {{ $gamesPlayedSummary['losses_percentage'] }}%"></div>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-3 text-[1.05rem] text-zinc-950">
                                <div>Wins: {{ $gamesPlayedSummary['wins'] }} ({{ number_format($gamesPlayedSummary['wins_percentage'], 2) }}%)</div>
                                <div>Losses: {{ $gamesPlayedSummary['losses'] }} ({{ number_format($gamesPlayedSummary['losses_percentage'], 2) }}%)</div>
                            </div>
                        </div>

                        <div class="space-y-2.5">
                            <div class="text-center text-[2rem] font-semibold tracking-tight text-zinc-950">Points For vs Points Against</div>

                            <div class="overflow-hidden rounded-full bg-zinc-100">
                                <div class="flex h-6 w-full">
                                    <div class="h-full bg-[#3ca0e6]" style="width: {{ $gamesPlayedSummary['points_for_percentage'] }}%"></div>
                                    <div class="h-full bg-[#ffca56]" style="width: {{ $gamesPlayedSummary['points_against_percentage'] }}%"></div>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-3 text-[1.05rem] text-zinc-950">
                                <div>For: {{ $gamesPlayedSummary['points_for'] }} ({{ number_format($gamesPlayedSummary['points_for_percentage'], 2) }}%)</div>
                                <div>Against: {{ $gamesPlayedSummary['points_against'] }} ({{ number_format($gamesPlayedSummary['points_against_percentage'], 2) }}%)</div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="space-y-3">
                    @forelse ($teamGames as $game)
                        @php
                            $homeLogo = $game['home_team']?->logoUrl();
                            $awayLogo = $game['away_team']?->logoUrl();
                        @endphp

                        <a
                            href="{{ route('tournaments.matches.show', ['tournament' => $tournament, 'match' => $game['match']]) }}"
                            class="block rounded-[1.8rem] border border-zinc-200 bg-white px-5 py-5 shadow-sm transition hover:border-zinc-300 hover:shadow-md"
                            wire:navigate
                        >
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <div class="text-[1.05rem] font-semibold uppercase tracking-[0.03em] text-zinc-900">
                                        {{ strtoupper($game['stage_label']) }}
                                    </div>

                                    @if ($game['group_label'])
                                        <span class="inline-flex rounded-full border border-zinc-200 bg-white px-2.5 py-0.5 text-[11px] font-semibold text-zinc-400">
                                            {{ $game['group_label'] }}
                                        </span>
                                    @endif
                                </div>

                                <div class="mt-1 text-lg font-semibold text-zinc-900">{{ $game['pitch_label'] }}</div>
                            </div>

                            <div class="mt-5 grid gap-5 md:grid-cols-[5.5rem_minmax(0,1fr)] md:items-center">
                                <div class="flex flex-col items-start md:items-center md:text-center">
                                    <div class="text-[1.35rem] font-semibold uppercase leading-none text-zinc-500">{{ $game['short_date_label'] }}</div>
                                    <div class="mt-3 text-[1.35rem] font-semibold leading-none text-zinc-500">{{ $game['time_label'] }}</div>
                                    <span class="mt-4 inline-flex rounded-[0.7rem] px-3 py-1.5 text-sm font-semibold uppercase tracking-[0.04em] {{ $game['status_tone'] }}">
                                        {{ strtoupper($game['status_label']) }}
                                    </span>
                                </div>

                                <div class="space-y-4">
                                    <div class="grid grid-cols-[auto_minmax(0,1fr)_2rem] items-center gap-3">
                                        <div class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-full border border-zinc-200 bg-white text-xs font-semibold text-zinc-700">
                                            @if ($homeLogo)
                                                <img src="{{ $homeLogo }}" alt="{{ $game['home_team']?->name }}" class="h-full w-full object-cover">
                                            @else
                                                {{ $teamBadge($game['home_team']) }}
                                            @endif
                                        </div>

                                        <div class="min-w-0 text-[1.55rem] font-semibold leading-tight text-zinc-950">
                                            {{ $game['home_team']?->name ?: 'TBD' }}
                                        </div>

                                        <div class="text-right text-[1.55rem] font-semibold text-zinc-950">
                                            {{ $game['home_score'] ?? '-' }}
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-[auto_minmax(0,1fr)_2rem] items-center gap-3">
                                        <div class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-full border border-zinc-200 bg-white text-xs font-semibold text-zinc-700">
                                            @if ($awayLogo)
                                                <img src="{{ $awayLogo }}" alt="{{ $game['away_team']?->name }}" class="h-full w-full object-cover">
                                            @else
                                                {{ $teamBadge($game['away_team']) }}
                                            @endif
                                        </div>

                                        <div class="min-w-0 text-[1.55rem] font-semibold leading-tight text-zinc-950">
                                            {{ $game['away_team']?->name ?: 'TBD' }}
                                        </div>

                                        <div class="text-right text-[1.55rem] font-semibold text-zinc-950">
                                            {{ $game['away_score'] ?? '-' }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="rounded-[1rem] border border-dashed border-zinc-200 bg-zinc-50 px-5 py-10 text-center text-sm text-zinc-600">
                            No team matches have been published yet.
                        </div>
                    @endforelse
                </div>
            </section>
        @else
            <section class="space-y-5">
                @if ($spiritProfile)
                    <div class="space-y-3">
                        <div class="text-[2.1rem] font-medium tracking-tight text-zinc-950">
                            Overall Spirit Score: {{ number_format($spiritProfile['overall_spirit_score'], 2) }}
                        </div>
                        <div class="text-[1.05rem] font-medium text-zinc-950">Spirit Scores Received</div>
                    </div>

                    @if ($spiritProfile['received_scores']->isNotEmpty())
                        <div class="overflow-hidden rounded-[1rem] border border-zinc-200 bg-white shadow-sm">
                            <div class="grid grid-cols-[minmax(0,1.4fr)_7rem_7rem_7rem_7rem_7rem_7rem] items-center gap-3 border-b border-zinc-200 bg-zinc-50 px-5 py-4 text-sm font-semibold text-zinc-900">
                                <div>Given by</div>
                                <div class="text-center">Rules</div>
                                <div class="text-center">Fouls</div>
                                <div class="text-center">Fair</div>
                                <div class="text-center">Attit.</div>
                                <div class="text-center">Comm.</div>
                                <div class="text-center">Total</div>
                            </div>

                            @foreach ($spiritProfile['received_scores'] as $score)
                                <div class="grid grid-cols-[minmax(0,1.4fr)_7rem_7rem_7rem_7rem_7rem_7rem] items-center gap-3 border-b border-zinc-200 px-5 py-5 text-[1.05rem] text-zinc-900 last:border-b-0">
                                    <div class="min-w-0">
                                        <div class="font-medium text-zinc-950">{{ $score['given_by'] }}</div>
                                    </div>
                                    <div class="text-center">{{ $score['rules'] }}</div>
                                    <div class="text-center">{{ $score['fouls'] }}</div>
                                    <div class="text-center">{{ $score['fair'] }}</div>
                                    <div class="text-center">{{ $score['attitude'] }}</div>
                                    <div class="text-center">{{ $score['communication'] }}</div>
                                    <div class="text-center font-semibold">{{ $score['total'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-[1rem] border border-dashed border-zinc-200 bg-zinc-50 px-5 py-10 text-center text-sm text-zinc-600">
                            No published spirit scores have been received yet.
                        </div>
                    @endif
                @else
                    <div class="rounded-[1rem] border border-dashed border-zinc-200 bg-zinc-50 px-5 py-10 text-center text-sm text-zinc-600">
                        No spirit profile has been published for this team yet.
                    </div>
                @endif
            </section>
        @endif
    </div>
@endsection
