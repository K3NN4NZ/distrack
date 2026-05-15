@extends('layouts.public', ['title' => 'Tournaments'])

@php
    $activeFilters = collect([
        ['label' => 'Search', 'value' => $filters['search']],
        ['label' => 'Country', 'value' => $filters['country']],
        ['label' => 'Type', 'value' => $filters['type'] ?: $filters['division'] ?: $filters['surface'] ?: $filters['event_type']],
        ['label' => 'Year and Month', 'value' => request()->filled('month') ? \Carbon\CarbonImmutable::createFromFormat('Y-m', $filters['month'])->format('F Y') : null],
    ])->filter(fn (array $filter) => filled($filter['value']))->values();

    $countryCodes = [
        'Australia' => 'AU',
        'Hong Kong, China' => 'HK',
        'Indonesia' => 'ID',
        'Kenya' => 'KE',
        'Malaysia' => 'MY',
        'Philippines' => 'PH',
        'South Korea' => 'KR',
        'Vietnam' => 'VN',
    ];

    $requestQuery = collect(request()->query());
    $boardBaseQuery = $requestQuery
        ->filter(fn ($value) => filled($value))
        ->all();
    $sharedQuery = $requestQuery
        ->except('period')
        ->filter(fn ($value) => filled($value))
        ->all();

    $boardUrl = fn (array $overrides = [], array $except = []) => route('tournaments.index', collect(array_merge($boardBaseQuery, $overrides))
        ->except($except)
        ->reject(fn ($value) => $value === null || $value === '')
        ->all());

    $calendarUrl = fn (array $overrides = []) => $boardUrl(array_merge([
        'period' => $filters['period'],
        'view' => 'calendar',
    ], $overrides));
    $listUrl = fn (array $overrides = []) => $boardUrl(array_merge([
        'period' => $filters['period'],
        'view' => 'list',
    ], $overrides));
    $calendarDayUrl = fn ($date) => $calendarUrl([
        'month' => $calendar['month']->format('Y-m'),
        'day' => $date->format('Y-m-d'),
    ]);
    $calendarMonthUrl = fn ($month) => $calendarUrl([
        'month' => $month->format('Y-m'),
        'day' => $calendar['selected_day']->isSameMonth($month) ? $calendar['selected_day']->format('Y-m-d') : null,
    ]);
    $calendarWeekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $calendarTagClasses = fn (string $tag) => match (str($tag)->lower()->toString()) {
        'mix', 'mixed' => 'bg-[#8b5cf6] text-white',
        'women' => 'bg-[#ec4899] text-white',
        'men' => 'bg-[#3b82f6] text-white',
        'outdoor' => 'bg-[#10b981] text-white',
        'indoor' => 'bg-[#14b8a6] text-white',
        'league' => 'bg-[#ef4444] text-white',
        'tournament' => 'bg-[#f97316] text-white',
        default => 'bg-zinc-100 text-zinc-700',
    };
    $selectedMonthLabel = request()->filled('month')
        ? \Carbon\CarbonImmutable::createFromFormat('Y-m', $filters['month'])->format('F Y')
        : 'Select Year and Month';
    $selectedCountryLabel = $filters['country'] ?: 'All Countries';
    $selectedTypeLabel = $filters['type'] ?: $filters['division'] ?: $filters['surface'] ?: $filters['event_type'] ?: 'All Types';
    $pickerMiniMonth = \Carbon\CarbonImmutable::create($filters['picker_year'], $filters['picker_month'], 1);
    $pickerMiniMonthEnd = $pickerMiniMonth->endOfMonth();
    $pickerMiniGridStart = $pickerMiniMonth->startOfWeek(\Carbon\CarbonImmutable::MONDAY);
    $pickerMiniGridEnd = $pickerMiniMonthEnd->endOfWeek(\Carbon\CarbonImmutable::SUNDAY);
    $pickerMiniWeeks = collect();
    $__miniCursor = $pickerMiniGridStart;
    while ($__miniCursor->lessThanOrEqualTo($pickerMiniGridEnd)) {
        $weekDays = collect();
        for ($__i = 0; $__i < 7; $__i++) {
            $weekDays->push($__miniCursor);
            $__miniCursor = $__miniCursor->addDay();
        }
        $pickerMiniWeeks->push($weekDays);
    }
    $pickerMiniBrowsePrev = $pickerMiniMonth->subMonth()->startOfMonth();
    $pickerMiniBrowseNext = $pickerMiniMonth->addMonth()->startOfMonth();
    $pickerMiniPrevMonthBrowseUrl = $boardUrl([
        'picker_year' => $pickerMiniBrowsePrev->year,
        'picker_month' => $pickerMiniBrowsePrev->month,
    ]);
    $pickerMiniNextMonthBrowseUrl = $boardUrl([
        'picker_year' => $pickerMiniBrowseNext->year,
        'picker_month' => $pickerMiniBrowseNext->month,
    ]);
    $pickerMiniToday = \Carbon\CarbonImmutable::today();
    $clearSearchUrl = $boardUrl(['search' => null]);
    $clearMonthUrl = $boardUrl(['month' => null, 'day' => null]);
    $clearCountryUrl = $boardUrl(['country' => null]);
    $clearTypeUrl = $boardUrl([
        'type' => null,
        'division' => null,
        'surface' => null,
        'event_type' => null,
    ]);
    $pickerPreviousYearUrl = $boardUrl(['picker_year' => $filters['picker_year'] - 1]);
    $pickerNextYearUrl = $boardUrl(['picker_year' => $filters['picker_year'] + 1]);
    $resetFiltersUrl = $boardUrl([
        'search' => null,
        'country' => null,
        'type' => null,
        'division' => null,
        'surface' => null,
        'event_type' => null,
        'month' => null,
        'day' => null,
    ]);
