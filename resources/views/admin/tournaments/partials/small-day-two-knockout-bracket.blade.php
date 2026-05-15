@php
    use App\Support\SmallDayTwoKnockoutBracket;
    use App\Support\SmallTournamentTeamStanding;

    $isAdmin = $isAdmin ?? false;
    $knockoutScheduleRedirectTab = $knockoutScheduleRedirectTab ?? 'quarter-final';

    /** @var \App\Models\Tournament $selectedTournament */
    $defs = SmallDayTwoKnockoutBracket::gameDefinitions();
    $byGame = SmallDayTwoKnockoutBracket::bracketMatches($selectedTournament)->keyBy(fn ($m) => (int) $m->match_number);
    $rrSchedule = SmallTournamentTeamStanding::roundRobinScheduleCompletion($selectedTournament);
    $standingBundle = SmallTournamentTeamStanding::roundRobinTeamStanding($selectedTournament);
    $rrFinal = ($standingBundle['meta']['standings_status'] ?? '') === 'final';
    $rrAwaiting = $rrSchedule['total'] > 0 && ! $rrSchedule['is_complete'];
    $rrMeta = $standingBundle['meta'] ?? [];
    $qfStandingsPending = ((int) ($rrMeta['round_robin_matches_total'] ?? 0)) > 0
        && ($rrMeta['standings_status'] ?? '') !== 'final';
    $qfScheduleRows = SmallDayTwoKnockoutBracket::quarterFinalScheduleRowGroups();
    $rankingPathScheduleRows = SmallDayTwoKnockoutBracket::rankingPathScheduleRowGroups();
    $semiFinalScheduleRows = SmallDayTwoKnockoutBracket::semiFinalScheduleRowGroups();
    $rankingFiveEightScheduleRows = SmallDayTwoKnockoutBracket::rankingFiveEightScheduleRowGroups();
    $rankingThreeFourScheduleRows = SmallDayTwoKnockoutBracket::rankingThreeFourScheduleRowGroups();
    $qfLosersPendingForRankingPath = ! SmallDayTwoKnockoutBracket::quarterFinalsThrough40Decided($selectedTournament);
    $semiFinalTeamsPendingQuarterFinalWinners = ! SmallDayTwoKnockoutBracket::quarterFinalsThrough40Decided($selectedTournament);
    $rankingFiveEightTeamsPendingRankingPath = ! SmallDayTwoKnockoutBracket::rankingPath41Through42Decided($selectedTournament);
    $rankingThreeFourTeamsPendingSemis = ! SmallDayTwoKnockoutBracket::semiFinals43Through44Decided($selectedTournament);

    $hideBracketOverview = filter_var($hideBracketOverview ?? false, FILTER_VALIDATE_BOOLEAN);

    $sectionTitles = [
        'quarter_finals' => __('Quarter Finals'),
        'ranking_path' => __('Ranking Path'),
        'semi_finals' => __('Semi Finals'),
        'ranking_56_78' => __('Ranking 5/6 & 7/8'),
        'ranking_34' => __('Ranking 3/4'),
        'championship' => __('Championship'),
    ];

    $sectionOrder = ['quarter_finals', 'ranking_path', 'semi_finals', 'ranking_56_78', 'ranking_34', 'championship'];

    $sectionsOnly = isset($sectionsOnly) && is_array($sectionsOnly) ? array_values(array_unique($sectionsOnly)) : null;
    if ($sectionsOnly !== null && $sectionsOnly !== []) {
        $sectionOrder = array_values(array_filter($sectionOrder, fn (string $key): bool => in_array($key, $sectionsOnly, true)));
    }

    $gamesBySection = collect($defs)->groupBy(
        fn (array $d, int|string $_gameNum): string => $d['section'],
        true,
    );
@endphp

