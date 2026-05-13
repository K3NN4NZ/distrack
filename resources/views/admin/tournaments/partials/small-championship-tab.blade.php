@php
    use App\Support\SmallDayTwoKnockoutBracket;

    /** @var \App\Models\Tournament $selectedTournament */
    $defs = SmallDayTwoKnockoutBracket::gameDefinitions();
    $byGame = SmallDayTwoKnockoutBracket::bracketMatches($selectedTournament)->keyBy(fn ($m) => (int) $m->match_number);
    $gameNum = 48;
    $def = $defs[$gameNum];
    $match = $byGame->get($gameNum);
    $semisDecided = SmallDayTwoKnockoutBracket::semiFinals43Through44Decided($selectedTournament);
@endphp

<section class="space-y-6">
    <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-baseline sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Championship') }}</h2>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                    {{ __('Game 48 — winner of Game 43 vs winner of Game 44.') }}
                </p>
            </div>
            <p class="text-sm font-medium text-zinc-700 dark:text-zinc-200">
                {{ __('Time') }}: {{ $def['time_label'] }}
            </p>
        </div>

        <div class="mt-4 max-w-xl space-y-2 text-sm text-zinc-600 dark:text-zinc-400">
            <p class="font-medium text-zinc-800 dark:text-zinc-200">{{ __('Meaning') }}</p>
            <ul class="list-inside list-disc space-y-0.5">
                <li>{{ __('W43 = winner of Game 43') }}</li>
                <li>{{ __('W44 = winner of Game 44') }}</li>
            </ul>
        </div>

        @if (! $semisDecided)
            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/55 dark:bg-amber-950/35 dark:text-amber-50">
                {{ __('Semi-finals are still in progress — Game 48 stays as :matchup until both semi-finals have decisive winners.', ['matchup' => 'W43 vs W44']) }}
            </div>
        @endif
    </section>

    @if ($match !== null)
        @include('admin.tournaments.partials.small-day-two-knockout-match-card', [
            'selectedTournament' => $selectedTournament,
            'gameNum' => $gameNum,
            'def' => $def,
            'match' => $match,
            'canEnterScores' => $canEnterScores,
            'isAdmin' => $isAdmin,
            'knockoutScheduleRedirectTab' => 'championship',
        ])
    @else
        <section class="rounded-xl border border-dashed border-neutral-300 bg-zinc-50 p-6 text-sm text-zinc-600 dark:border-neutral-700 dark:bg-zinc-950 dark:text-zinc-300">
            {{ __('Game 48 has not been generated yet. Open Quarter Finals first so the Day 2 bracket can sync.') }}
        </section>
    @endif
</section>