@endphp

@section('content')
    <div class="space-y-5">
        <section class="space-y-3">
            <form method="GET" action="{{ route('tournaments.index') }}" class="flex items-center gap-3" data-livewire-navigate-form>
                @foreach (collect($boardBaseQuery)->except('search') as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach

                <label class="relative block flex-1">
                    <span class="sr-only">Search events</span>
                    <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-zinc-400">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m1.85-5.15a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z" />
                        </svg>
                    </span>
                    <input
                        type="text"
                        name="search"
                        value="{{ $filters['search'] }}"
                        placeholder="Search for an event..."
                        class="h-12 w-full rounded-full border border-zinc-200 bg-white px-12 pr-5 text-base text-zinc-900 shadow-[0_16px_40px_-36px_rgba(15,23,42,0.35)] outline-none transition placeholder:text-zinc-400 focus:border-[#67dfbe]"
                    >
                </label>

                <a
                    href="{{ $clearSearchUrl }}"
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border border-zinc-200 bg-white text-zinc-700 shadow-[0_16px_40px_-36px_rgba(15,23,42,0.35)] transition hover:border-zinc-300 hover:text-zinc-900"
                    aria-label="Clear search"
                    wire:navigate.preserve-scroll
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6l-12 12" />
                    </svg>
                </a>
            </form>

            <div class="space-y-3">
                <details class="rounded-[1.25rem] border border-zinc-200 bg-white shadow-[0_16px_40px_-36px_rgba(15,23,42,0.25)] [&_summary::-webkit-details-marker]:hidden" @if (request()->filled('month')) open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3">
                        <div class="flex items-center gap-2.5">
                            <span class="text-zinc-500">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 2v4m8-4v4M4.5 9.5h15M6.75 4.5h10.5A2.25 2.25 0 0 1 19.5 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 17.25V6.75A2.25 2.25 0 0 1 6.75 4.5Z" />
                                </svg>
                            </span>
                            <div class="text-base font-semibold tracking-tight text-zinc-900">{{ $selectedMonthLabel }}</div>
                        </div>

                        <span class="text-zinc-500">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.24 4.24a.75.75 0 0 1 0 1.06l-4.24 4.24a.75.75 0 0 1-1.08 0Z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </summary>

                    <div class="border-t border-zinc-100 px-4 py-4">
                        <div class="flex items-center justify-between">
                            <a href="{{ $pickerPreviousYearUrl }}" class="text-xl font-semibold text-zinc-400 transition hover:text-zinc-700" wire:navigate.preserve-scroll>&lt;</a>
                            <div class="text-2xl font-semibold tracking-tight text-zinc-400">{{ $filters['picker_year'] }}</div>
                            <a href="{{ $pickerNextYearUrl }}" class="text-xl font-semibold text-zinc-400 transition hover:text-zinc-700" wire:navigate.preserve-scroll>&gt;</a>
                        </div>

                        <div class="mt-3 flex items-center justify-between gap-2">
                            <a
                                href="{{ $pickerMiniPrevMonthBrowseUrl }}"
                                class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-zinc-200 text-zinc-500 transition hover:border-zinc-300 hover:text-zinc-900"
                                aria-label="Previous month"
                                wire:navigate.preserve-scroll
                            >
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M11.78 4.22a.75.75 0 0 1 0 1.06L7.56 9.5h8.69a.75.75 0 0 1 0 1.5H7.56l4.22 4.22a.75.75 0 1 1-1.06 1.06l-5.5-5.5a.75.75 0 0 1 0-1.06l5.5-5.5a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" />
                                </svg>
                            </a>
                            <div class="min-w-0 flex-1 text-center text-sm font-semibold text-zinc-800">{{ $pickerMiniMonth->format('F') }}</div>
                            <a
                                href="{{ $pickerMiniNextMonthBrowseUrl }}"
                                class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-zinc-200 text-zinc-500 transition hover:border-zinc-300 hover:text-zinc-900"
                                aria-label="Next month"
                                wire:navigate.preserve-scroll
                            >
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M8.22 15.78a.75.75 0 0 1 0-1.06l4.22-4.22H3.75a.75.75 0 0 1 0-1.5h8.69L8.22 4.78a.75.75 0 0 1 1.06-1.06l5.5 5.5a.75.75 0 0 1 0 1.06l-5.5 5.5a.75.75 0 0 1-1.06 0Z" clip-rule="evenodd" />
                                </svg>
                            </a>
                        </div>

                        <div class="mt-2 grid grid-cols-7 gap-1 text-center text-[9px] font-semibold uppercase tracking-[0.1em] text-zinc-400">
                            @foreach ($calendarWeekdays as $weekday)
                                <div class="py-1">{{ $weekday }}</div>
                            @endforeach
                        </div>

                        <div class="mt-1 space-y-1">
                            @foreach ($pickerMiniWeeks as $__week)
                                <div class="grid grid-cols-7 gap-1">
                                    @foreach ($__week as $__day)
                                        @php
                                            $__inMiniMonth = $__day->isSameMonth($pickerMiniMonth);
                                            $__filteredHere = $__inMiniMonth && request()->filled('month') && $filters['month'] === $__day->format('Y-m');
                                            $__miniDayClasses = ! $__inMiniMonth
                                                ? 'text-zinc-300 hover:bg-zinc-50'
                                                : ($__filteredHere
                                                    ? 'border-[#dbe7ff] bg-[#f4f8ff] font-semibold text-[#2f55b7]'
                                                    : 'border-transparent text-zinc-700 hover:bg-zinc-50');
                                            $__todayRing = $__inMiniMonth && $__day->equalTo($pickerMiniToday)
                                                ? 'ring-2 ring-[#67dfbe]/55 ring-offset-1'
                                                : '';
                                        @endphp
                                        @if ($__inMiniMonth)
                                            <a
                                                href="{{ $boardUrl([
                                                    'month' => $__day->format('Y-m'),
                                                    'day' => null,
                                                    'picker_year' => $__day->year,
                                                    'picker_month' => $__day->month,
                                                ]) }}"
                                                class="flex aspect-square items-center justify-center rounded-lg border text-xs transition {{ $__miniDayClasses }} {{ $__todayRing }}"
                                                wire:navigate.preserve-scroll
                                            >
                                                {{ $__day->day }}
                                            </a>
                                        @else
                                            <a
                                                href="{{ $boardUrl([
                                                    'picker_year' => $__day->year,
                                                    'picker_month' => $__day->month,
                                                ]) }}"
                                                class="flex aspect-square items-center justify-center rounded-lg border border-transparent text-xs transition {{ $__miniDayClasses }}"
                                                wire:navigate.preserve-scroll
                                            >
                                                {{ $__day->day }}
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-4 flex justify-end">
                            <a href="{{ $clearMonthUrl }}" class="text-xs font-medium text-zinc-500 transition hover:text-zinc-900" wire:navigate.preserve-scroll>Clear month</a>
                        </div>
                    </div>
                </details>

                <details class="rounded-[1.7rem] border border-zinc-200 bg-white shadow-[0_16px_40px_-36px_rgba(15,23,42,0.25)] [&_summary::-webkit-details-marker]:hidden" @if (filled($filters['country'])) open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4">
                        <div class="flex items-center gap-3">
                            <span class="text-zinc-500">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 0c2.5 2.6 4 6.2 4 10s-1.5 7.4-4 10m0-20C9.5 4.6 8 8.2 8 12s1.5 7.4 4 10m-9.5-7h19M2.5 9h19" />
                                </svg>
                            </span>
                            <div class="text-xl font-semibold tracking-tight text-zinc-900">{{ $selectedCountryLabel }}</div>
                        </div>

                        <span class="text-zinc-500">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.24 4.24a.75.75 0 0 1 0 1.06l-4.24 4.24a.75.75 0 0 1-1.08 0Z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </summary>

                    <div class="max-h-72 overflow-y-auto border-t border-zinc-100 py-2">
                        <a href="{{ $clearCountryUrl }}" class="flex items-center justify-between px-5 py-3 text-xl font-semibold text-zinc-900 transition hover:bg-zinc-50" wire:navigate.preserve-scroll>
                            <span>All Countries</span>
                            @if (! filled($filters['country']))
                                <svg class="h-5 w-5 text-zinc-700" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.31a1 1 0 0 1-1.42.01l-3.75-3.75a1 1 0 1 1 1.414-1.414l3.04 3.04 6.54-6.595a1 1 0 0 1 1.42-.005Z" clip-rule="evenodd" />
                                </svg>
                            @endif
                        </a>

                        @foreach ($filterOptions['countries'] as $country)
                            <a href="{{ $boardUrl(['country' => $country]) }}" class="flex items-center justify-between px-5 py-3 text-xl font-semibold text-zinc-900 transition hover:bg-zinc-50" wire:navigate.preserve-scroll>
                                <span>{{ $country }}</span>
                                @if ($filters['country'] === $country)
                                    <svg class="h-5 w-5 text-zinc-700" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.31a1 1 0 0 1-1.42.01l-3.75-3.75a1 1 0 1 1 1.414-1.414l3.04 3.04 6.54-6.595a1 1 0 0 1 1.42-.005Z" clip-rule="evenodd" />
                                    </svg>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </details>

                <details class="rounded-[1.7rem] border border-zinc-200 bg-white shadow-[0_16px_40px_-36px_rgba(15,23,42,0.25)] [&_summary::-webkit-details-marker]:hidden" @if ($selectedTypeLabel !== 'All Types') open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4">
                        <div class="flex items-center gap-3">
                            <span class="text-zinc-500">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H5.75A1.75 1.75 0 0 0 4 6.75V10m5-5 7.19 7.19a1.8 1.8 0 0 1 0 2.55l-1.45 1.45a1.8 1.8 0 0 1-2.55 0L5 9m4-4 2.5-2.5a1.8 1.8 0 0 1 2.55 0l7.45 7.45a1.8 1.8 0 0 1 0 2.55L19 15" />
                                </svg>
                            </span>
                            <div class="text-xl font-semibold tracking-tight text-zinc-900">{{ $selectedTypeLabel }}</div>
                        </div>

                        <span class="text-zinc-500">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.24 4.24a.75.75 0 0 1 0 1.06l-4.24 4.24a.75.75 0 0 1-1.08 0Z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </summary>

                    <div class="max-h-72 overflow-y-auto border-t border-zinc-100 py-2">
                        <a href="{{ $clearTypeUrl }}" class="flex items-center justify-between bg-zinc-100/70 px-5 py-3 text-xl font-semibold text-zinc-900 transition hover:bg-zinc-100" wire:navigate.preserve-scroll>
                            <span>All Types</span>
                            @if ($selectedTypeLabel === 'All Types')
                                <svg class="h-5 w-5 text-zinc-700" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.31a1 1 0 0 1-1.42.01l-3.75-3.75a1 1 0 1 1 1.414-1.414l3.04 3.04 6.54-6.595a1 1 0 0 1 1.42-.005Z" clip-rule="evenodd" />
                                </svg>
                            @endif
                        </a>

                        @foreach ($filterOptions['types'] as $type)
                            <a href="{{ $boardUrl(['type' => $type, 'division' => null, 'surface' => null, 'event_type' => null]) }}" class="flex items-center justify-between px-5 py-3 text-xl font-semibold text-zinc-900 transition hover:bg-zinc-50" wire:navigate.preserve-scroll>
                                <span>{{ $type }}</span>
                                @if ($selectedTypeLabel === $type)
                                    <svg class="h-5 w-5 text-zinc-700" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.31a1 1 0 0 1-1.42.01l-3.75-3.75a1 1 0 1 1 1.414-1.414l3.04 3.04 6.54-6.595a1 1 0 0 1 1.42-.005Z" clip-rule="evenodd" />
                                    </svg>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </details>

                @if ($activeFilters->isNotEmpty())
                    <div class="flex flex-wrap items-center gap-2 px-1">
                        @foreach ($activeFilters as $filter)
                            <span class="rounded-full bg-white px-3 py-1.5 text-xs font-medium text-zinc-600 shadow-[0_12px_28px_-24px_rgba(15,23,42,0.4)] ring-1 ring-zinc-200">
                                {{ $filter['label'] }}: {{ $filter['value'] }}
                            </span>
                        @endforeach

                        <a href="{{ $resetFiltersUrl }}" class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500 transition hover:text-zinc-900" wire:navigate.preserve-scroll>
                            Reset filters
                        </a>
                    </div>
                @endif
            </div>

            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="inline-flex w-full rounded-full bg-white/80 p-1 shadow-[0_16px_40px_-36px_rgba(15,23,42,0.35)] ring-1 ring-zinc-200 sm:w-auto">
                    @foreach ([
                        ['key' => 'upcoming', 'label' => 'Upcoming Events'],
                        ['key' => 'past', 'label' => 'Past Events'],
                    ] as $tab)
                        <a
                            href="{{ route('tournaments.index', array_merge($sharedQuery, ['period' => $tab['key']])) }}"
                            class="rounded-full px-4 py-2.5 text-sm font-semibold transition {{ $filters['period'] === $tab['key']
                                ? 'bg-[linear-gradient(135deg,_#67dfbe_0%,_#77e4cc_100%)] text-white'
                                : 'text-zinc-400 hover:text-zinc-700' }}"
                            wire:navigate
                        >
                            {{ $tab['label'] }}
                        </a>
                    @endforeach
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <span class="rounded-full border border-zinc-200 bg-white px-3.5 py-2 text-sm font-medium text-zinc-600">
                        {{ $periodCounts[$filters['period']] }} {{ Str::plural('event', $periodCounts[$filters['period']]) }}
                    </span>

                    <a
                        href="{{ $filters['view'] === 'calendar' ? $listUrl() : $calendarUrl() }}"
                        class="inline-flex items-center gap-2.5 rounded-[1rem] border border-zinc-400 bg-white px-4 py-2.5 text-sm font-medium text-zinc-700 transition hover:border-zinc-500 hover:text-zinc-900"
                        wire:navigate.preserve-scroll
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 2v4m8-4v4M4.5 9.5h15M6.75 4.5h10.5A2.25 2.25 0 0 1 19.5 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 17.25V6.75A2.25 2.25 0 0 1 6.75 4.5Z" />
                        </svg>
                        {{ $filters['view'] === 'calendar' ? 'List View' : 'Calendar View' }}
                    </a>
                </div>
            </div>
        </section>

        @if ($filters['view'] === 'calendar')
            <section id="tournament-calendar" class="space-y-4">
                <div class="rounded-[1.6rem] border border-zinc-200 bg-white p-5 shadow-[0_20px_50px_-42px_rgba(15,23,42,0.26)] sm:p-6">
                    <div class="flex flex-col gap-4 border-b border-zinc-100 pb-5 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-[#40c7a2]">Calendar View</p>
                            <h2 class="mt-1.5 text-2xl font-semibold tracking-tight text-zinc-900">Event Calendar</h2>
                        </div>

                        <div class="flex items-center gap-2">
                            @if ($calendar['previous_month'])
                                <a
                                    href="{{ $calendarMonthUrl($calendar['previous_month']) }}"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-zinc-200 text-zinc-500 transition hover:border-zinc-300 hover:text-zinc-900"
                                    aria-label="Previous month"
                                    wire:navigate.preserve-scroll
                                >
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M11.78 4.22a.75.75 0 0 1 0 1.06L7.56 9.5h8.69a.75.75 0 0 1 0 1.5H7.56l4.22 4.22a.75.75 0 1 1-1.06 1.06l-5.5-5.5a.75.75 0 0 1 0-1.06l5.5-5.5a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            @else
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-zinc-100 text-zinc-300">
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M11.78 4.22a.75.75 0 0 1 0 1.06L7.56 9.5h8.69a.75.75 0 0 1 0 1.5H7.56l4.22 4.22a.75.75 0 1 1-1.06 1.06l-5.5-5.5a.75.75 0 0 1 0-1.06l5.5-5.5a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            @endif

                            <div class="rounded-full border border-zinc-200 bg-[#fbfaf8] px-5 py-2.5 text-sm font-semibold text-zinc-900">
                                {{ $calendar['month_label'] }}
                            </div>

                            @if ($calendar['next_month'])
                                <a
                                    href="{{ $calendarMonthUrl($calendar['next_month']) }}"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-zinc-200 text-zinc-500 transition hover:border-zinc-300 hover:text-zinc-900"
                                    aria-label="Next month"
                                    wire:navigate.preserve-scroll
                                >
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M8.22 15.78a.75.75 0 0 1 0-1.06l4.22-4.22H3.75a.75.75 0 0 1 0-1.5h8.69L8.22 4.78a.75.75 0 0 1 1.06-1.06l5.5 5.5a.75.75 0 0 1 0 1.06l-5.5 5.5a.75.75 0 0 1-1.06 0Z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            @else
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-zinc-100 text-zinc-300">
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M8.22 15.78a.75.75 0 0 1 0-1.06l4.22-4.22H3.75a.75.75 0 0 1 0-1.5h8.69L8.22 4.78a.75.75 0 0 1 1.06-1.06l5.5 5.5a.75.75 0 0 1 0 1.06l-5.5 5.5a.75.75 0 0 1-1.06 0Z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="mt-5 grid grid-cols-7 gap-2 text-center text-xs font-semibold uppercase tracking-[0.18em] text-zinc-400">
                        @foreach ($calendarWeekdays as $weekday)
                            <div class="py-2">{{ $weekday }}</div>
                        @endforeach
                    </div>

                    <div class="mt-2 space-y-2">
                        @foreach ($calendar['weeks'] as $week)
                            <div class="grid grid-cols-7 gap-2">
                                @foreach ($week as $day)
                                    @php
                                        $dayNumberClasses = $day['is_selected']
                                            ? 'bg-[#4f83f1] text-white shadow-[0_14px_28px_-18px_rgba(79,131,241,0.9)]'
                                            : ($day['is_today']
                                                ? 'border border-[#4f83f1] text-[#4f83f1]'
                                                : 'text-zinc-800');
                                    @endphp

                                    @if ($day['is_current_month'])
                                        <a
                                            href="{{ $calendarDayUrl($day['date']) }}"
                                            class="flex min-h-[5.75rem] flex-col items-center rounded-[1.2rem] border px-2 py-2.5 text-center transition {{ $day['is_selected']
                                                ? 'border-[#dbe7ff] bg-[#f7faff]'
                                                : 'border-transparent hover:border-zinc-200 hover:bg-zinc-50' }}"
                                            wire:navigate.preserve-scroll
                                        >
                                            <span class="flex h-10 w-10 items-center justify-center rounded-full text-lg font-semibold {{ $dayNumberClasses }}">
                                                {{ $day['day_number'] }}
                                            </span>

                                            <span class="mt-2 flex flex-wrap justify-center gap-1">
                                                @foreach ($day['dot_colors'] as $dotColor)
                                                    <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $dotColor }}"></span>
                                                @endforeach

                                                @if ($day['hidden_event_count'] > 0)
                                                    <span class="text-[10px] font-semibold text-zinc-400">+{{ $day['hidden_event_count'] }}</span>
                                                @endif
                                            </span>
                                        </a>
                                    @else
                                        <div class="flex min-h-[5.75rem] flex-col items-center rounded-[1.2rem] px-2 py-2.5 text-center opacity-35">
                                            <span class="flex h-10 w-10 items-center justify-center rounded-full text-lg font-semibold text-zinc-400">
                                                {{ $day['day_number'] }}
                                            </span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-6 border-t border-zinc-100 pt-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <h3 class="text-3xl font-semibold tracking-tight text-zinc-900">{{ $calendar['selected_day_label'] }}</h3>
                                <p class="mt-1 text-lg text-zinc-500">
                                    {{ $calendar['selected_day_count'] }} {{ Str::plural('event', $calendar['selected_day_count']) }}
                                </p>
                            </div>

                            <div class="text-sm text-zinc-400">sorted by country</div>
                        </div>

                        @if ($calendar['selected_day_events']->isNotEmpty())
                            <div class="mt-4 space-y-3">
                                @foreach ($calendar['selected_day_events'] as $event)
                                    <article class="rounded-[1.3rem] border border-zinc-200 bg-white px-5 py-4 shadow-[0_18px_40px_-36px_rgba(15,23,42,0.28)]">
                                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                            <div class="min-w-0">
                                                <h3 class="text-2xl font-semibold tracking-tight text-zinc-900">
                                                    <a href="{{ $event['url'] }}" class="transition hover:text-[#239f81]" wire:navigate>
                                                        {{ $event['name'] }}
                                                    </a>
                                                </h3>

                                                <div class="mt-2 text-base text-zinc-500">
                                                    {{ $event['date_label'] }}
                                                </div>

                                                @if (! empty($event['tags']))
                                                    <div class="mt-3 flex flex-wrap gap-2">
                                                        @foreach ($event['tags'] as $tag)
                                                            <span class="rounded-[0.45rem] px-2.5 py-1 text-xs font-semibold {{ $calendarTagClasses($tag) }}">
                                                                {{ $tag }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="flex items-center gap-3 sm:pt-1">
                                                <span class="h-3 w-3 rounded-full" style="background-color: {{ $event['dot_color'] }}"></span>
                                                <div class="text-left sm:text-right">
                                                    <div class="text-lg text-zinc-500">{{ $event['country'] }}</div>
                                                    <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-400">{{ $event['status'] }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @else
                            <div class="mt-4 rounded-[1.2rem] border border-dashed border-zinc-200 bg-zinc-50 px-5 py-6 text-sm leading-7 text-zinc-600">
                                No events fall on the selected day for the current public board filters.
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        @else
        <section id="tournament-list" class="space-y-3">
            @if ($tournaments->count() > 0)
                @foreach ($tournaments as $tournament)
                    @php
                        $countryCode = $countryCodes[$tournament->country_name] ?? null;
                        $startsAt = $tournament->starts_at ?? $tournament->ends_at;
                        $dateDay = $startsAt?->format('d');
                        $dateMonth = strtoupper($startsAt?->format('M') ?? '');
                    @endphp

                    <article class="rounded-[1.6rem] border border-zinc-200/90 bg-white p-3.5 shadow-[0_20px_50px_-42px_rgba(15,23,42,0.26)] transition hover:border-zinc-300 hover:shadow-[0_28px_60px_-42px_rgba(15,23,42,0.28)] sm:p-4">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                            @if ($tournament->logoUrl())
                                <div class="flex h-24 w-28 shrink-0 items-center justify-center overflow-hidden sm:h-28 sm:w-32">
                                    <img
                                        src="{{ $tournament->logoUrl() }}"
                                        alt="{{ $tournament->name }} {{ __('logo') }}"
                                        class="max-h-24 max-w-28 object-contain sm:max-h-28 sm:max-w-32"
                                    >
                                </div>
                            @else
                                <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-[1.2rem] border border-zinc-200 bg-[#edf4ff] sm:h-24 sm:w-24">
                                    @if ($tournament->thumbnail_path)
                                        <img
                                            src="{{ $tournament->thumbnail_path }}"
                                            alt="{{ $tournament->name }}"
                                            class="h-full w-full object-cover"
                                        >
                                    @elseif ($startsAt)
                                        <div class="flex h-full w-full flex-col items-center justify-center bg-[linear-gradient(135deg,_#d8e7ff_0%,_#bcd3fb_100%)]">
                                            <span class="text-3xl font-semibold leading-none text-[#4f7ecc]">{{ $dateDay }}</span>
                                            <span class="mt-1 text-base font-semibold uppercase tracking-[0.18em] text-[#4f7ecc]">{{ $dateMonth }}</span>
                                        </div>
                                    @else
                                        <span class="text-2xl font-semibold tracking-tight text-[#4f7ecc]">{{ $tournament->initials() }}</span>
                                    @endif
                                </div>
                            @endif

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                                    <div class="min-w-0">
                                        <h2 class="truncate text-2xl font-semibold leading-tight tracking-tight text-zinc-900 sm:text-[1.75rem]">
                                            <a href="{{ route('tournaments.show', $tournament) }}" class="transition hover:text-[#239f81]" wire:navigate>
                                                {{ $tournament->name }}
                                            </a>
                                        </h2>

                                        <div class="mt-2.5 space-y-1.5 text-base text-zinc-700">
                                            <div class="flex items-center gap-2.5">
                                                <span class="text-zinc-500">
                                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M5.75 3A2.75 2.75 0 003 5.75v8.5A2.75 2.75 0 005.75 17h8.5A2.75 2.75 0 0017 14.25v-8.5A2.75 2.75 0 0014.25 3h-8.5zM5 7.25A.75.75 0 015.75 6.5h8.5a.75.75 0 010 1.5h-8.5A.75.75 0 015 7.25zm2 3a.75.75 0 01.75-.75h4.5a.75.75 0 010 1.5h-4.5A.75.75 0 017 10.25z" clip-rule="evenodd" />
                                                    </svg>
                                                </span>
                                                <span>{{ $tournament->dateRangeLabel() }}</span>
                                            </div>

                                            <div class="flex items-center gap-2.5">
                                                <span class="text-zinc-500">
                                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M10 1.75a5.75 5.75 0 00-5.75 5.75c0 4.115 4.616 9.426 5.142 10.02a.75.75 0 001.116 0c.526-.594 5.242-5.905 5.242-10.02A5.75 5.75 0 0010 1.75zM7.5 7.5a2.5 2.5 0 115 0 2.5 2.5 0 01-5 0z" clip-rule="evenodd" />
                                                    </svg>
                                                </span>
                                                <span>{{ $tournament->country_name ?: 'Location to be announced' }}</span>
                                                @if ($countryCode)
                                                    <span class="rounded-md bg-[#f4f7fb] px-2 py-0.5 text-[11px] font-semibold uppercase tracking-[0.16em] text-zinc-500">
                                                        {{ $countryCode }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <span class="self-start rounded-full border border-zinc-200 bg-[#fafafa] px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500">
                                        {{ str($tournament->status)->headline() }}
                                    </span>
                                </div>

                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($tournament->publicTags() as $tag)
                                        <span class="rounded-full border border-zinc-200 bg-white px-3 py-1 text-xs font-medium text-zinc-500">
                                            {{ $tag }}
                                        </span>
                                    @endforeach
                                </div>

                                <div class="mt-4 flex flex-col gap-2.5 lg:flex-row lg:items-center lg:justify-between">
                                    <div class="flex flex-wrap items-center gap-2.5">
                                        @if ($tournament->registrations->isNotEmpty())
                                            <div class="flex -space-x-2">
                                                @foreach ($tournament->registrations->take(4) as $registration)
                                                    @php
                                                        $teamLogo = $registration->team->logoUrl();
                                                        $teamBadge = str($registration->team->name)
                                                            ->explode(' ')
                                                            ->take(2)
                                                            ->map(fn ($word) => str($word)->substr(0, 1))
                                                            ->implode('');
                                                    @endphp
                                                    <div class="flex h-7 w-7 items-center justify-center overflow-hidden rounded-full border-2 border-white bg-[#f3f4f6] text-[9px] font-semibold text-zinc-700 shadow-sm">
                                                        @if ($teamLogo)
                                                            <img
                                                                src="{{ $teamLogo }}"
                                                                alt="{{ $registration->team->name }}"
                                                                class="h-full w-full object-cover"
                                                            >
                                                        @else
                                                            {{ $teamBadge }}
                                                        @endif
                                                    </div>
                                                @endforeach

                                                @if ($tournament->registrations_count > 4)
                                                    <div class="flex h-7 w-7 items-center justify-center rounded-full border-2 border-white bg-[#243041] text-[9px] font-semibold text-white shadow-sm">
                                                        +{{ $tournament->registrations_count - 4 }}
                                                    </div>
                                                @endif
                                            </div>
                                        @endif

                                        <span class="text-xs text-zinc-500 sm:text-sm">
                                            @if ($tournament->registrations_count > 0)
                                                {{ trans_choice('{1} :count team joined|[2,*] :count teams joined', $tournament->registrations_count, ['count' => $tournament->registrations_count]) }}
                                            @else
                                                Open registration
                                            @endif
                                        </span>

                                        @if ($tournament->creator?->name)
                                            <span class="text-xs text-zinc-400 sm:text-sm">
                                                by {{ $tournament->creator->name }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="flex items-center gap-2.5">
                                        @if ($tournament->venue_google_map_link)
                                            <a
                                                href="{{ $tournament->venue_google_map_link }}"
                                                target="_blank"
                                                rel="noreferrer"
                                                class="text-xs font-medium text-zinc-500 transition hover:text-[#239f81] sm:text-sm"
                                            >
                                                Open map
                                            </a>
                                        @endif

                                        <a
                                            href="{{ route('tournaments.show', $tournament) }}"
                                            class="text-xs font-semibold text-zinc-700 transition hover:text-[#239f81] sm:text-sm"
                                            wire:navigate
                                        >
                                            View details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach

                <div class="pt-2">
                    {{ $tournaments->links() }}
                </div>
            @else
                <div class="rounded-[1.6rem] border border-dashed border-zinc-200 bg-white p-8 text-center shadow-[0_18px_50px_-42px_rgba(15,23,42,0.22)]">
                    <h3 class="text-xl font-semibold text-zinc-900">
                        {{ $filters['period'] === 'past' ? 'No past events match these filters.' : 'No upcoming events match these filters.' }}
                    </h3>
                    <p class="mx-auto mt-3 max-w-xl text-sm leading-7 text-zinc-600">
                        Adjust the search or board filters to find a different tournament listing.
                    </p>
                    <a
                        href="{{ $resetFiltersUrl }}"
                        class="mt-5 inline-flex rounded-full bg-[#1f1d16] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-black"
                        wire:navigate.preserve-scroll
                    >
                        Reset filters
                    </a>
                </div>
            @endif
        </section>
        @endif
    </div>
@endsection
