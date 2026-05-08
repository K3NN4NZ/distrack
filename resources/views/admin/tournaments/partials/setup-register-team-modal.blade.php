@php
    $registeredTeamIds = $tournament->registrations
        ->pluck('team_id')
        ->map(fn ($teamId): int => (int) $teamId)
        ->all();
@endphp

<flux:modal
    name="setup-register-team-modal-{{ $tournament->id }}"
    :show="$show"
    class="max-w-3xl"
>
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <flux:heading size="lg">{{ __('Register Team') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ __('Attach an existing team to :tournament without leaving the setup workspace.', ['tournament' => $tournament->name]) }}
                </flux:text>
            </div>

            <flux:modal.close>
                <flux:button variant="ghost">
                    {{ __('Close') }}
                </flux:button>
            </flux:modal.close>
        </div>

        <form method="POST" action="{{ route('admin.tournaments.registrations.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="tournament_id" value="{{ $tournament->id }}">
            <input type="hidden" name="registration_tournament_id" value="{{ $tournament->id }}">
            <input type="hidden" name="redirect_route" value="admin.tournaments.index">
            <input type="hidden" name="redirect_tab" value="bracket-ranking">

            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                {{ __('Team') }}
                <select
                    name="team_id"
                    required
                    class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                >
                    <option value="">{{ __('Select team') }}</option>
                    @foreach ($availableTeams as $team)
                        @php($isRegistered = in_array($team->id, $registeredTeamIds, true))
                        <option value="{{ $team->id }}" {{ $isRegistered ? 'disabled' : '' }} @selected((string) old('team_id') === (string) $team->id)>
                            {{ $team->name }} | {{ trans_choice('{1} :count member|[2,*] :count members', $team->members_count, ['count' => $team->members_count]) }}{{ $isRegistered ? ' | '.__('Already registered') : '' }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('Disabled teams are already registered in this tournament.') }}
                </p>
                @error('team_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </label>

            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Register Team') }}
            </flux:button>
        </form>
    </div>
</flux:modal>
