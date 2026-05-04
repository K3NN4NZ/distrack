@extends('layouts.public', ['title' => $tournament->name])

@php
    $timezoneNotice = match ($tournament->timezone) {
        'Asia/Manila' => 'All times shown below are in Philippines (GMT+8) time.',
        default => $tournament->timezone
            ? "All times shown below are in {$tournament->timezone} time."
            : 'All times shown below follow the published tournament schedule.',
    };

    $baseQuery = collect([
        'date' => $scheduleFilters['date'] !== 'all' ? $scheduleFilters['date'] : null,
        'stage' => $scheduleFilters['stage'] !== 'all' ? $scheduleFilters['stage'] : null,
    ])->filter()->all();

    $timetableInitiallyOpen = request()->boolean('timetable');

    $scheduleUrl = function (array $overrides = [], bool $openTimetable = false) use ($tournament, $scheduleFilters) {
        return route('tournaments.show', array_merge(
            ['tournament' => $tournament, 'tab' => 'schedule'],
            collect([
                'date' => $scheduleFilters['date'] !== 'all' ? $scheduleFilters['date'] : null,
                'stage' => $scheduleFilters['stage'] !== 'all' ? $scheduleFilters['stage'] : null,
                'timetable' => $openTimetable ? 1 : null,
            ])->merge($overrides)
                ->filter(fn ($value) => ! is_null($value) && $value !== '')
                ->all(),
        ));
    };

    $tabs = [
        'info' => 'Info',
        'teams' => 'Teams',
        'schedule' => 'Schedule',
        'spirit' => 'Spirit',
        'group' => 'Group',
        'bracket' => 'Bracket',
        'stats' => 'Stats',
        'mvp' => 'MVP',
        'standings' => 'Standings',
        'crew' => 'Crew',
        'pitches' => 'Pitches',
    ];

    $profileSections = $tournament->publicProfileSections();
@endphp

