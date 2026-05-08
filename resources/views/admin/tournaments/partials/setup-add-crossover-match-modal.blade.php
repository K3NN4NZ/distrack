@php
    use App\Support\BracketCodes;

    $matchModalTournamentId = (int) old('match_tournament_id', 0);
    $isAddOpen = $matchModalTournamentId === (int) $tournament->id
        && old('match_form_intent') === 'crossover_add';

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
@endphp
<flux:modal
    name="setup-add-crossover-match-modal-{{ $tournament->id }}"
    :show="$isAddOpen"
    class="max-w-3xl"
>
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <flux:heading size="lg">{{ __('Add crossover match') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ __('Pair teams that already have bracket ranks (e.g. A1 vs B2). Teams must come from different brackets.') }}
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
                action="{{ route('admin.tournaments.matches.store') }}"
                class="space-y-4"
                x-data="{
                    home: @js((string) old('home_registration_id', '')),
                    away: @js((string) old('away_registration_id', '')),
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
                <input type="hidden" name="tournament_id" value="{{ $tournament->id }}">
                <input type="hidden" name="match_tournament_id" value="{{ $tournament->id }}">
                <input type="hidden" name="match_form_intent" value="crossover_add">
                <input type="hidden" name="redirect_route" value="admin.tournaments.index">
                <input type="hidden" name="redirect_tab" value="crossover">
                <input type="hidden" name="stage" value="crossover">

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ __('Home (from bracket ranking)') }}
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
                        @error('home_registration_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </label>

                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ __('Away (other bracket)') }}
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
                        @error('away_registration_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </label>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <flux:input name="round_label" :label="__('Round label')" :value="old('round_label', __('Crossover'))" type="text" />
                    <flux:input name="match_number" :label="__('Match number')" :value="old('match_number')" type="number" min="1" />
                    <flux:input name="scheduled_at" :label="__('Scheduled at')" :value="old('scheduled_at')" type="datetime-local" />
                </div>

                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    {{ __('Playing field') }}
                    <select
                        name="pitch_id"
                        class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                    >
                        <option value="">{{ __('No field assigned') }}</option>
                        @foreach ($tournament->pitches as $pitch)
                            <option value="{{ $pitch->id }}" @selected((string) old('pitch_id') === (string) $pitch->id)>{{ $pitch->name }}</option>
                        @endforeach
                    </select>
                    @error('pitch_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </label>

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

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input name="home_score" :label="__('Home score')" :value="old('home_score')" type="number" min="0" />
                    <flux:input name="away_score" :label="__('Away score')" :value="old('away_score')" type="number" min="0" />
                </div>

                <flux:button type="submit" variant="primary" class="w-full">
                    {{ __('Schedule crossover match') }}
                </flux:button>
            </form>
        @else
            <div class="rounded-xl border border-dashed border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100">
                {{ __('Need at least two ranked teams. Apply bracket ranking on the Bracket Ranking tab first.') }}
            </div>
        @endif
    </div>
</flux:modal>
