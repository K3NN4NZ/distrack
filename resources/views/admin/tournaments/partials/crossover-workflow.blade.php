@php
    $unassignedCrossoverMatches = $crossoverMatches
        ->filter(fn ($match) => blank($match->pitch_id))
        ->values();

    $assignedCrossoverMatchesByPitch = $crossoverMatches
        ->filter(fn ($match) => filled($match->pitch_id))
        ->groupBy(fn ($match) => (int) $match->pitch_id);

    $crossoverPitchClearEligibleCount = $crossoverMatches->filter(
        fn ($match): bool => filled($match->pitch_id)
            && (string) $match->status === 'scheduled'
            && (int) ($match->score_logs_count ?? 0) === 0,
    )->count();

    $crossoverBulkClearConfirmMessage = $crossoverPitchClearEligibleCount > 0
        ? __('Remove all scheduled crossover games from their fields? Games that are live, completed, or already have scoring plays stay assigned. This affects :count games.', ['count' => $crossoverPitchClearEligibleCount])
        : '';
@endphp

<section class="space-y-6">
    <section class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-zinc-900">
        <div class="border-b border-neutral-200 bg-zinc-50 px-6 py-5 dark:border-neutral-700 dark:bg-zinc-950/70">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-[#2f55b7]/10 text-[#2f55b7] dark:bg-[#2f55b7]/20 dark:text-blue-300">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path d="M6 7h12" />
                                <path d="M6 17h12" />
                                <path d="m8 5-2 2 2 2" />
                                <path d="m16 15 2 2-2 2" />
                            </svg>
                        </span>
                        <div>
                            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Crossover Setup') }}</h2>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ __('Tournament: :name', ['name' => $selectedTournament->name]) }}
                            </p>
                        </div>
                    </div>
                    <p class="mt-3 max-w-3xl text-sm text-zinc-600 dark:text-zinc-300">
                        {{ __('Pair ranked teams from different brackets. Automatic generation uses mirror ranks: A1 plays the lowest ranked team in B, A2 plays the next lowest, and so on.') }}
                    </p>
                </div>

                @if ($canCreateMatches)
                    <div class="flex flex-wrap gap-2 xl:shrink-0">
                        <form method="POST" action="{{ route('admin.tournaments.matches.crossover.generate') }}" class="inline">
                            @csrf
                            <input type="hidden" name="tournament_id" value="{{ $selectedTournament->id }}">
                            <input type="hidden" name="redirect_route" value="admin.tournaments.index">
                            <input type="hidden" name="redirect_tab" value="crossover">
                            <flux:button
                                type="submit"
                                variant="primary"
                                :disabled="! $canGenerateAutomaticCrossover"
                            >
                                {{ __('Generate games') }}
                            </flux:button>
                        </form>

                        <flux:modal.trigger name="setup-add-crossover-match-modal-{{ $selectedTournament->id }}">
                            <flux:button variant="ghost" :disabled="! $crossoverReadyForManualPairing">
                                {{ __('Add game') }}
                            </flux:button>
                        </flux:modal.trigger>

                        <flux:modal.trigger name="setup-add-pitch-modal-{{ $selectedTournament->id }}">
                            <flux:button variant="ghost">
                                {{ __('Add field') }}
                            </flux:button>
                        </flux:modal.trigger>

                        @if ($crossoverPitchClearEligibleCount > 0)
                            <form
                                method="POST"
                                action="{{ route('admin.tournaments.matches.crossover.clear-pitch-assignments', $selectedTournament) }}"
                                class="inline"
                                onsubmit="return confirm(@json($crossoverBulkClearConfirmMessage))"
                            >
                                @csrf
                                <input type="hidden" name="redirect_tab" value="crossover">
                                <flux:button type="submit" variant="ghost" class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300">
                                    {{ __('Clear field assignments') }}
                                </flux:button>
                            </form>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        <div class="grid divide-y divide-neutral-200 dark:divide-neutral-700 lg:grid-cols-4 lg:divide-x lg:divide-y-0">
            <div class="px-6 py-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">{{ __('Ranked teams') }}</div>
                    <span class="h-2 w-2 rounded-full {{ $rankedCrossoverRegistrations->count() >= 2 ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                </div>
                <div class="mt-2 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $rankedCrossoverRegistrations->count() }}</div>
                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Apply Bracket Ranking first') }}</div>
            </div>
            <div class="px-6 py-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">{{ __('Bracket pairs') }}</div>
                    <span class="h-2 w-2 rounded-full {{ $canGenerateAutomaticCrossover ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                </div>
                <div class="mt-2 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $crossoverBracketPairCount }}</div>
                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Equal team counts per pair') }}</div>
            </div>
            <div class="px-6 py-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">{{ __('Games') }}</div>
                    <span class="h-2 w-2 rounded-full {{ $crossoverMatches->isNotEmpty() ? 'bg-emerald-500' : 'bg-zinc-400' }}"></span>
                </div>
                <div class="mt-2 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $crossoverMatches->count() }}</div>
                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Generated or manually added') }}</div>
            </div>
            <div class="px-6 py-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">{{ __('Fields') }}</div>
                    <span class="h-2 w-2 rounded-full {{ $pitchCount > 0 ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                </div>
                <div class="mt-2 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $pitchCount }}</div>
                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                    {{ $crossoverUnassignedFieldCount > 0 ? __(':count games unassigned', ['count' => $crossoverUnassignedFieldCount]) : __('Ready for assignment') }}
                </div>
            </div>
        </div>

        @if (\App\Support\TournamentPooling::isCrossoverScheduleFullyResolved($crossoverMatches))
            <div class="border-t border-emerald-200 bg-emerald-50 px-6 py-4 dark:border-emerald-900/45 dark:bg-emerald-950/30">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm font-semibold text-emerald-900 dark:text-emerald-100">
                        {{ __('Crossover completed. Continue to Pooling.') }}
                    </p>
                    <a
                        href="{{ route('admin.tournaments.index', ['tournament' => $selectedTournament->id, 'tab' => 'pooling']) }}"
                        wire:navigate
                        class="inline-flex shrink-0 items-center justify-center rounded-lg border border-emerald-700 bg-emerald-700 px-4 py-2 text-sm font-medium text-white transition hover:border-emerald-800 hover:bg-emerald-800 dark:border-emerald-500 dark:bg-emerald-600 dark:hover:bg-emerald-500"
                    >
                        {{ __('Open Pooling tab') }}
                    </a>
                </div>
            </div>
        @endif

        <div class="border-t border-neutral-200 px-6 py-4 dark:border-neutral-700">
            <div class="grid gap-3 text-sm md:grid-cols-3">
                <div class="flex gap-3">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-zinc-900 text-xs font-semibold text-white dark:bg-white dark:text-zinc-900">1</span>
                    <div>
                        <div class="font-semibold text-zinc-900 dark:text-white">{{ __('Rank teams') }}</div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Finish round robin, then apply Bracket Ranking.') }}</div>
                    </div>
                </div>
                <div class="flex gap-3">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-zinc-900 text-xs font-semibold text-white dark:bg-white dark:text-zinc-900">2</span>
                    <div>
                        <div class="font-semibold text-zinc-900 dark:text-white">{{ __('Create games') }}</div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Generate mirror-rank games or add one manually.') }}</div>
                    </div>
                </div>
                <div class="flex gap-3">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-zinc-900 text-xs font-semibold text-white dark:bg-white dark:text-zinc-900">3</span>
                    <div>
                        <div class="font-semibold text-zinc-900 dark:text-white">{{ __('Assign field and time') }}</div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Edit each game when the final schedule is known.') }}</div>
                    </div>
                </div>
            </div>
        </div>

        @if ($errors->has('crossover'))
            <div class="mx-6 mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
                {{ $errors->first('crossover') }}
            </div>
        @endif
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(20rem,0.9fr)]">
        <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">{{ __('Ranked teams by bracket') }}</h3>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ __('These are the teams available for crossover pairings.') }}</p>
                </div>
                <span class="rounded-md border border-neutral-200 px-3 py-1 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                    {{ trans_choice('{0} No ranked teams yet|{1} :count ranked team|[2,*] :count ranked teams', $rankedCrossoverRegistrations->count(), ['count' => $rankedCrossoverRegistrations->count()]) }}
                </span>
            </div>

            @if ($rankedCrossoverByBracket->isNotEmpty())
                <div class="grid gap-3 md:grid-cols-2">
                    @foreach ($rankedCrossoverByBracket as $bracketTitle => $registrations)
                        <div class="rounded-lg border border-neutral-200 bg-zinc-50 p-4 dark:border-neutral-700 dark:bg-zinc-950">
                            <div class="flex items-center justify-between gap-3">
                                <div class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Bracket :bracket', ['bracket' => $bracketTitle]) }}</div>
                                <span class="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-zinc-600 ring-1 ring-neutral-200 dark:bg-zinc-900 dark:text-zinc-300 dark:ring-neutral-700">
                                    {{ trans_choice('{1} :count team|[2,*] :count teams', $registrations->count(), ['count' => $registrations->count()]) }}
                                </span>
                            </div>
                            <div class="mt-3 divide-y divide-neutral-200 dark:divide-neutral-800">
                                @foreach ($registrations as $registration)
                                    <div class="flex items-center gap-3 py-2 first:pt-0 last:pb-0">
                                        <span class="inline-flex min-w-10 justify-center rounded-md bg-[#2f55b7]/10 px-2 py-1 text-xs font-bold tabular-nums text-[#2f55b7] dark:bg-blue-500/15 dark:text-blue-200">{{ $registration->bracket_rank }}</span>
                                        <span class="min-w-0 flex-1 truncate text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $registration->team?->name ?? __('Team deleted') }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="rounded-xl border border-dashed border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100">
                    {{ __('No bracket ranks yet. Finish round robin, open Bracket Ranking, then apply ranks so teams appear here.') }}
                </div>
            @endif
        </section>

        <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="mb-4">
                <h3 class="text-base font-semibold text-zinc-900 dark:text-white">{{ __('Automatic pairing check') }}</h3>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ __('Generate works only when bracket pairs have the same number of ranked teams.') }}</p>
            </div>

            @if ($canGenerateAutomaticCrossover)
                <div class="space-y-3">
                    @foreach (range(0, $sortedCrossoverBracketCodes->count() - 2, 2) as $pairStart)
                        @php
                            $leftCode = $sortedCrossoverBracketCodes[$pairStart];
                            $rightCode = $sortedCrossoverBracketCodes[$pairStart + 1];
                            $leftCount = $rankedCrossoverGroupsNormalized[$leftCode]->count();
                        @endphp
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 dark:border-emerald-900/60 dark:bg-emerald-950/20">
                            <div class="flex items-center justify-between gap-3">
                                <div class="font-semibold text-emerald-900 dark:text-emerald-100">{{ __('Bracket :left vs :right', ['left' => $leftCode, 'right' => $rightCode]) }}</div>
                                <span class="text-xs font-medium text-emerald-700 dark:text-emerald-200">{{ __(':count games', ['count' => $leftCount]) }}</span>
                            </div>
                            <div class="mt-2 text-xs text-emerald-800 dark:text-emerald-200">
                                {{ __('Mirror ranks: :left1 vs :rightLast, :left2 vs :rightNext', ['left1' => $leftCode.'1', 'rightLast' => $rightCode.$leftCount, 'left2' => $leftCode.'2', 'rightNext' => $rightCode.max($leftCount - 1, 1)]) }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="rounded-xl border border-dashed border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100">
                    @if ($rankedCrossoverRegistrations->isEmpty())
                        {{ __('Waiting for ranked teams. Use Bracket Ranking first.') }}
                    @elseif ($sortedCrossoverBracketCodes->count() < 2)
                        {{ __('Need ranked teams from at least two brackets.') }}
                    @elseif ($sortedCrossoverBracketCodes->count() % 2 !== 0)
                        {{ __('Automatic generation needs an even number of ranked brackets.') }}
                    @else
                        {{ __('One bracket pair has different team counts. Add manually or adjust bracket ranks.') }}
                    @endif
                </div>
            @endif
        </section>
    </div>

    <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-zinc-900 dark:text-white">{{ __('Crossover games') }}</h3>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ __('Review pairings, then assign each saved game into the correct field bucket.') }}</p>
            </div>
            <div class="flex flex-wrap gap-2 text-xs">
                <span class="inline-flex items-center gap-1.5 rounded-full border border-neutral-200 px-3 py-1 font-medium text-zinc-700 dark:border-neutral-700 dark:text-zinc-200">
                    <span class="h-1.5 w-1.5 rounded-full bg-zinc-400"></span>
                    {{ trans_choice('{0} No games|{1} :count game|[2,*] :count games', $crossoverMatches->count(), ['count' => $crossoverMatches->count()]) }}
                </span>
                @if ($crossoverScheduledCount > 0)
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-blue-200 bg-blue-50 px-3 py-1 font-medium text-blue-700 dark:border-blue-900/60 dark:bg-blue-950/30 dark:text-blue-200">
                        <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                        {{ __(':count scheduled', ['count' => $crossoverScheduledCount]) }}
                    </span>
                @endif
                @if ($crossoverLiveCount > 0)
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 font-medium text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-500"></span>
                        {{ __(':count live', ['count' => $crossoverLiveCount]) }}
                    </span>
                @endif
                @if ($crossoverCompletedCount > 0)
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 font-medium text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-200">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        {{ __(':count completed', ['count' => $crossoverCompletedCount]) }}
                    </span>
                @endif
            </div>
        </div>

        @if ($crossoverMatches->isEmpty())
            <div class="rounded-xl border border-dashed border-neutral-300 p-4 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                {{ __('No crossover games yet. Generate games when bracket pairs are ready, or add a game manually.') }}
            </div>
        @else
            <div class="space-y-6">
                <div>
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div>
                            <h4 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Unassigned games') }}</h4>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Games stay here until a field is selected and saved.') }}</p>
                        </div>
                        <span class="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200">
                            {{ __(':count waiting for field', ['count' => $unassignedCrossoverMatches->count()]) }}
                        </span>
                    </div>

                    @if ($unassignedCrossoverMatches->isNotEmpty())
                        <div class="grid gap-3 xl:grid-cols-2" data-crossover-unassigned-list>
                            @foreach ($unassignedCrossoverMatches as $crossMatch)
                                @include('admin.tournaments.partials.crossover-match-card', ['crossMatch' => $crossMatch])
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-xl border border-dashed border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-100">
                            {{ __('No crossover games are waiting for field assignment.') }}
                        </div>
                    @endif
                </div>

                <div>
                    <div class="mb-3">
                        <h4 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Assigned by field') }}</h4>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Saved games move out of the unassigned list and into their assigned field below. Use Remove from field to send a scheduled game back to the unassigned list without deleting it. Use Delete game to permanently remove a match—including completed games and all score data—after confirming.') }}
                        </p>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        @foreach ($selectedTournament->pitches as $pitch)
                            @php
                                $pitchMatches = $assignedCrossoverMatchesByPitch->get($pitch->id, collect())->values();
                            @endphp
                            <section class="rounded-xl border border-neutral-200 bg-zinc-50 p-4 dark:border-neutral-700 dark:bg-zinc-950" data-crossover-pitch-group="{{ $pitch->id }}">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ $pitch->name }}</div>
                                        <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ $pitch->location ?: __('No location provided') }}
                                        </div>
                                    </div>
                                    <span class="rounded-full border border-neutral-200 bg-white px-2.5 py-1 text-xs font-medium text-zinc-600 dark:border-neutral-700 dark:bg-zinc-900 dark:text-zinc-300">
                                        {{ trans_choice('{0} No games|{1} :count game|[2,*] :count games', $pitchMatches->count(), ['count' => $pitchMatches->count()]) }}
                                    </span>
                                </div>

                                @if ($pitchMatches->isNotEmpty())
                                    <div class="mt-4 space-y-3">
                                        @foreach ($pitchMatches as $crossMatch)
                                            @include('admin.tournaments.partials.crossover-match-card', [
                                                'crossMatch' => $crossMatch,
                                                'showCrossoverDeleteFromFieldSection' => true,
                                            ])
                                        @endforeach
                                    </div>
                                @else
                                    <div class="mt-4 rounded-lg border border-dashed border-neutral-300 px-4 py-3 text-sm text-zinc-500 dark:border-neutral-700 dark:text-zinc-400">
                                        {{ __('No crossover games assigned here yet.') }}
                                    </div>
                                @endif
                            </section>
                        @endforeach
                    </div>

                    @if ($selectedTournament->pitches->isEmpty())
                        <div class="mt-4 rounded-xl border border-dashed border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100">
                            {{ __('Add a field first so saved crossover games can be placed under a location.') }}
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </section>

    <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-zinc-900 dark:text-white">{{ __('Playing fields') }}</h3>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                    {{ __('Create and edit fields used by crossover games.') }}
                </p>
            </div>
            <span class="rounded-md border border-neutral-200 px-3 py-1 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                {{ trans_choice('{0} No fields|{1} :count field|[2,*] :count fields', $pitchCount, ['count' => $pitchCount]) }}
            </span>
        </div>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($selectedTournament->pitches as $pitch)
                <div class="rounded-xl border border-neutral-200 bg-zinc-50 p-4 dark:border-neutral-700 dark:bg-zinc-950">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="truncate font-semibold text-zinc-900 dark:text-white">{{ $pitch->name }}</div>
                            <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                {{ $pitch->location ?: __('No location provided') }}
                            </div>
                            <div class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ __('Sort order: :order', ['order' => $pitch->sort_order]) }}
                            </div>
                        </div>

                        @if ($canCreateMatches)
                            <div class="flex shrink-0 flex-wrap gap-2">
                                <flux:modal.trigger name="setup-edit-pitch-modal-{{ $pitch->id }}">
                                    <flux:button variant="ghost" size="sm">
                                        {{ __('Edit') }}
                                    </flux:button>
                                </flux:modal.trigger>

                                <form
                                    method="POST"
                                    action="{{ route('admin.tournaments.pitches.destroy', ['pitch' => $pitch->id]) }}"
                                    onsubmit="return confirm('{{ __('Delete this field? Matches linked to it will lose this assignment.') }}')"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="redirect_route" value="admin.tournaments.index">
                                    <input type="hidden" name="redirect_tab" value="crossover">

                                    <button
                                        type="submit"
                                        class="rounded-full border border-red-200 px-3 py-1 text-[11px] font-medium text-red-600 transition hover:border-red-300 hover:bg-red-50 dark:border-red-900/70 dark:text-red-300 dark:hover:bg-red-950/40"
                                    >
                                        {{ __('Delete') }}
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-neutral-300 p-4 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300 md:col-span-2 xl:col-span-3">
                    {{ __('No playing fields yet. Use Add field to create FIELD 1, FIELD 2, or other surfaces.') }}
                </div>
            @endforelse
        </div>
    </section>
</section>
