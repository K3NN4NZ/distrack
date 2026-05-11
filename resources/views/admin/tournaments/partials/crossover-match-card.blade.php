@php
    $crossHome = $crossMatch->homeRegistration;
    $crossAway = $crossMatch->awayRegistration;
    $crossHomeRank = $crossHome?->bracket_rank;
    $crossAwayRank = $crossAway?->bracket_rank;
    $crossStatus = (string) $crossMatch->status;
    $crossStatusClasses = match ($crossStatus) {
        'live' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200',
        'completed' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-200',
        default => 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900/60 dark:bg-blue-950/30 dark:text-blue-200',
    };
    $showCrossoverDeleteFromFieldSection = $showCrossoverDeleteFromFieldSection ?? false;
    $crossoverDeleteConfirmMessage = match (true) {
        $crossStatus === 'completed' => __('This permanently deletes this crossover game and removes all final scores, player stats, and the scoring timeline. This cannot be undone. Continue?'),
        $crossStatus === 'live' => __('This permanently deletes this crossover game and removes any scores, player stats, and scoring timeline data recorded so far. Continue?'),
        default => __('Permanently delete this crossover game? It will be removed from the schedule. This cannot be undone.'),
    };
@endphp

<div class="rounded-xl border border-neutral-200 bg-zinc-50 p-4 dark:border-neutral-700 dark:bg-zinc-950">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
            <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $crossMatch->round_label ?: __('Crossover') }}</span>
            @if ($crossMatch->match_number)
                <span>{{ __('Game #:number', ['number' => $crossMatch->match_number]) }}</span>
            @endif
        </div>
        <span class="rounded-full border px-2.5 py-1 text-xs font-medium {{ $crossStatusClasses }}">
            {{ str($crossStatus)->headline() }}
        </span>
    </div>

    <div class="grid items-center gap-3 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]">
        <div class="min-w-0 rounded-lg bg-white p-3 ring-1 ring-neutral-200 dark:bg-zinc-900 dark:ring-neutral-800">
            <div class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">{{ __('Home') }}</div>
            <div class="mt-1 flex items-center gap-2">
                @if ($crossHomeRank)
                    <span class="inline-flex shrink-0 rounded-md bg-[#2f55b7]/10 px-2 py-1 text-xs font-bold tabular-nums text-[#2f55b7] dark:bg-blue-500/15 dark:text-blue-200">{{ $crossHomeRank }}</span>
                @endif
                <span class="min-w-0 truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ $crossHome?->team?->name ?? __('TBD') }}</span>
            </div>
        </div>

        <div class="text-center text-xs font-semibold uppercase text-zinc-400">{{ __('vs') }}</div>

        <div class="min-w-0 rounded-lg bg-white p-3 ring-1 ring-neutral-200 dark:bg-zinc-900 dark:ring-neutral-800">
            <div class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">{{ __('Away') }}</div>
            <div class="mt-1 flex items-center gap-2">
                @if ($crossAwayRank)
                    <span class="inline-flex shrink-0 rounded-md bg-[#2f55b7]/10 px-2 py-1 text-xs font-bold tabular-nums text-[#2f55b7] dark:bg-blue-500/15 dark:text-blue-200">{{ $crossAwayRank }}</span>
                @endif
                <span class="min-w-0 truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ $crossAway?->team?->name ?? __('TBD') }}</span>
            </div>
        </div>
    </div>

    <div class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-neutral-200 pt-3 text-xs text-zinc-500 dark:border-neutral-800 dark:text-zinc-400">
        <div class="flex flex-col gap-1">
            <div class="flex flex-wrap gap-x-4 gap-y-1">
                <span>{{ __('Field: :field', ['field' => $crossMatch->pitch?->name ?? __('Unassigned')]) }}</span>
                <span>{{ $crossMatch->scheduled_at ? $crossMatch->scheduled_at->format('M j, Y g:i A') : __('No time set') }}</span>
                @if ($crossMatch->home_score !== null && $crossMatch->away_score !== null)
                    <span class="font-semibold tabular-nums text-zinc-700 dark:text-zinc-200">{{ __('Score: :home - :away', ['home' => $crossMatch->home_score, 'away' => $crossMatch->away_score]) }}</span>
                @endif
            </div>
            @if (filled($crossMatch->pitch_id))
                <div class="text-[11px] font-medium text-zinc-600 dark:text-zinc-300">
                    {{ __('Assigned by: :name', ['name' => $crossMatch->pitchAssignedBy?->name ?? __('Unknown')]) }}
                </div>
            @endif
        </div>

        @if ($canCreateMatches)
            <div class="flex flex-wrap items-center justify-end gap-2">
                <flux:modal.trigger name="setup-edit-crossover-match-modal-{{ $crossMatch->id }}">
                    <flux:button
                        variant="ghost"
                        size="sm"
                        x-on:click="$dispatch('crossover-match-edit-opened', { matchId: '{{ $crossMatch->id }}' })"
                    >
                        {{ __('Edit') }}
                    </flux:button>
                </flux:modal.trigger>

                @if (
                    $crossMatch->stage === 'crossover'
                    && filled($crossMatch->pitch_id)
                    && $crossMatch->status === 'scheduled'
                    && (int) ($crossMatch->score_logs_count ?? 0) === 0
                )
                    <form
                        method="POST"
                        action="{{ route('admin.tournaments.matches.crossover-unassign-pitch', ['match' => $crossMatch->id]) }}"
                        class="inline"
                        onsubmit="return confirm(@json(__('Remove this game from the field? It will return to the unassigned crossover list.')))"
                    >
                        @csrf
                        <input type="hidden" name="redirect_route" value="admin.tournaments.index">
                        <input type="hidden" name="redirect_tab" value="crossover">

                        <flux:button type="submit" variant="ghost" size="sm">
                            {{ __('Remove from field') }}
                        </flux:button>
                    </form>
                @endif

                @if ($showCrossoverDeleteFromFieldSection && $crossMatch->stage === 'crossover' && filled($crossMatch->pitch_id))
                    <form
                        method="POST"
                        action="{{ route('admin.tournaments.matches.destroy', ['match' => $crossMatch]) }}"
                        class="inline"
                        onsubmit="return confirm(@json($crossoverDeleteConfirmMessage))"
                    >
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="tournament_id" value="{{ $selectedTournament->id }}">
                        <input type="hidden" name="redirect_route" value="admin.tournaments.index">
                        <input type="hidden" name="redirect_tab" value="crossover">

                        <flux:button type="submit" variant="ghost" size="sm" class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300">
                            {{ __('Delete game') }}
                        </flux:button>
                    </form>
                @endif
            </div>
        @endif
    </div>
</div>
