<x-layouts::app :title="__('Tournament Registration')">
    <div class="space-y-6">
        <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div>
                    <flux:heading size="xl">{{ __('Available Tournaments') }}</flux:heading>
                    <flux:text class="mt-2 max-w-3xl">
                        {{ __('This captain view is separate from the public event board. Pick one of your teams and submit a tournament registration request from here.') }}
                    </flux:text>
                </div>

                <div class="rounded-lg border border-neutral-200 px-4 py-2 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                    {{ trans_choice('{0} No tournaments open|{1} :count tournament open|[2,*] :count tournaments open', $tournaments->count(), ['count' => $tournaments->count()]) }}
                </div>
            </div>

            @if (session('status'))
                <div class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-950/40 dark:text-green-300">
                    @switch(session('status'))
                        @case('team-registered')
                            {{ __('Tournament registration submitted successfully.') }}
                            @break
                        @case('team-already-registered')
                            {{ __('That team is already registered in the selected tournament.') }}
                            @break
                        @default
                            {{ __('Saved.') }}
                    @endswitch
                </div>
            @endif
        </section>

        @if ($ownedTeams->isEmpty())
            <section class="rounded-xl border border-dashed border-neutral-300 bg-zinc-50 p-6 text-sm text-zinc-600 dark:border-neutral-700 dark:bg-zinc-900 dark:text-zinc-300">
                {{ __('You need to create a team first before registering for tournaments.') }}
                <a href="{{ route('teams.index') }}" wire:navigate class="ml-2 font-medium text-zinc-900 underline dark:text-white">
                    {{ __('Open Teams') }}
                </a>
            </section>
        @endif

        <section class="grid gap-4 xl:grid-cols-2">
            @forelse ($tournaments as $tournament)
                @php
                    $myRegistrations = $tournament->registrations
                        ->filter(fn ($registration) => $registration->team?->owner_user_id === auth()->id());
                    $registeredTeamIds = $myRegistrations
                        ->pluck('team_id')
                        ->map(fn ($teamId) => (int) $teamId)
                        ->all();
                    $availableOwnedTeams = $ownedTeams
                        ->reject(fn ($team) => in_array($team->id, $registeredTeamIds, true))
                        ->values();
                @endphp

                <article class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-xl font-semibold text-zinc-900 dark:text-white">{{ $tournament->name }}</h2>
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $tournament->locationLabel() ?: __('Venue to be announced') }}</p>

                            <div class="mt-3 flex flex-wrap gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                @if ($tournament->division)
                                    <span class="rounded-full border border-neutral-200 px-3 py-1 dark:border-neutral-700">{{ $tournament->division }}</span>
                                @endif
                                @if ($tournament->surface)
                                    <span class="rounded-full border border-neutral-200 px-3 py-1 dark:border-neutral-700">{{ $tournament->surface }}</span>
                                @endif
                                <span class="rounded-full border border-neutral-200 px-3 py-1 dark:border-neutral-700">
                                    {{ __('Registrations: :count', ['count' => $tournament->registrations_count]) }}
                                </span>
                            </div>
                        </div>

                        <div class="rounded-lg border border-neutral-200 px-4 py-2 text-xs text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                            {{ $tournament->registration_deadline
                                ? __('Deadline: :date', ['date' => $tournament->registration_deadline->format('M j, Y g:i A')])
                                : __('Deadline not published') }}
                        </div>
                    </div>

                    @if (filled($tournament->description))
                        <p class="mt-4 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $tournament->description }}</p>
                    @endif

                    @if ($myRegistrations->isNotEmpty())
                        <div class="mt-5 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-950/40 dark:text-green-300">
                            <div class="font-medium">{{ __('Your Registered Teams') }}</div>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($myRegistrations as $registration)
                                    <span class="rounded-full border border-green-200 bg-white px-3 py-1 text-xs dark:border-green-900/60 dark:bg-zinc-900">
                                        {{ $registration->team?->name ?? __('Team removed') }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="mt-5 rounded-xl border border-neutral-200 bg-zinc-50 p-4 dark:border-neutral-700 dark:bg-zinc-950">
                        <div class="mb-3">
                            <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Register Team') }}</h3>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Choose one of your owned teams that is not yet registered in this tournament.') }}</p>
                        </div>

                        @if ($availableOwnedTeams->isEmpty())
                            <div class="rounded-lg border border-dashed border-neutral-300 p-4 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                                {{ $ownedTeams->isEmpty()
                                    ? __('No teams available yet. Create a team first.')
                                    : __('All of your teams are already registered in this tournament.') }}
                            </div>
                        @else
                            <form method="POST" action="{{ route('captain.tournaments.registrations.store') }}" class="space-y-4">
                                @csrf
                                <input type="hidden" name="tournament_id" value="{{ $tournament->id }}">

                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                    {{ __('Team') }}
                                    <select
                                        name="team_id"
                                        required
                                        class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-900 dark:text-white"
                                    >
                                        <option value="">{{ __('Select your team') }}</option>
                                        @foreach ($availableOwnedTeams as $team)
                                            <option value="{{ $team->id }}" @selected((string) old('team_id') === (string) $team->id && (int) old('tournament_id') === $tournament->id)>
                                                {{ $team->name }} | {{ trans_choice('{1} :count member|[2,*] :count members', $team->members_count, ['count' => $team->members_count]) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @if ((int) old('tournament_id') === $tournament->id)
                                        @error('team_id')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    @endif
                                </label>

                                <flux:button type="submit" variant="primary" class="w-full">
                                    {{ __('Register This Team') }}
                                </flux:button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-neutral-300 bg-zinc-50 p-6 text-sm text-zinc-600 dark:border-neutral-700 dark:bg-zinc-900 dark:text-zinc-300 xl:col-span-2">
                    {{ __('No tournaments are currently open for captain registration.') }}
                </div>
            @endforelse
        </section>
    </div>
</x-layouts::app>
