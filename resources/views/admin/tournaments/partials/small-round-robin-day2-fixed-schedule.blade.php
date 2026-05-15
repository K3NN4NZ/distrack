@php
    $day2ScheduleRowErrors = collect($errors->getMessages())->filter(fn ($_, $k) => str_starts_with((string) $k, 'day2_rows.'));
    $day2SlotRowErrors = collect($errors->getMessages())->filter(fn ($_, $k) => str_starts_with((string) $k, 'day2_slot.'));
    $day2ScheduleUnsynced = $day2ScheduleUnsynced ?? false;
    $day2AttachToDay1Form = $day2AttachToDay1Form ?? false;
    $roundRobinPitchOptions = $roundRobinPitchOptions ?? collect();
    $roundRobinRegistrationOptions = $roundRobinRegistrationOptions ?? collect();
    $smallDay2FormDefaults = $smallDay2FormDefaults ?? [];
    $day2DefaultScheduleRowCount = $day2DefaultScheduleRowCount ?? count($smallDay2FormDefaults);
    $day2ScheduleFormRowCount = $day2ScheduleFormRowCount ?? ($smallDayTwoGridRows ?? collect())->count();
    $day2FormAttr = ($day2ScheduleUnsynced && $isAdmin && $day2AttachToDay1Form) ? 'small-day1-schedule-sync-form' : null;
    $firstRobinPitchId = optional($roundRobinPitchOptions->first())->id;
    $secondRobinPitchId = optional($roundRobinPitchOptions->skip(1)->first())->id ?? $firstRobinPitchId;
    $removedDayTwoScheduleGroups = $removedDayTwoScheduleGroups ?? collect();
@endphp

