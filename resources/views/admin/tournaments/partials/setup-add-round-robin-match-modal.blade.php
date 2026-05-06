@php
    $matchModalTournamentId = (int) old('match_tournament_id', 0);
    $pitchContext = isset($pitch) && $pitch ? 'pitch-'.$pitch->id : 'general';
    $isAddOpen = $matchModalTournamentId === (int) $tournament->id
        && old('match_form_intent') === 'round_robin_add'
        && old('round_robin_pitch_context', 'general') === $pitchContext;
    $modalName = isset($pitch) && $pitch
        ? 'setup-add-round-robin-match-modal-'.$tournament->id.'-pitch-'.$pitch->id
        : 'setup-add-round-robin-match-modal-'.$tournament->id;
    $bracketOptions = $tournament->registrations
        ->filter(fn ($registration): bool => filled($registration->bracket_code))
        ->groupBy('bracket_code')
        ->filter(fn ($registrations): bool => $registrations->count() >= 2)
        ->keys()
        ->values();
    $selectedBracketCode = old('round_robin_bracket_code', $bracketOptions->first());
    $modalId = str($modalName)->slug('-')->toString();
    $roundRobinPairingsByRegistration = collect($tournament->matches ?? [])
        ->filter(fn ($match): bool => $match->stage === 'round_robin')
        ->reduce(function ($carry, $match) {
            $homeId = (int) ($match->home_registration_id ?? 0);
            $awayId = (int) ($match->away_registration_id ?? 0);

            if ($homeId < 1 || $awayId < 1) {
                return $carry;
            }

            $carry[$homeId] ??= [];
            $carry[$awayId] ??= [];

            if (! in_array($awayId, $carry[$homeId], true)) {
                $carry[$homeId][] = $awayId;
            }

            if (! in_array($homeId, $carry[$awayId], true)) {
                $carry[$awayId][] = $homeId;
            }

            return $carry;
        }, []);
    $roundRobinRegistrationsScheduledOnPitch = isset($pitch) && $pitch
        ? collect($tournament->matches ?? [])
            ->filter(fn ($match): bool => $match->stage === 'round_robin' && (int) $match->pitch_id === (int) $pitch->id)
            ->flatMap(fn ($match): array => [
                (int) ($match->home_registration_id ?? 0),
                (int) ($match->away_registration_id ?? 0),
            ])
            ->filter(fn (int $registrationId): bool => $registrationId > 0)
            ->unique()
            ->values()
            ->all()
        : [];
@endphp
<flux:modal
    id="{{ $modalId }}"
    name="{{ $modalName }}"
    :show="$isAddOpen"
    class="max-w-3xl"
