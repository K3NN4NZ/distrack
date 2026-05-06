@php
    $matchModalTournamentId = (int) old('match_tournament_id', 0);
    $isAddOpen = $matchModalTournamentId === (int) $tournament->id
        && old('match_form_intent') === 'round_robin_add';
@endphp
<flux:modal
    name="setup-add-round-robin-match-modal-{{ $tournament->id }}"
    :show="$isAddOpen"
    class="max-w-3xl"
>
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <flux:heading size="lg">{{ __('Add Round Robin Match') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ __('Manually pair teams and assign a pitch for a round robin match.') }}
                </flux:text>
            </div>

            <flux:modal.close>
                <flux:button variant="ghost">
                    {{ __('Close') }}
                </flux:button>
            </flux:modal.close>
        </div>

        @if ($tournament->registrations->count() >= 2)
            <form method="POST" action="{{ route('admin.tournaments.matches.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="tournament_id" value="{{ $tournament->id }}">
                <input type="hidden" name="match_tournament_id" value="{{ $tournament->id }}">
                <input type="hidden" name="match_form_intent" value="round_robin_add">
                <input type="hidden" name="redirect_route" value="admin.tournaments.index">
                <input type="hidden" name="redirect_tab" value="round-robin">
                <input type="hidden" name="stage" value="round_robin">
                <input type="hidden" name="status" value="scheduled">

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
                                <option value="{{ $registration->id }}" @selected((string) old('home_registration_id') === (string) $registration->id)>
                                    {{ $registration->team->name }}{{ $registration->bracket_code ? ' | '.__('Bracket').' '.$registration->bracket_code : '' }}{{ $registration->seed_number ? ' | '.__('Seed').' '.$registration->seed_number : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('home_registration_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
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
                                <option value="{{ $registration->id }}" @selected((string) old('away_registration_id') === (string) $registration->id)>
                                    {{ $registration->team->name }}{{ $registration->bracket_code ? ' | '.__('Bracket').' '.$registration->bracket_code : '' }}{{ $registration->seed_number ? ' | '.__('Seed').' '.$registration->seed_number : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('away_registration_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
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
                                <option value="{{ $pitch->id }}" @selected((string) old('pitch_id') === (string) $pitch->id)>{{ $pitch->name }}</option>
                            @endforeach
                        </select>
                        @error('pitch_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </label>

                    <flux:input name="round_label" :label="__('Round Label')" :value="old('round_label')" type="text" placeholder="{{ __('e.g. Round 1 - Bracket A') }}" />
                    <flux:input name="match_number" :label="__('Match Number')" :value="old('match_number')" type="number" min="1" />
                </div>

                <flux:input name="scheduled_at" :label="__('Scheduled At')" :value="old('scheduled_at')" type="datetime-local" />

                <flux:button type="submit" variant="primary" class="w-full">
                    {{ __('Add Round Robin Match') }}
                </flux:button>
            </form>
        @else
            <div class="rounded-xl border border-dashed border-neutral-300 p-4 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                {{ __('Register at least two teams before creating a round robin match.') }}
            </div>
        @endif
    </div>
</flux:modal>
