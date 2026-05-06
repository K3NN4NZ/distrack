@php
    $bracketTeamLimit = \App\Http\Controllers\Admin\TournamentController::BRACKET_TEAM_LIMIT;
    $minimumBracketTeamCount = \App\Http\Controllers\Admin\TournamentController::MINIMUM_BRACKET_TEAM_COUNT;
    $formatCards = [
        [
            'day' => __('Day 0'),
            'title' => __('Seeding'),
            'summary' => __('Lock the seed order first. Brackets only begin once at least :minimum teams are registered, and each bracket holds :count teams.', ['minimum' => $minimumBracketTeamCount, 'count' => $bracketTeamLimit]),
            'tone' => 'border-emerald-200 bg-emerald-50 dark:border-emerald-900/60 dark:bg-emerald-950/30',
            'accent' => 'bg-emerald-600 text-white',
            'steps' => [
                [
                    'label' => __('Seeding'),
                    'key' => 'seeding',
                    'detail' => __('Assign the event seed list, then open bracket play only when you can form at least two full :count-team brackets.', ['count' => $bracketTeamLimit]),
                ],
            ],
        ],
        [
            'day' => __('Day 1'),
            'title' => __('Preliminary Flow'),
            'summary' => __('Run the full frisbee day-one progression from bracket play into crossover and pooling so Day 2 placements are ready.'),
            'tone' => 'border-lime-200 bg-lime-50 dark:border-lime-900/60 dark:bg-lime-950/30',
            'accent' => 'bg-lime-600 text-white',
            'steps' => [
                [
                    'label' => __('Round Robin'),
                    'key' => 'round_robin',
                    'detail' => __('Play the opening bracket games across the assigned pitches.'),
                ],
                [
                    'label' => __('Bracket Ranking'),
                    'key' => 'bracket_ranking',
                    'detail' => __('Rank teams inside each bracket after the round robin results are complete.'),
                ],
                [
                    'label' => __('Crossover'),
                    'key' => 'crossover',
                    'detail' => __('Run crossover games that feed the pool placements for the next phase.'),
                ],
                [
                    'label' => __('Pooling'),
                    'key' => 'pooling',
                    'detail' => __('Build Pool A and Pool B from the crossover outcomes.'),
                ],
            ],
        ],
        [
            'day' => __('Day 2'),
            'title' => __('Finals Bracket'),
            'summary' => __('Finish the frisbee event with the knockout bracket from quarterfinals through championship.'),
            'tone' => 'border-sky-200 bg-sky-50 dark:border-sky-900/60 dark:bg-sky-950/30',
            'accent' => 'bg-sky-600 text-white',
            'steps' => [
                [
                    'label' => __('Quarter Finals'),
                    'key' => 'quarterfinal',
                    'detail' => __('Open the elimination bracket using the pooled placements.'),
                ],
                [
                    'label' => __('Semi-Finals'),
                    'key' => 'semifinal',
                    'detail' => __('Advance the winners into the final four.'),
                ],
                [
                    'label' => __('Championship'),
                    'key' => 'championship',
                    'detail' => __('Close the event with the medal and title games.'),
                ],
            ],
        ],
    ];
@endphp

<section class="space-y-6">
    <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Frisbee Tournament Format') }}</h2>
                <p class="mt-1 max-w-3xl text-sm text-zinc-600 dark:text-zinc-300">
                    {{ __('This tournament now follows the frisbee flow you approved: Day 0 seeding, Day 1 bracket progression, then Day 2 elimination finals. Use these exact stage keys when scheduling matches so the setup stays consistent.') }}
                </p>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-700 dark:border-neutral-700 dark:bg-zinc-950 dark:text-zinc-300">
                <div class="font-semibold text-zinc-900 dark:text-white">{{ __('Current Tournament') }}</div>
                <div class="mt-1">{{ $tournament->name }}</div>
            </div>
        </div>
    </section>

    <section class="grid gap-4 xl:grid-cols-3">
        @foreach ($formatCards as $card)
            <section class="rounded-xl border p-6 {{ $card['tone'] }}">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500 dark:text-zinc-400">{{ $card['day'] }}</div>
                        <h3 class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-white">{{ $card['title'] }}</h3>
                    </div>

                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $card['accent'] }}">
                        {{ trans_choice('{1} :count stage|[2,*] :count stages', count($card['steps']), ['count' => count($card['steps'])]) }}
                    </span>
                </div>

                <p class="mt-4 text-sm text-zinc-700 dark:text-zinc-300">{{ $card['summary'] }}</p>

                <div class="mt-5 space-y-3">
                    @foreach ($card['steps'] as $step)
                        <div class="rounded-xl border border-white/60 bg-white/80 p-4 dark:border-white/10 dark:bg-zinc-900/70">
                            <div class="flex items-center justify-between gap-3">
                                <div class="font-semibold text-zinc-900 dark:text-white">{{ $step['label'] }}</div>
                                <code class="rounded-md border border-neutral-200 bg-zinc-50 px-2 py-1 text-xs text-zinc-700 dark:border-neutral-700 dark:bg-zinc-950 dark:text-zinc-300">{{ $step['key'] }}</code>
                            </div>
                            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $step['detail'] }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    </section>

    <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
        <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Scheduling Reminder') }}</h2>
        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
            {{ __('Create the quick match records from the Matches tab, but keep the stage values aligned to the frisbee workflow above so public schedules, filtering, and later automation all stay predictable.') }}
        </p>

        <div class="mt-4 flex flex-wrap gap-2">
            @foreach ($formatCards as $card)
                @foreach ($card['steps'] as $step)
                    <span class="inline-flex items-center rounded-full border border-neutral-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-700 dark:border-neutral-700 dark:bg-zinc-950 dark:text-zinc-300">
                        {{ $step['label'] }}: <code class="ms-2 text-xs">{{ $step['key'] }}</code>
                    </span>
                @endforeach
            @endforeach
        </div>
    </section>
</section>
