@php
    $hasBracketThreshold = $hasBracketThreshold ?? true;
@endphp

@if (! $hasBracketThreshold)
    @include('admin.tournaments.partials.small-day-two-knockout-bracket', [
        'selectedTournament' => $selectedTournament,
        'canEnterScores' => $canEnterScores,
        'canCreateMatches' => $canCreateMatches,
        'isAdmin' => $isAdmin ?? false,
        'sectionsOnly' => ['quarter_finals', 'ranking_path'],
        'knockoutScheduleRedirectTab' => 'quarter-final',
        'hideBracketOverview' => true,
    ])
@else
    @php
        /** @var \Illuminate\Support\Collection<int, \App\Models\TournamentMatch> $crossoverMatches */
        /** @var \App\Models\Tournament $selectedTournament */

        $poolingData = \App\Support\TournamentPooling::buildPoolingAssignments($selectedTournament, $crossoverMatches);
        $poolingReadyForQuarterFinals = \App\Support\TournamentPooling::poolingFinalizedForQuarterFinalGeneration($poolingData);

        $roundRobinSchedule = \App\Support\SmallTournamentTeamStanding::roundRobinScheduleCompletion($selectedTournament);
        $roundRobinKnockoutReady = $roundRobinSchedule['is_complete'];
        $roundRobinAwaitingCompletion = $roundRobinSchedule['total'] > 0 && ! $roundRobinSchedule['is_complete'];

        $qfStages = \App\Support\SmallDayTwoKnockoutBracket::quarterFinalStageAliases();
        $qfMatches = collect($selectedTournament->matches ?? [])
            ->filter(fn ($match): bool => in_array((string) $match->stage, $qfStages, true))
            ->sort(function ($left, $right): int {
                $ln = $left->match_number ?? PHP_INT_MAX;
                $rn = $right->match_number ?? PHP_INT_MAX;

                if ($ln !== $rn) {
                    return $ln <=> $rn;
                }

                $lt = $left->scheduled_at?->getTimestamp() ?? PHP_INT_MAX;
                $rt = $right->scheduled_at?->getTimestamp() ?? PHP_INT_MAX;

                if ($lt !== $rt) {
                    return $lt <=> $rt;
                }

                return $left->id <=> $right->id;
            })
            ->values();
    @endphp

    <section class="space-y-6">
        <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Quarter Finals') }}</h2>
                    <p class="mt-1 max-w-3xl text-sm text-zinc-600 dark:text-zinc-300">
                        {{ __('Pair Pool A seeds against Pool B seeds using the standard crossover bracket once pooling has decisive crossover results and every Pool A / Pool B slot shows a team.') }}
                    </p>
                </div>

                @if ($qfMatches->isNotEmpty())
                    <span class="inline-flex shrink-0 items-center rounded-full border border-neutral-200 bg-zinc-50 px-3 py-1 text-xs font-semibold text-zinc-700 dark:border-neutral-700 dark:bg-zinc-950 dark:text-zinc-200">
                        {{ trans_choice('{1} :count Quarter Final game|[2,*] :count Quarter Final games', $qfMatches->count(), ['count' => $qfMatches->count()]) }}
                    </span>
                @endif
            </div>

            @if ($errors->has('quarter_final'))
                <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-200">
                    {{ $errors->first('quarter_final') }}
                </div>
            @endif

            @if (! $poolingReadyForQuarterFinals)
                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/35 dark:text-amber-100">
                    {{ __('Cannot generate Quarter Finals yet. Please finalize Pool A and Pool B first.') }}
                </div>
            @elseif ($roundRobinAwaitingCompletion)
                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/35 dark:text-amber-100">
                    {{ __('Pooling is ready, but Round Robin is still in progress. Quarter Final pairings stay pending until every Round Robin game is completed and standings are final.') }}
                </div>
            @else
                <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/35 dark:text-emerald-100">
                    {{ __('Pooling is ready — Pool A and Pool B slots are fully resolved.') }}
                    @if ($roundRobinSchedule['total'] > 0)
                        <span class="mt-1 block text-xs font-normal text-emerald-800/95 dark:text-emerald-200/90">
                            {{ __('All :total scheduled Round Robin games are complete.', ['total' => $roundRobinSchedule['total']]) }}
                        </span>
                    @endif
                </div>
            @endif

            @if ($qfMatches->isEmpty())
                <div class="mt-4 rounded-xl border border-dashed border-neutral-300 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-neutral-700 dark:bg-zinc-950 dark:text-zinc-300">
                    @if ($roundRobinAwaitingCompletion && $poolingReadyForQuarterFinals)
                        {{ __('Pending final standings — finish every Round Robin game, then generate Quarter Finals from pooling.') }}
                    @else
                        {{ __('No Quarter Final games generated yet. Generate Quarter Finals after pooling is finalized.') }}
                    @endif
                </div>
            @endif

            <div class="mt-6 flex flex-wrap gap-3">
                <form
                    method="POST"
                    action="{{ route('admin.tournaments.matches.quarter-finals.generate', $selectedTournament) }}"
                    class="inline-flex"
                >
                    @csrf
                    <input type="hidden" name="redirect_tab" value="quarter-final">
                    <flux:button type="submit" variant="primary" :disabled="! $poolingReadyForQuarterFinals || ! $roundRobinKnockoutReady">
                        {{ __('Generate Quarter Finals from pooling') }}
                    </flux:button>
                </form>
                @if ($poolingReadyForQuarterFinals && $roundRobinKnockoutReady)
                    <flux:text class="self-center text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('Re-running replaces auto-generated Quarter Finals only (manual Day 2 matches stay untouched).') }}
                    </flux:text>
                @endif
            </div>
        </section>

        @include('admin.tournaments.partials.game-score-dashboard', [
            'selectedTournament' => $selectedTournament,
            'matches' => $qfMatches,
            'canEnterScores' => $canEnterScores,
            'canCreateMatches' => $canCreateMatches,
            'showAddMatchButton' => true,
            'showPublicLinks' => $selectedTournament->is_public,
            'dashboardTitle' => __('Quarter Final games'),
            'dashboardIntro' => __('Games listed below use stage Quarter Finals (manual adds appear alongside generated rows).'),
            'dashboardEmptyMessage' => __('No Quarter Final games to display yet—generate from pooling or use Add Match.'),
            'dashboardTab' => \App\Support\AdminTournamentTabStatusPresentation::TAB_QUARTER_FINAL,
        ])
    </section>
@endif