<section class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-zinc-900">
    <div class="space-y-6 p-6 sm:p-8">
        @if ($day2SlotRowErrors->isNotEmpty())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
                <p class="font-semibold">{{ __('Fix the Day 2 add/edit schedule fields, then try again.') }}</p>
                <ul class="mt-2 list-inside list-disc space-y-0.5">
                    @foreach ($day2SlotRowErrors as $messages)
                        @foreach ($messages as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($errors->has('small_day2_schedule'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
                {{ $errors->first('small_day2_schedule') }}
            </div>
        @endif

        @if ($errors->has('schedule_row'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
                {{ $errors->first('schedule_row') }}
            </div>
        @endif

        @if ($day2ScheduleRowErrors->isNotEmpty())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
                <p class="font-semibold">{{ __('Fix the Day 2 schedule fields below, then sync again.') }}</p>
                <ul class="mt-2 list-inside list-disc space-y-0.5">
                    @foreach ($day2ScheduleRowErrors as $messages)
                        @foreach ($messages as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    @endforeach
                </ul>
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
                <div class="mt-1 text-[11px] text-zinc-500 dark:text-zinc-400">{{ __('Uses the same tournament pitches as Day 1; pick a pitch per game in each row.') }}</div>
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

        @if ($day2ScheduleUnsynced && $isAdmin && ! ($day2AttachToDay1Form ?? false))
            <form
                id="small-day2-schedule-sync-form"
                method="POST"
                action="{{ route('admin.tournaments.matches.small-day2-schedule.sync', $selectedTournament) }}"
                class="space-y-6"
                onsubmit="return (typeof window.confirmSmallDay2ScheduleSyncSubmit === 'function' ? window.confirmSmallDay2ScheduleSyncSubmit(this) : true)"
            >
                @csrf
                <input type="hidden" name="small_day2_sync_from_grid" value="1">
                <input type="hidden" name="redirect_route" value="admin.tournaments.index">
                <input type="hidden" name="redirect_tab" value="round-robin">
        @endif

        <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
            <div class="border-b border-neutral-200 bg-gradient-to-br from-zinc-50 to-white px-6 py-6 text-center dark:border-neutral-700 dark:from-zinc-900 dark:to-zinc-950">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500 dark:text-zinc-400">{{ __('Round Robin') }}</p>
                <h2 class="mt-3 text-3xl font-bold uppercase tracking-[0.06em] text-zinc-900 dark:text-white">{{ __('DAY 2') }}</h2>
                <p class="mt-2 text-lg font-semibold text-zinc-700 dark:text-zinc-200">{{ \App\Support\SmallFixedRoundRobinDayTwoSchedule::SCHEDULE_DATE_LABEL }}</p>
            </div>

            @if ($isAdmin)
                <div class="flex flex-wrap items-center gap-2 border-b border-neutral-200 bg-zinc-50/80 px-4 py-3 dark:border-neutral-700 dark:bg-zinc-900/50">
                    @if ($day2ScheduleUnsynced)
                        <flux:button type="button" variant="outline" size="sm" onclick="window.addSmallDay2ScheduleRow?.()">
                            {{ __('+ Add Schedule') }}
                        </flux:button>
                    @elseif (\App\Support\SmallFixedRoundRobinDayTwoSchedule::dayTwoRoundSlotCount($selectedTournament) > 0)
                        <flux:modal.trigger name="small-day2-add-schedule-slot-modal">
                            <flux:button type="button" variant="outline" size="sm">
                                {{ __('+ Add Schedule') }}
                            </flux:button>
                        </flux:modal.trigger>
                    @endif
                </div>
            @endif

            <div class="overflow-x-auto">
                <table class="{{ $day2ScheduleUnsynced && $isAdmin ? 'min-w-[106rem]' : 'min-w-[60rem]' }} w-full text-left text-sm">
                <thead class="border-b border-neutral-200 bg-zinc-50 text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:border-neutral-700 dark:bg-zinc-900 dark:text-zinc-400">
                    <tr>
                        @if ($day2ScheduleUnsynced && $isAdmin)
                            <th class="whitespace-nowrap px-2 py-2.5">{{ __('Start') }}</th>
                            <th class="whitespace-nowrap px-2 py-2.5">{{ __('End') }}</th>
                            <th class="whitespace-nowrap px-2 py-2.5">{{ __('Round') }}</th>
                            <th class="whitespace-nowrap px-2 py-2.5">{{ __('No.') }}</th>
                            <th class="min-w-[6rem] px-2 py-2.5">{{ __('Pitch') }}</th>
                            <th class="min-w-[7rem] px-2 py-2.5">{{ __('Home') }}</th>
                            <th class="min-w-[7rem] px-2 py-2.5">{{ __('Away') }}</th>
                            <th class="whitespace-nowrap px-2 py-2.5">{{ __('No.') }}</th>
                            <th class="min-w-[6rem] px-2 py-2.5">{{ __('Pitch') }}</th>
                            <th class="min-w-[7rem] px-2 py-2.5">{{ __('Home') }}</th>
                            <th class="min-w-[7rem] px-2 py-2.5">{{ __('Away') }}</th>
                            <th class="whitespace-nowrap px-2 py-2.5">{{ __('Status') }}</th>
                            <th class="whitespace-nowrap px-2 py-2.5">{{ __('Actions') }}</th>
                        @else
                            <th class="whitespace-nowrap px-4 py-2.5">{{ __('Time') }}</th>
                            <th class="px-4 py-2.5">{{ __('Game A') }}</th>
                            <th class="whitespace-nowrap px-4 py-2.5">{{ __('Game No.') }}</th>
                            <th class="px-4 py-2.5">{{ __('Game B') }}</th>
                            <th class="whitespace-nowrap px-4 py-2.5">{{ __('Game No.') }}</th>
                            <th class="whitespace-nowrap px-4 py-2.5">{{ __('Round') }}</th>
                            <th class="whitespace-nowrap px-4 py-2.5">{{ __('Status') }}</th>
                            <th class="whitespace-nowrap px-2 py-2.5">{{ __('Actions') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody id="small-day2-schedule-tbody" class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @for ($i = 0; $i < $day2ScheduleFormRowCount; $i++)
                    @php
                        $row = $smallDayTwoGridRows->get($i) ?? [
                            'time_label' => '—',
                            'round' => \App\Support\SmallFixedRoundRobinDayTwoSchedule::FIRST_ROUND_NUMBER + $i,
                            'pitch1_game_no' => \App\Support\SmallFixedRoundRobinDayTwoSchedule::FIRST_GAME_NUMBER + ($i * 2),
                            'pitch2_game_no' => \App\Support\SmallFixedRoundRobinDayTwoSchedule::FIRST_GAME_NUMBER + ($i * 2) + 1,
                            'pitch1_matchup' => '—',
                            'pitch2_matchup' => '—',
                            'pitch1_match' => null,
                            'pitch2_match' => null,
                        ];
                        $defaults = $smallDay2FormDefaults[$i] ?? [
                            'round' => \App\Support\SmallFixedRoundRobinDayTwoSchedule::FIRST_ROUND_NUMBER + $i,
                            'start_time' => '07:00',
                            'end_time' => '07:40',
                            'pitch1_pitch_id' => null,
                            'pitch2_pitch_id' => null,
                            'match1_home_registration_id' => null,
                            'match1_away_registration_id' => null,
                            'match2_home_registration_id' => null,
                            'match2_away_registration_id' => null,
                            'status' => 'upcoming',
                            'match1_match_number' => \App\Support\SmallFixedRoundRobinDayTwoSchedule::FIRST_GAME_NUMBER + ($i * 2),
                            'match2_match_number' => \App\Support\SmallFixedRoundRobinDayTwoSchedule::FIRST_GAME_NUMBER + ($i * 2) + 1,
                        ];
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
                        $day2SyncedRowRemovable = false;
                        if (! $day2ScheduleUnsynced && $isAdmin && $p1 && $p2) {
                            $day2SyncedRowRemovable = true;
                            foreach ([$p1, $p2] as $m) {
                                if (in_array($m->status, [\App\Models\TournamentMatch::STATUS_LIVE, \App\Models\TournamentMatch::STATUS_COMPLETED], true)) {
                                    $day2SyncedRowRemovable = false;
                                    break;
                                }
                                if ($m->home_score !== null || $m->away_score !== null) {
                                    $day2SyncedRowRemovable = false;
                                    break;
                                }
                                if ($m->scoreLogs()->exists() || $m->playerStats()->exists() || $m->spiritScores()->exists()) {
                                    $day2SyncedRowRemovable = false;
                                    break;
                                }
                            }
                        }
                        $inputClass = 'h-8 w-full min-w-0 rounded-md border border-neutral-300 bg-white px-2 text-xs font-medium text-zinc-800 shadow-sm dark:border-neutral-600 dark:bg-zinc-950 dark:text-zinc-100';
                        $regLabel = static function ($reg): string {
                            if ($reg === null) {
                                return '';
                            }

                            return (string) ($reg->team?->name ?? __('Registration #:id', ['id' => $reg->id]));
                        };
                        $formAttr = $day2FormAttr;
                        $slotFirstGameNo = ($p1 && $p2) ? min((int) $row['pitch1_game_no'], (int) $row['pitch2_game_no']) : (int) ($row['pitch1_game_no'] ?: $row['pitch2_game_no']);
                    @endphp
                    <tr
                        class="bg-white dark:bg-zinc-900"
                        data-day2-row-client-id="{{ (string) str()->uuid() }}"
                        data-day2-schedule-row-source="{{ $i < $day2DefaultScheduleRowCount ? 'default' : 'added' }}"
                    >
                        @if ($day2ScheduleUnsynced && $isAdmin)
                            <td class="whitespace-nowrap px-2 py-2 align-top">
                                <input
                                    type="time"
                                    name="day2_rows[{{ $i }}][start_time]"
                                    value="{{ old('day2_rows.'.$i.'.start_time', $defaults['start_time']) }}"
                                    required
                                    class="{{ $inputClass }}"
                                    @if ($formAttr) form="{{ $formAttr }}" @endif
                                >
                            </td>
                            <td class="whitespace-nowrap px-2 py-2 align-top">
                                <input
                                    type="time"
                                    name="day2_rows[{{ $i }}][end_time]"
                                    value="{{ old('day2_rows.'.$i.'.end_time', $defaults['end_time']) }}"
                                    required
                                    class="{{ $inputClass }}"
                                    @if ($formAttr) form="{{ $formAttr }}" @endif
                                >
                            </td>
                            <td class="whitespace-nowrap px-2 py-2 align-top">
                                <input
                                    type="number"
                                    name="day2_rows[{{ $i }}][round]"
                                    min="1"
                                    required
                                    value="{{ old('day2_rows.'.$i.'.round', $defaults['round']) }}"
                                    class="{{ $inputClass }} w-16"
                                    @if ($formAttr) form="{{ $formAttr }}" @endif
                                >
                            </td>
                            <td class="whitespace-nowrap px-2 py-2 align-top">
                                <input
                                    type="number"
                                    name="day2_rows[{{ $i }}][match1_match_number]"
                                    min="{{ \App\Support\SmallFixedRoundRobinDayTwoSchedule::FIRST_GAME_NUMBER }}"
                                    required
                                    value="{{ old('day2_rows.'.$i.'.match1_match_number', $defaults['match1_match_number'] ?? $row['pitch1_game_no']) }}"
                                    class="{{ $inputClass }} w-16"
                                    @if ($formAttr) form="{{ $formAttr }}" @endif
                                >
                            </td>
                            <td class="min-w-[6rem] px-2 py-2 align-top">
                                <select name="day2_rows[{{ $i }}][pitch1_pitch_id]" required class="{{ $inputClass }}" @if ($formAttr) form="{{ $formAttr }}" @endif>
                                    @foreach ($roundRobinPitchOptions as $pitchOpt)
                                        <option
                                            value="{{ $pitchOpt->id }}"
                                            @selected((int) old('day2_rows.'.$i.'.pitch1_pitch_id', (int) ($defaults['pitch1_pitch_id'] ?? 0)) === (int) $pitchOpt->id)
                                        >{{ $pitchOpt->name }}</option>
                                    @endforeach
                                </select>
                                @error('day2_rows.'.$i.'.pitch1_pitch_id')
                                    <p class="mt-0.5 text-[11px] text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </td>
                            <td class="min-w-[7rem] px-2 py-2 align-top">
                                <select name="day2_rows[{{ $i }}][match1_home_registration_id]" required class="{{ $inputClass }}" @if ($formAttr) form="{{ $formAttr }}" @endif>
                                    @foreach ($roundRobinRegistrationOptions as $reg)
                                        <option
                                            value="{{ $reg->id }}"
                                            @selected((int) old('day2_rows.'.$i.'.match1_home_registration_id', $defaults['match1_home_registration_id'] ?? 0) === (int) $reg->id)
                                        >{{ $regLabel($reg) }}</option>
                                    @endforeach
                                </select>
                                @error('day2_rows.'.$i.'.match1_home_registration_id')
                                    <p class="mt-0.5 text-[11px] text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </td>
                            <td class="min-w-[7rem] px-2 py-2 align-top">
                                <select name="day2_rows[{{ $i }}][match1_away_registration_id]" required class="{{ $inputClass }}" @if ($formAttr) form="{{ $formAttr }}" @endif>
                                    @foreach ($roundRobinRegistrationOptions as $reg)
                                        <option
                                            value="{{ $reg->id }}"
                                            @selected((int) old('day2_rows.'.$i.'.match1_away_registration_id', $defaults['match1_away_registration_id'] ?? 0) === (int) $reg->id)
                                        >{{ $regLabel($reg) }}</option>
                                    @endforeach
                                </select>
                                @error('day2_rows.'.$i.'.match1_away_registration_id')
                                    <p class="mt-0.5 text-[11px] text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </td>
                            <td class="whitespace-nowrap px-2 py-2 align-top">
                                <input
                                    type="number"
                                    name="day2_rows[{{ $i }}][match2_match_number]"
                                    min="{{ \App\Support\SmallFixedRoundRobinDayTwoSchedule::FIRST_GAME_NUMBER }}"
                                    required
                                    value="{{ old('day2_rows.'.$i.'.match2_match_number', $defaults['match2_match_number'] ?? $row['pitch2_game_no']) }}"
                                    class="{{ $inputClass }} w-16"
                                    @if ($formAttr) form="{{ $formAttr }}" @endif
                                >
                            </td>
                            <td class="min-w-[6rem] px-2 py-2 align-top">
                                <select name="day2_rows[{{ $i }}][pitch2_pitch_id]" required class="{{ $inputClass }}" @if ($formAttr) form="{{ $formAttr }}" @endif>
                                    @foreach ($roundRobinPitchOptions as $pitchOpt)
                                        <option
                                            value="{{ $pitchOpt->id }}"
                                            @selected((int) old('day2_rows.'.$i.'.pitch2_pitch_id', (int) ($defaults['pitch2_pitch_id'] ?? 0)) === (int) $pitchOpt->id)
                                        >{{ $pitchOpt->name }}</option>
                                    @endforeach
                                </select>
                                @error('day2_rows.'.$i.'.pitch2_pitch_id')
                                    <p class="mt-0.5 text-[11px] text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </td>
                            <td class="min-w-[7rem] px-2 py-2 align-top">
                                <select name="day2_rows[{{ $i }}][match2_home_registration_id]" required class="{{ $inputClass }}" @if ($formAttr) form="{{ $formAttr }}" @endif>
                                    @foreach ($roundRobinRegistrationOptions as $reg)
                                        <option
                                            value="{{ $reg->id }}"
                                            @selected((int) old('day2_rows.'.$i.'.match2_home_registration_id', $defaults['match2_home_registration_id'] ?? 0) === (int) $reg->id)
                                        >{{ $regLabel($reg) }}</option>
                                    @endforeach
                                </select>
                                @error('day2_rows.'.$i.'.match2_home_registration_id')
                                    <p class="mt-0.5 text-[11px] text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </td>
                            <td class="min-w-[7rem] px-2 py-2 align-top">
                                <select name="day2_rows[{{ $i }}][match2_away_registration_id]" required class="{{ $inputClass }}" @if ($formAttr) form="{{ $formAttr }}" @endif>
                                    @foreach ($roundRobinRegistrationOptions as $reg)
                                        <option
                                            value="{{ $reg->id }}"
                                            @selected((int) old('day2_rows.'.$i.'.match2_away_registration_id', $defaults['match2_away_registration_id'] ?? 0) === (int) $reg->id)
                                        >{{ $regLabel($reg) }}</option>
                                    @endforeach
                                </select>
                                @error('day2_rows.'.$i.'.match2_away_registration_id')
                                    <p class="mt-0.5 text-[11px] text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </td>
                            <td class="max-w-[8rem] px-2 py-2 align-top">
                                <select name="day2_rows[{{ $i }}][status]" class="{{ $inputClass }}" @if ($formAttr) form="{{ $formAttr }}" @endif>
                                    @foreach ($smallDayTwoRowStatusOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(old('day2_rows.'.$i.'.status', $defaults['status'] ?? 'upcoming') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="whitespace-nowrap px-2 py-2 align-top">
                                <button
                                    type="button"
                                    class="inline-flex h-8 min-w-[2rem] items-center justify-center rounded-md border border-red-200 bg-red-50 px-2 text-sm font-semibold text-red-700 hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-40 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-200 dark:hover:bg-red-950/60"
                                    title="{{ __('Remove schedule row (both games)') }}"
                                    data-day2-remove-row="1"
                                    onclick="window.removeSmallDay2ScheduleRow?.(this)"
                                >−</button>
                            </td>
                        @else
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
                                            <input type="hidden" name="first_game_number" value="{{ $slotFirstGameNo }}">
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
                                                    {{ ($p1->pitch?->name ?? __('Game A')).' — '.__('Score') }}
                                                </a>
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
                                                >
                                                    {{ ($p2->pitch?->name ?? __('Game B')).' — '.__('Score') }}
                                                </a>
                                            @endif
                                            @if ($p2 && $isAdmin)
                                                <flux:modal.trigger name="setup-edit-round-robin-match-modal-{{ $p2->id }}">
                                                    <button type="button" class="font-semibold text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                                                        {{ ($p2->pitch?->name ?? __('Game B')).' — '.__('Edit') }}
                                                    </button>
                                                </flux:modal.trigger>
                                            @endif
                                        </div>
                                    </div>
                                @else
                                    <span class="text-[11px] text-zinc-400 dark:text-zinc-500">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-2 py-2 align-top">
                                @if ($isAdmin && $p1 && $p2)
                                    <div class="flex flex-col gap-1">
                                        @if ($day2SyncedRowRemovable)
                                            <form
                                                method="POST"
                                                action="{{ route('admin.tournaments.round-robin.day2-schedule-row.destroy', $selectedTournament) }}"
                                                class="inline"
                                                onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('Remove this schedule row? This will remove both games from the schedule.')) }});"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="redirect_route" value="admin.tournaments.index">
                                                <input type="hidden" name="redirect_tab" value="round-robin">
                                                <input type="hidden" name="match_numbers[]" value="{{ $row['pitch1_game_no'] }}">
                                                <input type="hidden" name="match_numbers[]" value="{{ $row['pitch2_game_no'] }}">
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
                                                title="{{ __('Cannot remove this schedule because one or more games already has scores or is in progress.') }}"
                                            >−</button>
                                        @endif
                                        <flux:modal.trigger name="small-day2-edit-schedule-slot-{{ $p1->id }}-{{ $p2->id }}">
                                            <flux:button type="button" variant="outline" size="sm">{{ __('Edit') }}</flux:button>
                                        </flux:modal.trigger>
                                    </div>
                                @else
                                    <span class="text-[11px] text-zinc-400 dark:text-zinc-500">—</span>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endfor
                </tbody>
                </table>

                @if (! $day2ScheduleUnsynced && $isAdmin && \App\Support\SmallFixedRoundRobinDayTwoSchedule::dayTwoRoundSlotCount($selectedTournament) > 0)
                    @php
                        $day2Tz = \App\Support\SmallFixedRoundRobinDayOneSchedule::tournamentTimezone($selectedTournament);
                        $day2First = \App\Support\SmallFixedRoundRobinDayTwoSchedule::FIRST_GAME_NUMBER;
                        $day2AddMaxGame = 0;
                        $day2AddMaxRound = 0;
                        foreach ($smallDayTwoGridRows as $__gr) {
                            $day2AddMaxGame = max($day2AddMaxGame, (int) ($__gr['pitch1_game_no'] ?? 0), (int) ($__gr['pitch2_game_no'] ?? 0));
                            $day2AddMaxRound = max($day2AddMaxRound, (int) ($__gr['round'] ?? 0));
                        }
                        $day2AddDefaults = [
                            'round' => $day2AddMaxRound + 1,
                            'start_time' => '07:00',
                            'end_time' => '07:40',
                            'match1_match_number' => max($day2First, $day2AddMaxGame + 1),
                            'match2_match_number' => max($day2First + 1, $day2AddMaxGame + 2),
                            'pitch1_pitch_id' => $firstRobinPitchId,
                            'pitch2_pitch_id' => $secondRobinPitchId,
                            'match1_home_registration_id' => optional($roundRobinRegistrationOptions->first())->id,
                            'match1_away_registration_id' => optional($roundRobinRegistrationOptions->skip(1)->first())->id ?? optional($roundRobinRegistrationOptions->first())->id,
                            'match2_home_registration_id' => optional($roundRobinRegistrationOptions->skip(2)->first())->id,
                            'match2_away_registration_id' => optional($roundRobinRegistrationOptions->skip(3)->first())->id,
                            'status' => 'upcoming',
                        ];
                    @endphp
                    @include('admin.tournaments.partials.small-round-robin-slot-row-admin-modal', [
                        'tournament' => $selectedTournament,
                        'modalKey' => 'add',
                        'slotPrefix' => 'day2_slot',
                        'modalName' => 'small-day2-add-schedule-slot-modal',
                        'heading' => __('Add Day 2 schedule row'),
                        'formAction' => route('admin.tournaments.round-robin.day2-schedule-rows.store', $selectedTournament),
                        'httpMethod' => 'post',
                        'dateLabel' => \App\Support\SmallFixedRoundRobinDayTwoSchedule::SCHEDULE_DATE_LABEL,
                        'slotDateIso' => \App\Support\SmallFixedRoundRobinDayTwoSchedule::SCHEDULE_DATE_ISO,
                        'pitch1Match' => null,
                        'pitch2Match' => null,
                        'defaults' => $day2AddDefaults,
                        'roundRobinPitchOptions' => $roundRobinPitchOptions,
                        'roundRobinRegistrationOptions' => $roundRobinRegistrationOptions,
                        'minMatchNumber' => \App\Support\SmallFixedRoundRobinDayTwoSchedule::FIRST_GAME_NUMBER,
                        'statusOptions' => $smallDayTwoRowStatusOptions,
                    ])
                    @for ($mi = 0; $mi < $day2ScheduleFormRowCount; $mi++)
                        @php
                            $erow = $smallDayTwoGridRows->get($mi) ?? [];
                            $ep1 = $erow['pitch1_match'] ?? null;
                            $ep2 = $erow['pitch2_match'] ?? null;
                        @endphp
                        @if ($ep1 && $ep2)
                            @php
                                $ers = collect([$ep1->status, $ep2->status])->filter()->unique()->values();
                                $editRowStatus = $ers->count() === 1 ? match ($ers->first()) {
                                    \App\Models\TournamentMatch::STATUS_LIVE => 'live',
                                    \App\Models\TournamentMatch::STATUS_COMPLETED => 'completed',
                                    default => 'upcoming',
                                } : 'upcoming';
                                $slotEditDefaults = [
                                    'round' => (int) ($erow['round'] ?? 1),
                                    'start_time' => $ep1->scheduled_at?->timezone($day2Tz)->format('H:i') ?? '07:00',
                                    'end_time' => $ep1->scheduled_ends_at?->timezone($day2Tz)->format('H:i') ?? '07:40',
                                    'match1_match_number' => (int) $erow['pitch1_game_no'],
                                    'match2_match_number' => (int) $erow['pitch2_game_no'],
                                    'pitch1_pitch_id' => $ep1->pitch_id,
                                    'pitch2_pitch_id' => $ep2->pitch_id,
                                    'match1_home_registration_id' => $ep1->home_registration_id,
                                    'match1_away_registration_id' => $ep1->away_registration_id,
                                    'match2_home_registration_id' => $ep2->home_registration_id,
                                    'match2_away_registration_id' => $ep2->away_registration_id,
                                    'status' => $editRowStatus,
                                ];
                            @endphp
                            @include('admin.tournaments.partials.small-round-robin-slot-row-admin-modal', [
                                'tournament' => $selectedTournament,
                                'modalKey' => (string) $ep1->id,
                                'slotPrefix' => 'day2_slot',
                                'modalName' => 'small-day2-edit-schedule-slot-'.$ep1->id.'-'.$ep2->id,
                                'heading' => __('Edit Day 2 schedule row'),
                                'formAction' => route('admin.tournaments.round-robin.day2-schedule-rows.update', $selectedTournament),
                                'httpMethod' => 'patch',
                                'dateLabel' => \App\Support\SmallFixedRoundRobinDayTwoSchedule::SCHEDULE_DATE_LABEL,
                                'slotDateIso' => \App\Support\SmallFixedRoundRobinDayTwoSchedule::SCHEDULE_DATE_ISO,
                                'pitch1Match' => $ep1,
                                'pitch2Match' => $ep2,
                                'defaults' => $slotEditDefaults,
                                'roundRobinPitchOptions' => $roundRobinPitchOptions,
                                'roundRobinRegistrationOptions' => $roundRobinRegistrationOptions,
                                'minMatchNumber' => \App\Support\SmallFixedRoundRobinDayTwoSchedule::FIRST_GAME_NUMBER,
                                'statusOptions' => $smallDayTwoRowStatusOptions,
                            ])
                        @endif
                    @endfor
                @endif

                @if (! $day2ScheduleUnsynced && $isAdmin && $removedDayTwoScheduleGroups->isNotEmpty())
                    <div class="border-t border-neutral-200 bg-zinc-50/90 px-4 py-4 dark:border-neutral-700 dark:bg-zinc-900/60">
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Removed schedule rows (Day 2)') }}</h3>
                        <p class="mt-1 text-xs text-zinc-600 dark:text-zinc-400">{{ __('Soft-removed games can be restored to the main schedule.') }}</p>
                        <div class="mt-3 overflow-x-auto">
                            <table class="min-w-[48rem] w-full text-left text-xs">
                                <thead class="border-b border-neutral-200 text-zinc-600 dark:border-neutral-700 dark:text-zinc-400">
                                    <tr>
                                        <th class="py-2 pr-3">{{ __('Time') }}</th>
                                        <th class="py-2 pr-3">{{ __('Round') }}</th>
                                        <th class="py-2 pr-3">{{ __('Games') }}</th>
                                        <th class="py-2 pr-3">{{ __('Game A') }}</th>
                                        <th class="py-2 pr-3">{{ __('Game B') }}</th>
                                        <th class="py-2">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                                    @foreach ($removedDayTwoScheduleGroups as $removedRow)
                                        <tr>
                                            <td class="py-2 pr-3 text-zinc-800 dark:text-zinc-200">{{ $removedRow['time_label'] }}</td>
                                            <td class="py-2 pr-3 text-zinc-800 dark:text-zinc-200">{{ $removedRow['round_label'] }}</td>
                                            <td class="py-2 pr-3 font-mono text-zinc-700 dark:text-zinc-300">{{ implode(', ', $removedRow['match_numbers']) }}</td>
                                            <td class="py-2 pr-3 text-zinc-700 dark:text-zinc-300">{{ $removedRow['pitch1_matchup'] }}</td>
                                            <td class="py-2 pr-3 text-zinc-700 dark:text-zinc-300">{{ $removedRow['pitch2_matchup'] }}</td>
                                            <td class="py-2">
                                                <form method="POST" action="{{ route('admin.tournaments.round-robin.day2-schedule-rows.restore', $selectedTournament) }}" class="inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="redirect_route" value="admin.tournaments.index">
                                                    <input type="hidden" name="redirect_tab" value="round-robin">
                                                    @if (! empty($removedRow['slot_ulid']))
                                                        <input type="hidden" name="slot_ulid" value="{{ $removedRow['slot_ulid'] }}">
                                                    @else
                                                        @foreach ($removedRow['match_numbers'] as $n)
                                                            <input type="hidden" name="match_numbers[]" value="{{ $n }}">
                                                        @endforeach
                                                    @endif
                                                    <flux:button type="submit" size="sm" variant="outline">{{ __('Restore') }}</flux:button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('Fixed Day 2 schedule') }}</p>
                <p class="mt-2 max-w-3xl text-sm text-zinc-600 dark:text-zinc-300">
                    @if ($day2ScheduleUnsynced && $isAdmin)
                        {{ __('Adjust times, rounds, game numbers, pitches, matchups (home/away from registered teams), and row status before syncing. The day date above applies to every row. Times apply to both games in the row.') }}
                    @else
                        {{ __('Day 2 continues the round robin (game numbers from the Berger plan, typically 25 upward). These games count toward Team Standing alongside Day 1. Quarter Finals stay locked until every Day 1 and Day 2 row is marked Completed.') }}
                    @endif
                </p>
                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('Times use the tournament timezone (:tz).', ['tz' => \App\Support\SmallFixedRoundRobinDayOneSchedule::tournamentTimezone($selectedTournament)]) }}
                </p>
                @if ($day2ScheduleUnsynced && $isAdmin && ($day2AttachToDay1Form ?? false))
                    <p class="mt-2 text-xs font-medium text-zinc-600 dark:text-zinc-300">
                        {{ __('Use the Day 1 “Sync Day 1 & Day 2 schedules” button above to save these rows together with Day 1.') }}
                    </p>
                @endif
            </div>

            @if ($isAdmin)
                @if ($day2ScheduleUnsynced && ! ($day2AttachToDay1Form ?? false))
                    <div class="flex shrink-0 flex-col items-stretch gap-2 sm:flex-row sm:items-center">
                        <flux:button type="button" variant="ghost" onclick="window.resetSmallDay2ScheduleForm?.()">
                            {{ __('Reset to default schedule') }}
                        </flux:button>
                        <flux:button type="submit" variant="primary" :disabled="$teamCount === 0">
                            {{ __('Sync Day 2 schedule to database') }}
                        </flux:button>
                    </div>
                @elseif ($day2ScheduleUnsynced && ($day2AttachToDay1Form ?? false))
                    <div class="shrink-0">
                        <flux:button type="button" variant="ghost" onclick="window.resetSmallDay2ScheduleForm?.()">
                            {{ __('Reset Day 2 matchups to defaults') }}
                        </flux:button>
                    </div>
                @elseif (! $day2ScheduleUnsynced)
                    <form method="POST" action="{{ route('admin.tournaments.matches.small-day2-schedule.sync', $selectedTournament) }}" class="shrink-0">
                        @csrf
                        <input type="hidden" name="redirect_route" value="admin.tournaments.index">
                        <input type="hidden" name="redirect_tab" value="round-robin">
                        <flux:button type="submit" variant="primary" :disabled="$teamCount === 0">
                            {{ __('Sync Day 2 schedule to database') }}
                        </flux:button>
                    </form>
                @endif
            @endif
        </div>

        @if ($day2ScheduleUnsynced && $isAdmin && ! ($day2AttachToDay1Form ?? false))
            </form>
        @endif
    </div>
</section>

@if ($day2ScheduleUnsynced && $isAdmin)
    <script>
        window.__SMALL_DAY2_DEFAULTS = @json($smallDay2FormDefaults);
        window.__SMALL_DAY2_DEFAULT_ROW_COUNT = @json($day2DefaultScheduleRowCount);
        window.__SMALL_DAY2_FIRST_GAME = @json(\App\Support\SmallFixedRoundRobinDayTwoSchedule::FIRST_GAME_NUMBER);
        window.__SMALL_DAY2_PITCH_DEFAULTS = @json([$firstRobinPitchId, $secondRobinPitchId]);
        window.__SMALL_DAY2_ROW_TEMPLATE = (function () {
            const tbody = document.querySelector('#small-day2-schedule-tbody');
            return tbody ? tbody.querySelector('tr')?.cloneNode(true) ?? null : null;
        })();
        window.__DAY2_SYNC_NEED_ROW_ALERT = @json(__('Add at least one Day 2 schedule row before syncing.'));
        window.confirmSmallDay2ScheduleSyncSubmit = function (form) {
            if (form.id !== 'small-day2-schedule-sync-form') {
                return true;
            }
            const tbody = form.querySelector('#small-day2-schedule-tbody') || form.querySelector('tbody');
            if (!tbody || tbody.querySelectorAll('tr').length === 0) {
                window.alert(window.__DAY2_SYNC_NEED_ROW_ALERT);
                return false;
            }
            return true;
        };
        window.refreshDay2UnsyncedRemoveButtons = function () {
            const tbody = document.querySelector('#small-day2-schedule-tbody');
            if (!tbody) return;
            tbody.querySelectorAll('[data-day2-remove-row]').forEach(function (btn) {
                btn.disabled = false;
            });
        };
        window.reindexSmallDay2ScheduleRows = function () {
            const tbody = document.querySelector('#small-day2-schedule-tbody');
            if (!tbody) return;
            tbody.querySelectorAll('tr').forEach(function (tr, idx) {
                tr.querySelectorAll('[name^="day2_rows["]').forEach(function (el) {
                    el.name = el.name.replace(/day2_rows\[\d+]/, 'day2_rows[' + idx + ']');
                });
            });
            window.refreshDay2UnsyncedRemoveButtons();
        };
        window.removeSmallDay2ScheduleRow = function (btn) {
            const tr = btn.closest('tr');
            const tbody = tr && tr.parentElement;
            if (!tbody) return;
            tr.remove();
            window.reindexSmallDay2ScheduleRows();
        };
        window.addSmallDay2ScheduleRow = function () {
            const tbody = document.querySelector('#small-day2-schedule-tbody');
            if (!tbody) return;
            const trs = tbody.querySelectorAll('tr');
            const tmpl = window.__SMALL_DAY2_ROW_TEMPLATE;
            const last = trs[trs.length - 1];
            const source = last || tmpl;
            if (!source) return;
            let maxM2 = window.__SMALL_DAY2_FIRST_GAME - 1;
            trs.forEach(function (tr) {
                const m2 = tr.querySelector('[name$="[match2_match_number]"]');
                if (m2 && m2.value) maxM2 = Math.max(maxM2, parseInt(m2.value, 10) || 0);
            });
            let maxR = 0;
            trs.forEach(function (tr) {
                const rd = tr.querySelector('[name$="[round]"]');
                if (rd && rd.value) maxR = Math.max(maxR, parseInt(rd.value, 10) || 0);
            });
            const clone = source.cloneNode(true);
            clone.setAttribute('data-day2-schedule-row-source', 'added');
            clone.setAttribute('data-day2-row-client-id', window.crypto?.randomUUID?.() || String(Date.now()) + '-' + Math.random());
            clone.querySelectorAll('select').forEach(function (sel) {
                if (sel.options.length) sel.selectedIndex = 0;
            });
            tbody.appendChild(clone);
            window.reindexSmallDay2ScheduleRows();
            const lastTr = tbody.querySelector('tr:last-child');
            const m1 = lastTr && lastTr.querySelector('[name$="[match1_match_number]"]');
            const m2 = lastTr && lastTr.querySelector('[name$="[match2_match_number]"]');
            const rd = lastTr && lastTr.querySelector('[name$="[round]"]');
            if (m1) m1.value = String(maxM2 + 1);
            if (m2) m2.value = String(maxM2 + 2);
            if (rd) rd.value = String(maxR + 1);
            const pd = window.__SMALL_DAY2_PITCH_DEFAULTS || [];
            const p1sel = lastTr && lastTr.querySelector('[name$="[pitch1_pitch_id]"]');
            const p2sel = lastTr && lastTr.querySelector('[name$="[pitch2_pitch_id]"]');
            if (p1sel && pd[0]) p1sel.value = String(pd[0]);
            if (p2sel && pd[1]) p2sel.value = String(pd[1]);
            window.refreshDay2UnsyncedRemoveButtons();
        };
        window.resetSmallDay2ScheduleForm = function () {
            const tbody = document.querySelector('#small-day2-schedule-tbody');
            const tmpl = window.__SMALL_DAY2_ROW_TEMPLATE;
            if (!tbody || !tmpl) return;
            const n = window.__SMALL_DAY2_DEFAULT_ROW_COUNT || 6;
            tbody.innerHTML = '';
            for (let i = 0; i < n; i++) {
                const tr = tmpl.cloneNode(true);
                tr.setAttribute('data-day2-schedule-row-source', 'default');
                tr.setAttribute('data-day2-row-client-id', window.crypto?.randomUUID?.() || String(Date.now()) + '-' + i + '-' + Math.random());
                tbody.appendChild(tr);
            }
            window.reindexSmallDay2ScheduleRows();
            const rows = window.__SMALL_DAY2_DEFAULTS;
            if (!Array.isArray(rows)) return;
            rows.forEach(function (row, i) {
                Object.keys(row).forEach(function (key) {
                    const el = document.querySelector('[name="day2_rows[' + i + '][' + key + ']"]');
                    if (el && 'value' in el) {
                        el.value = row[key] === null || row[key] === undefined ? '' : String(row[key]);
                    }
                });
            });
            window.refreshDay2UnsyncedRemoveButtons();
        };
        setTimeout(function () {
            window.refreshDay2UnsyncedRemoveButtons();
        }, 0);
    </script>
@endif
