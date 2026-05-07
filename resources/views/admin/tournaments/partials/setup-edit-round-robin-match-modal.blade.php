@php
    $editMatchOldId = old('edit_match_id');
    $isThisMatchInOld = $editMatchOldId !== null && (int) $editMatchOldId === (int) $match->id;

    $scheduledAtValue = $match->scheduled_at?->format('Y-m-d\TH:i');
@endphp
<flux:modal
    name="setup-edit-round-robin-match-modal-{{ $match->id }}"
    :show="$isThisMatchInOld"
    class="max-w-3xl"
>
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <flux:heading size="lg">{{ __('Edit Robin') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ __('Adjust the team pairing, pitch, schedule, or round label for this robin.') }}
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

            <div class="grid gap-4 md:grid-cols-3">
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

                <flux:input
                    name="round_label"
                    :label="__('Round Label')"
                    :value="$isThisMatchInOld ? old('round_label', $match->round_label) : $match->round_label"
                    type="text"
                />
                <flux:input
                    name="match_number"
                    :label="__('Robin Number')"
                    :value="$isThisMatchInOld ? old('match_number', $match->match_number) : $match->match_number"
                    type="number"
                    min="1"
                />
            </div>

            <flux:input
                name="scheduled_at"
                :label="__('Scheduled At')"
                :value="$isThisMatchInOld ? old('scheduled_at', $scheduledAtValue) : $scheduledAtValue"
                type="datetime-local"
            />

            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Save Changes') }}
            </flux:button>
        </form>
    </div>
</flux:modal>
