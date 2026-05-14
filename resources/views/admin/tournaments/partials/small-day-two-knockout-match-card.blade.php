@php
    /** @var \App\Models\Tournament $selectedTournament */
    /** @var int $gameNum */
    /** @var array $def */
    /** @var \App\Models\TournamentMatch $match */
    $isAdmin = $isAdmin ?? false;
    $knockoutScheduleRedirectTab = $knockoutScheduleRedirectTab ?? 'quarter-final';
    $homeName = $match->homeRegistration?->team?->name;
    $awayName = $match->awayRegistration?->team?->name;
    $homeLabel = $homeName ?: $def['home_placeholder'];
    $awayLabel = $awayName ?: $def['away_placeholder'];
    $hasScoreline = $match->home_score !== null && $match->away_score !== null;
    $completed = $match->status === 'completed' && $hasScoreline;
    $bothTeamsAssigned = $match->home_registration_id !== null && $match->away_registration_id !== null;
    $scoringUrl = route('admin.tournaments.matches.scoring', ['tournament' => $selectedTournament, 'match' => $match]);
    $bracketStatusOptions = \App\Support\AdminTournamentTabStatusPresentation::knockoutMatchStatusSelectOptions();
@endphp

<article
    class="flex flex-col rounded-xl border border-neutral-200 bg-zinc-50/80 p-4 shadow-sm transition dark:border-neutral-700 dark:bg-zinc-950/40"
>
    <div class="flex flex-wrap items-start justify-between gap-2 border-b border-neutral-200/80 pb-2 dark:border-neutral-700/80">
        <div>
            <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                {{ __('Game') }} {{ $gameNum }}
            </div>
            <div class="mt-0.5 text-sm font-medium text-zinc-800 dark:text-zinc-100">
                {{ $match->round_label ?? $def['round_label'] }}
            </div>
        </div>
        @if ($isAdmin)
            <form
                method="POST"
                action="{{ route('admin.tournaments.matches.status.update', ['tournament' => $selectedTournament, 'match' => $match]) }}"
                class="flex shrink-0 items-center gap-1"
            >
                @csrf
                @method('PATCH')
                <input type="hidden" name="redirect_route" value="admin.tournaments.index">
                <input type="hidden" name="redirect_tab" value="{{ $knockoutScheduleRedirectTab }}">
                <label class="sr-only">{{ __('Status for game :num', ['num' => $gameNum]) }}</label>
                <select
                    name="status"
                    required
                    class="h-8 max-w-[11rem] rounded-md border border-neutral-300 bg-white px-1.5 text-[11px] font-medium text-zinc-800 shadow-sm dark:border-neutral-600 dark:bg-zinc-950 dark:text-zinc-100"
                    onchange="(() => { const f = this.closest('form'); if (! f) return; if (typeof f.requestSubmit === 'function') { f.requestSubmit(); } else { f.submit(); } })()"
                >
                    @foreach ($bracketStatusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($match->status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        @else
            <span
                @class([
                    'inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium',
                    'border-emerald-300 bg-emerald-100 text-emerald-900 dark:border-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-100' => $match->status === 'completed',
                    'border-sky-300 bg-sky-100 text-sky-900 dark:border-sky-800 dark:bg-sky-900/40 dark:text-sky-100' => $match->status === 'live',
                    'border-zinc-200 bg-white text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200' => ! in_array($match->status, ['completed', 'live'], true),
                ])
            >
                {{ $bracketStatusOptions[$match->status] ?? \Illuminate\Support\Str::headline($match->status) }}
            </span>
        @endif
    </div>

    <dl class="mt-3 space-y-1 text-xs text-zinc-600 dark:text-zinc-400">
        <div class="flex flex-wrap gap-1">
            <dt class="font-medium text-zinc-500 dark:text-zinc-500">{{ __('Time') }}</dt>
            <dd>{{ $def['time_label'] }}</dd>
        </div>
        @if ($match->pitch)
            <div class="flex flex-wrap gap-1">
                <dt class="font-medium text-zinc-500 dark:text-zinc-500">{{ __('Pitch') }}</dt>
                <dd>{{ $match->pitch->name }}</dd>
            </div>
        @endif
    </dl>

    <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
        <div class="{{ \App\Support\MatchTeamBoxResultPresentation::teamBoxClasses($match, 'home') }}">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">{{ __('Home') }}</span>
                @if ($def['home_rank'])
                    <span class="rounded bg-zinc-200 px-1.5 py-0.5 text-[10px] font-bold text-zinc-800 dark:bg-zinc-700 dark:text-zinc-100">
                        {{ __('Rank') }} {{ $def['home_rank'] }}
                    </span>
                @endif
            </div>
            <div class="mt-1 text-sm font-semibold text-zinc-900 dark:text-white">{{ $homeLabel }}</div>
        </div>
        <div class="{{ \App\Support\MatchTeamBoxResultPresentation::teamBoxClasses($match, 'away') }}">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">{{ __('Away') }}</span>
                @if ($def['away_rank'])
                    <span class="rounded bg-zinc-200 px-1.5 py-0.5 text-[10px] font-bold text-zinc-800 dark:bg-zinc-700 dark:text-zinc-100">
                        {{ __('Rank') }} {{ $def['away_rank'] }}
                    </span>
                @endif
            </div>
            <div class="mt-1 text-sm font-semibold text-zinc-900 dark:text-white">{{ $awayLabel }}</div>
        </div>
    </div>

    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-neutral-200/80 pt-3 dark:border-neutral-700/80">
        <div class="font-mono text-sm tabular-nums text-zinc-800 dark:text-zinc-200">
            @if ($hasScoreline)
                <span class="font-semibold">{{ (int) $match->home_score }}</span>
                <span class="mx-1 text-zinc-400">—</span>
                <span class="font-semibold">{{ (int) $match->away_score }}</span>
            @else
                <span class="text-zinc-500 dark:text-zinc-400">{{ __('Score pending') }}</span>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($bothTeamsAssigned)
                <a
                    href="{{ $scoringUrl }}"
                    class="inline-flex items-center justify-center rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-800 shadow-sm transition hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800"
                >
                    @if ($completed)
                        {{ __('View scoring') }}
                    @elseif ($canEnterScores)
                        {{ __('Open scoring') }}
                    @else
                        {{ __('View match') }}
                    @endif
                </a>
            @else
                <span class="inline-flex items-center rounded-lg border border-dashed border-zinc-300 bg-zinc-50 px-3 py-1.5 text-xs font-medium text-zinc-500 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-400">
                    {{ __('Teams not assigned yet') }}
                </span>
            @endif
        </div>
    </div>

    <div class="mt-2 text-[11px] uppercase tracking-wide text-zinc-400 dark:text-zinc-500">
        {{ __('Stage') }}: {{ $match->stage }}
    </div>
</article>
