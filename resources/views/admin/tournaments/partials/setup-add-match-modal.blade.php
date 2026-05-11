<flux:modal
    name="setup-add-match-modal-{{ $tournament->id }}"
    :show="$show"
    class="max-w-4xl"
>
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <flux:heading size="lg">{{ __('Add Match') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ __('Create the schedule and optional scoreline data that powers the public schedule and match detail pages.') }}
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
                <input type="hidden" name="match_form_intent" value="general_match_add">
                <input type="hidden" name="tournament_id" value="{{ $tournament->id }}">
                <input type="hidden" name="match_tournament_id" value="{{ $tournament->id }}">
                <input type="hidden" name="redirect_route" value="admin.tournaments.index">
                <input type="hidden" name="redirect_tab" value="quarter-final">

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ __('Home Team') }}
                        <select
                            name="home_registration_id"
                            required
                            class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                        >
                            <option value="">{{ __('Select registration') }}</option>
                            @foreach ($tournament->registrations as $registration)
                                <option value="{{ $registration->id }}" @selected((string) old('home_registration_id') === (string) $registration->id)>
                                    {{ $registration->team->name }}{{ $registration->seed_number ? ' | '.__('Seed').' '.$registration->seed_number : '' }}
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
                            <option value="">{{ __('Select registration') }}</option>
                            @foreach ($tournament->registrations as $registration)
                                <option value="{{ $registration->id }}" @selected((string) old('away_registration_id') === (string) $registration->id)>
                                    {{ $registration->team->name }}{{ $registration->seed_number ? ' | '.__('Seed').' '.$registration->seed_number : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('away_registration_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </label>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ __('Stage') }}
                        <select
                            name="stage"
                            required
                            class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                        >
                            @foreach ($stageOptions as $option)
                                <option value="{{ $option['value'] }}" @selected(old('stage', 'round_robin') === $option['value'])>{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Use the frisbee stage keys so Day 0, Day 1, and Day 2 matches stay aligned with the approved format flow.') }}
                        </p>
                        @error('stage')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </label>

                    <flux:input name="round_label" :label="__('Round Label')" :value="old('round_label')" type="text" />
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <flux:input
                        name="match_number"
                        :label="__('Match Number')"
                        :value="old('match_number', $suggestedNextMatchNumber ?? \App\Models\TournamentMatch::nextMatchNumberForTournament((int) $tournament->id))"
                        type="number"
                        min="1"
                    />
                    <flux:input name="scheduled_at" :label="__('Scheduled At')" :value="old('scheduled_at')" type="datetime-local" />

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
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ __('Status') }}
                        <select
                            name="status"
                            class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                        >
                            @foreach (['scheduled' => 'Scheduled', 'live' => 'Live', 'completed' => 'Completed'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', 'scheduled') === $value)>{{ __($label) }}</option>
                            @endforeach
                        </select>
                        @error('status')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </label>

                    <flux:input name="home_score" :label="__('Home Score')" :value="old('home_score')" type="number" min="0" />
                    <flux:input name="away_score" :label="__('Away Score')" :value="old('away_score')" type="number" min="0" />
                </div>

                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    {{ __('Notes') }}
                    <textarea
                        name="notes"
                        rows="4"
                        class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                    >{{ old('notes') }}</textarea>
                    @error('notes')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </label>

                <flux:button type="submit" variant="primary" class="w-full">
                    {{ __('Add Match') }}
                </flux:button>
            </form>
        @else
            <div class="rounded-xl border border-dashed border-neutral-300 p-4 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                {{ __('Register at least two teams before creating a match.') }}
            </div>
        @endif
    </div>
</flux:modal>
