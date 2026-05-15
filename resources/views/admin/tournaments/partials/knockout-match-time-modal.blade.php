@php
    use App\Support\SmallDayTwoKnockoutBracket;
    use App\Support\SmallFixedRoundRobinDayOneSchedule;

    /** @var \App\Models\Tournament $selectedTournament */
    /** @var \App\Models\TournamentMatch $match */
    /** @var int $gameNum */

    $modalName = 'knockout-match-time-modal-'.$match->id;
    $openKey = 'edit_knockout_time_match_id';
    $showModal = (string) old($openKey) === (string) $match->id;
    $timeDefaults = SmallDayTwoKnockoutBracket::matchTimeFormDefaults($match, $selectedTournament);
    $tzLabel = SmallFixedRoundRobinDayOneSchedule::tournamentTimezone($selectedTournament);
    $inputClass = 'mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white';
@endphp
<flux:modal
    name="{{ $modalName }}"
    :show="$showModal"
    class="max-w-md"
>
    <div class="space-y-5">
        <div class="flex items-start justify-between gap-4">
            <div>
                <flux:heading size="lg">{{ __('Edit match time') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                    {{ __('Game :num — date stays fixed; adjust start and end times only (:tz).', ['num' => $gameNum, 'tz' => $tzLabel]) }}
                </flux:text>
            </div>
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Close') }}</flux:button>
            </flux:modal.close>
        </div>

        <form
            method="POST"
            action="{{ route('admin.tournaments.matches.time-range.update', ['tournament' => $selectedTournament, 'match' => $match]) }}"
            class="space-y-4"
        >
            @csrf
            @method('PATCH')
            <input type="hidden" name="{{ $openKey }}" value="{{ $match->id }}">
            <input type="hidden" name="redirect_route" value="admin.tournaments.index">
            <input type="hidden" name="redirect_tab" value="{{ $knockoutScheduleRedirectTab }}">

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    {{ __('Start time') }}
                    <input
                        type="time"
                        name="start_time"
                        value="{{ old('start_time', $timeDefaults['start_time']) }}"
                        required
                        class="{{ $inputClass }}"
                    >
                    @error('start_time')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </label>
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    {{ __('End time') }}
                    <input
                        type="time"
                        name="end_time"
                        value="{{ old('end_time', $timeDefaults['end_time']) }}"
                        required
                        class="{{ $inputClass }}"
                    >
                    @error('end_time')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </label>
            </div>

            <div class="flex flex-wrap justify-end gap-2 border-t border-neutral-200 pt-4 dark:border-neutral-700">
                <flux:modal.close>
                    <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save time') }}</flux:button>
            </div>
        </form>
    </div>
</flux:modal>
