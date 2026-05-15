@php
    $dayNum = (int) ($dayNum ?? 1);
    $dayTitle = $dayTitle ?? ($dayNum === 1 ? __('DAY 1') : __('DAY 2'));
    $dayDateLabel = $dayDateLabel ?? '—';
    $slotRows = $slotRows ?? collect();
    $removedRows = $removedRows ?? collect();
    $roundRobinPitchOptions = $roundRobinPitchOptions ?? collect();
    $roundRobinRegistrationOptions = $roundRobinRegistrationOptions ?? collect();
    $tournamentTimezone = $tournamentTimezone ?? \App\Support\ManualRoundRobinSchedule::tournamentTimezone($selectedTournament);
    $addDefaults = $addDefaults ?? \App\Support\ManualRoundRobinSchedule::defaultAddSlotForm($selectedTournament);
    $rrSlotErrors = collect($errors->getMessages())->filter(fn ($_, $k) => str_starts_with((string) $k, 'rr_slot.'));
    $smallDayOneRowStatusOptions = [
        'upcoming' => __('Upcoming'),
        'live' => __('Live'),
        'completed' => __('Completed'),
    ];
@endphp

<section class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-zinc-900">
    <div class="space-y-6 p-6 sm:p-8">
        @if ($rrSlotErrors->isNotEmpty())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
                <p class="font-semibold">{{ __('Fix the schedule fields, then try again.') }}</p>
                <ul class="mt-2 list-inside list-disc space-y-0.5">
                    @foreach ($rrSlotErrors as $messages)
                        @foreach ($messages as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($errors->has('schedule_row'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
                {{ $errors->first('schedule_row') }}
            </div>
        @endif

        @if ($errors->has('status'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
                {{ $errors->first('status') }}
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
                <div class="mt-1 text-[11px] text-zinc-500 dark:text-zinc-400">{{ __('Pitch options follow your tournament field list (active pitches first).') }}</div>
            </div>
            <div class="rounded-xl border border-neutral-200 bg-zinc-50 px-4 py-3 dark:border-neutral-700 dark:bg-zinc-950">
                <div class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500 dark:text-zinc-400">{{ __('Day :day games', ['day' => $dayNum]) }}</div>
                <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-white">
                    {{ $slotRows->sum(fn ($r) => collect([$r['pitch1_match'] ?? null, $r['pitch2_match'] ?? null])->filter()->count()) }}
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
            <div class="border-b border-neutral-200 bg-gradient-to-br from-zinc-50 to-white px-6 py-6 text-center dark:border-neutral-700 dark:from-zinc-900 dark:to-zinc-950">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500 dark:text-zinc-400">{{ __('Round Robin') }}</p>
                <h2 class="mt-3 text-3xl font-bold uppercase tracking-[0.06em] text-zinc-900 dark:text-white">{{ $dayTitle }}</h2>
                <p class="mt-2 text-lg font-semibold text-zinc-700 dark:text-zinc-200">{{ $dayDateLabel }}</p>
            </div>

            @if ($isAdmin)
                <div class="flex flex-wrap items-center gap-2 border-b border-neutral-200 bg-zinc-50/80 px-4 py-3 dark:border-neutral-700 dark:bg-zinc-900/50">
                    <flux:modal.trigger name="manual-rr-day-{{ $dayNum }}-add-schedule-slot-modal">
                        <flux:button type="button" variant="outline" size="sm">
                            {{ __('+ Add Schedule') }}
                        </flux:button>
                    </flux:modal.trigger>
                </div>
            @endif

            @if ($slotRows->isEmpty())
                <div class="px-6 py-10 text-center text-sm text-zinc-600 dark:text-zinc-300">
                    {{ __('No Round Robin schedule yet. Click + Add Schedule to create games.') }}
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-[72rem] w-full text-left text-sm">
                        <thead class="border-b border-neutral-200 bg-zinc-50 text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:border-neutral-700 dark:bg-zinc-900 dark:text-zinc-400">
                            <tr>
                                <th class="whitespace-nowrap px-3 py-2.5">{{ __('Start') }}</th>
                                <th class="whitespace-nowrap px-3 py-2.5">{{ __('End') }}</th>
                                <th class="whitespace-nowrap px-3 py-2.5">{{ __('Round') }}</th>
                                <th class="whitespace-nowrap px-3 py-2.5">{{ __('Game A') }}</th>
                                <th class="whitespace-nowrap px-3 py-2.5">{{ __('Pitch') }}</th>
                                <th class="min-w-[7rem] px-3 py-2.5">{{ __('Home') }}</th>
                                <th class="min-w-[7rem] px-3 py-2.5">{{ __('Away') }}</th>
                                <th class="whitespace-nowrap px-3 py-2.5">{{ __('Game B') }}</th>
                                <th class="whitespace-nowrap px-3 py-2.5">{{ __('Pitch') }}</th>
                                <th class="min-w-[7rem] px-3 py-2.5">{{ __('Home') }}</th>
                                <th class="min-w-[7rem] px-3 py-2.5">{{ __('Away') }}</th>
                                <th class="whitespace-nowrap px-3 py-2.5">{{ __('Status') }}</th>
                                <th class="whitespace-nowrap px-2 py-2.5">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @foreach ($slotRows as $row)
                                @php
                                    $p1 = $row['pitch1_match'] ?? null;
                                    $p2 = $row['pitch2_match'] ?? null;
                                    $tzRow = $tournamentTimezone;
                                    $startT = $p1?->scheduled_at?->timezone($tzRow)->format('g:i A') ?? ($p2?->scheduled_at?->timezone($tzRow)->format('g:i A') ?? '—');
                                    $endT = $p1?->scheduled_ends_at?->timezone($tzRow)->format('g:i A') ?? ($p2?->scheduled_ends_at?->timezone($tzRow)->format('g:i A') ?? '—');
                                    $slotKey = $row['slot_route_key'] ?? '';
                                @endphp
                                <tr class="bg-white dark:bg-zinc-900">
                                    <td class="whitespace-nowrap px-3 py-2.5 text-zinc-900 dark:text-white">{{ $startT }}</td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-zinc-900 dark:text-white">{{ $endT }}</td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-zinc-600 dark:text-zinc-300">{{ $row['round_label'] ?? $row['round'] ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-3 py-2.5 tabular-nums text-zinc-600 dark:text-zinc-300">{{ $row['pitch1_game_no'] ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-xs text-zinc-600 dark:text-zinc-300">{{ $p1?->pitch?->name ?? '—' }}</td>
                                    <td class="max-w-[10rem] px-3 py-2.5 text-xs text-zinc-700 dark:text-zinc-200">{{ $p1?->homeRegistration?->team?->name ?? '—' }}</td>
                                    <td class="max-w-[10rem] px-3 py-2.5 text-xs text-zinc-700 dark:text-zinc-200">{{ $p1?->awayRegistration?->team?->name ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-3 py-2.5 tabular-nums text-zinc-600 dark:text-zinc-300">{{ $row['pitch2_game_no'] ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-xs text-zinc-600 dark:text-zinc-300">{{ $p2?->pitch?->name ?? '—' }}</td>
                                    <td class="max-w-[10rem] px-3 py-2.5 text-xs text-zinc-700 dark:text-zinc-200">{{ $p2?->homeRegistration?->team?->name ?? '—' }}</td>
                                    <td class="max-w-[10rem] px-3 py-2.5 text-xs text-zinc-700 dark:text-zinc-200">{{ $p2?->awayRegistration?->team?->name ?? '—' }}</td>
                                    <td class="max-w-[12rem] px-3 py-2.5 align-top">
                                        @if ($p1 || $p2)
                                            <form method="POST" action="{{ route('admin.tournaments.round-robin.schedules.status', [$selectedTournament, $slotKey]) }}" class="flex flex-wrap items-center gap-1">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="redirect_route" value="admin.tournaments.index">
                                                <input type="hidden" name="redirect_tab" value="round-robin">
                                                <label class="sr-only">{{ __('Row status') }}</label>
                                                <select
                                                    name="status"
                                                    required
                                                    class="h-8 max-w-[10rem] rounded-md border border-neutral-300 bg-white px-1.5 text-[11px] font-medium text-zinc-800 shadow-sm dark:border-neutral-600 dark:bg-zinc-950 dark:text-zinc-100"
                                                    @disabled(! $isAdmin)
                                                    onchange="(() => { const f = this.closest('form'); if (! f) return; if (typeof f.requestSubmit === 'function') { f.requestSubmit(); } else { f.submit(); } })()"
                                                >
                                                    @if (($row['row_ui_status'] ?? null) === null)
                                                        <option value="" disabled selected>{{ __('Mixed — choose row status') }}</option>
                                                    @endif
                                                    @foreach ($smallDayOneRowStatusOptions as $value => $label)
                                                        <option value="{{ $value }}" @selected(($row['row_ui_status'] ?? null) === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </form>
                                            <div class="mt-1 flex flex-wrap gap-x-2 gap-y-0.5 text-[11px]">
                                                @if ($p1 && $canEnterScores && $p1->home_registration_id && $p1->away_registration_id)
                                                    <a
                                                        href="{{ route('admin.tournaments.matches.scoring', ['tournament' => $selectedTournament, 'match' => $p1]) }}"
                                                        wire:navigate
                                                        class="font-semibold text-[#2f55b7] hover:underline dark:text-blue-300"
                                                    >{{ ($p1->pitch?->name ?? __('Game A')).' — '.__('Score') }}</a>
                                                @endif
                                                @if ($p1 && $isAdmin)
                                                    <flux:modal.trigger name="setup-edit-round-robin-match-modal-{{ $p1->id }}">
                                                        <button type="button" class="font-semibold text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                                                            {{ ($p1->pitch?->name ?? __('Game A')).' — '.__('Edit') }}
                                                        </button>
                                                    </flux:modal.trigger>
                                                @endif
                                                @if ($p2 && $canEnterScores && $p2->home_registration_id && $p2->away_registration_id)
                                                    <a
                                                        href="{{ route('admin.tournaments.matches.scoring', ['tournament' => $selectedTournament, 'match' => $p2]) }}"
                                                        wire:navigate
                                                        class="font-semibold text-[#2f55b7] hover:underline dark:text-blue-300"
                                                    >{{ ($p2->pitch?->name ?? __('Game B')).' — '.__('Score') }}</a>
                                                @endif
                                                @if ($p2 && $isAdmin)
                                                    <flux:modal.trigger name="setup-edit-round-robin-match-modal-{{ $p2->id }}">
                                                        <button type="button" class="font-semibold text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                                                            {{ ($p2->pitch?->name ?? __('Game B')).' — '.__('Edit') }}
                                                        </button>
                                                    </flux:modal.trigger>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-[11px] text-zinc-400 dark:text-zinc-500">—</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-2 py-2 align-top">
                                        @if ($isAdmin && $p1 && $p2)
                                            <div class="flex flex-col gap-1">
                                                @if ($row['removable'] ?? false)
                                                    <form
                                                        method="POST"
                                                        action="{{ route('admin.tournaments.round-robin.schedules.destroy', [$selectedTournament, $slotKey]) }}"
                                                        class="inline"
                                                        onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('Remove this schedule row? This will remove both games from the schedule.')) }});"
                                                    >
                                                        @csrf
                                                        @method('DELETE')
                                                        <input type="hidden" name="redirect_route" value="admin.tournaments.index">
                                                        <input type="hidden" name="redirect_tab" value="round-robin">
                                                        <button
                                                            type="submit"
                                                            class="inline-flex h-8 min-w-[2rem] items-center justify-center rounded-md border border-red-200 bg-red-50 px-2 text-sm font-semibold text-red-700 hover:bg-red-100 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-200 dark:hover:bg-red-950/60"
                                                            title="{{ __('Remove schedule row (both games)') }}"
                                                        >−</button>
                                                    </form>
                                                @else
                                                    <button
                                                        type="button"
                                                        disabled
                                                        class="inline-flex h-8 min-w-[2rem] cursor-not-allowed items-center justify-center rounded-md border border-zinc-200 bg-zinc-100 px-2 text-sm font-semibold text-zinc-400 dark:border-neutral-700 dark:bg-zinc-900 dark:text-zinc-500"
                                                        title="{{ __('Cannot remove this schedule row because one or more games already has scores or is in progress.') }}"
                                                    >−</button>
                                                @endif
                                                <flux:modal.trigger name="manual-rr-edit-{{ $dayNum }}-{{ $slotKey }}">
                                                    <flux:button type="button" variant="outline" size="sm">{{ __('Edit row') }}</flux:button>
                                                </flux:modal.trigger>
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
            @endif
        </div>

        @if ($isAdmin)
            @include('admin.tournaments.partials.small-round-robin-slot-row-admin-modal', [
                'tournament' => $selectedTournament,
                'tournamentTimezone' => $tournamentTimezone,
                'modalKey' => 'day-'.$dayNum.'-add',
                'slotPrefix' => 'rr_slot',
                'modalName' => 'manual-rr-day-'.$dayNum.'-add-schedule-slot-modal',
                'heading' => $dayNum === 1 ? __('Add Day 1 schedule row') : __('Add Day 2 schedule row'),
                'formAction' => route('admin.tournaments.round-robin.schedules.store', $selectedTournament),
                'httpMethod' => 'post',
                'dateLabel' => $dayDateLabel,
                'slotDateIso' => $dayDateIso ?? '',
                'pitch1Match' => null,
                'pitch2Match' => null,
                'defaults' => $addDefaults,
                'roundRobinPitchOptions' => $roundRobinPitchOptions,
                'roundRobinRegistrationOptions' => $roundRobinRegistrationOptions,
                'minMatchNumberGameA' => 1,
                'minMatchNumberGameB' => 1,
                'statusOptions' => $smallDayOneRowStatusOptions,
                'extraHiddenFields' => ['day' => (string) $dayNum],
            ])

            @foreach ($slotRows as $row)
                @php
                    $p1 = $row['pitch1_match'] ?? null;
                    $p2 = $row['pitch2_match'] ?? null;
                    $slotKey = $row['slot_route_key'] ?? '';
                @endphp
                @if ($p1 && $p2)
                    @php
                        $dayTz = $tournamentTimezone;
                        $rowSensitiveForEdit = false;
                        foreach ([$p1, $p2] as $__m) {
                            if (
                                in_array($__m->status, [\App\Models\TournamentMatch::STATUS_LIVE, \App\Models\TournamentMatch::STATUS_COMPLETED], true)
                                || $__m->home_score !== null
                                || $__m->away_score !== null
                                || $__m->scoreLogs()->exists()
                                || $__m->playerStats()->exists()
                                || $__m->spiritScores()->exists()
                            ) {
                                $rowSensitiveForEdit = true;
                                break;
                            }
                        }
                        $ers = collect([$p1->status, $p2->status])->filter()->unique()->values();
                        $editRowStatus = $ers->count() === 1 ? \App\Support\ManualRoundRobinSchedule::mapMatchStatusToRowUi((string) $ers->first()) : 'upcoming';
                        $slotEditDefaults = [
                            'round' => (int) ($row['round'] ?? 1),
                            'start_time' => $p1->scheduled_at?->timezone($dayTz)->format('H:i') ?? '07:00',
                            'end_time' => $p1->scheduled_ends_at?->timezone($dayTz)->format('H:i') ?? '07:40',
                            'match1_match_number' => (int) ($p1->match_number ?? 0),
                            'match2_match_number' => (int) ($p2->match_number ?? 0),
                            'pitch1_pitch_id' => (int) ($p1->pitch_id ?? 0),
                            'pitch2_pitch_id' => (int) ($p2->pitch_id ?? 0),
                            'match1_home_registration_id' => (int) ($p1->home_registration_id ?? 0),
                            'match1_away_registration_id' => (int) ($p1->away_registration_id ?? 0),
                            'match2_home_registration_id' => (int) ($p2->home_registration_id ?? 0),
                            'match2_away_registration_id' => (int) ($p2->away_registration_id ?? 0),
                            'status' => $editRowStatus,
                        ];
                    @endphp
                    @include('admin.tournaments.partials.small-round-robin-slot-row-admin-modal', [
                        'tournament' => $selectedTournament,
                        'tournamentTimezone' => $tournamentTimezone,
                        'modalKey' => 'day-'.$dayNum.'-edit-'.$slotKey,
                        'slotPrefix' => 'rr_slot',
                        'modalName' => 'manual-rr-edit-'.$dayNum.'-'.$slotKey,
                        'heading' => $dayNum === 1 ? __('Edit Day 1 schedule row') : __('Edit Day 2 schedule row'),
                        'formAction' => route('admin.tournaments.round-robin.schedules.update', [$selectedTournament, $slotKey]),
                        'httpMethod' => 'patch',
                        'dateLabel' => $dayDateLabel,
                        'slotDateIso' => $dayDateIso ?? '',
                        'pitch1Match' => $p1,
                        'pitch2Match' => $p2,
                        'defaults' => $slotEditDefaults,
                        'roundRobinPitchOptions' => $roundRobinPitchOptions,
                        'roundRobinRegistrationOptions' => $roundRobinRegistrationOptions,
                        'minMatchNumberGameA' => 1,
                        'minMatchNumberGameB' => 1,
                        'statusOptions' => $smallDayOneRowStatusOptions,
                        'extraHiddenFields' => [],
                        'requiresStructuralEditConfirm' => $rowSensitiveForEdit,
                    ])
                @endif
            @endforeach
        @endif

        @if ($removedRows->isNotEmpty())
            <div class="rounded-xl border border-dashed border-neutral-300 bg-zinc-50 px-4 py-4 dark:border-neutral-700 dark:bg-zinc-950">
                <div class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Removed rows (restore)') }}</div>
                <ul class="mt-3 space-y-2 text-sm text-zinc-700 dark:text-zinc-200">
                    @foreach ($removedRows as $rrow)
                        @php
                            $k = $rrow['slot_route_key'] ?? '';
                            $t1 = $rrow['time_label'] ?? '—';
                        @endphp
                        <li class="flex flex-wrap items-center justify-between gap-2">
                            <span>{{ $t1 }} — {{ __('Games') }} {{ ($rrow['pitch1_game_no'] ?? '?') }} / {{ ($rrow['pitch2_game_no'] ?? '?') }}</span>
                            @if ($isAdmin)
                                <form method="POST" action="{{ route('admin.tournaments.round-robin.schedules.restore', [$selectedTournament, $k]) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="redirect_route" value="admin.tournaments.index">
                                    <input type="hidden" name="redirect_tab" value="round-robin">
                                    <flux:button type="submit" size="sm" variant="outline">{{ __('Restore') }}</flux:button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <p class="text-xs text-zinc-500 dark:text-zinc-400">
            {{ __('Times use the tournament timezone (:tz). Dates follow the day headers above.', ['tz' => $tournamentTimezone]) }}
        </p>
    </div>
</section>
