@extends('layouts.public', ['title' => (($homeTeam?->name ?? 'TBD').' vs '.($awayTeam?->name ?? 'TBD'))])

@php
    $tabs = [
        'score-breakdown' => 'Score Breakdown',
        'stats' => 'Stats',
        'spirit' => 'Spirit',
        'mvp' => 'MVP',
    ];

    $statusClasses = match ($match->status) {
        'completed' => 'bg-[#61d9a5] text-white',
        'live' => 'bg-[#2f55b7] text-white',
        default => 'bg-zinc-100 text-zinc-600',
    };

    $statusLabel = match ($match->status) {
        'completed' => 'ENDED',
        'live' => 'LIVE',
        default => 'SCHEDULED',
    };

    $homeLogo = $homeTeam?->logoUrl();
    $awayLogo = $awayTeam?->logoUrl();

    $homeBadge = $homeTeam
        ? str($homeTeam->name)->explode(' ')->take(2)->map(fn ($word) => str($word)->substr(0, 1))->implode('')
        : 'TBD';

    $awayBadge = $awayTeam
        ? str($awayTeam->name)->explode(' ')->take(2)->map(fn ($word) => str($word)->substr(0, 1))->implode('')
        : 'TBD';

    $homeLeaders = [
        'goals' => $homeStats->sortByDesc('goals')->first(),
        'assists' => $homeStats->sortByDesc('assists')->first(),
        'blocks' => $homeStats->sortByDesc('blocks')->first(),
    ];

    $awayLeaders = [
        'goals' => $awayStats->sortByDesc('goals')->first(),
        'assists' => $awayStats->sortByDesc('assists')->first(),
        'blocks' => $awayStats->sortByDesc('blocks')->first(),
    ];
@endphp

