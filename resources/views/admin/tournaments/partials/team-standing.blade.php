@php
    $meta = $teamStandingMeta ?? null;
    $standingsStatus = is_array($meta) ? ($meta['standings_status'] ?? 'none') : 'none';
    $showProvisionalChrome = $standingsStatus === 'provisional' && (($meta['round_robin_matches_total'] ?? 0) > 0);
@endphp
<section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">
                    @if ($showProvisionalChrome)
                        {{ __('Current Standings — Round Robin still in progress') }}
                    @else
                        {{ __('Team Standing') }}
                    @endif
                </h2>
                @if ($showProvisionalChrome)
                    <span class="inline-flex items-center rounded-md border border-amber-300/90 bg-amber-100/90 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-amber-950 dark:border-amber-700/80 dark:bg-amber-950/50 dark:text-amber-100">
                        {{ __('Provisional') }}
                    </span>
                @endif
            </div>
            <p class="mt-1 max-w-3xl text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('Totals from completed round robin and pool/group games only — knockout or placement games never affect this table. Primary order is rank score (wins minus losses). If exactly two teams share a rank score, the next sort is head-to-head from their completed direct pool game (even when goals or point differential differ). If three or more teams share a rank score, head-to-head is not used; those teams are ordered by accumulated points, then score difference, then points against. Advancing count comes from this tournament’s setting or inferred playoff size. Until every scheduled Round Robin game is completed, standings and cutoff notes stay provisional.') }}
            </p>
        </div>
    </div>

    @if ($teamStandingRows->isEmpty())
        <div class="mt-4 rounded-lg border border-dashed border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-950/60 dark:text-zinc-300">
            {{ __('No teams are registered for this tournament yet.') }}
        </div>
    @else
        @if ($showProvisionalChrome && filled($meta['standings_confirmation_note'] ?? null))
            <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50/95 px-3 py-2 text-sm text-amber-950 dark:border-amber-900/55 dark:bg-amber-950/40 dark:text-amber-50">
                {{ $meta['standings_confirmation_note'] }}
            </p>
        @endif
        @if ($showProvisionalChrome && filled($meta['round_robin_progress_note'] ?? null))
            <p class="mt-2 rounded-lg border border-amber-200 bg-amber-50/90 px-3 py-2 text-sm text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/35 dark:text-amber-100">
                {{ $meta['round_robin_progress_note'] }}
            </p>
        @endif
        @if ($showProvisionalChrome && filled($meta['provisional_cutoff_summary'] ?? null))
            <p class="mt-2 rounded-lg border border-amber-200 bg-amber-50/90 px-3 py-2 text-sm text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/35 dark:text-amber-100">
                {{ $meta['provisional_cutoff_summary'] }}
            </p>
        @endif
        @if (is_array($meta) && ($meta['applies_elimination'] ?? false) && filled($meta['advancement_summary'] ?? null))
            <p class="mt-4 rounded-lg border border-rose-200 bg-rose-50/90 px-3 py-2 text-sm text-rose-900 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-100">
                {{ $meta['advancement_summary'] }}
            </p>
        @endif
        <div class="mt-5 overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-700">
            <table class="min-w-[60rem] w-full text-left text-sm">
                <thead class="border-b border-neutral-200 bg-zinc-50 text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:border-neutral-700 dark:bg-zinc-900 dark:text-zinc-400">
                    <tr>
                        <th class="whitespace-nowrap px-4 py-2.5">{{ __('Seed / Number') }}</th>
                        <th class="px-4 py-2.5">{{ __('Team') }}</th>
                        <th class="whitespace-nowrap px-4 py-2.5">{{ __('Wins') }}</th>
                        <th class="whitespace-nowrap px-4 py-2.5">{{ __('Losses') }}</th>
                        <th class="whitespace-nowrap px-4 py-2.5" title="{{ __('Wins minus losses; primary sort key.') }}">{{ __('Rank score') }}</th>
                        <th class="whitespace-nowrap px-4 py-2.5">{{ __('Point differential') }}</th>
                        <th class="whitespace-nowrap px-4 py-2.5">{{ __('Accumulated score') }}</th>
                        <th class="whitespace-nowrap px-4 py-2.5">{{ __('Rank') }}</th>
                        <th class="min-w-[11rem] px-4 py-2.5">{{ __('Notes') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                    @foreach ($teamStandingRows as $row)
                        @php
                            $tb = data_get($row, 'tiebreaker');
                            $tbType = is_array($tb) ? (string) ($tb['tiebreaker_type'] ?? $tb['type'] ?? '') : '';
                            $tbResult = is_array($tb) ? (string) ($tb['tiebreaker_result'] ?? $tb['result'] ?? '') : '';
                            $tbNote = '';
                            if (is_array($tb)) {
                                $tbNote = (string) (data_get($tb, 'tiebreaker_note') ?: data_get($tb, 'note') ?: '');
                            }
                            if ($tbNote === '') {
                                $tbNote = (string) (data_get($row, 'tiebreaker_note') ?: data_get($row, 'remarks') ?: data_get($row, 'note') ?: '');
                            }
                            $tbBadge = is_array($tb) ? data_get($tb, 'badge') : null;
                            $isH2hWon = $tbType === 'head_to_head' && $tbResult === 'won';
                            $isH2hLost = $tbType === 'head_to_head' && $tbResult === 'lost';
                            $isMultiPts = $tbType === 'accumulated_points_multi';
                            $isSecondaryTie = $tbType === 'tie_secondary';
                            $isElim = (bool) ($row['is_eliminated'] ?? false);
                            $isProvBelow = (bool) ($row['is_provisional_below_cutoff'] ?? false);
                            $h2hTooltip = __('Exactly two teams tied on rank score; order follows their decisive completed direct round robin or pool game.');
                            $multiTooltip = __('Three or more teams tied on rank score; order uses accumulated points, then score difference, then points against (not head-to-head).');
                        @endphp
                        <tr
                            @class([
                                'border-l-4 border-l-transparent' => true,
                                'bg-rose-50/90 border-l-rose-500 dark:bg-rose-950/35 dark:border-l-rose-400' => $isElim,
                                'bg-amber-50/90 border-l-amber-500 dark:bg-amber-950/35 dark:border-l-amber-400' => ! $isElim && $isProvBelow,
                                'bg-emerald-50/90 border-l-emerald-500 dark:bg-emerald-950/35 dark:border-l-emerald-400' => ! $isElim && ! $isProvBelow && $isH2hWon,
                                'bg-zinc-50/90 border-l-zinc-300 dark:bg-zinc-900/80 dark:border-l-zinc-600' => ! $isElim && ! $isProvBelow && $isH2hLost,
                                'bg-sky-50/80 border-l-sky-400 dark:bg-sky-950/30 dark:border-l-sky-500' => ! $isElim && ! $isProvBelow && $isMultiPts,
                                'bg-violet-50/85 border-l-violet-400 dark:bg-violet-950/35 dark:border-l-violet-500' => ! $isElim && ! $isProvBelow && $isSecondaryTie,
                                'bg-white dark:bg-zinc-900' => ! $isElim && ! $isProvBelow && ! $isH2hWon && ! $isH2hLost && ! $isMultiPts && ! $isSecondaryTie,
                            ])
                            @if ($tbType === 'head_to_head')
                                title="{{ $h2hTooltip }}"
                            @elseif ($isMultiPts)
                                title="{{ $multiTooltip }}"
                            @elseif ($isSecondaryTie)
                                title="{{ __('Two-way rank score tie without a decisive completed round-robin match; order follows secondary stats.') }}"
                            @endif
                        >
                            <td class="whitespace-nowrap px-4 py-2.5 font-medium text-zinc-900 dark:text-white">{{ $row['seed_display'] }}</td>
                            <td class="px-4 py-2.5 text-zinc-800 dark:text-zinc-100">{{ $row['team_name'] }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-zinc-600 dark:text-zinc-300">{{ $row['wins'] }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-zinc-600 dark:text-zinc-300">{{ $row['losses'] }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 font-medium tabular-nums text-zinc-800 dark:text-zinc-200">{{ $row['rank_score'] ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-zinc-600 dark:text-zinc-300">{{ $row['point_differential'] }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-zinc-600 dark:text-zinc-300">{{ $row['accumulated_score'] }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-zinc-600 dark:text-zinc-300">{{ $row['rank_display'] }}</td>
                            <td class="px-4 py-2.5 align-top text-zinc-600 dark:text-zinc-300">
                                @php
                                    $hasTb = filled($tbNote)
                                        || (is_array($tb) && filled($tbBadge))
                                        || $isSecondaryTie;
                                    $hasFinalElim = $isElim && filled($row['elimination_note'] ?? null);
                                    $hasProvNote = $isProvBelow && filled($row['elimination_note'] ?? null);
                                @endphp
                                @if ($hasTb || $hasFinalElim || $hasProvNote)
                                    <div class="flex max-w-xs flex-col gap-1.5">
                                        @if ($hasTb)
                                            <span
                                                @class([
                                                    'inline-flex w-fit items-center rounded-md px-2 py-0.5 text-xs font-medium',
                                                    'bg-emerald-600/15 text-emerald-900 dark:bg-emerald-400/15 dark:text-emerald-100' => $isH2hWon,
                                                    'bg-zinc-500/10 text-zinc-600 dark:bg-zinc-500/15 dark:text-zinc-400' => $isH2hLost,
                                                    'bg-sky-600/15 text-sky-900 dark:bg-sky-400/15 dark:text-sky-100' => $isMultiPts,
                                                    'bg-violet-600/15 text-violet-900 dark:bg-violet-400/15 dark:text-violet-100' => $isSecondaryTie,
                                                ])
                                            >
                                                {{ $tbBadge ?? __('Tiebreaker') }}
                                            </span>
                                            @if (filled($tbNote))
                                                <span class="text-xs leading-snug text-zinc-500 dark:text-zinc-400">{{ $tbNote }}</span>
                                            @endif
                                        @endif
                                        @if ($hasFinalElim)
                                            <span class="inline-flex w-fit items-center rounded-md border border-rose-300/80 bg-rose-100/90 px-2 py-0.5 text-xs font-medium text-rose-900 dark:border-rose-700/80 dark:bg-rose-900/50 dark:text-rose-100">
                                                {{ $row['elimination_note'] }}
                                            </span>
                                            @if (filled($row['advancement_note'] ?? null))
                                                <span class="text-xs leading-snug text-rose-700/90 dark:text-rose-300/90">{{ $row['advancement_note'] }}</span>
                                            @endif
                                        @endif
                                        @if ($hasProvNote)
                                            <span class="inline-flex w-fit items-center rounded-md border border-amber-300/80 bg-amber-100/90 px-2 py-0.5 text-xs font-medium text-amber-950 dark:border-amber-700/80 dark:bg-amber-900/45 dark:text-amber-100">
                                                {{ $row['elimination_note'] }}
                                            </span>
                                            @if (filled($row['advancement_note'] ?? null))
                                                <span class="text-xs leading-snug text-amber-900/90 dark:text-amber-200/90">{{ $row['advancement_note'] }}</span>
                                            @endif
                                        @endif
                                    </div>
                                @else
                                    <span class="text-zinc-400 dark:text-zinc-600">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
