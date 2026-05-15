@php
    $editMode = ($roundRobinMatchModalEditScope ?? 'slot') === 'game' ? 'game' : 'slot';

    $editMatchOldId = old('edit_match_id');
    $isThisMatchInOld = $editMatchOldId !== null && (int) $editMatchOldId === (int) $match->id;

    $roundRobinScheduleTz = \App\Support\ManualRoundRobinSchedule::tournamentTimezone($tournament);

    $defaultStartTime = $match->scheduled_at?->timezone($roundRobinScheduleTz)->format('H:i') ?? '';
    $defaultEndTime = $match->scheduled_ends_at?->timezone($roundRobinScheduleTz)->format('H:i')
        ?? ($match->scheduled_at
            ? $match->scheduled_at->timezone($roundRobinScheduleTz)->addMinutes(55)->format('H:i')
            : '');

    if ($defaultStartTime === '') {
        $defaultStartTime = '09:00';
    }

    if ($defaultEndTime === '') {
        $defaultEndTime = '10:00';
    }
@endphp
<flux:modal
    name="setup-edit-round-robin-match-modal-{{ $match->id }}"
    :show="$isThisMatchInOld"
    class="max-w-3xl"
>
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <flux:heading size="lg">{{ __('Edit Round Robin Match') }}</flux:heading>
                <flux:text class="mt-1">
                    @if ($editMode === 'game')
                        {{ __('Adjust the team pairing or pitch for this game. Start and end times, round, and row status for this time slot are edited with the schedule row’s Edit control.') }}
                    @else
                        {{ __('Adjust the team pairing, pitch, schedule, or round label for this match.') }}
                    @endif
                </flux:text>
            </div>

            <flux:modal.close>
                <flux:button variant="ghost">
                    {{ __('Close') }}
                </flux:button>
            </flux:modal.close>
        </div>

        <form method="POST" action="{{ route('admin.tournaments.matches.update', ['match' => $match->id]) }}" class="space-y-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="edit_match_id" value="{{ $match->id }}">
            <input type="hidden" name="redirect_route" value="admin.tournaments.index">
            <input type="hidden" name="redirect_tab" value="round-robin">
            @if ($editMode === 'game')
                <input type="hidden" name="match_edit_scope" value="game">
            @endif

            <div class="grid gap-4 md:grid-cols-2">
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    {{ __('Home Team') }}
                    <select
                        name="home_registration_id"
                        required
                        class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                    >
                        <option value="">{{ __('Select team') }}</option>
                        @foreach ($tournament->registrations as $registration)
                            <option
                                value="{{ $registration->id }}"
                                @selected(
                                    $isThisMatchInOld
                                        ? (string) old('home_registration_id') === (string) $registration->id
                                        : (int) $match->home_registration_id === (int) $registration->id
                                )
                            >
                                {{ $registration->team->name }}{{ $registration->bracket_code ? ' | '.__('Bracket').' '.$registration->bracket_code : '' }}{{ $registration->seed_number ? ' | '.__('Seed').' '.$registration->seed_number : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    {{ __('Away Team') }}
                    <select
                        name="away_registration_id"
                        required
                        class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                    >
                        <option value="">{{ __('Select team') }}</option>
                        @foreach ($tournament->registrations as $registration)
                            <option
                                value="{{ $registration->id }}"
                                @selected(
                                    $isThisMatchInOld
                                        ? (string) old('away_registration_id') === (string) $registration->id
                                        : (int) $match->away_registration_id === (int) $registration->id
                                )
                            >
                                {{ $registration->team->name }}{{ $registration->bracket_code ? ' | '.__('Bracket').' '.$registration->bracket_code : '' }}{{ $registration->seed_number ? ' | '.__('Seed').' '.$registration->seed_number : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="grid gap-4 {{ $editMode === 'game' ? 'md:grid-cols-2' : 'md:grid-cols-3' }}">
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    {{ __('Pitch') }}
                    <select
                        name="pitch_id"
                        class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                    >
                        <option value="">{{ __('No pitch assigned') }}</option>
                        @foreach ($tournament->pitches as $pitch)
                            <option
                                value="{{ $pitch->id }}"
                                @selected(
                                    $isThisMatchInOld
                                        ? (string) old('pitch_id') === (string) $pitch->id
                                        : (int) $match->pitch_id === (int) $pitch->id
                                )
                            >{{ $pitch->name }}</option>
                        @endforeach
                    </select>
                </label>

                @if ($editMode === 'slot')
                    <flux:input
                        name="round_label"
                        :label="__('Round Label')"
                        :value="$isThisMatchInOld ? old('round_label', $match->round_label) : $match->round_label"
                        type="text"
                    />
                @endif
                <flux:input
                    name="match_number"
                    :label="__('Match Number')"
                    :value="$isThisMatchInOld ? old('match_number', $match->match_number) : $match->match_number"
                    type="number"
                    min="1"
                />
            </div>

            @if ($editMode === 'slot')
                <div class="rounded-lg border border-neutral-200 bg-zinc-50/80 px-3 py-2 dark:border-neutral-700 dark:bg-zinc-900/50">
                    <flux:text class="text-xs text-zinc-600 dark:text-zinc-400">
                        {{ __('Times use the tournament timezone (:tz). The calendar date is taken from the match’s current schedule (Day 1 / Day 2 flow).', ['tz' => $roundRobinScheduleTz]) }}
                    </flux:text>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ __('Start Time') }}
                        <input
                            type="time"
                            name="start_time"
                            value="{{ $isThisMatchInOld ? old('start_time', $defaultStartTime) : $defaultStartTime }}"
                            required
                            class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                        >
                        @error('start_time')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </label>

                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ __('End Time') }}
                        <input
                            type="time"
                            name="end_time"
                            value="{{ $isThisMatchInOld ? old('end_time', $defaultEndTime) : $defaultEndTime }}"
                            required
                            class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                        >
                        @error('end_time')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </label>
                </div>
            @endif

            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Save Changes') }}
            </flux:button>
        </form>
    </div>
</flux:modal>
