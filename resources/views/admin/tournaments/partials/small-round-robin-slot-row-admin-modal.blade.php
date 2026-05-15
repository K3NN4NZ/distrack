@php
    $p = $slotPrefix;
    $modalOpenKey = $p.'_modal';
    $showModal = (string) old($modalOpenKey) === (string) $modalKey;
    $tzLabel = $tournamentTimezone ?? \App\Support\ManualRoundRobinSchedule::tournamentTimezone($tournament);
    $minA = (int) ($minMatchNumberGameA ?? $minMatchNumber ?? 1);
    $minB = isset($minMatchNumberGameB) ? (int) $minMatchNumberGameB : $minA + 1;
    $regLabel = static function ($reg): string {
        if ($reg === null) {
            return '';
        }

        return (string) ($reg->team?->name ?? __('Registration #:id', ['id' => $reg->id]));
    };
    $inputClass = 'mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white';
@endphp
<flux:modal
    name="{{ $modalName }}"
    :show="$showModal"
    class="max-w-4xl"
>
    <div class="max-h-[85vh] space-y-4 overflow-y-auto pr-1">
        <div class="flex items-start justify-between gap-4">
            <div>
                <flux:heading size="lg">{{ $heading }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                    {{ __('Date: :date (this day section). Times use :tz.', ['date' => $dateLabel, 'tz' => $tzLabel]) }}
                </flux:text>
            </div>
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Close') }}</flux:button>
            </flux:modal.close>
        </div>

        <form method="POST" action="{{ $formAction }}" class="space-y-4">
            @csrf
            @if (strtolower((string) $httpMethod) === 'patch')
                @method('PATCH')
            @endif
            <input type="hidden" name="redirect_route" value="admin.tournaments.index">
            <input type="hidden" name="redirect_tab" value="round-robin">
            <input type="hidden" name="{{ $modalOpenKey }}" value="{{ $modalKey }}">

            @foreach ($extraHiddenFields ?? [] as $hiddenName => $hiddenValue)
                <input type="hidden" name="{{ $hiddenName }}" value="{{ $hiddenValue }}">
            @endforeach

            @if (filled($slotDateIso ?? null))
                <input type="hidden" name="{{ $p }}[date]" value="{{ $slotDateIso }}">
            @endif

            @if ($pitch1Match && $pitch2Match)
                <input type="hidden" name="{{ $p }}[pitch1_match_id]" value="{{ (int) $pitch1Match->id }}">
                <input type="hidden" name="{{ $p }}[pitch2_match_id]" value="{{ (int) $pitch2Match->id }}">
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    {{ __('Start time') }}
                    <input
                        type="time"
                        name="{{ $p }}[start_time]"
                        value="{{ old($p.'.start_time', $defaults['start_time'] ?? '') }}"
                        required
                        class="{{ $inputClass }}"
                    >
                    @error($p.'.start_time')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </label>
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    {{ __('End time') }}
                    <input
                        type="time"
                        name="{{ $p }}[end_time]"
                        value="{{ old($p.'.end_time', $defaults['end_time'] ?? '') }}"
                        required
                        class="{{ $inputClass }}"
                    >
                    @error($p.'.end_time')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </label>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input
                    name="{{ $p }}[round]"
                    type="number"
                    min="1"
                    :label="__('Round')"
                    :value="old($p.'.round', $defaults['round'] ?? 1)"
                    required
                />
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    {{ __('Row status') }}
                    <select name="{{ $p }}[status]" required class="{{ $inputClass }}">
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old($p.'.status', $defaults['status'] ?? 'upcoming') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error($p.'.status')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </label>
            </div>

            <div class="rounded-lg border border-neutral-200 bg-zinc-50/80 px-3 py-2 text-xs text-zinc-600 dark:border-neutral-700 dark:bg-zinc-900/50 dark:text-zinc-300">
                {{ __('Game A (first column in the schedule table)') }}
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <flux:input
                    name="{{ $p }}[match1_match_number]"
                    type="number"
                    :min="$minA"
                    :label="__('Game A number')"
                    :value="old($p.'.match1_match_number', $defaults['match1_match_number'] ?? '')"
                    required
                />
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 sm:col-span-1">
                    {{ __('Game A pitch') }}
                    <select name="{{ $p }}[pitch1_pitch_id]" required class="{{ $inputClass }}">
                        @foreach ($roundRobinPitchOptions as $pitchOpt)
                            <option
                                value="{{ $pitchOpt->id }}"
                                @selected((int) old($p.'.pitch1_pitch_id', (int) ($defaults['pitch1_pitch_id'] ?? 0)) === (int) $pitchOpt->id)
                            >{{ $pitchOpt->name }}</option>
                        @endforeach
                    </select>
                    @error($p.'.pitch1_pitch_id')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </label>
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 sm:col-span-1">
                    {{ __('Game A home') }}
                    <select name="{{ $p }}[match1_home_registration_id]" required class="{{ $inputClass }}">
                        @foreach ($roundRobinRegistrationOptions as $reg)
                            <option
                                value="{{ $reg->id }}"
                                @selected((int) old($p.'.match1_home_registration_id', (int) ($defaults['match1_home_registration_id'] ?? 0)) === (int) $reg->id)
                            >{{ $regLabel($reg) }}</option>
                        @endforeach
                    </select>
                    @error($p.'.match1_home_registration_id')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </label>
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 sm:col-span-1">
                    {{ __('Game A away') }}
                    <select name="{{ $p }}[match1_away_registration_id]" required class="{{ $inputClass }}">
                        @foreach ($roundRobinRegistrationOptions as $reg)
                            <option
                                value="{{ $reg->id }}"
                                @selected((int) old($p.'.match1_away_registration_id', (int) ($defaults['match1_away_registration_id'] ?? 0)) === (int) $reg->id)
                            >{{ $regLabel($reg) }}</option>
                        @endforeach
                    </select>
                    @error($p.'.match1_away_registration_id')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </label>
            </div>

            <div class="rounded-lg border border-neutral-200 bg-zinc-50/80 px-3 py-2 text-xs text-zinc-600 dark:border-neutral-700 dark:bg-zinc-900/50 dark:text-zinc-300">
                {{ __('Game B (second column)') }}
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <flux:input
                    name="{{ $p }}[match2_match_number]"
                    type="number"
                    :min="$minB"
                    :label="__('Game B number')"
                    :value="old($p.'.match2_match_number', $defaults['match2_match_number'] ?? '')"
                    required
                />
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 sm:col-span-1">
                    {{ __('Game B pitch') }}
                    <select name="{{ $p }}[pitch2_pitch_id]" required class="{{ $inputClass }}">
                        @foreach ($roundRobinPitchOptions as $pitchOpt)
                            <option
                                value="{{ $pitchOpt->id }}"
                                @selected((int) old($p.'.pitch2_pitch_id', (int) ($defaults['pitch2_pitch_id'] ?? 0)) === (int) $pitchOpt->id)
                            >{{ $pitchOpt->name }}</option>
                        @endforeach
                    </select>
                    @error($p.'.pitch2_pitch_id')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </label>
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 sm:col-span-1">
                    {{ __('Game B home') }}
                    <select name="{{ $p }}[match2_home_registration_id]" required class="{{ $inputClass }}">
                        @foreach ($roundRobinRegistrationOptions as $reg)
                            <option
                                value="{{ $reg->id }}"
                                @selected((int) old($p.'.match2_home_registration_id', (int) ($defaults['match2_home_registration_id'] ?? 0)) === (int) $reg->id)
                            >{{ $regLabel($reg) }}</option>
                        @endforeach
                    </select>
                    @error($p.'.match2_home_registration_id')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </label>
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 sm:col-span-1">
                    {{ __('Game B away') }}
                    <select name="{{ $p }}[match2_away_registration_id]" required class="{{ $inputClass }}">
                        @foreach ($roundRobinRegistrationOptions as $reg)
                            <option
                                value="{{ $reg->id }}"
                                @selected((int) old($p.'.match2_away_registration_id', (int) ($defaults['match2_away_registration_id'] ?? 0)) === (int) $reg->id)
                            >{{ $regLabel($reg) }}</option>
                        @endforeach
                    </select>
                    @error($p.'.match2_away_registration_id')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </label>
            </div>

            @if ($requiresStructuralEditConfirm ?? false)
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-amber-200 bg-amber-50/80 px-3 py-3 text-sm text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100">
                    <input
                        type="checkbox"
                        name="confirm_sensitive_changes"
                        value="1"
                        class="mt-0.5 h-4 w-4 rounded border-amber-400 text-amber-700 focus:ring-amber-500"
                        @checked(old('confirm_sensitive_changes'))
                    >
                    <span>
                        {{ __('This row has scores, scoring activity, or is live/completed. Confirm that you intend to change teams, game numbers, pitches, times, or round.') }}
                    </span>
                </label>
                @error($p.'.confirm_sensitive_changes')
                    <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            @endif

            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Save') }}
            </flux:button>
        </form>
    </div>
</flux:modal>