<section class="space-y-8">
    @if ($errors->has('quarter_final'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-200">
            {{ $errors->first('quarter_final') }}
        </div>
    @endif

    @if (! $hideBracketOverview)
        <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Day 2 knockout bracket') }}</h2>
                    <p class="mt-1 max-w-3xl text-sm text-zinc-600 dark:text-zinc-300">
                        {{ __('Games 37–48: quarter finals through championship. Teams slot in from final round robin standings (ranks 1–8), then winners and losers advance automatically as scores are completed.') }}
                    </p>
                </div>
                <span class="inline-flex shrink-0 items-center rounded-full border border-neutral-200 bg-zinc-50 px-3 py-1 text-xs font-semibold text-zinc-700 dark:border-neutral-700 dark:bg-zinc-950 dark:text-zinc-200">
                    {{ trans_choice('{1} :count bracket game|[2,*] :count bracket games', $byGame->count(), ['count' => $byGame->count()]) }}
                </span>
            </div>

            @if ($rrAwaiting || ! $rrFinal)
                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/55 dark:bg-amber-950/35 dark:text-amber-50">
                    {{ __('Bracket teams will be finalized after all Round Robin games are completed.') }}
                    @if ($rrSchedule['total'] > 0)
                        <span class="mt-1 block text-xs font-normal text-amber-900/90 dark:text-amber-200/90">
                            {{ __(':done of :total round robin games complete.', ['done' => $rrSchedule['completed'], 'total' => $rrSchedule['total']]) }}
                        </span>
                    @endif
                </div>
            @else
                <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/35 dark:text-emerald-100">
                    {{ __('Round robin is complete — quarter final slots use final standings (ranks 1–8).') }}
                </div>
            @endif
        </section>
    @endif

    @foreach ($sectionOrder as $sectionKey)
        @php
            $games = $gamesBySection->get($sectionKey, collect())->sortKeys();
        @endphp
        @if ($games->isEmpty())
            @continue
        @endif

        @php
            $sectionHasScheduledMatch = $games->keys()->contains(fn ($gameKey): bool => $byGame->has((int) $gameKey));
        @endphp
        @if (! $sectionHasScheduledMatch)
            @continue
        @endif

        @php
            $sectionGridClass = match ($sectionKey) {
                'championship', 'ranking_34' => 'grid grid-cols-1 gap-4 md:max-w-xl',
                default => 'grid grid-cols-1 gap-4 md:grid-cols-2',
            };
        @endphp

        <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <h3 class="text-base font-semibold text-zinc-900 dark:text-white">
                {{ $sectionTitles[$sectionKey] ?? $sectionKey }}
            </h3>

            @if ($sectionKey === 'quarter_finals')
                @if ($qfStandingsPending)
                    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/55 dark:bg-amber-950/35 dark:text-amber-50">
                        {{ __('Pending final standings.') }}
                    </div>
                @endif

                <div class="mt-4 space-y-8">
                    @foreach ($qfScheduleRows as $row)
                        @php
                            $qfRowGameNums = array_values(array_filter(
                                $row['game_numbers'],
                                fn ($n): bool => $byGame->has((int) $n),
                            ));
                        @endphp
                        @if ($qfRowGameNums === [])
                            @continue
                        @endif

                        <div class="rounded-xl border border-neutral-200/90 bg-zinc-50/60 p-4 dark:border-neutral-600 dark:bg-zinc-950/40">
                            <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-neutral-200/80 pb-3 dark:border-neutral-700/80">
                                <h4 class="text-base font-semibold text-zinc-900 dark:text-white">{{ $row['label'] }}</h4>
                                <p class="text-sm font-medium text-zinc-600 dark:text-zinc-300">{{ SmallDayTwoKnockoutBracket::scheduleRowTimeLabel($byGame, $qfRowGameNums, $row['time_label'], $selectedTournament) }}</p>
                            </div>
                            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                                @foreach ($qfRowGameNums as $gameNum)
                                    @php
                                        $gameNum = (int) $gameNum;
                                        $def = $defs[$gameNum];
                                        $match = $byGame->get($gameNum);
                                    @endphp

                                    @include('admin.tournaments.partials.small-day-two-knockout-match-card', [
                                        'selectedTournament' => $selectedTournament,
                                        'gameNum' => $gameNum,
                                        'def' => $def,
                                        'match' => $match,
                                        'canEnterScores' => $canEnterScores,
                                        'isAdmin' => $isAdmin,
                                        'knockoutScheduleRedirectTab' => $knockoutScheduleRedirectTab,
                                    ])
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @elseif ($sectionKey === 'ranking_path')
                <p class="mt-2 max-w-3xl text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('Quarter Final losers continue here for placement ranking. Ranking 21 is the round label only—not final standings rank.') }}
                </p>

                @if ($qfLosersPendingForRankingPath)
                    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/55 dark:bg-amber-950/35 dark:text-amber-50">
                        {{ __('Teams pending Quarter Finals results.') }}
                    </div>
                @endif

                <div class="mt-4 space-y-8">
                    @foreach ($rankingPathScheduleRows as $row)
                        @php
                            $rpRowGameNums = array_values(array_filter(
                                $row['game_numbers'],
                                fn ($n): bool => $byGame->has((int) $n),
                            ));
                        @endphp
                        @if ($rpRowGameNums === [])
                            @continue
                        @endif

                        <div class="rounded-xl border border-neutral-200/90 bg-zinc-50/60 p-4 dark:border-neutral-600 dark:bg-zinc-950/40">
                            <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-neutral-200/80 pb-3 dark:border-neutral-700/80">
                                <h4 class="text-base font-semibold text-zinc-900 dark:text-white">{{ $row['label'] }}</h4>
                                <p class="text-sm font-medium text-zinc-600 dark:text-zinc-300">{{ SmallDayTwoKnockoutBracket::scheduleRowTimeLabel($byGame, $rpRowGameNums, $row['time_label'], $selectedTournament) }}</p>
                            </div>
                            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                                @foreach ($rpRowGameNums as $gameNum)
                                    @php
                                        $gameNum = (int) $gameNum;
                                        $def = $defs[$gameNum];
                                        $match = $byGame->get($gameNum);
                                    @endphp

                                    @include('admin.tournaments.partials.small-day-two-knockout-match-card', [
                                        'selectedTournament' => $selectedTournament,
                                        'gameNum' => $gameNum,
                                        'def' => $def,
                                        'match' => $match,
                                        'canEnterScores' => $canEnterScores,
                                        'isAdmin' => $isAdmin,
                                        'knockoutScheduleRedirectTab' => $knockoutScheduleRedirectTab,
                                    ])
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @elseif ($sectionKey === 'semi_finals')
                <div class="mt-2 max-w-3xl space-y-2 text-sm text-zinc-600 dark:text-zinc-400">
                    <p class="font-medium text-zinc-800 dark:text-zinc-200">{{ __('Meaning') }}</p>
                    <ul class="list-inside list-disc space-y-0.5">
                        <li>{{ __('W37 = winner of Game 37') }}</li>
                        <li>{{ __('W40 = winner of Game 40') }}</li>
                        <li>{{ __('W38 = winner of Game 38') }}</li>
                        <li>{{ __('W39 = winner of Game 39') }}</li>
                    </ul>
                </div>

                @if ($semiFinalTeamsPendingQuarterFinalWinners)
                    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/55 dark:bg-amber-950/35 dark:text-amber-50">
                        {{ __('Teams pending source results') }}
                    </div>
                @endif

                <div class="mt-4 space-y-8">
                    @foreach ($semiFinalScheduleRows as $row)
                        @php
                            $sfRowGameNums = array_values(array_filter(
                                $row['game_numbers'],
                                fn ($n): bool => $byGame->has((int) $n),
                            ));
                        @endphp
                        @if ($sfRowGameNums === [])
                            @continue
                        @endif

                        <div class="rounded-xl border border-neutral-200/90 bg-zinc-50/60 p-4 dark:border-neutral-600 dark:bg-zinc-950/40">
                            <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-neutral-200/80 pb-3 dark:border-neutral-700/80">
                                <h4 class="text-base font-semibold text-zinc-900 dark:text-white">{{ $row['label'] }}</h4>
                                <p class="text-sm font-medium text-zinc-600 dark:text-zinc-300">{{ SmallDayTwoKnockoutBracket::scheduleRowTimeLabel($byGame, $sfRowGameNums, $row['time_label'], $selectedTournament) }}</p>
                            </div>
                            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                                @foreach ($sfRowGameNums as $gameNum)
                                    @php
                                        $gameNum = (int) $gameNum;
                                        $def = $defs[$gameNum];
                                        $match = $byGame->get($gameNum);
                                    @endphp

                                    @include('admin.tournaments.partials.small-day-two-knockout-match-card', [
                                        'selectedTournament' => $selectedTournament,
                                        'gameNum' => $gameNum,
                                        'def' => $def,
                                        'match' => $match,
                                        'canEnterScores' => $canEnterScores,
                                        'isAdmin' => $isAdmin,
                                        'knockoutScheduleRedirectTab' => $knockoutScheduleRedirectTab,
                                    ])
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @elseif ($sectionKey === 'ranking_56_78')
                <div class="mt-2 max-w-3xl space-y-2 text-sm text-zinc-600 dark:text-zinc-400">
                    <p class="font-medium text-zinc-800 dark:text-zinc-200">{{ __('Meaning') }}</p>
                    <ul class="list-inside list-disc space-y-0.5">
                        <li>{{ __('W41 = winner of Game 41') }}</li>
                        <li>{{ __('W42 = winner of Game 42') }}</li>
                        <li>{{ __('L41 = loser of Game 41') }}</li>
                        <li>{{ __('L42 = loser of Game 42') }}</li>
                    </ul>
                </div>

                @if ($rankingFiveEightTeamsPendingRankingPath)
                    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/55 dark:bg-amber-950/35 dark:text-amber-50">
                        {{ __('Teams pending source results') }}
                    </div>
                @endif

                <div class="mt-4 space-y-8">
                    @foreach ($rankingFiveEightScheduleRows as $row)
                        @php
                            $r58RowGameNums = array_values(array_filter(
                                $row['game_numbers'],
                                fn ($n): bool => $byGame->has((int) $n),
                            ));
                        @endphp
                        @if ($r58RowGameNums === [])
                            @continue
                        @endif

                        <div class="rounded-xl border border-neutral-200/90 bg-zinc-50/60 p-4 dark:border-neutral-600 dark:bg-zinc-950/40">
                            <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-neutral-200/80 pb-3 dark:border-neutral-700/80">
                                <h4 class="text-base font-semibold text-zinc-900 dark:text-white">{{ $row['label'] }}</h4>
                                <p class="text-sm font-medium text-zinc-600 dark:text-zinc-300">{{ SmallDayTwoKnockoutBracket::scheduleRowTimeLabel($byGame, $r58RowGameNums, $row['time_label'], $selectedTournament) }}</p>
                            </div>
                            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                                @foreach ($r58RowGameNums as $gameNum)
                                    @php
                                        $gameNum = (int) $gameNum;
                                        $def = $defs[$gameNum];
                                        $match = $byGame->get($gameNum);
                                    @endphp

                                    @include('admin.tournaments.partials.small-day-two-knockout-match-card', [
                                        'selectedTournament' => $selectedTournament,
                                        'gameNum' => $gameNum,
                                        'def' => $def,
                                        'match' => $match,
                                        'canEnterScores' => $canEnterScores,
                                        'isAdmin' => $isAdmin,
                                        'knockoutScheduleRedirectTab' => $knockoutScheduleRedirectTab,
                                    ])
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @elseif ($sectionKey === 'ranking_34')
                <div class="mt-2 max-w-3xl space-y-2 text-sm text-zinc-600 dark:text-zinc-400">
                    <p class="font-medium text-zinc-800 dark:text-zinc-200">{{ __('Meaning') }}</p>
                    <ul class="list-inside list-disc space-y-0.5">
                        <li>{{ __('L43 = loser of Game 43') }}</li>
                        <li>{{ __('L44 = loser of Game 44') }}</li>
                    </ul>
                </div>

                @if ($rankingThreeFourTeamsPendingSemis)
                    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/55 dark:bg-amber-950/35 dark:text-amber-50">
                        {{ __('Teams pending source results') }}
                    </div>
                @endif

                <div class="mt-4 space-y-8">
                    @foreach ($rankingThreeFourScheduleRows as $row)
                        @php
                            $r34RowGameNums = array_values(array_filter(
                                $row['game_numbers'],
                                fn ($n): bool => $byGame->has((int) $n),
                            ));
                        @endphp
                        @if ($r34RowGameNums === [])
                            @continue
                        @endif

                        <div class="rounded-xl border border-neutral-200/90 bg-zinc-50/60 p-4 dark:border-neutral-600 dark:bg-zinc-950/40">
                            <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-neutral-200/80 pb-3 dark:border-neutral-700/80">
                                <h4 class="text-base font-semibold text-zinc-900 dark:text-white">{{ $row['label'] }}</h4>
                                <p class="text-sm font-medium text-zinc-600 dark:text-zinc-300">{{ SmallDayTwoKnockoutBracket::scheduleRowTimeLabel($byGame, $r34RowGameNums, $row['time_label'], $selectedTournament) }}</p>
                            </div>
                            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                                @foreach ($r34RowGameNums as $gameNum)
                                    @php
                                        $gameNum = (int) $gameNum;
                                        $def = $defs[$gameNum];
                                        $match = $byGame->get($gameNum);
                                    @endphp

                                    @include('admin.tournaments.partials.small-day-two-knockout-match-card', [
                                        'selectedTournament' => $selectedTournament,
                                        'gameNum' => $gameNum,
                                        'def' => $def,
                                        'match' => $match,
                                        'canEnterScores' => $canEnterScores,
                                        'isAdmin' => $isAdmin,
                                        'knockoutScheduleRedirectTab' => $knockoutScheduleRedirectTab,
                                    ])
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div @class(['mt-4', $sectionGridClass])>
                    @foreach ($games as $gameNum => $def)
                        @php
                            $gameNum = (int) $gameNum;
                            $match = $byGame->get($gameNum);
                        @endphp

                        @if ($match === null)
                            @continue
                        @endif

                        @include('admin.tournaments.partials.small-day-two-knockout-match-card', [
                            'selectedTournament' => $selectedTournament,
                            'gameNum' => $gameNum,
                            'def' => $def,
                            'match' => $match,
                            'canEnterScores' => $canEnterScores,
                            'isAdmin' => $isAdmin,
                            'knockoutScheduleRedirectTab' => $knockoutScheduleRedirectTab,
                        ])
                    @endforeach
                </div>
            @endif
        </section>
    @endforeach
</section>
