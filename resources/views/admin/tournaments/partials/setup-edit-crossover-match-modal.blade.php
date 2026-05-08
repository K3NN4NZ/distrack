@php
    use App\Support\BracketCodes;

    $editMatchOldId = old('edit_match_id');
    $isThisMatchInOld = $editMatchOldId !== null && (int) $editMatchOldId === (int) $match->id;

    $scheduledAtValue = $match->scheduled_at?->format('Y-m-d\TH:i');

    $rankedRegistrations = $tournament->registrations
        ->filter(fn ($registration): bool => filled(trim((string) ($registration->bracket_rank ?? ''))))
        ->values();

    $teamCatalog = $rankedRegistrations
        ->map(function ($registration): array {
            return [
                'id' => (string) $registration->id,
                'bracket' => (string) (BracketCodes::normalize($registration->bracket_code) ?? ''),
                'label' => $registration->team->name
                    .' · '.($registration->bracket_rank ?? '')
                    .($registration->bracket_code ? ' ('.$registration->bracket_code.')' : ''),
            ];
        })
        ->values()
        ->all();

    $initialHome = $isThisMatchInOld ? (string) old('home_registration_id', '') : (string) $match->home_registration_id;
    $initialAway = $isThisMatchInOld ? (string) old('away_registration_id', '') : (string) $match->away_registration_id;
@endphp
<flux:modal
    name="setup-edit-crossover-match-modal-{{ $match->id }}"
    :show="$isThisMatchInOld"
    class="max-w-3xl"
>
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <flux:heading size="lg">{{ __('Edit crossover match') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ __('Adjust teams (must stay on different brackets), field, or schedule.') }}
                </flux:text>
            </div>

            <flux:modal.close>
                <flux:button variant="ghost">
                    {{ __('Close') }}
                </flux:button>
            </flux:modal.close>
        </div>

        @if ($rankedRegistrations->count() >= 2)
            <form
                method="POST"
                action="{{ route('admin.tournaments.matches.update', ['match' => $match->id]) }}"
                class="space-y-4"
                x-data="{
                    home: @js($initialHome),
                    away: @js($initialAway),
                    teams: @js($teamCatalog),
                    homeBracket() {
                        const row = this.teams.find((t) => t.id === this.home);

                        return row ? row.bracket : '';
                    },
                    awayBracket() {
                        const row = this.teams.find((t) => t.id === this.away);

                        return row ? row.bracket : '';
                    },
                    homeOptions() {
                        return this.teams.filter((t) => {
                            if (this.away !== '' && t.id === this.away) {
                                return false;
                            }

                            const ab = this.awayBracket();

                            return ! (ab !== '' && t.bracket === ab);
                        });
                    },
                    awayOptions() {
                        return this.teams.filter((t) => {
                            if (this.home !== '' && t.id === this.home) {
                                return false;
                            }

                            const hb = this.homeBracket();

                            return ! (hb !== '' && t.bracket === hb);
                        });
                    },
                    syncSelections() {
                        if (this.home !== '' && this.away !== '' && this.homeBracket() !== '' && this.homeBracket() === this.awayBracket()) {
                            this.away = '';
                        }

                        if (this.home !== '' && ! this.homeOptions().some((t) => t.id === this.home)) {
                            this.home = '';
                        }

                        if (this.away !== '' && ! this.awayOptions().some((t) => t.id === this.away)) {
                            this.away = '';
                        }
                    },
                }"
                x-init="$watch('home', () => syncSelections()); $watch('away', () => syncSelections());"
            >
                @csrf
                @method('PUT')
                <input type="hidden" name="edit_match_id" value="{{ $match->id }}">
                <input type="hidden" name="redirect_route" value="admin.tournaments.index">
                <input type="hidden" name="redirect_tab" value="crossover">

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ __('Home') }}
                        <select
                            name="home_registration_id"
                            required
                            x-model="home"
                            class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                        >
                            <option value="">{{ __('Select team') }}</option>
                            <template x-for="team in homeOptions()" :key="team.id">
                                <option :value="team.id" x-text="team.label"></option>
                            </template>
                        </select>
                    </label>

                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ __('Away') }}
                        <select
                            name="away_registration_id"
                            required
                            x-model="away"
                            class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                        >
                            <option value="">{{ __('Select team') }}</option>
                            <template x-for="team in awayOptions()" :key="team.id">
                                <option :value="team.id" x-text="team.label"></option>
                            </template>
                        </select>
                    </label>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ __('Playing field') }}
                        <select
                            name="pitch_id"
                            class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                        >
                            <option value="">{{ __('No field assigned') }}</option>
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
                        :label="__('Round label')"
                        :value="$isThisMatchInOld ? old('round_label', $match->round_label) : $match->round_label"
                        type="text"
                    />
                    <flux:input
                        name="match_number"
                        :label="__('Match number')"
                        :value="$isThisMatchInOld ? old('match_number', $match->match_number) : $match->match_number"
                        type="number"
                        min="1"
                    />
                </div>

                <flux:input
                    name="scheduled_at"
                    :label="__('Scheduled at')"
                    :value="$isThisMatchInOld ? old('scheduled_at', $scheduledAtValue) : $scheduledAtValue"
                    type="datetime-local"
                />

                <flux:button type="submit" variant="primary" class="w-full">
                    {{ __('Save changes') }}
                </flux:button>
            </form>
        @else
            <div class="rounded-xl border border-dashed border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100">
                {{ __('Not enough ranked teams remain to edit this pairing.') }}
            </div>
        @endif
    </div>
</flux:modal>