@section('content')
    <div class="space-y-5">
        <a
            href="{{ $backLink }}"
            class="inline-flex items-center gap-2 text-sm font-semibold text-zinc-600 transition hover:text-zinc-900"
            wire:navigate
        >
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M11.78 4.22a.75.75 0 0 1 0 1.06L7.06 10l4.72 4.72a.75.75 0 1 1-1.06 1.06l-5.25-5.25a.75.75 0 0 1 0-1.06l5.25-5.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" />
            </svg>
            Back to schedule
        </a>

        <section class="rounded-[1rem] border border-zinc-200 bg-white p-6 shadow-sm">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_15rem_minmax(0,1fr)] lg:items-center">
                <div class="text-center lg:text-left">
                    <div class="mx-auto flex h-20 w-20 items-center justify-center overflow-hidden rounded-full border border-zinc-200 bg-white text-lg font-semibold text-zinc-700 lg:mx-0">
                        @if ($homeLogo)
                            <img src="{{ $homeLogo }}" alt="{{ $homeTeam?->name }}" class="h-full w-full object-cover">
                        @else
                            {{ $homeBadge }}
                        @endif
                    </div>
                    <div class="mt-4 text-3xl font-semibold tracking-tight text-zinc-900">
                        {{ $homeTeam?->name ?: 'TBD' }}
                    </div>
                    <div class="mt-2 text-sm text-zinc-500">
                        {{ $homeTeam?->locationLabel() ?: 'Team details pending' }}
                    </div>
                </div>

                <div class="text-center">
                    <div class="text-5xl font-semibold tracking-tight text-zinc-900">
                        {{ $match->home_score ?? '-' }}
                        <span class="mx-2 text-zinc-500">-</span>
                        {{ $match->away_score ?? '-' }}
                    </div>

                    <div class="mt-4">
                        <span class="inline-flex rounded-[0.65rem] px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.04em] {{ $statusClasses }}">
                            {{ $statusLabel }}
                        </span>
                    </div>
                </div>

                <div class="text-center lg:text-right">
                    <div class="mx-auto flex h-20 w-20 items-center justify-center overflow-hidden rounded-full border border-zinc-200 bg-white text-lg font-semibold text-zinc-700 lg:mx-0 lg:ml-auto">
                        @if ($awayLogo)
                            <img src="{{ $awayLogo }}" alt="{{ $awayTeam?->name }}" class="h-full w-full object-cover">
                        @else
                            {{ $awayBadge }}
                        @endif
                    </div>
                    <div class="mt-4 text-3xl font-semibold tracking-tight text-zinc-900">
                        {{ $awayTeam?->name ?: 'TBD' }}
                    </div>
                    <div class="mt-2 text-sm text-zinc-500">
                        {{ $awayTeam?->locationLabel() ?: 'Team details pending' }}
                    </div>
                </div>
            </div>

            <div class="mt-6 border-t border-zinc-200 pt-4 text-center text-zinc-600">
                <div class="text-lg font-semibold text-zinc-800">
                    @if ($match->scheduled_at)
                        {{ $match->scheduled_at->format('D, M j') }} | {{ $match->scheduled_at->format('g:i A') }}
                    @else
                        Schedule pending
                    @endif

                    @if ($match->pitch?->name)
                        | {{ $match->pitch->name }}
                    @endif
                </div>

                <div class="mt-2 text-base">
                    {{ str($match->stage)->replace('_', ' ')->headline() }}
                    @if ($match->round_label)
                        | {{ $match->round_label }}
                    @endif
                </div>

                <div class="mt-2 text-sm">
                    Tournament:
                    <a
                        href="{{ route('tournaments.show', ['tournament' => $tournament, 'tab' => 'schedule', 'stage' => $matchStageBucket]) }}"
                        class="font-semibold text-[#2f55b7] transition hover:underline"
                        wire:navigate
                    >
                        {{ $tournament->name }}
                    </a>
                </div>
            </div>
        </section>

        <div class="flex flex-wrap justify-center gap-2">
            @foreach ($tabs as $tabKey => $tabLabel)
                <a
                    href="{{ route('tournaments.matches.show', ['tournament' => $tournament, 'match' => $match, 'tab' => $tabKey]) }}"
                    class="rounded-full border px-6 py-2.5 text-sm font-semibold transition {{ $activeMatchTab === $tabKey
                        ? 'border-[#2f55b7] bg-[#2f55b7] text-white'
                        : 'border-zinc-200 bg-white text-zinc-700 hover:border-zinc-300 hover:bg-zinc-50' }}"
                    wire:navigate
                >
                    {{ $tabLabel }}
                </a>
            @endforeach
        </div>

        @if ($activeMatchTab === 'stats')
            <section class="overflow-hidden rounded-[1rem] border border-zinc-200 bg-white shadow-sm">
                <div class="grid grid-cols-[6rem_minmax(0,1fr)_6rem] items-center gap-4 border-b border-zinc-200 bg-zinc-50 px-6 py-4 text-center">
                    <div class="flex justify-center">
                        <div class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full border border-zinc-200 bg-white text-xs font-semibold text-zinc-700">
                            @if ($homeLogo)
                                <img src="{{ $homeLogo }}" alt="{{ $homeTeam?->name }}" class="h-full w-full object-cover">
                            @else
                                {{ $homeBadge }}
                            @endif
                        </div>
                    </div>
                    <div class="text-2xl font-semibold text-zinc-900">Team Stats</div>
                    <div class="flex justify-center">
                        <div class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full border border-zinc-200 bg-white text-xs font-semibold text-zinc-700">
                            @if ($awayLogo)
                                <img src="{{ $awayLogo }}" alt="{{ $awayTeam?->name }}" class="h-full w-full object-cover">
                            @else
                                {{ $awayBadge }}
                            @endif
                        </div>
                    </div>
                </div>

                <div class="divide-y divide-zinc-200">
                    @foreach ($comparisonRows as $row)
                        <div class="grid grid-cols-[6rem_minmax(0,1fr)_6rem] items-center gap-4 px-6 py-5 text-center">
                            <div class="text-4xl font-semibold tracking-tight text-zinc-900">{{ $row['home'] }}</div>
                            <div class="text-sm font-medium uppercase tracking-[0.08em] text-zinc-600">{{ $row['label'] }}</div>
                            <div class="text-4xl font-semibold tracking-tight text-zinc-900">{{ $row['away'] }}</div>
                        </div>
                    @endforeach
                </div>
            </section>
        @elseif ($activeMatchTab === 'spirit')
            <section class="overflow-hidden rounded-[1rem] border border-zinc-200 bg-white shadow-sm">
                <div class="border-b border-zinc-200 bg-zinc-50 px-6 py-4 text-center text-2xl font-semibold text-zinc-900">
                    Spirit Score
                </div>

                <div class="px-6 py-10">
                    @if ($matchSpirit->status === \App\Support\MatchSpiritPresentation::STATUS_NOT_COMPLETED)
                        <p class="mx-auto max-w-3xl text-center text-xl leading-9 text-zinc-900">
                            Spirit scores will be available once this match is completed.
                        </p>
                    @elseif ($matchSpirit->status === \App\Support\MatchSpiritPresentation::STATUS_NO_DATA)
                        <p class="mx-auto max-w-3xl text-center text-xl leading-9 text-zinc-900">
                            No spirit score recorded yet.
                        </p>
                    @else
                        @if ($matchSpirit->winnerMessage)
                            <p class="mx-auto mb-6 max-w-3xl text-center text-base font-semibold text-[#2f55b7]">
                                {{ $matchSpirit->winnerMessage }}
                            </p>
                        @endif

                        <div class="mx-auto grid max-w-3xl gap-6 xl:grid-cols-2">
                            @include('shared.partials.match-spirit-card', ['spirit' => $matchSpirit->home])
                            @include('shared.partials.match-spirit-card', ['spirit' => $matchSpirit->away])
                        </div>
                    @endif
                </div>
            </section>
        @elseif ($activeMatchTab === 'mvp')
            <section class="overflow-hidden rounded-[1rem] border border-zinc-200 bg-white shadow-sm">
                <div class="border-b border-zinc-200 bg-zinc-50 px-6 py-4 text-center text-2xl font-semibold text-zinc-900">
                    MVP
                </div>

                <div class="px-6 py-10">
                    @if ($matchMvp->status === \App\Support\MatchMvpPresentation::STATUS_NOT_COMPLETED)
                        <p class="mx-auto max-w-3xl text-center text-xl leading-9 text-zinc-900">
                            MVP results will be available once this match is completed.
                        </p>
                    @elseif ($matchMvp->status === \App\Support\MatchMvpPresentation::STATUS_PENDING)
                        <p class="mx-auto max-w-3xl text-center text-xl leading-9 text-zinc-900">
                            MVP pending
                        </p>
                    @elseif ($matchMvp->status === \App\Support\MatchMvpPresentation::STATUS_NO_DATA)
                        <p class="mx-auto max-w-3xl text-center text-xl leading-9 text-zinc-900">
                            No MVP data available yet.
                        </p>
                    @else
                        <div class="mx-auto grid max-w-3xl gap-6">
                            @include('shared.partials.match-mvp-card', [
                                'label' => 'Winning Team MVP',
                                'mvp' => $matchMvp->winningMvp,
                            ])
                            @include('shared.partials.match-mvp-card', [
                                'label' => 'Losing Team MVP',
                                'mvp' => $matchMvp->losingMvp,
                            ])
                        </div>
                    @endif
                </div>
            </section>
        @elseif ($activeMatchTab === 'score-breakdown')
            <section class="overflow-hidden rounded-[1rem] border border-zinc-200 bg-white shadow-sm">
                <div class="border-b border-zinc-200 bg-zinc-50 px-6 py-4 text-center text-2xl font-semibold text-zinc-900">
                    Score Breakdown
                </div>

                @if ($matchScoreSheet->hasPublishedScoreSheet())
                    <div class="p-6">
                        @include('shared.partials.match-score-sheet-panel', [
                            'scoreSheetConfigs' => $matchScoreSheet->scoreSheetConfigs,
                            'readonly' => true,
                        ])
                    </div>
                @else
                    <div class="px-6 py-10 text-center text-lg text-zinc-500">
                        No score sheet available yet.
                    </div>
                @endif
            </section>
        @endif
    </div>
@endsection
