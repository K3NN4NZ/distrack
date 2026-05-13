<section class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-zinc-900">
    <div class="space-y-6 p-6 sm:p-8">
        @if ($errors->has('small_day2_schedule'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
                {{ $errors->first('small_day2_schedule') }}
            </div>
        @endif

        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-neutral-200 bg-zinc-50 px-4 py-3 dark:border-neutral-700 dark:bg-zinc-950">
                <div class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500 dark:text-zinc-400">{{ __('Teams') }}</div>
                <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-white">{{ $teamCount }}</div>
            </div>
            <div class="rounded-xl border border-neutral-200 bg-zinc-50 px-4 py-3 dark:border-neutral-700 dark:bg-zinc-950">
                <div class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500 dark:text-zinc-400">{{ __('Pitches') }}</div>
                <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-white">{{ $pitchCount }}</div>
                <div class="mt-1 text-[11px] text-zinc-500 dark:text-zinc-400">{{ __('Day 2 continues on the same Pitch 1 & Pitch 2 as Day 1.') }}</div>
            </div>
            <div class="rounded-xl border border-neutral-200 bg-zinc-50 px-4 py-3 dark:border-neutral-700 dark:bg-zinc-950">
                <div class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500 dark:text-zinc-400">{{ __('Tracked Day 2 games') }}</div>
                <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-white">
                    {{ $selectedTournament->matches->filter(fn ($m) => $m->stage === 'round_robin' && \App\Support\SmallFixedRoundRobinDayTwoSchedule::isTrackedMatch($m))->count() }}
                </div>
            </div>
        </div>

        @php
            /** @var array<string, string> POST values (normalized server-side to matches.status). */
            $smallDayTwoRowStatusOptions = [
                'upcoming' => __('Upcoming'),
                'live' => __('Live'),
                'completed' => __('Completed'),
            ];
        @endphp

        <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
            <div class="border-b border-neutral-200 bg-gradient-to-br from-zinc-50 to-white px-6 py-6 text-center dark:border-neutral-700 dark:from-zinc-900 dark:to-zinc-950">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500 dark:text-zinc-400">{{ __('Round Robin') }}</p>
                <h2 class="mt-3 text-3xl font-bold uppercase tracking-[0.06em] text-zinc-900 dark:text-white">{{ __('DAY 2') }}</h2>
                <p class="mt-2 text-lg font-semibold text-zinc-700 dark:text-zinc-200">{{ \App\Support\SmallFixedRoundRobinDayTwoSchedule::SCHEDULE_DATE_LABEL }}</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[56rem] w-full text-left text-sm">
                <thead class="border-b border-neutral-200 bg-zinc-50 text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:border-neutral-700 dark:bg-zinc-900 dark:text-zinc-400">
                    <tr>
                        <th class="whitespace-nowrap px-4 py-2.5">{{ __('Time') }}</th>
                        <th class="px-4 py-2.5">{{ __('Pitch 1') }}</th>
                        <th class="whitespace-nowrap px-4 py-2.5">{{ __('Game No.') }}</th>
                        <th class="px-4 py-2.5">{{ __('Pitch 2') }}</th>
                        <th class="whitespace-nowrap px-4 py-2.5">{{ __('Game No.') }}</th>
                        <th class="whitespace-nowrap px-4 py-2.5">{{ __('Round') }}</th>
                        <th class="whitespace-nowrap px-4 py-2.5">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @foreach ($smallDayTwoGridRows as $row)
                    @php
                        $p1 = $row['pitch1_match'];
                        $p2 = $row['pitch2_match'];
                        $rowStatuses = collect([$p1?->status, $p2?->status])->filter()->unique()->values();
                        $rowStatusUnified = $rowStatuses->count() === 1 ? $rowStatuses->first() : null;
                        $rowUiStatus = match ($rowStatusUnified) {
                            'scheduled' => 'upcoming',
                            'live' => 'live',
                            'completed' => 'completed',
                            default => null,
                        };
                    @endphp
                    <tr class="bg-white dark:bg-zinc-900">
                        <td class="whitespace-nowrap px-4 py-2.5 font-medium text-zinc-900 dark:text-white">{{ $row['time_label'] }}</td>
                        <td class="max-w-[14rem] px-4 py-2.5 text-xs text-zinc-700 dark:text-zinc-200">{{ $row['pitch1_matchup'] }}</td>
                        <td class="whitespace-nowrap px-4 py-2.5 tabular-nums text-zinc-600 dark:text-zinc-300">{{ $row['pitch1_game_no'] }}</td>
                        <td class="max-w-[14rem] px-4 py-2.5 text-xs text-zinc-700 dark:text-zinc-200">{{ $row['pitch2_matchup'] }}</td>
                        <td class="whitespace-nowrap px-4 py-2.5 tabular-nums text-zinc-600 dark:text-zinc-300">{{ $row['pitch2_game_no'] }}</td>
                        <td class="whitespace-nowrap px-4 py-2.5 text-zinc-600 dark:text-zinc-300">{{ $row['round'] }}</td>
                        <td class="max-w-[14rem] px-4 py-2.5 align-top">
                            @if ($p1 || $p2)
                                <div class="flex flex-col gap-1.5">
                                    <form
                                        method="POST"
                                        action="{{ route('admin.tournaments.matches.small-day2-slot-status.update', $selectedTournament) }}"
                                        class="flex flex-wrap items-center gap-1"
                                    >
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="redirect_route" value="admin.tournaments.index">
                                        <input type="hidden" name="redirect_tab" value="round-robin">
                                        <input type="hidden" name="round" value="{{ $row['round'] }}">
                                        <label class="sr-only">{{ __('Row status for round :round (Upcoming, Live, or Completed)', ['round' => $row['round']]) }}</label>
                                        <select
                                            name="status"
                                            required
                                            class="h-8 max-w-[10rem] rounded-md border border-neutral-300 bg-white px-1.5 text-[11px] font-medium text-zinc-800 shadow-sm dark:border-neutral-600 dark:bg-zinc-950 dark:text-zinc-100"
                                            @disabled(! $isAdmin)
                                            onchange="(() => { const f = this.closest('form'); if (! f) return; if (typeof f.requestSubmit === 'function') { f.requestSubmit(); } else { f.submit(); } })()"
                                        >
                                            @if ($rowUiStatus === null)
                                                <option value="" disabled selected>{{ __('Mixed — choose row status') }}</option>
                                            @endif
                                            @foreach ($smallDayTwoRowStatusOptions as $value => $label)
                                                <option value="{{ $value }}" @selected($rowUiStatus === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                    <div class="flex flex-wrap gap-x-2 gap-y-0.5 text-[11px]">
                                        @if ($p1 && $canEnterScores && $p1->home_registration_id && $p1->away_registration_id)
                                            <a
                                                href="{{ route('admin.tournaments.matches.scoring', ['tournament' => $selectedTournament, 'match' => $p1]) }}"
                                                wire:navigate
                                                class="font-semibold text-[#2f55b7] hover:underline dark:text-blue-300"
                                            >
                                                {{ __('Pitch 1 — Score') }}
                                            </a>
                                        @endif
                                        @if ($p1 && $isAdmin)
                                            <flux:modal.trigger name="setup-edit-round-robin-match-modal-{{ $p1->id }}">
                                                <button type="button" class="font-semibold text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                                                    {{ __('Pitch 1 — Edit') }}
                                                </button>
                                            </flux:modal.trigger>
                                        @endif
                                        @if ($p2 && $canEnterScores && $p2->home_registration_id && $p2->away_registration_id)
                                            <a
                                                href="{{ route('admin.tournaments.matches.scoring', ['tournament' => $selectedTournament, 'match' => $p2]) }}"
                                                wire:navigate
                                                class="font-semibold text-[#2f55b7] hover:underline dark:text-blue-300"
                                            >
                                                {{ __('Pitch 2 — Score') }}
                                            </a>
                                        @endif
                                        @if ($p2 && $isAdmin)
                                            <flux:modal.trigger name="setup-edit-round-robin-match-modal-{{ $p2->id }}">
                                                <button type="button" class="font-semibold text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                                                    {{ __('Pitch 2 — Edit') }}
                                                </button>
                                            </flux:modal.trigger>
                                        @endif
                                    </div>
                                </div>
                            @else
                                <span class="text-[11px] text-zinc-400 dark:text-zinc-500">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
                </table>
            </div>
        </div>

        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('Fixed Day 2 schedule') }}</p>
                <p class="mt-2 max-w-3xl text-sm text-zinc-600 dark:text-zinc-300">
                    {{ __('Six rounds on Pitch 1 and Pitch 2 (12 games, numbered 25–36). These remain Round Robin games and continue to count toward Team Standing alongside Day 1. Quarter Finals stay locked until every Day 1 and Day 2 row is marked Completed.') }}
                </p>
                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('Times use the tournament timezone (:tz).', ['tz' => \App\Support\SmallFixedRoundRobinDayOneSchedule::tournamentTimezone($selectedTournament)]) }}
                </p>
            </div>

            @if ($isAdmin)
                <form method="POST" action="{{ route('admin.tournaments.matches.small-day2-schedule.sync', $selectedTournament) }}" class="shrink-0">
                    @csrf
                    <input type="hidden" name="redirect_route" value="admin.tournaments.index">
                    <input type="hidden" name="redirect_tab" value="round-robin">
                    <flux:button type="submit" variant="primary" :disabled="$teamCount === 0">
                        {{ __('Sync Day 2 schedule to database') }}
                    </flux:button>
                </form>
            @endif
        </div>
    </div>
</section>