@section('content')
    <div class="space-y-5">
        <section class="rounded-[1rem] border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                <div class="flex h-[4.6rem] w-[4.6rem] shrink-0 items-center justify-center overflow-hidden rounded-[0.9rem] border border-zinc-200 bg-zinc-100">
                    @if ($tournament->thumbnail_path)
                        <img
                            src="{{ $tournament->thumbnail_path }}"
                            alt="{{ $tournament->name }}"
                            class="h-full w-full object-cover"
                        >
                    @else
                        @php
                            $initials = str($tournament->name)
                                ->explode(' ')
                                ->take(2)
                                ->map(fn ($word) => str($word)->substr(0, 1))
                                ->implode('');
                        @endphp
                        <span class="text-2xl font-semibold text-zinc-400">{{ $initials }}</span>
                    @endif
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-col gap-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <a
                                href="{{ route('tournaments.index') }}"
                                class="text-sm font-medium text-zinc-500 transition hover:text-zinc-900"
                                wire:navigate
                            >
                                Back to tournaments
                            </a>
                        </div>

                        <h1 class="text-3xl font-semibold tracking-tight text-zinc-900">
                            {{ $tournament->name }}
                        </h1>

                        <div class="space-y-1.5 text-base text-zinc-700">
                            <div class="flex items-center gap-2">
                                <svg class="h-4.5 w-4.5 text-zinc-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.75 3A2.75 2.75 0 003 5.75v8.5A2.75 2.75 0 005.75 17h8.5A2.75 2.75 0 0017 14.25v-8.5A2.75 2.75 0 0014.25 3h-8.5zM5 7.25A.75.75 0 015.75 6.5h8.5a.75.75 0 010 1.5h-8.5A.75.75 0 015 7.25zm2 3a.75.75 0 01.75-.75h4.5a.75.75 0 010 1.5h-4.5A.75.75 0 017 10.25z" clip-rule="evenodd" />
                                </svg>
                                <span>{{ $tournament->dateRangeLabel() }}</span>
                            </div>

                            <div class="flex items-center gap-2">
                                <svg class="h-4.5 w-4.5 text-zinc-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M10 1.75a5.75 5.75 0 00-5.75 5.75c0 4.115 4.616 9.426 5.142 10.02a.75.75 0 001.116 0c.526-.594 5.242-5.905 5.242-10.02A5.75 5.75 0 0010 1.75zM7.5 7.5a2.5 2.5 0 115 0 2.5 2.5 0 01-5 0z" clip-rule="evenodd" />
                                </svg>
                                @if ($tournament->venue_google_map_link)
                                    <a
                                        href="{{ $tournament->venue_google_map_link }}"
                                        target="_blank"
                                        rel="noreferrer"
                                        class="font-medium text-[#2f55b7] underline-offset-2 transition hover:underline"
                                    >
                                        {{ $tournament->addressLabel() ?: ($tournament->venue ?: 'Venue to be announced') }}
                                    </a>
                                @else
                                    <span>{{ $tournament->addressLabel() ?: ($tournament->venue ?: 'Venue to be announced') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 rounded-[0.9rem] border border-[#c8d7f8] bg-[#e9f0ff] px-4 py-3 text-sm font-medium text-[#2f55b7]">
                {{ $timezoneNotice }}
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-2 border-b border-zinc-200 pb-4">
                @foreach ($tabs as $tabKey => $tabLabel)
                    <a
                        href="{{ route('tournaments.show', array_merge(['tournament' => $tournament, 'tab' => $tabKey], $baseQuery)) }}"
                        class="rounded-full border px-5 py-2.5 text-sm font-semibold transition {{ $activeTab === $tabKey
                            ? 'border-[#2f55b7] bg-[#2f55b7] text-white'
                            : 'border-zinc-200 bg-white text-zinc-700 hover:border-zinc-300 hover:bg-zinc-50' }}"
                        wire:navigate
                    >
                        {{ $tabLabel }}
                    </a>
                @endforeach
            </div>
        </section>

        @if ($activeTab === 'schedule')
            @php
                $timetableHasRows = $scheduleTimetable['columns']->isNotEmpty()
                    && $scheduleTimetable['days']->contains(fn (array $day) => collect($day['slots'])->isNotEmpty());
                $timetableColumnCount = $scheduleTimetable['columns']->count() + 1;
            @endphp

            <section class="rounded-[1rem] border border-zinc-200 bg-white p-6 shadow-sm">
                <div class="space-y-6">
                    <div class="flex justify-end">
                        <a
                            href="{{ $scheduleUrl([], true) }}"
                            class="inline-flex items-center rounded-[0.8rem] bg-[#1f2937] px-5 py-3 text-sm font-semibold text-white transition hover:bg-black"
                            wire:navigate
                        >
                            View Timetable
                        </a>
                    </div>

                    <div class="space-y-4">
                        <div class="grid gap-3 md:grid-cols-[6rem_minmax(0,1fr)] md:items-center">
                            <div class="text-sm font-semibold text-zinc-900">Division</div>
                            <div class="rounded-full border border-zinc-200 px-2 py-1.5">
                                <span class="inline-flex rounded-full bg-[#2f55b7] px-5 py-2 text-sm font-semibold text-white">
                                    {{ $tournament->division ?: 'Open' }}
                                </span>
                            </div>
                        </div>

                        <div class="grid gap-3 md:grid-cols-[6rem_minmax(0,1fr)] md:items-center">
                            <div class="text-sm font-semibold text-zinc-900">Date</div>
                            <div class="flex flex-wrap gap-2 rounded-full border border-zinc-200 px-2 py-1.5">
                                <a
                                    href="{{ $scheduleUrl(['date' => null]) }}"
                                    class="rounded-full px-5 py-2 text-sm font-semibold transition {{ $scheduleFilters['date'] === 'all'
                                        ? 'bg-[#2f55b7] text-white'
                                        : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900' }}"
                                    wire:navigate
                                >
                                    All
                                </a>

                                @foreach ($scheduleOptions['dates'] as $dateOption)
                                    <a
                                        href="{{ $scheduleUrl(['date' => $dateOption['value']]) }}"
                                        class="rounded-full px-5 py-2 text-sm font-semibold transition {{ $scheduleFilters['date'] === $dateOption['value']
                                            ? 'bg-[#2f55b7] text-white'
                                            : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900' }}"
                                        wire:navigate
                                    >
                                        {{ $dateOption['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>

                        <div class="grid gap-3 md:grid-cols-[6rem_minmax(0,1fr)] md:items-center">
                            <div class="text-sm font-semibold text-zinc-900">Stage</div>
                            <div class="flex flex-wrap gap-2 rounded-full border border-zinc-200 px-2 py-1.5">
                                <a
                                    href="{{ $scheduleUrl(['stage' => null]) }}"
                                    class="rounded-full px-5 py-2 text-sm font-semibold transition {{ $scheduleFilters['stage'] === 'all'
                                        ? 'bg-[#2f55b7] text-white'
                                        : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900' }}"
                                    wire:navigate
                                >
                                    All
                                </a>

                                @foreach ($scheduleOptions['stages'] as $stageOption)
                                    <a
                                        href="{{ $scheduleUrl(['stage' => $stageOption['value']]) }}"
                                        class="rounded-full px-5 py-2 text-sm font-semibold transition {{ $scheduleFilters['stage'] === $stageOption['value']
                                            ? 'bg-[#2f55b7] text-white'
                                            : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900' }}"
                                        wire:navigate
                                    >
                                        {{ $stageOption['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    @if ($visibleMatches->isNotEmpty())
                        <div class="space-y-4">
                            @foreach ($visibleMatches as $match)
                                @php
                                    $homeTeam = $match->homeRegistration?->team;
                                    $awayTeam = $match->awayRegistration?->team;
                                    $homeLogo = $homeTeam?->logoUrl();
                                    $awayLogo = $awayTeam?->logoUrl();
                                    $homeBadge = $homeTeam
                                        ? str($homeTeam->name)->explode(' ')->take(2)->map(fn ($word) => str($word)->substr(0, 1))->implode('')
                                        : 'TBD';
                                    $awayBadge = $awayTeam
                                        ? str($awayTeam->name)->explode(' ')->take(2)->map(fn ($word) => str($word)->substr(0, 1))->implode('')
                                        : 'TBD';
                                    $matchStageBucket = in_array($match->stage, ['group', 'group_play', 'pool', 'pool_play', 'round_robin'], true) ? 'group' : 'bracket';
                                    $matchStageLabel = $matchStageBucket === 'group'
                                        ? 'GROUP GAME'
                                        : strtoupper(str($match->stage)->replace('_', ' ')->headline()->toString());
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
                                @endphp

                                <article class="overflow-hidden rounded-[1.1rem] border border-zinc-200 bg-white transition hover:border-zinc-300 hover:shadow-sm">
                                    <a
                                        href="{{ route('tournaments.matches.show', ['tournament' => $tournament, 'match' => $match]) }}"
                                        class="block px-5 pb-5 pt-4"
                                        wire:navigate
                                    >
                                        <div class="flex flex-wrap items-center gap-2">
                                            <div class="text-sm font-semibold uppercase tracking-[0.02em] text-zinc-900">
                                                {{ $matchStageLabel }}
                                            </div>

                                            @if ($match->round_label)
                                                <span class="rounded-full border border-zinc-200 bg-zinc-50 px-2.5 py-0.5 text-[11px] font-semibold text-zinc-400">
                                                    {{ $match->round_label }}
                                                </span>
                                            @endif
                                        </div>

                                        <div class="mt-1 text-sm font-semibold text-zinc-900">
                                            {{ $match->pitch?->name ?: 'Field TBD' }}
                                        </div>

                                        <div class="mt-4 grid gap-4 md:grid-cols-[5.5rem_minmax(0,1fr)]">
                                            <div class="space-y-2 border-zinc-200 text-center md:border-r md:pr-4">
                                                @if ($match->scheduled_at)
                                                    <div class="text-sm font-semibold uppercase text-zinc-500">
                                                        {{ strtoupper($match->scheduled_at->format('d M')) }}
                                                    </div>
                                                    <div class="text-2xl font-semibold text-zinc-500">
                                                        {{ $match->scheduled_at->format('H:i') }}
                                                    </div>
                                                @else
                                                    <div class="text-sm font-semibold uppercase text-zinc-500">TBD</div>
                                                @endif

                                                <span class="inline-flex rounded-[0.55rem] px-3 py-1 text-xs font-semibold uppercase tracking-[0.04em] {{ $statusClasses }}">
                                                    {{ $statusLabel }}
                                                </span>
                                            </div>

                                            <div class="space-y-4">
                                                <div class="flex items-center gap-3">
                                                    <div class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-full border border-zinc-200 bg-white text-xs font-semibold text-zinc-700">
                                                        @if ($homeLogo)
                                                            <img src="{{ $homeLogo }}" alt="{{ $homeTeam?->name }}" class="h-full w-full object-cover">
                                                        @else
                                                            {{ $homeBadge }}
                                                        @endif
                                                    </div>
                                                    <div class="min-w-0 flex-1 text-xl font-semibold text-zinc-900">
                                                        {{ $homeTeam?->name ?: 'TBD' }}
                                                    </div>
                                                    <div class="text-3xl font-semibold text-zinc-900">
                                                        {{ $match->home_score ?? '-' }}
                                                    </div>
                                                </div>

                                                <div class="flex items-center gap-3">
                                                    <div class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-full border border-zinc-200 bg-white text-xs font-semibold text-zinc-700">
                                                        @if ($awayLogo)
                                                            <img src="{{ $awayLogo }}" alt="{{ $awayTeam?->name }}" class="h-full w-full object-cover">
                                                        @else
                                                            {{ $awayBadge }}
                                                        @endif
                                                    </div>
                                                    <div class="min-w-0 flex-1 text-xl font-semibold text-zinc-900">
                                                        {{ $awayTeam?->name ?: 'TBD' }}
                                                    </div>
                                                    <div class="text-3xl font-semibold text-zinc-900">
                                                        {{ $match->away_score ?? '-' }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-[1rem] border border-dashed border-zinc-200 bg-zinc-50 p-5 text-sm leading-6 text-zinc-600">
                            No matches match the selected schedule filters.
                        </div>
                    @endif
                </div>

                @if ($timetableInitiallyOpen)
                <div class="fixed inset-0 z-50 bg-white">
                    <div class="flex h-full flex-col bg-[#f4f5f7]">
                        <div class="bg-[#2f55b7] text-white">
                            <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-4 px-4 py-4 sm:px-6 lg:px-8">
                                <div></div>
                                <div class="text-center text-2xl font-semibold tracking-tight sm:text-3xl">Timetable</div>
                                <a
                                    href="{{ $scheduleUrl() }}"
                                    class="justify-self-end text-sm font-semibold transition hover:text-white/80"
                                    wire:navigate
                                >
                                    Close
                                </a>
                            </div>
                        </div>

                        <div class="flex-1 overflow-y-auto">
                            <div class="px-4 py-5 sm:px-6 lg:px-8">
                                <h2 class="text-center text-3xl font-semibold tracking-tight text-zinc-950 sm:text-4xl">
                                    {{ $tournament->name }}
                                </h2>

                                <div class="mt-6 space-y-4">
                                    <div class="grid gap-3 lg:grid-cols-[6.5rem_minmax(0,1fr)] lg:items-center">
                                        <div class="text-lg font-semibold text-zinc-950">Division</div>
                                        <div class="flex flex-wrap gap-2 rounded-full border border-zinc-200 bg-white px-2 py-1.5 shadow-sm">
                                            <span class="inline-flex rounded-full bg-[#2f55b7] px-5 py-2 text-sm font-semibold text-white">
                                                All
                                            </span>
                                            <span class="inline-flex rounded-full px-5 py-2 text-sm font-semibold text-zinc-600">
                                                {{ $tournament->division ?: 'Open' }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="grid gap-3 lg:grid-cols-[6.5rem_minmax(0,1fr)] lg:items-center">
                                        <div class="text-lg font-semibold text-zinc-950">Dates</div>
                                        <div class="flex flex-wrap gap-2 rounded-full border border-zinc-200 bg-white px-2 py-1.5 shadow-sm">
                                            <a
                                                href="{{ $scheduleUrl(['date' => null], true) }}"
                                                class="inline-flex rounded-full px-5 py-2 text-sm font-semibold transition {{ $scheduleFilters['date'] === 'all'
                                                    ? 'bg-[#2f55b7] text-white'
                                                    : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900' }}"
                                                wire:navigate
                                            >
                                                All
                                            </a>

                                            @foreach ($scheduleOptions['dates'] as $dateOption)
                                                <a
                                                    href="{{ $scheduleUrl(['date' => $dateOption['value']], true) }}"
                                                    class="inline-flex rounded-full px-5 py-2 text-sm font-semibold transition {{ $scheduleFilters['date'] === $dateOption['value']
                                                        ? 'bg-[#2f55b7] text-white'
                                                        : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900' }}"
                                                    wire:navigate
                                                >
                                                    {{ $dateOption['label'] }}
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div class="grid gap-3 lg:grid-cols-[6.5rem_minmax(0,1fr)] lg:items-center">
                                        <div class="text-lg font-semibold text-zinc-950">Stage</div>
                                        <div class="flex flex-wrap gap-2 rounded-full border border-zinc-200 bg-white px-2 py-1.5 shadow-sm">
                                            <a
                                                href="{{ $scheduleUrl(['stage' => null], true) }}"
                                                class="inline-flex rounded-full px-5 py-2 text-sm font-semibold transition {{ $scheduleFilters['stage'] === 'all'
                                                    ? 'bg-[#2f55b7] text-white'
                                                    : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900' }}"
                                                wire:navigate
                                            >
                                                All
                                            </a>

                                            @foreach ($scheduleOptions['stages'] as $stageOption)
                                                <a
                                                    href="{{ $scheduleUrl(['stage' => $stageOption['value']], true) }}"
                                                    class="inline-flex rounded-full px-5 py-2 text-sm font-semibold transition {{ $scheduleFilters['stage'] === $stageOption['value']
                                                        ? 'bg-[#2f55b7] text-white'
                                                        : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900' }}"
                                                    wire:navigate
                                                >
                                                    {{ $stageOption['label'] }}
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-6 overflow-hidden rounded-[1rem] border border-zinc-200 bg-white shadow-sm">
                                    @if ($timetableHasRows)
                                        <div class="max-h-[calc(100vh-18rem)] overflow-auto">
                                            <table class="min-w-full border-separate border-spacing-0">
                                                <thead class="sticky top-0 z-10">
                                                    <tr class="bg-[#ececec] text-sm font-semibold text-zinc-800">
                                                        <th class="sticky left-0 z-20 min-w-[8.75rem] border-b border-r border-zinc-200 bg-[#ececec] px-4 py-3 text-center">
                                                            Time
                                                        </th>
                                                        @foreach ($scheduleTimetable['columns'] as $column)
                                                            <th class="min-w-[17rem] border-b border-r border-zinc-200 bg-[#ececec] px-4 py-3 text-center last:border-r-0">
                                                                <div>{{ $column['name'] }}</div>
                                                                @if ($column['location'])
                                                                    <div class="mt-1 text-xs font-medium text-zinc-500">{{ $column['location'] }}</div>
                                                                @endif
                                                            </th>
                                                        @endforeach
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white">
                                                    @foreach ($scheduleTimetable['days'] as $day)
                                                        @if ($scheduleFilters['date'] === 'all' && $scheduleTimetable['days']->count() > 1)
                                                            <tr>
                                                                <td colspan="{{ $timetableColumnCount }}" class="border-b border-zinc-200 bg-[#edf3ff] px-5 py-3 text-left text-sm font-semibold uppercase tracking-[0.08em] text-[#2f55b7]">
                                                                    {{ $day['label'] }}
                                                                </td>
                                                            </tr>
                                                        @endif

                                                        @foreach ($day['slots'] as $slot)
                                                            <tr>
                                                                <td class="min-w-[8.75rem] border-b border-r border-zinc-200 bg-white px-4 py-5 align-top text-center">
                                                                    <div class="text-[1.65rem] font-semibold leading-none text-slate-500">
                                                                        {{ $slot['starts_at']->format('H:i') }}
                                                                    </div>
                                                                    <div class="mt-3 text-[1.65rem] font-semibold leading-none text-slate-500">
                                                                        {{ $slot['ends_at']->format('H:i') }}
                                                                    </div>
                                                                </td>

                                                                @foreach ($slot['cells'] as $cell)
                                                                    <td class="min-w-[17rem] border-b border-r border-zinc-200 bg-white px-3 py-3 align-top last:border-r-0">
                                                                        @if ($cell['matches']->isNotEmpty())
                                                                            <div class="space-y-3">
                                                                                @foreach ($cell['matches'] as $match)
                                                                                    @php
                                                                                        $homeRegistration = $match->homeRegistration;
                                                                                        $awayRegistration = $match->awayRegistration;
                                                                                        $homeTeam = $homeRegistration?->team;
                                                                                        $awayTeam = $awayRegistration?->team;
                                                                                        $matchStageBucket = in_array($match->stage, ['group', 'group_play', 'pool', 'pool_play', 'round_robin'], true) ? 'group' : 'bracket';
                                                                                        $matchStageLabel = $matchStageBucket === 'group'
                                                                                            ? 'GROUP GAME'
                                                                                            : strtoupper(str($match->stage)->replace('_', ' ')->headline()->toString());
                                                                                        $statusClasses = match ($match->status) {
                                                                                            'completed' => 'bg-emerald-100 text-emerald-700',
                                                                                            'live' => 'bg-[#e6efff] text-[#2f55b7]',
                                                                                            default => 'bg-zinc-100 text-zinc-600',
                                                                                        };
                                                                                        $statusLabel = match ($match->status) {
                                                                                            'completed' => 'Ended',
                                                                                            'live' => 'Live',
                                                                                            default => 'Scheduled',
                                                                                        };
                                                                                        $homeNumber = $match->status === 'scheduled'
                                                                                            ? ($homeRegistration?->seed_number ?? '-')
                                                                                            : ($match->home_score ?? ($homeRegistration?->seed_number ?? '-'));
                                                                                        $awayNumber = $match->status === 'scheduled'
                                                                                            ? ($awayRegistration?->seed_number ?? '-')
                                                                                            : ($match->away_score ?? ($awayRegistration?->seed_number ?? '-'));
                                                                                    @endphp

                                                                                    <a
                                                                                        href="{{ route('tournaments.matches.show', ['tournament' => $tournament, 'match' => $match]) }}"
                                                                                        class="block rounded-[0.9rem] border border-zinc-200 bg-white px-4 py-4 transition hover:border-[#2f55b7] hover:shadow-sm"
                                                                                        wire:navigate
                                                                                    >
                                                                                        <div class="flex flex-wrap items-center gap-2">
                                                                                            <span class="inline-flex rounded-[0.3rem] bg-[#ff8a52] px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.08em] text-white">
                                                                                                {{ $matchStageLabel }}
                                                                                            </span>

                                                                                            @if ($match->round_label)
                                                                                                <span class="text-[11px] font-semibold uppercase tracking-[0.04em] text-zinc-400">
                                                                                                    {{ $match->round_label }}
                                                                                                </span>
                                                                                            @endif

                                                                                            <span class="ml-auto inline-flex rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.06em] {{ $statusClasses }}">
                                                                                                {{ $statusLabel }}
                                                                                            </span>
                                                                                        </div>

                                                                                        <div class="mt-3 space-y-1.5">
                                                                                            <div class="flex items-start gap-2 text-lg font-semibold text-zinc-900">
                                                                                                <span class="min-w-[1.6rem] text-[#1d4ed8]">{{ $homeNumber }}</span>
                                                                                                <span class="min-w-0 flex-1 truncate">{{ $homeTeam?->name ?: 'TBD' }}</span>
                                                                                            </div>
                                                                                            <div class="flex items-start gap-2 text-lg font-semibold text-zinc-900">
                                                                                                <span class="min-w-[1.6rem] text-[#1d4ed8]">{{ $awayNumber }}</span>
                                                                                                <span class="min-w-0 flex-1 truncate">{{ $awayTeam?->name ?: 'TBD' }}</span>
                                                                                            </div>
                                                                                        </div>
                                                                                    </a>
                                                                                @endforeach
                                                                            </div>
                                                                        @else
                                                                            <div class="min-h-[7.5rem] rounded-[0.9rem] bg-zinc-50"></div>
                                                                        @endif
                                                                    </td>
                                                                @endforeach
                                                            </tr>
                                                        @endforeach
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @else
                                        <div class="p-6 text-sm leading-6 text-zinc-600">
                                            No scheduled matches have been published for this timetable yet.
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </section>
        @elseif ($activeTab === 'teams')
            <section class="rounded-[1rem] border border-zinc-200 bg-white p-6 shadow-sm">
                <div
                    x-data="{
                        directoryView: @js($showCaptains ? 'captains' : null),
                        toggleDirectoryView(view) {
                            this.directoryView = this.directoryView === view ? null : view;

                            this.$refs.teamDirectory
                                ?.querySelectorAll('details')
                                .forEach((detail) => {
                                    detail.open = this.directoryView !== null;
                                });
                        },
                    }"
                    class="space-y-5"
                >
                    <div class="grid gap-3 md:grid-cols-[6rem_minmax(0,1fr)] md:items-center">
                        <div class="text-sm font-semibold text-zinc-900">Division</div>
                        <div class="rounded-full border border-zinc-200 px-3 py-2">
                            <span class="inline-flex rounded-full bg-[#2f55b7] px-5 py-2 text-sm font-semibold text-white">
                                {{ $tournament->division ?: 'Open' }}
                            </span>
                        </div>
                    </div>

                    @if ($tournament->registrations->isNotEmpty())
                        @php
                            $registeredTeamsCount = $tournament->registrations->count();
                            $registeredPlayersCount = $tournament->registrations->sum(
                                fn ($registration) => $registration->team->members_count
                            );
                        @endphp

                        <div class="space-y-4">
                            <div class="flex flex-col gap-3 rounded-[1rem] border border-zinc-200 bg-zinc-50 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex flex-wrap items-center gap-3 text-sm text-zinc-600">
                                    <span class="inline-flex items-center rounded-full bg-white px-3 py-2 font-semibold text-zinc-900">
                                        {{ trans_choice('{1} :count registered team|[2,*] :count registered teams', $registeredTeamsCount, ['count' => $registeredTeamsCount]) }}
                                    </span>
                                    <span class="inline-flex items-center rounded-full bg-white px-3 py-2 font-semibold text-zinc-900">
                                        {{ trans_choice('{1} :count total player|[2,*] :count total players', $registeredPlayersCount, ['count' => $registeredPlayersCount]) }}
                                    </span>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center rounded-[0.8rem] px-4 py-2 text-sm font-semibold transition"
                                        x-bind:class="directoryView === 'captains'
                                            ? 'bg-[#1f2937] text-white hover:bg-black'
                                            : 'border border-zinc-200 bg-white text-zinc-700 hover:border-zinc-300 hover:bg-zinc-100'"
                                        @click="toggleDirectoryView('captains')"
                                        x-bind:aria-pressed="directoryView === 'captains'"
                                    >
                                        <span x-text="directoryView === 'captains' ? 'Hide Captains' : 'View Captains'"></span>
                                    </button>

                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center rounded-[0.8rem] px-4 py-2 text-sm font-semibold transition"
                                        x-bind:class="directoryView === 'members'
                                            ? 'bg-[#2f55b7] text-white hover:bg-[#244591]'
                                            : 'border border-zinc-200 bg-white text-zinc-700 hover:border-zinc-300 hover:bg-zinc-100'"
                                        @click="toggleDirectoryView('members')"
                                        x-bind:aria-pressed="directoryView === 'members'"
                                    >
                                        <span x-text="directoryView === 'members' ? 'Hide Members' : 'View Members'"></span>
                                    </button>
                                </div>
                            </div>

                            <div x-ref="teamDirectory" class="overflow-hidden rounded-[1rem] border border-zinc-200">
                                <div class="grid grid-cols-[4.5rem_minmax(0,1fr)_auto_2.75rem_3.5rem] items-center gap-3 border-b border-zinc-200 bg-zinc-50 px-4 py-3 text-sm font-semibold text-zinc-800">
                                    <div>Seed</div>
                                    <div>Team</div>
                                    <div class="justify-self-end">Captains</div>
                                    <div class="text-center">Open</div>
                                    <div class="text-right">Roster</div>
                                </div>

                                @foreach ($tournament->registrations as $registration)
                                    @php
                                        $team = $registration->team;
                                        $teamLogo = $team->logoUrl();
                                        $teamBadge = str($team->name)
                                            ->explode(' ')
                                            ->take(2)
                                            ->map(fn ($word) => str($word)->substr(0, 1))
                                            ->implode('');
                                        $leaders = $team->members
                                            ->whereIn('role', ['captain', 'spirit_captain'])
                                            ->values();
                                    @endphp

                                    <details class="group border-b border-zinc-200 last:border-b-0" @if ($showCaptains) open @endif>
                                        <summary class="grid cursor-pointer list-none grid-cols-[4.5rem_minmax(0,1fr)_auto_2.75rem_3.5rem] items-center gap-3 px-4 py-4 transition hover:bg-zinc-50 [&::-webkit-details-marker]:hidden">
                                            <div class="text-3xl font-light text-zinc-900">
                                                {{ $registration->seed_number ?? '-' }}
                                            </div>

                                            <div class="flex min-w-0 items-center gap-4">
                                                <div class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-full border border-zinc-200 bg-white text-sm font-semibold text-zinc-700">
                                                    @if ($teamLogo)
                                                        <img src="{{ $teamLogo }}" alt="{{ $team->name }}" class="h-full w-full object-cover">
                                                    @else
                                                        {{ $teamBadge }}
                                                    @endif
                                                </div>

                                                <div class="min-w-0">
                                                    <div class="truncate text-xl font-medium text-zinc-900">{{ $team->name }}</div>
                                                    <div class="mt-1 truncate text-sm text-zinc-400">
                                                        {{ $team->locationLabel() ?: 'Location not listed' }}
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="justify-self-end text-xs font-medium text-zinc-400">
                                                {{ trans_choice('{0} No captains|{1} :count captain|[2,*] :count captains', $leaders->count(), ['count' => $leaders->count()]) }}
                                            </div>

                                            <div class="flex justify-center">
                                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-[#4b5563] text-white">
                                                    <svg class="h-4 w-4 transition group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                                                    </svg>
                                                </span>
                                            </div>

                                            <div class="text-right text-3xl font-light text-zinc-900">
                                                {{ $team->members_count }}
                                            </div>
                                        </summary>

                                        <div class="border-t border-zinc-200 bg-zinc-50/70 px-4 pb-5 pl-[8.65rem] pt-4">
                                            <div x-cloak x-show="directoryView !== 'members'">
                                                @if ($leaders->isNotEmpty())
                                                    <div class="space-y-2">
                                                        @foreach ($leaders as $leader)
                                                            <div class="flex flex-wrap items-center gap-3 text-sm text-zinc-700">
                                                                <span>{{ $leader->name }}</span>
                                                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $leader->role === 'captain' ? 'bg-[#e6efff] text-[#2f55b7]' : 'bg-[#ffe8e5] text-[#da4b34]' }}">
                                                                    {{ str($leader->role)->replace('_', ' ')->headline() }}
                                                                </span>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <div class="text-sm text-zinc-500">
                                                        No captains have been tagged for this team yet.
                                                    </div>
                                                @endif
                                            </div>

                                            <div
                                                x-cloak
                                                x-show="directoryView !== 'captains'"
                                                x-bind:class="directoryView === null ? 'mt-4 border-t border-zinc-200 pt-4' : ''"
                                            >
                                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">Members</div>

                                                @if ($team->members->isNotEmpty())
                                                    <div class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                                        @foreach ($team->members as $member)
                                                            @php
                                                                $memberRoleTone = match ($member->role) {
                                                                    'captain' => 'bg-[#e6efff] text-[#2f55b7]',
                                                                    'spirit_captain' => 'bg-[#ffe8e5] text-[#da4b34]',
                                                                    default => 'bg-zinc-200 text-zinc-700',
                                                                };
                                                            @endphp

                                                            <div class="rounded-[0.85rem] border border-zinc-200 bg-white px-3 py-3">
                                                                <div class="flex items-start justify-between gap-3">
                                                                    <div class="min-w-0">
                                                                        <div class="truncate text-sm font-semibold text-zinc-900">{{ $member->name }}</div>

                                                                        @if ($member->nickname)
                                                                            <div class="truncate text-xs text-zinc-500">{{ $member->nickname }}</div>
                                                                        @endif
                                                                    </div>

                                                                    <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $memberRoleTone }}">
                                                                        {{ str($member->role)->replace('_', ' ')->headline() }}
                                                                    </span>
                                                                </div>

                                                                @if ($member->gender || $member->age)
                                                                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-zinc-500">
                                                                        @if ($member->gender)
                                                                            <span class="rounded-full bg-zinc-100 px-2 py-1">
                                                                                {{ str($member->gender)->headline() }}
                                                                            </span>
                                                                        @endif

                                                                        @if ($member->age)
                                                                            <span class="rounded-full bg-zinc-100 px-2 py-1">
                                                                                {{ $member->age }} yrs
                                                                            </span>
                                                                        @endif
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <div class="mt-3 text-sm text-zinc-500">
                                                        No published roster members are available for this team yet.
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </details>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="rounded-[0.9rem] border border-dashed border-zinc-200 bg-zinc-50 p-5 text-sm leading-6 text-zinc-600">
                            Team registrations are not public yet for this tournament.
                        </div>
                    @endif
                </div>
            </section>
        @elseif ($activeTab === 'pitches')
            <section class="rounded-[1rem] border border-zinc-200 bg-white p-6 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">Venue Ops</div>
                <h2 class="mt-2 text-2xl font-semibold tracking-tight text-zinc-900">Pitches</h2>

                @if ($tournament->pitches->isNotEmpty())
                    <div class="mt-5 space-y-3">
                        @foreach ($tournament->pitches as $pitch)
                            <div class="rounded-[0.9rem] border border-zinc-200 bg-zinc-50 p-4">
                                <div class="text-base font-semibold text-zinc-900">{{ $pitch->name }}</div>
                                <div class="mt-1 text-sm text-zinc-600">{{ $pitch->location ?: 'Location to be confirmed' }}</div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="mt-5 rounded-[0.9rem] border border-dashed border-zinc-200 bg-zinc-50 p-5 text-sm leading-6 text-zinc-600">
                        Pitch assignments have not been published yet.
                    </div>
                @endif
            </section>
        @elseif ($activeTab === 'stats')
            @php
                $defaultStatsGender = data_get($statsOptions['gender']->first(), 'value', 'all');
                $currentStatsPage = $statsLeaderboard->currentPage();
                $statsUrl = function (array $overrides = []) use ($tournament, $statsFilters, $defaultStatsGender, $currentStatsPage) {
                    return route('tournaments.show', array_merge(
                        ['tournament' => $tournament, 'tab' => 'stats'],
                        collect([
                            'gender' => $statsFilters['gender'] !== $defaultStatsGender ? $statsFilters['gender'] : null,
                            'metric' => $statsFilters['metric'] !== 'goals' ? $statsFilters['metric'] : null,
                            'search' => $statsFilters['search'] !== '' ? $statsFilters['search'] : null,
                            'page' => $currentStatsPage > 1 ? $currentStatsPage : null,
                        ])->merge($overrides)
                            ->filter(fn ($value) => ! is_null($value) && $value !== '')
                            ->all(),
                    ));
                };
            @endphp

            <section class="rounded-[1rem] border border-zinc-200 bg-white p-6 shadow-sm">
                <div class="sr-only text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">Stats</div>
                <h2 class="sr-only mt-2 text-2xl font-semibold tracking-tight text-zinc-900">Player leaderboard</h2>

                <div class="space-y-4">
                    <div class="grid gap-3 md:grid-cols-[6.5rem_minmax(0,1fr)] md:items-center">
                        <div class="text-sm font-semibold text-zinc-900">Division</div>
                        <div class="rounded-full border border-zinc-200 px-2 py-1.5">
                            <span class="inline-flex rounded-full bg-[#2f55b7] px-5 py-2 text-sm font-semibold text-white">
                                {{ $statsDivision['label'] }}
                            </span>
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-[6.5rem_minmax(0,1fr)] md:items-center">
                        <div class="text-sm font-semibold text-zinc-900">Gender</div>
                        <div class="flex flex-wrap gap-2 rounded-full border border-zinc-200 px-2 py-1.5">
                            @foreach ($statsOptions['gender'] as $option)
                                <a
                                    href="{{ $statsUrl([
                                        'gender' => $option['value'] === $defaultStatsGender ? null : $option['value'],
                                        'page' => null,
                                    ]) }}"
                                    class="rounded-full px-5 py-2 text-sm font-semibold transition {{ $statsFilters['gender'] === $option['value']
                                        ? 'bg-[#2f55b7] text-white'
                                        : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900' }}"
                                    wire:navigate.preserve-scroll
                                >
                                    {{ $option['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-[6.5rem_minmax(0,1fr)] md:items-center">
                        <div class="text-sm font-semibold text-zinc-900">Filter by</div>
                        <div class="flex flex-wrap gap-2 rounded-full border border-zinc-200 px-2 py-1.5">
                            @foreach ($statsOptions['metrics'] as $option)
                                <a
                                    href="{{ $statsUrl([
                                        'metric' => $option['value'] === 'goals' ? null : $option['value'],
                                        'page' => null,
                                    ]) }}"
                                    class="rounded-full px-5 py-2 text-sm font-semibold transition {{ $statsFilters['metric'] === $option['value']
                                        ? 'bg-[#2f55b7] text-white'
                                        : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900' }}"
                                    wire:navigate.preserve-scroll
                                >
                                    {{ $option['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="mt-3 flex justify-end">
                    <form method="GET" action="{{ route('tournaments.show', $tournament) }}" class="w-full sm:w-auto" data-livewire-navigate-form>
                        <input type="hidden" name="tab" value="stats">
                        <input type="hidden" name="gender" value="{{ $statsFilters['gender'] }}">
                        <input type="hidden" name="metric" value="{{ $statsFilters['metric'] }}">
                        <input
                            type="search"
                            name="search"
                            value="{{ $statsFilters['search'] }}"
                            placeholder="Search by name"
                            class="w-full rounded-[0.8rem] border border-zinc-300 px-5 py-3 text-lg text-zinc-700 outline-none transition placeholder:text-zinc-400 focus:border-[#2f55b7] sm:w-[16.5rem]"
                        >
                    </form>
                </div>

                <div class="mt-5 overflow-hidden rounded-[1rem] border border-zinc-200 bg-white">
                    <div class="overflow-x-auto">
                        <div class="min-w-[44rem]">
                            <div class="grid grid-cols-[5rem_minmax(0,1.55fr)_5.75rem_5.75rem_5.75rem] items-center gap-3 border-b border-zinc-200 bg-zinc-50 px-5 py-3 text-sm font-semibold text-zinc-900">
                                <div>Rank</div>
                                <div>Name</div>
                                <div class="text-center">Goals</div>
                                <div class="text-center">Asst.</div>
                                <div class="text-center">Total</div>
                            </div>

                            @forelse ($statsLeaderboard as $player)
                                @php
                                    $rankBadgeClasses = match ($player['rank']) {
                                        1 => 'bg-[#ffd34d] text-[#7a4a00]',
                                        2 => 'bg-zinc-200 text-zinc-700',
                                        3 => 'bg-[#d78a33] text-[#663300]',
                                        default => '',
                                    };
                                @endphp

                                <div class="grid grid-cols-[5rem_minmax(0,1.55fr)_5.75rem_5.75rem_5.75rem] items-center gap-3 border-b border-zinc-200 px-5 py-4 last:border-b-0">
                                    <div class="text-center text-2xl font-medium text-zinc-900">
                                        @if ($player['rank'] <= 3)
                                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-full text-xl font-medium {{ $rankBadgeClasses }}">
                                                {{ $player['rank'] }}
                                            </span>
                                        @else
                                            {{ $player['rank'] }}
                                        @endif
                                    </div>

                                    <div class="min-w-0">
                                        <div class="truncate text-[1.05rem] font-medium text-zinc-950">
                                            {{ $player['display_name'] }}
                                        </div>
                                        <div class="mt-1 truncate text-sm text-zinc-400">
                                            {{ $player['team']->name }}
                                        </div>
                                    </div>

                                    <div class="text-center text-2xl font-medium text-zinc-900">{{ $player['goals'] }}</div>
                                    <div class="text-center text-2xl font-medium text-zinc-900">{{ $player['assists'] }}</div>
                                    <div class="text-center text-2xl font-medium text-zinc-900">{{ $player['total_offense'] }}</div>
                                </div>
                            @empty
                                <div class="px-5 py-12 text-center text-sm leading-6 text-zinc-600">
                                    No player stats match the current filters yet.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                @if ($statsLeaderboard->lastPage() > 1)
                    <div class="mt-5 flex flex-wrap items-center justify-center gap-2.5 text-xl font-medium text-zinc-900">
                        @if ($statsLeaderboard->onFirstPage())
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-full text-zinc-300">
                                &laquo;
                            </span>
                        @else
                            <a
                                href="{{ $statsUrl(['page' => $statsLeaderboard->currentPage() - 1]) }}"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-full text-[#2f55b7] transition hover:bg-[#edf3ff]"
                                aria-label="Previous page"
                                wire:navigate.preserve-scroll
                            >
                                &laquo;
                            </a>
                        @endif

                        @foreach (range(1, $statsLeaderboard->lastPage()) as $pageNumber)
                            <a
                                href="{{ $statsUrl(['page' => $pageNumber === 1 ? null : $pageNumber]) }}"
                                class="inline-flex h-11 w-11 items-center justify-center rounded-full transition {{ $statsLeaderboard->currentPage() === $pageNumber
                                    ? 'bg-[#2f55b7] text-white'
                                    : 'text-zinc-900 hover:bg-zinc-100' }}"
                                @if ($statsLeaderboard->currentPage() === $pageNumber) aria-current="page" @endif
                                wire:navigate.preserve-scroll
                            >
                                {{ $pageNumber }}
                            </a>
                        @endforeach

                        @if ($statsLeaderboard->hasMorePages())
                            <a
                                href="{{ $statsUrl(['page' => $statsLeaderboard->currentPage() + 1]) }}"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-full text-[#2f55b7] transition hover:bg-[#edf3ff]"
                                aria-label="Next page"
                                wire:navigate.preserve-scroll
                            >
                                &raquo;
                            </a>
                        @else
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-full text-zinc-300">
                                &raquo;
                            </span>
                        @endif
                    </div>
                @endif
            </section>
        @elseif ($activeTab === 'spirit')
            @php
                $spiritSortUrl = function (string $column) use ($tournament, $baseQuery, $spiritSort) {
                    $defaultDirection = $column === 'team' ? 'asc' : 'desc';
                    $direction = $spiritSort['column'] === $column
                        ? ($spiritSort['direction'] === 'asc' ? 'desc' : 'asc')
                        : $defaultDirection;

                    return route('tournaments.show', array_merge(
                        ['tournament' => $tournament, 'tab' => 'spirit'],
                        $baseQuery,
                        collect([
                            'spirit_sort' => $column !== 'overall_spirit_score' ? $column : null,
                            'spirit_direction' => $direction !== $defaultDirection ? $direction : null,
                        ])->filter(fn ($value) => ! is_null($value) && $value !== '')
                            ->all(),
                    ));
                };
            @endphp

            <section class="rounded-[1rem] border border-zinc-200 bg-white p-6 shadow-sm">
                <div class="sr-only text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">Spirit</div>
                <h2 class="sr-only mt-2 text-2xl font-semibold tracking-tight text-zinc-900">Spirit roster</h2>

                <div class="grid gap-3 md:grid-cols-[6rem_minmax(0,1fr)] md:items-center">
                    <div class="text-sm font-semibold text-zinc-900">Division</div>
                    <div class="rounded-full border border-zinc-200 px-2 py-1.5">
                        <span class="inline-flex rounded-full bg-[#2f55b7] px-5 py-2 text-sm font-semibold text-white">
                            {{ $tournament->division ?: 'Open' }}
                        </span>
                    </div>
                </div>

                @if ($spiritDirectory->isNotEmpty())
                    <div class="mt-4 text-[1.05rem] text-zinc-900">Average Spirit Score of Each Team</div>

                    <div class="mt-3 overflow-hidden rounded-[1rem] border border-zinc-200 bg-white">
                        <div class="overflow-x-auto">
                            <div class="min-w-[67rem]">
                                <div class="grid grid-cols-[5rem_minmax(0,1.7fr)_6.5rem_5.5rem_5.5rem_5.5rem_5.5rem_5.5rem_5.5rem] items-center gap-3 border-b border-zinc-200 bg-zinc-50 px-4 py-3 text-sm font-semibold text-zinc-900">
                                    <div>Rank</div>

                                    @foreach ([
                                        ['key' => 'team', 'label' => 'Team', 'align' => 'justify-start'],
                                        ['key' => 'games_rated', 'label' => 'Games Rated', 'align' => 'justify-center'],
                                        ['key' => 'overall_spirit_score', 'label' => 'Avg', 'align' => 'justify-center'],
                                        ['key' => 'avg_rules', 'label' => 'Rules', 'align' => 'justify-center'],
                                        ['key' => 'avg_fouls', 'label' => 'Fouls', 'align' => 'justify-center'],
                                        ['key' => 'avg_fair', 'label' => 'Fair', 'align' => 'justify-center'],
                                        ['key' => 'avg_attitude', 'label' => 'Attit.', 'align' => 'justify-center'],
                                        ['key' => 'avg_communication', 'label' => 'Comm.', 'align' => 'justify-center'],
                                    ] as $header)
                                        @php
                                            $isActiveSort = $spiritSort['column'] === $header['key'];
                                        @endphp

                                        <a
                                            href="{{ $spiritSortUrl($header['key']) }}"
                                            class="flex items-center gap-1.5 {{ $header['align'] }} transition {{ $isActiveSort ? 'text-zinc-950' : 'text-zinc-700 hover:text-zinc-950' }}"
                                            wire:navigate.preserve-scroll
                                        >
                                            <span>{{ $header['label'] }}</span>
                                            <svg
                                                class="h-3.5 w-3.5 transition {{ $isActiveSort && $spiritSort['direction'] === 'asc' ? 'rotate-180' : '' }}"
                                                viewBox="0 0 20 20"
                                                fill="currentColor"
                                                aria-hidden="true"
                                            >
                                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                                            </svg>
                                        </a>
                                    @endforeach
                                </div>

                                @foreach ($spiritDirectory as $entry)
                                    @php
                                        $team = $entry['team'];
                                        $teamLogo = $team->logoUrl();
                                        $teamBadge = str($team->name)
                                            ->explode(' ')
                                            ->take(2)
                                            ->map(fn ($word) => str($word)->substr(0, 1))
                                            ->implode('');
                                        $rankBadgeClasses = match ($loop->iteration) {
                                            1 => 'bg-[#ffd34d] text-[#7a4a00]',
                                            2 => 'bg-zinc-300 text-zinc-700',
                                            3 => 'bg-[#d78a33] text-[#663300]',
                                            default => '',
                                        };
                                    @endphp

                                    <div class="grid grid-cols-[5rem_minmax(0,1.7fr)_6.5rem_5.5rem_5.5rem_5.5rem_5.5rem_5.5rem_5.5rem] items-center gap-3 border-b border-zinc-200 px-4 py-5 text-[1.05rem] text-zinc-900 last:border-b-0">
                                        <div class="text-center text-2xl font-medium text-zinc-900">
                                            @if ($loop->iteration <= 3)
                                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-full text-xl font-medium {{ $rankBadgeClasses }}">
                                                    {{ $loop->iteration }}
                                                </span>
                                            @else
                                                {{ $loop->iteration }}
                                            @endif
                                        </div>

                                        <div class="flex min-w-0 items-center gap-3">
                                            <div class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-full border border-zinc-200 bg-white text-xs font-semibold text-zinc-700">
                                                @if ($teamLogo)
                                                    <img src="{{ $teamLogo }}" alt="{{ $team->name }}" class="h-full w-full object-cover">
                                                @else
                                                    {{ $teamBadge }}
                                                @endif
                                            </div>

                                            <div class="min-w-0">
                                                <div class="truncate text-[1.55rem] font-medium leading-tight text-zinc-950">{{ $team->name }}</div>
                                                <div class="mt-1 truncate text-sm text-zinc-400">
                                                    {{ $team->country_name ?: ($team->locationLabel() ?: 'Location not listed') }}

                                                    @if ($entry['country_flag'])
                                                        <span class="ms-1">{{ $entry['country_flag'] }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        <div class="text-center text-[1.45rem] font-medium text-[#0ea5a4]">
                                            {{ $entry['games_rated_label'] }}
                                        </div>
                                        <div class="text-center">{{ number_format($entry['overall_spirit_score'], 2) }}</div>
                                        <div class="text-center">{{ number_format($entry['avg_rules'], 2) }}</div>
                                        <div class="text-center">{{ number_format($entry['avg_fouls'], 2) }}</div>
                                        <div class="text-center">{{ number_format($entry['avg_fair'], 2) }}</div>
                                        <div class="text-center">{{ number_format($entry['avg_attitude'], 2) }}</div>
                                        <div class="text-center">{{ number_format($entry['avg_communication'], 2) }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @else
                    <div class="mt-4 rounded-[0.9rem] border border-dashed border-zinc-200 bg-zinc-50 p-5 text-sm leading-6 text-zinc-600">
                        No published spirit scores have been recorded for this tournament yet.
                    </div>
                @endif
            </section>
        @elseif ($activeTab === 'group')
            @php
                $groupUrl = function (?string $pool = null) use ($tournament) {
                    return route('tournaments.show', array_filter([
                        'tournament' => $tournament,
                        'tab' => 'group',
                        'pool' => $pool,
                    ]));
                };

                $formatGroupLabel = fn (?string $name) => $name
                    ? str($name)->replace('POOL', 'GROUP')->headline()->toString()
                    : 'Unassigned';
            @endphp

            <section class="rounded-[1rem] border border-zinc-200 bg-white p-6 shadow-sm">
                <div class="sr-only text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">Group</div>
                <h2 class="sr-only mt-2 text-2xl font-semibold tracking-tight text-zinc-900">Pool play overview</h2>

                <div class="grid gap-3 md:grid-cols-[6rem_minmax(0,1fr)] md:items-center">
                    <div class="text-sm font-semibold text-zinc-900">Division</div>
                    <div class="rounded-full border border-zinc-200 px-2 py-1.5">
                        <span class="inline-flex rounded-full bg-[#2f55b7] px-5 py-2 text-sm font-semibold text-white">
                            {{ $tournament->division ?: 'Open' }}
                        </span>
                    </div>
                </div>

                @if ($groupPools->isNotEmpty())
                    <div class="mt-4 grid gap-3 md:grid-cols-[6rem_minmax(0,1fr)] md:items-center">
                        <div class="text-sm font-semibold text-zinc-900">Group</div>
                        <div class="flex flex-wrap gap-2 rounded-full border border-zinc-200 px-2 py-1.5">
                            @foreach ($groupPools as $pool)
                                <a
                                    href="{{ $groupUrl($pool['name']) }}"
                                    class="rounded-full px-5 py-2 text-sm font-semibold transition {{ $groupFilter === $pool['name']
                                        ? 'bg-[#2f55b7] text-white'
                                        : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900' }}"
                                    wire:navigate.preserve-scroll
                                >
                                    {{ $pool['label'] ?? $formatGroupLabel($pool['name']) }}
                                </a>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-4 overflow-hidden rounded-[1rem] border border-zinc-200">
                        <div class="grid grid-cols-[2.5rem_minmax(0,1.5fr)_2.75rem_2.75rem_2.75rem_3.5rem_3.75rem_2.75rem_2.75rem_5rem] items-center gap-3 border-b border-zinc-200 bg-zinc-50 px-4 py-3 text-sm font-semibold text-zinc-900">
                            <div>#</div>
                            <div>Team</div>
                            <div class="text-center">G</div>
                            <div class="text-center">W</div>
                            <div class="text-center">L</div>
                            <div class="text-center">Pts</div>
                            <div class="text-center">GD</div>
                            <div class="text-center">F</div>
                            <div class="text-center">A</div>
                            <div class="text-center">Form</div>
                        </div>

                        @foreach ($selectedGroupStandings as $row)
                            @php
                                $team = $row['team'];
                                $teamLogo = $team->logoUrl();
                                $teamBadge = str($team->name)
                                    ->explode(' ')
                                    ->take(2)
                                    ->map(fn ($word) => str($word)->substr(0, 1))
                                    ->implode('');
                                $form = collect($row['form'])
                                    ->take(4)
                                    ->pad(4, 'pending');
                            @endphp

                            <div class="grid grid-cols-[2.5rem_minmax(0,1.5fr)_2.75rem_2.75rem_2.75rem_3.5rem_3.75rem_2.75rem_2.75rem_5rem] items-center gap-3 border-b border-zinc-200 px-4 py-4 text-sm last:border-b-0">
                                <div class="text-2xl font-light text-zinc-900">{{ $loop->iteration }}</div>

                                <div class="flex min-w-0 items-center gap-3">
                                    <div class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-full border border-zinc-200 bg-white text-[10px] font-semibold text-zinc-700">
                                        @if ($teamLogo)
                                            <img src="{{ $teamLogo }}" alt="{{ $team->name }}" class="h-full w-full object-cover">
                                        @else
                                            {{ $teamBadge }}
                                        @endif
                                    </div>

                                    <div class="min-w-0">
                                        <div class="truncate text-lg font-medium text-zinc-900">{{ $team->name }}</div>
                                    </div>
                                </div>

                                <div class="text-center text-lg font-medium text-zinc-900">{{ $row['played'] }}</div>
                                <div class="text-center text-lg font-medium text-zinc-900">{{ $row['wins'] }}</div>
                                <div class="text-center text-lg font-medium text-zinc-900">{{ $row['losses'] }}</div>
                                <div class="text-center text-lg font-semibold text-zinc-900">{{ $row['points'] }}</div>
                                <div class="text-center text-lg font-medium text-zinc-900">{{ $row['goal_difference'] > 0 ? '+' : '' }}{{ $row['goal_difference'] }}</div>
                                <div class="text-center text-lg font-medium text-zinc-900">{{ $row['goals_for'] }}</div>
                                <div class="text-center text-lg font-medium text-zinc-900">{{ $row['goals_against'] }}</div>

                                <div class="flex items-center justify-center gap-1.5">
                                    @foreach ($form as $formResult)
                                        <span class="h-2.5 w-2.5 rounded-full {{ match ($formResult) {
                                            'win' => 'bg-[#22b8a2]',
                                            'loss' => 'bg-[#ef4444]',
                                            'tie' => 'bg-[#9ca3af]',
                                            default => 'bg-zinc-300',
                                        } }}"></span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 rounded-[0.9rem] border border-zinc-200 bg-white px-4 py-4 text-sm leading-7 text-zinc-700">
                        <div class="grid gap-2 md:grid-cols-2">
                            <div><span class="font-semibold text-zinc-900">G</span> - Games Played</div>
                            <div><span class="font-semibold text-zinc-900">GD</span> - Goal Difference</div>
                            <div><span class="font-semibold text-zinc-900">W</span> - Number of Wins</div>
                            <div><span class="font-semibold text-zinc-900">F</span> - Goals Scored</div>
                            <div><span class="font-semibold text-zinc-900">L</span> - Number of Losses</div>
                            <div><span class="font-semibold text-zinc-900">A</span> - Goals Against</div>
                            <div><span class="font-semibold text-zinc-900">Pts</span> - Points</div>
                        </div>

                        <div class="mt-3 text-sm text-zinc-900">1 win = 3 points, 1 tie = 1 point, 1 loss = 0 point</div>
                        <div class="text-sm font-medium text-zinc-900">*The tiebreaker format for this event is based on WFDF Tiebreaker Rule</div>
                    </div>
                @endif

                <div class="mt-6">
                    <div class="text-3xl font-semibold tracking-tight text-zinc-900">Group Games</div>

                    @if ($selectedGroupMatches->isNotEmpty())
                        <div class="mt-4 space-y-4">
                            @foreach ($selectedGroupMatches as $match)
                                @php
                                    $homeTeam = $match->homeRegistration?->team;
                                    $awayTeam = $match->awayRegistration?->team;
                                    $statusClasses = match ($match->status) {
                                        'completed' => 'bg-zinc-100 text-zinc-500',
                                        'live' => 'bg-[#2f55b7] text-white',
                                        default => 'bg-zinc-100 text-zinc-600',
                                    };
                                    $statusLabel = match ($match->status) {
                                        'completed' => 'Finalised',
                                        'live' => 'Live',
                                        default => 'Scheduled',
                                    };
                                    $homeTeamLogo = $homeTeam?->logoUrl();
                                    $awayTeamLogo = $awayTeam?->logoUrl();
                                    $homeBadge = $homeTeam
                                        ? str($homeTeam->name)->explode(' ')->take(2)->map(fn ($word) => str($word)->substr(0, 1))->implode('')
                                        : 'TBD';
                                    $awayBadge = $awayTeam
                                        ? str($awayTeam->name)->explode(' ')->take(2)->map(fn ($word) => str($word)->substr(0, 1))->implode('')
                                        : 'TBD';
                                @endphp

                                <a
                                    href="{{ route('tournaments.matches.show', ['tournament' => $tournament, 'match' => $match]) }}"
                                    class="block rounded-[1rem] border border-zinc-200 bg-white px-5 py-4 transition hover:border-zinc-300 hover:shadow-sm"
                                    wire:navigate
                                >
                                    <div class="flex flex-wrap items-center gap-2">
                                        <div class="text-xs font-semibold uppercase tracking-[0.08em] text-zinc-900">Group Game</div>
                                        <span class="rounded-full border border-zinc-200 bg-zinc-50 px-2.5 py-0.5 text-[11px] font-semibold text-zinc-400">
                                            {{ data_get($groupPools->firstWhere('name', $groupFilter), 'label', $formatGroupLabel($groupFilter)) }}
                                        </span>
                                    </div>

                                    <div class="mt-1 text-sm font-semibold text-zinc-900">
                                        {{ $match->pitch?->name ?: 'Field TBD' }}
                                    </div>

                                    <div class="mt-4 grid gap-4 md:grid-cols-[5.75rem_minmax(0,1fr)]">
                                        <div>
                                            <div class="text-sm font-semibold uppercase text-zinc-500">
                                                {{ $match->scheduled_at ? strtoupper($match->scheduled_at->format('d M')) : 'TBD' }}
                                            </div>
                                            <div class="text-2xl font-semibold text-zinc-500">
                                                {{ $match->scheduled_at ? $match->scheduled_at->format('H:i') : 'TBD' }}
                                            </div>

                                            <span class="mt-3 inline-flex rounded-[0.55rem] px-3 py-1 text-xs font-semibold uppercase tracking-[0.04em] {{ $statusClasses }}">
                                                {{ $statusLabel }}
                                            </span>
                                        </div>

                                        <div class="space-y-4">
                                            <div class="flex items-center gap-3">
                                                <div class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-full border border-zinc-200 bg-white text-xs font-semibold text-zinc-700">
                                                    @if ($homeTeamLogo)
                                                        <img src="{{ $homeTeamLogo }}" alt="{{ $homeTeam?->name }}" class="h-full w-full object-cover">
                                                    @else
                                                        {{ $homeBadge }}
                                                    @endif
                                                </div>
                                                <div class="min-w-0 flex-1 text-xl font-semibold text-zinc-900">{{ $homeTeam?->name ?: 'TBD' }}</div>
                                                <div class="text-3xl font-semibold text-zinc-900">{{ $match->home_score ?? '-' }}</div>
                                            </div>

                                            <div class="flex items-center gap-3">
                                                <div class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-full border border-zinc-200 bg-white text-xs font-semibold text-zinc-700">
                                                    @if ($awayTeamLogo)
                                                        <img src="{{ $awayTeamLogo }}" alt="{{ $awayTeam?->name }}" class="h-full w-full object-cover">
                                                    @else
                                                        {{ $awayBadge }}
                                                    @endif
                                                </div>
                                                <div class="min-w-0 flex-1 text-xl font-semibold text-zinc-900">{{ $awayTeam?->name ?: 'TBD' }}</div>
                                                <div class="text-3xl font-semibold text-zinc-900">{{ $match->away_score ?? '-' }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="mt-4 rounded-[0.9rem] border border-dashed border-zinc-200 bg-zinc-50 p-5 text-sm leading-6 text-zinc-600">
                            No group-stage assignments or fixtures have been published yet.
                        </div>
                    @endif
                </div>
            </section>
        @elseif ($activeTab === 'bracket')
            @php
                $bracketUrl = function (?string $flight = null) use ($tournament) {
                    return route('tournaments.show', array_filter([
                        'tournament' => $tournament,
                        'tab' => 'bracket',
                        'flight' => $flight,
                    ]));
                };

                $bracketColumns = $bracketBoard['columns'];
                $bracketPlacements = $bracketBoard['placements'];
                $bracketTotalRows = $bracketBoard['total_rows'];
                $hasBracketBoard = $bracketColumns->isNotEmpty() || $bracketPlacements->isNotEmpty();
                $bracketGridStyle = 'grid-template-columns: repeat('.max($bracketColumns->count(), 1).', minmax(17rem, 1fr));';
            @endphp

            <section class="rounded-[1rem] border border-zinc-200 bg-white p-6 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">Bracket</div>
                <h2 class="mt-2 text-2xl font-semibold tracking-tight text-zinc-900">Bracket overview</h2>

                <div class="mt-5 grid gap-3 md:grid-cols-[6rem_minmax(0,1fr)] md:items-center">
                    <div class="text-sm font-semibold text-zinc-900">Division</div>
                    <div class="rounded-full border border-zinc-200 px-2 py-1.5">
                        <span class="inline-flex rounded-full bg-[#2f55b7] px-5 py-2 text-sm font-semibold text-white">
                            {{ $tournament->division ?: 'Open' }}
                        </span>
                    </div>
                </div>

                @if ($bracketFlights->isNotEmpty())
                    <div class="mt-4 grid gap-3 md:grid-cols-[6rem_minmax(0,1fr)] md:items-center">
                        <div class="text-sm font-semibold text-zinc-900">Pool</div>
                        <div class="flex flex-wrap gap-2 rounded-full border border-zinc-200 px-2 py-1.5">
                            @foreach ($bracketFlights as $flight)
                                <a
                                    href="{{ $bracketUrl($flight['code']) }}"
                                    class="rounded-full px-5 py-2 text-sm font-semibold transition {{ $bracketFilter === $flight['code']
                                        ? 'bg-[#2f55b7] text-white'
                                        : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900' }}"
                                    wire:navigate
                                >
                                    {{ $flight['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($hasBracketBoard)
                    @include('tournaments.partials.bracket-board', [
                        'tournament' => $tournament,
                        'bracketColumns' => $bracketColumns,
                        'bracketPlacements' => $bracketPlacements,
                        'bracketTotalRows' => $bracketTotalRows,
                    ])

                    {{--
                    <div class="mt-8 overflow-x-auto pb-2">
                        <div class="grid min-w-[56rem] items-start gap-8" style="{{ $bracketGridStyle }}">
                            @foreach ($bracketColumns as $column)
                                @php
                                    $columnOffsetClass = match ($column['key']) {
                                        'quarterfinals' => '',
                                        'semifinals' => 'pt-14',
                                        'finals' => 'pt-28',
                                        default => 'pt-8',
                                    };
                                    $isFirstBracketColumn = $loop->first;
                                    $isLastBracketColumn = $loop->last;
                                @endphp

                                <div class="space-y-4">
                                    <div class="pl-3 text-2xl font-semibold tracking-tight text-zinc-900">{{ $column['label'] }}</div>

                                    <div class="space-y-4 {{ $columnOffsetClass }}">
                                        @foreach ($column['matches'] as $card)
                                            @php
                                                $match = $card['match'];
                                                $homeTeam = $match->homeRegistration?->team;
                                                $awayTeam = $match->awayRegistration?->team;
                                                $homeTeamLogo = $homeTeam?->logoUrl();
                                                $awayTeamLogo = $awayTeam?->logoUrl();
                                                $homeBadge = $homeTeam
                                                    ? str($homeTeam->name)->explode(' ')->take(2)->map(fn ($word) => str($word)->substr(0, 1))->implode('')
                                                    : 'TBD';
                                                $awayBadge = $awayTeam
                                                    ? str($awayTeam->name)->explode(' ')->take(2)->map(fn ($word) => str($word)->substr(0, 1))->implode('')
                                                    : 'TBD';
                                                $statusClasses = match ($match->status) {
                                                    'completed' => 'bg-zinc-100 text-zinc-500',
                                                    'live' => 'bg-[#61d9a5] text-white',
                                                    default => 'bg-zinc-100 text-zinc-600',
                                                };
                                                $statusLabel = match ($match->status) {
                                                    'completed' => 'Finalised',
                                                    'live' => 'Live',
                                                    default => 'Scheduled',
                                                };
                                            @endphp

                                            <div class="relative">
                                                <a
                                                    href="{{ route('tournaments.matches.show', ['tournament' => $tournament, 'match' => $match]) }}"
                                                    class="block rounded-[1rem] border border-zinc-200 bg-white p-4 shadow-sm transition hover:border-zinc-300 hover:shadow-md"
                                                >
                                                    <div class="flex items-start justify-between gap-2">
                                                        <div class="min-w-0">
                                                            <div class="truncate text-[11px] font-semibold uppercase leading-4 text-zinc-900">
                                                                {{ $match->round_label ?: str($match->stage)->replace('_', ' ')->headline() }}
                                                            </div>
                                                            <div class="mt-1 text-xs font-medium text-zinc-500">
                                                                {{ $match->pitch?->name ?: 'Field TBD' }}
                                                            </div>
                                                        </div>

                                                        @if ($match->match_number)
                                                            <span class="shrink-0 rounded-full border border-zinc-200 bg-zinc-50 px-2 py-0.5 text-[10px] font-semibold text-zinc-400">
                                                                M{{ $match->match_number }}
                                                            </span>
                                                        @endif
                                                    </div>

                                                    <div class="mt-4 grid grid-cols-[3.9rem_minmax(0,1fr)] gap-4">
                                                        <div>
                                                            <div class="text-[11px] font-semibold uppercase text-zinc-500">
                                                                {{ $match->scheduled_at ? strtoupper($match->scheduled_at->format('d M')) : 'TBD' }}
                                                            </div>
                                                            <div class="mt-1 text-sm font-semibold text-zinc-400">
                                                                {{ $match->scheduled_at ? $match->scheduled_at->format('H:i') : 'TBD' }}
                                                            </div>

                                                            <span class="mt-3 inline-flex rounded-[0.55rem] px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.04em] {{ $statusClasses }}">
                                                                {{ $statusLabel }}
                                                            </span>
                                                        </div>

                                                        <div class="space-y-3">
                                                            <div class="flex items-center gap-2.5">
                                                                <div class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full border border-zinc-200 bg-white text-[10px] font-semibold text-zinc-700">
                                                                    @if ($homeTeamLogo)
                                                                        <img src="{{ $homeTeamLogo }}" alt="{{ $homeTeam?->name }}" class="h-full w-full object-cover">
                                                                    @else
                                                                        {{ $homeBadge }}
                                                                    @endif
                                                                </div>
                                                                <div class="min-w-0 flex-1 truncate text-sm font-semibold text-zinc-900">{{ $homeTeam?->name ?: 'TBD' }}</div>
                                                                <div class="text-lg font-semibold text-zinc-900">{{ $match->home_score ?? '-' }}</div>
                                                            </div>

                                                            <div class="flex items-center gap-2.5">
                                                                <div class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full border border-zinc-200 bg-white text-[10px] font-semibold text-zinc-700">
                                                                    @if ($awayTeamLogo)
                                                                        <img src="{{ $awayTeamLogo }}" alt="{{ $awayTeam?->name }}" class="h-full w-full object-cover">
                                                                    @else
                                                                        {{ $awayBadge }}
                                                                    @endif
                                                                </div>
                                                                <div class="min-w-0 flex-1 truncate text-sm font-semibold text-zinc-900">{{ $awayTeam?->name ?: 'TBD' }}</div>
                                                                <div class="text-lg font-semibold text-zinc-900">{{ $match->away_score ?? '-' }}</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </a>

                                                @if (! $isFirstBracketColumn)
                                                    <div class="pointer-events-none absolute -left-8 top-1/2 hidden h-px w-8 -translate-y-1/2 bg-zinc-300 xl:block"></div>
                                                @endif

                                                @if (! $isLastBracketColumn)
                                                    <div class="pointer-events-none absolute -right-8 top-1/2 hidden h-px w-8 -translate-y-1/2 bg-zinc-300 xl:block"></div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>

                                    @if ($column['key'] === 'finals' && $bracketPlacements->isNotEmpty())
                                        <div class="space-y-4 pt-8">
                                            @foreach ($bracketPlacements as $card)
                                                @php
                                                    $match = $card['match'];
                                                    $homeTeam = $match->homeRegistration?->team;
                                                    $awayTeam = $match->awayRegistration?->team;
                                                    $homeTeamLogo = $homeTeam?->logoUrl();
                                                    $awayTeamLogo = $awayTeam?->logoUrl();
                                                    $homeBadge = $homeTeam
                                                        ? str($homeTeam->name)->explode(' ')->take(2)->map(fn ($word) => str($word)->substr(0, 1))->implode('')
                                                        : 'TBD';
                                                    $awayBadge = $awayTeam
                                                        ? str($awayTeam->name)->explode(' ')->take(2)->map(fn ($word) => str($word)->substr(0, 1))->implode('')
                                                        : 'TBD';
                                                    $statusClasses = match ($match->status) {
                                                        'completed' => 'bg-[#61d9a5] text-white',
                                                        'live' => 'bg-[#2f55b7] text-white',
                                                        default => 'bg-zinc-100 text-zinc-600',
                                                    };
                                                    $statusLabel = match ($match->status) {
                                                        'completed' => 'Ended',
                                                        'live' => 'Live',
                                                        default => 'Scheduled',
                                                    };
                                                @endphp

                                                <a
                                                    href="{{ route('tournaments.matches.show', ['tournament' => $tournament, 'match' => $match]) }}"
                                                    class="block rounded-[1rem] border border-zinc-200 bg-white p-4 shadow-sm transition hover:border-zinc-300 hover:shadow-md"
                                                >
                                                    <div class="flex items-start justify-between gap-2">
                                                        <div class="min-w-0">
                                                            <div class="truncate text-[11px] font-semibold uppercase leading-4 text-zinc-900">
                                                                {{ $match->round_label ?: str($match->stage)->replace('_', ' ')->headline() }}
                                                            </div>
                                                            <div class="mt-1 text-xs font-medium text-zinc-500">
                                                                {{ $match->pitch?->name ?: 'Field TBD' }}
                                                            </div>
                                                        </div>

                                                        @if ($match->match_number)
                                                            <span class="shrink-0 rounded-full border border-zinc-200 bg-zinc-50 px-2 py-0.5 text-[10px] font-semibold text-zinc-400">
                                                                M{{ $match->match_number }}
                                                            </span>
                                                        @endif
                                                    </div>

                                                    <div class="mt-4 grid grid-cols-[3.9rem_minmax(0,1fr)] gap-4">
                                                        <div>
                                                            <div class="text-[11px] font-semibold uppercase text-zinc-500">
                                                                {{ $match->scheduled_at ? strtoupper($match->scheduled_at->format('d M')) : 'TBD' }}
                                                            </div>
                                                            <div class="mt-1 text-sm font-semibold text-zinc-400">
                                                                {{ $match->scheduled_at ? $match->scheduled_at->format('H:i') : 'TBD' }}
                                                            </div>

                                                            <span class="mt-3 inline-flex rounded-[0.55rem] px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.04em] {{ $statusClasses }}">
                                                                {{ $statusLabel }}
                                                            </span>
                                                        </div>

                                                        <div class="space-y-3">
                                                            <div class="flex items-center gap-2.5">
                                                                <div class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full border border-zinc-200 bg-white text-[10px] font-semibold text-zinc-700">
                                                                    @if ($homeTeamLogo)
                                                                        <img src="{{ $homeTeamLogo }}" alt="{{ $homeTeam?->name }}" class="h-full w-full object-cover">
                                                                    @else
                                                                        {{ $homeBadge }}
                                                                    @endif
                                                                </div>
                                                                <div class="min-w-0 flex-1 truncate text-sm font-semibold text-zinc-900">{{ $homeTeam?->name ?: 'TBD' }}</div>
                                                                <div class="text-lg font-semibold text-zinc-900">{{ $match->home_score ?? '-' }}</div>
                                                            </div>

                                                            <div class="flex items-center gap-2.5">
                                                                <div class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full border border-zinc-200 bg-white text-[10px] font-semibold text-zinc-700">
                                                                    @if ($awayTeamLogo)
                                                                        <img src="{{ $awayTeamLogo }}" alt="{{ $awayTeam?->name }}" class="h-full w-full object-cover">
                                                                    @else
                                                                        {{ $awayBadge }}
                                                                    @endif
                                                                </div>
                                                                <div class="min-w-0 flex-1 truncate text-sm font-semibold text-zinc-900">{{ $awayTeam?->name ?: 'TBD' }}</div>
                                                                <div class="text-lg font-semibold text-zinc-900">{{ $match->away_score ?? '-' }}</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                    --}}
                @else
                    <div class="mt-6 rounded-[0.9rem] border border-dashed border-zinc-200 bg-zinc-50 p-5 text-sm leading-6 text-zinc-600">
                        No bracket assignments or elimination matches have been published yet.
                    </div>
                @endif
            </section>
        @elseif ($activeTab === 'mvp')
            @php
                $defaultMvpGender = data_get($statsOptions['gender']->first(), 'value', 'all');
                $mvpUrl = function (array $overrides = []) use ($tournament, $mvpFilters, $defaultMvpGender) {
                    return route('tournaments.show', array_merge(
                        ['tournament' => $tournament, 'tab' => 'mvp'],
                        collect([
                            'gender' => $mvpFilters['gender'] !== $defaultMvpGender ? $mvpFilters['gender'] : null,
                        ])->merge($overrides)
                            ->filter(fn ($value) => ! is_null($value) && $value !== '')
                            ->all(),
                    ));
                };
            @endphp

            <section class="rounded-[1rem] border border-zinc-200 bg-white p-6 shadow-sm">
                <div class="sr-only text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">MVP</div>
                <h2 class="sr-only mt-2 text-2xl font-semibold tracking-tight text-zinc-900">Tournament MVP race</h2>

                <div class="space-y-4">
                    <div class="grid gap-3 md:grid-cols-[6.5rem_minmax(0,1fr)] md:items-center">
                        <div class="text-sm font-semibold text-zinc-900">Division</div>
                        <div class="rounded-full border border-zinc-200 px-2 py-1.5">
                            <span class="inline-flex rounded-full bg-[#2f55b7] px-5 py-2 text-sm font-semibold text-white">
                                {{ $statsDivision['label'] }}
                            </span>
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-[6.5rem_minmax(0,1fr)] md:items-center">
                        <div class="text-sm font-semibold text-zinc-900">Gender</div>
                        <div class="flex flex-wrap gap-2 rounded-full border border-zinc-200 px-2 py-1.5">
                            @foreach ($statsOptions['gender'] as $option)
                                <a
                                    href="{{ $mvpUrl(['gender' => $option['value'] === $defaultMvpGender ? null : $option['value']]) }}"
                                    class="rounded-full px-5 py-2 text-sm font-semibold transition {{ $mvpFilters['gender'] === $option['value']
                                        ? 'bg-[#2f55b7] text-white'
                                        : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900' }}"
                                    wire:navigate
                                >
                                    {{ $option['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>

                @if ($mvpLeaderboard->isNotEmpty())
                    <div class="mt-5 overflow-hidden rounded-[1rem] border border-zinc-200 bg-white">
                        <div class="overflow-x-auto">
                            <div class="min-w-[42rem]">
                                <div class="grid grid-cols-[minmax(0,1.45fr)_minmax(0,1fr)_7rem] items-center gap-4 border-b border-zinc-200 bg-zinc-50 px-5 py-3 text-sm font-semibold text-zinc-900">
                                    <div>Name</div>
                                    <div>Team</div>
                                    <div>Gender</div>
                                </div>

                                @foreach ($mvpLeaderboard as $candidate)
                                    <div class="grid grid-cols-[minmax(0,1.45fr)_minmax(0,1fr)_7rem] items-center gap-4 border-b border-zinc-200 px-5 py-4 last:border-b-0">
                                        <div class="min-w-0 text-[1.05rem] font-medium text-zinc-950">
                                            {{ $candidate['display_name'] }}
                                        </div>
                                        <div class="min-w-0 text-[1.05rem] text-zinc-900">
                                            {{ $candidate['team']?->name ?: 'Team not listed' }}
                                        </div>
                                        <div class="text-[1.05rem] text-zinc-900">
                                            {{ $candidate['gender_label'] }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @else
                    <div class="mt-5 rounded-[0.9rem] border border-dashed border-zinc-200 bg-zinc-50 p-5 text-sm leading-6 text-zinc-600">
                        No published player stats match the current MVP filters yet.
                    </div>
                @endif
            </section>
        @elseif ($activeTab === 'standings')
            @php
                $hasStandingsResults = $standings->contains(fn (array $row) => $row['played'] > 0);
            @endphp

            <section class="rounded-[1rem] border border-zinc-200 bg-white p-6 shadow-sm">
                <div class="sr-only text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">Standings</div>
                <h2 class="sr-only mt-2 text-2xl font-semibold tracking-tight text-zinc-900">Tournament table</h2>

                <div class="grid gap-3 md:grid-cols-[6.5rem_minmax(0,1fr)] md:items-center">
                    <div class="text-sm font-semibold text-zinc-900">Division</div>
                    <div class="rounded-full border border-zinc-200 px-2 py-1.5">
                        <span class="inline-flex rounded-full bg-[#2f55b7] px-5 py-2 text-sm font-semibold text-white">
                            {{ $statsDivision['label'] }}
                        </span>
                    </div>
                </div>

                @if ($hasStandingsResults)
                    <div class="mt-5 overflow-hidden rounded-[1rem] border border-zinc-200 bg-white">
                        <div class="grid grid-cols-[5rem_minmax(0,1.6fr)_8rem] items-center gap-4 border-b border-zinc-200 bg-zinc-50 px-5 py-3 text-sm font-semibold text-zinc-900">
                            <div>Rank</div>
                            <div>Name</div>
                            <div>Division</div>
                        </div>

                        @foreach ($standings as $row)
                            @php
                                $teamLogo = $row['team']->logoUrl();
                                $rankBadgeClasses = match ($loop->iteration) {
                                    1 => 'bg-[#ffd34d] text-[#7a4a00]',
                                    2 => 'bg-zinc-300 text-zinc-700',
                                    3 => 'bg-[#d78a33] text-[#663300]',
                                    default => '',
                                };
                            @endphp

                            <a
                                href="{{ route('tournaments.teams.show', ['tournament' => $tournament, 'team' => $row['team']]) }}"
                                class="grid grid-cols-[5rem_minmax(0,1.6fr)_8rem] items-center gap-4 border-b border-zinc-200 px-5 py-4 transition hover:bg-zinc-50 last:border-b-0"
                                wire:navigate
                            >
                                <div class="text-center text-2xl font-medium text-zinc-900">
                                    @if ($loop->iteration <= 3)
                                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-full text-xl font-medium {{ $rankBadgeClasses }}">
                                            {{ $loop->iteration }}
                                        </span>
                                    @else
                                        {{ $loop->iteration }}
                                    @endif
                                </div>

                                <div class="flex min-w-0 items-center gap-4">
                                    <div class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-full border border-[#ff5b63] bg-white">
                                        @if ($teamLogo)
                                            <img src="{{ $teamLogo }}" alt="{{ $row['team']->name }}" class="h-full w-full object-cover">
                                        @else
                                            <svg class="h-8 w-8 text-[#ff5b63]" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"></circle>
                                                <path d="M12 6.5l1.7 3.45 3.8.56-2.75 2.68.65 3.81L12 15.2l-3.4 1.8.65-3.81L6.5 10.5l3.8-.56L12 6.5Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"></path>
                                            </svg>
                                        @endif
                                    </div>

                                    <div class="min-w-0">
                                        <div class="truncate text-[1.05rem] font-medium text-[#2f55b7]">
                                            {{ $row['team']->name }}
                                        </div>
                                        <div class="mt-1 truncate text-sm text-zinc-400">
                                            {{ $row['team']->country_name ?: ($row['team']->locationLabel() ?: 'Location not listed') }}

                                            @if ($row['country_flag'])
                                                <span class="ms-1">{{ $row['country_flag'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="text-[1.05rem] text-zinc-900">
                                    {{ $row['division_label'] }}
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="mt-5 rounded-[0.9rem] border border-dashed border-zinc-200 bg-zinc-50 p-5 text-sm leading-6 text-zinc-600">
                        No completed matches have been published yet, so the standings table is still empty.
                    </div>
                @endif
            </section>
        @elseif ($activeTab === 'crew')
            <section class="rounded-[1rem] border border-zinc-200 bg-white p-6 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">Crew</div>
                <h2 class="mt-2 text-2xl font-semibold tracking-tight text-zinc-900">Event and team crew</h2>

                @if ($crewSections->isNotEmpty())
                    <div class="mt-6 space-y-8">
                        @foreach ($crewSections as $section)
                            <div>
                                <div class="text-center text-3xl font-semibold tracking-tight text-zinc-900">
                                    {{ $section['category'] }}
                                </div>

                                <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                    @foreach ($section['members'] as $member)
                                        @php
                                            $crewPhoto = $member->photoUrl();
                                        @endphp

                                        <div class="flex flex-col items-center rounded-[0.9rem] border border-zinc-200 bg-white px-5 py-6 text-center shadow-sm">
                                            <div class="flex h-24 w-24 items-center justify-center overflow-hidden rounded-full border border-zinc-200 bg-zinc-100 text-2xl font-semibold text-zinc-400">
                                                @if ($crewPhoto)
                                                    <img src="{{ $crewPhoto }}" alt="{{ $member->name }}" class="h-full w-full object-cover">
                                                @else
                                                    {{ $member->initials() }}
                                                @endif
                                            </div>

                                            <div class="mt-4 text-xl font-semibold text-zinc-900">{{ $member->name }}</div>

                                            @if ($member->title)
                                                <div class="mt-1 text-sm font-medium text-zinc-500">{{ $member->title }}</div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @elseif ($crewDirectory->isNotEmpty())
                    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($crewDirectory as $entry)
                            <div class="rounded-[0.9rem] border border-zinc-200 bg-zinc-50 p-5">
                                <div class="text-lg font-semibold text-zinc-900">{{ $entry['team']->name }}</div>
                                <div class="mt-1 text-sm text-zinc-500">{{ $entry['team']->locationLabel() ?: 'Location not listed' }}</div>

                                <div class="mt-4 space-y-3">
                                    @foreach ($entry['leaders'] as $leader)
                                        <div class="rounded-[0.8rem] border border-zinc-200 bg-white px-4 py-3">
                                            <div class="font-semibold text-zinc-900">{{ $leader->name }}</div>
                                            <div class="mt-1 text-sm text-zinc-500">{{ $leader->nickname ?: 'Nickname not listed' }}</div>
                                            <div class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $leader->role === 'captain' ? 'bg-[#e6efff] text-[#2f55b7]' : 'bg-[#fff1e8] text-[#b45309]' }}">
                                                {{ str($leader->role)->replace('_', ' ')->headline() }}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="mt-6 rounded-[0.9rem] border border-dashed border-zinc-200 bg-zinc-50 p-5 text-sm leading-6 text-zinc-600">
                        No team crew assignments have been published yet.
                    </div>
                @endif
            </section>
        @else
            <section class="rounded-[1rem] border border-zinc-200 bg-white p-6 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">Info</div>
                <h2 class="mt-2 text-2xl font-semibold tracking-tight text-zinc-900">Tournament profile</h2>

                <div class="mt-5 space-y-3">
                    @foreach ($profileSections as $section)
                        <details class="group overflow-hidden rounded-[1rem] border border-zinc-200/90 bg-white">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 transition hover:bg-zinc-50 [&::-webkit-details-marker]:hidden">
                                <div class="flex items-center gap-3">
                                    <span class="text-zinc-700">
                                        @switch($section['key'])
                                            @case('about')
                                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 18.25h10.5M8 5.75h8M8.75 18.25v-10.5a1 1 0 0 1 1-1h4.5a1 1 0 0 1 1 1v10.5M10 10.5h4" />
                                                </svg>
                                                @break
                                            @case('organizer')
                                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.25v-1.5a3.25 3.25 0 0 0-3.25-3.25h-3.5A3.25 3.25 0 0 0 5 17.75v1.5M10 11.25a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm8.25 8v-1a2.75 2.75 0 0 0-2.75-2.75h-1.25M14.75 5.5a2.5 2.5 0 1 1 0 5" />
                                                </svg>
                                                @break
                                            @case('location')
                                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 20.25s7-5.75 7-11a7 7 0 1 0-14 0c0 5.25 7 11 7 11Zm0-8.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" />
                                                </svg>
                                                @break
                                            @case('additional')
                                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7.75h8M8 12h8m-8 4.25h5.5M6.75 4.75h10.5a2 2 0 0 1 2 2v10.5a2 2 0 0 1-2 2H6.75a2 2 0 0 1-2-2V6.75a2 2 0 0 1 2-2Z" />
                                                </svg>
                                                @break
                                            @default
                                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.75 9.25 12 12.5m0 0 3.25 3.25M12 12.5l3.25-3.25M12 12.5 8.75 15.75M6.75 5.75h10.5a1 1 0 0 1 1 1v10.5a1 1 0 0 1-1 1H6.75a1 1 0 0 1-1-1V6.75a1 1 0 0 1 1-1Z" />
                                                </svg>
                                        @endswitch
                                    </span>
                                    <span class="text-xl font-semibold text-zinc-900">{{ $section['title'] }}</span>
                                </div>

                                <svg class="h-5 w-5 text-zinc-500 transition group-open:rotate-90" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m7.5 4.75 5 5-5 5" />
                                </svg>
                            </summary>

                            @if ($section['type'] === 'text')
                                <div class="border-t border-zinc-200/90 px-5 py-4 text-sm leading-7 text-zinc-600">
                                    {{ $section['content'] }}
                                </div>
                            @elseif ($section['type'] === 'links')
                                <div class="border-t border-zinc-200/90 px-5 py-4 text-sm text-zinc-600">
                                    @if ($section['items'] !== [])
                                        <div class="space-y-3">
                                            @foreach ($section['items'] as $item)
                                                <div>
                                                    <a
                                                        href="{{ $item['href'] }}"
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        class="font-medium text-[#2f55b7] underline-offset-2 transition hover:underline"
                                                    >
                                                        {{ $item['label'] }}
                                                    </a>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        {{ $section['empty_message'] }}
                                    @endif
                                </div>
                            @else
                                <div class="grid gap-3 border-t border-zinc-200/90 px-5 py-4 text-sm text-zinc-600 sm:grid-cols-2">
                                    @if ($section['items'] !== [])
                                        @foreach ($section['items'] as $item)
                                            <div class="{{ ($item['label'] ?? null) === 'Registration deadline' ? 'sm:col-span-2' : '' }}">
                                                <span class="font-medium text-zinc-900">{{ $item['label'] }}:</span>
                                                {{ $item['value'] }}
                                            </div>
                                        @endforeach
                                    @else
                                        <div class="sm:col-span-2">{{ $section['empty_message'] }}</div>
                                    @endif
                                </div>
                            @endif
                        </details>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