>
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <flux:heading size="lg">{{ __('Add Round Robin Match') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ isset($pitch) && $pitch
                        ? __('Choose the teams for :pitch and set the schedule for this round robin match.', ['pitch' => $pitch->name])
                        : __('Manually pair teams and assign a pitch for a round robin match.') }}
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
                <input type="hidden" name="round_robin_pitch_context" value="{{ $pitchContext }}">
                <input type="hidden" name="redirect_route" value="admin.tournaments.index">
                <input type="hidden" name="redirect_tab" value="round-robin">
                <input type="hidden" name="stage" value="round_robin">
                <input type="hidden" name="status" value="scheduled">

                @if ($bracketOptions->isNotEmpty())
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ __('Bracket') }}
                        <select
                            name="round_robin_bracket_code"
                            data-round-robin-bracket-select
                            class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                        >
                            @foreach ($bracketOptions as $bracketCode)
                                <option value="{{ $bracketCode }}" @selected((string) $selectedBracketCode === (string) $bracketCode)>{{ $bracketCode }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Only teams from the selected bracket will load into the matchup fields.') }}
                        </p>
                    </label>
                @endif

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ __('Home Team') }}
                        <select
                            name="home_registration_id"
                            required
                            data-round-robin-team-select
                            class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                        >
                            <option value="">{{ __('Select team') }}</option>
                            @foreach ($tournament->registrations as $registration)
                                <option
                                    value="{{ $registration->id }}"
                                    data-bracket-code="{{ $registration->bracket_code }}"
                                    @selected((string) old('home_registration_id') === (string) $registration->id)
                                >
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
                            data-round-robin-team-select
                            class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                        >
                            <option value="">{{ __('Select team') }}</option>
                            @foreach ($tournament->registrations as $registration)
                                <option
                                    value="{{ $registration->id }}"
                                    data-bracket-code="{{ $registration->bracket_code }}"
                                    @selected((string) old('away_registration_id') === (string) $registration->id)
                                >
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
                    @if (isset($pitch) && $pitch)
                        <div class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            {{ __('Pitch') }}
                            <input type="hidden" name="pitch_id" value="{{ old('pitch_id', $pitch->id) }}">
                            <div class="mt-2 rounded-lg border border-neutral-300 bg-zinc-50 px-3 py-2 text-sm text-zinc-900 shadow-sm dark:border-neutral-700 dark:bg-zinc-900 dark:text-white">
                                {{ $pitch->name }}
                            </div>
                            @error('pitch_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @else
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            {{ __('Pitch') }}
                            <select
                                name="pitch_id"
                                class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                            >
                                <option value="">{{ __('No pitch assigned') }}</option>
                                @foreach ($tournament->pitches as $pitchOption)
                                    <option value="{{ $pitchOption->id }}" @selected((string) old('pitch_id') === (string) $pitchOption->id)>{{ $pitchOption->name }}</option>
                                @endforeach
                            </select>
                            @error('pitch_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </label>
                    @endif

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

    @if ($bracketOptions->isNotEmpty())
        <script>
            (() => {
                const modal = document.getElementById(@js($modalId));
                const existingOpponentsByRegistration = @json($roundRobinPairingsByRegistration);
                const pitchScheduledRegistrationIds = @json($roundRobinRegistrationsScheduledOnPitch);

                if (!modal || modal.dataset.roundRobinBracketFilterInitialized === 'true') {
                    return;
                }

                modal.dataset.roundRobinBracketFilterInitialized = 'true';

                const bracketSelect = modal.querySelector('[data-round-robin-bracket-select]');
                const teamSelects = modal.querySelectorAll('[data-round-robin-team-select]');

                if (!bracketSelect || teamSelects.length === 0) {
                    return;
                }

                const syncTeamOptions = () => {
                    const selectedBracket = bracketSelect.value;
                    const selectedValues = Array.from(teamSelects)
                        .map((select) => select.value)
                        .filter((value) => value !== '');
                    const bracketOptionValues = Array.from(teamSelects[0].options)
                        .filter((option, index) => index > 0 && option.dataset.bracketCode === selectedBracket)
                        .map((option) => option.value);

                    teamSelects.forEach((select) => {
                        const counterpartSelection = Array.from(teamSelects)
                            .find((teamSelect) => teamSelect !== select)?.value ?? '';

                        Array.from(select.options).forEach((option, index) => {
                            if (index === 0) {
                                option.hidden = false;
                                option.disabled = false;

                                return;
                            }

                            const matchesBracket = option.dataset.bracketCode === selectedBracket;
                            const selectedInOtherField = selectedValues.includes(option.value) && select.value !== option.value;
                            const alreadyScheduledOnPitch = pitchScheduledRegistrationIds.includes(Number(option.value)) || pitchScheduledRegistrationIds.includes(option.value);
                            const scheduledOpponents = existingOpponentsByRegistration[option.value] ?? [];
                            const availableOpponents = bracketOptionValues.filter((candidateValue) => {
                                if (candidateValue === option.value) {
                                    return false;
                                }

                                if (pitchScheduledRegistrationIds.includes(Number(candidateValue)) || pitchScheduledRegistrationIds.includes(candidateValue)) {
                                    return false;
                                }

                                if (scheduledOpponents.includes(Number(candidateValue)) || scheduledOpponents.includes(candidateValue)) {
                                    return false;
                                }

                                if (counterpartSelection !== '' && candidateValue !== counterpartSelection) {
                                    return false;
                                }

                                return true;
                            });
                            const hasAvailableOpponent = availableOpponents.length > 0;
                            const shouldShow = matchesBracket && !selectedInOtherField && !alreadyScheduledOnPitch && hasAvailableOpponent;

                            option.hidden = !shouldShow;
                            option.disabled = !shouldShow;

                            if (!shouldShow && option.selected) {
                                select.value = '';
                            }
                        });
                    });

                    if (teamSelects.length >= 2) {
                        const [homeSelect, awaySelect] = teamSelects;
                        const homeScheduledOpponents = existingOpponentsByRegistration[homeSelect.value] ?? [];

                        Array.from(awaySelect.options).forEach((option, index) => {
                            if (index === 0) {
                                return;
                            }

                            const alreadyPairedWithHome = homeSelect.value !== ''
                                && (homeScheduledOpponents.includes(Number(option.value)) || homeScheduledOpponents.includes(option.value));

                            if (alreadyPairedWithHome) {
                                option.hidden = true;
                                option.disabled = true;

                                if (option.selected) {
                                    awaySelect.value = '';
                                }
                            }
                        });
                    }
                };

                bracketSelect.addEventListener('change', syncTeamOptions);
                teamSelects.forEach((select) => {
                    select.addEventListener('change', syncTeamOptions);
                });
                syncTeamOptions();
            })();
        </script>
    @endif
</flux:modal>
