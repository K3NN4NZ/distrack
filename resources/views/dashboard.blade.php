@php
    $user = auth()->user();
    $ownedTeamsCount = $user->ownedTeams()->count();
    $rosterMembersCount = \App\Models\TeamMember::query()
        ->whereHas('team', fn ($query) => $query->where('owner_user_id', $user->id))
        ->count();
    $activeTournamentsCount = \App\Models\Tournament::query()
        ->whereIn('status', ['draft', 'registration', 'live'])
        ->count();
    $myRegistrationsCount = \App\Models\TournamentRegistration::query()
        ->whereHas('team', fn ($query) => $query->where('owner_user_id', $user->id))
        ->count();
    $scheduledMatchesCount = \App\Models\TournamentMatch::query()->count();
@endphp

<x-layouts::app :title="__('Dashboard')">
    <div class="space-y-6">
        <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <flux:heading size="xl">{{ __('DISCTRACK Dashboard') }}</flux:heading>
                    <flux:text class="mt-2 max-w-3xl">
                        {{ __('This project now has the first tournament-management slice: team setup, roster management, tournament setup, pitch planning, and tournament registrations.') }}
                    </flux:text>
                </div>

                <div class="rounded-lg border border-neutral-200 px-4 py-2 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                    {{ __('Role') }}:
                    <span class="font-semibold text-zinc-900 dark:text-white">{{ str($user->role)->headline() }}</span>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            @if ($user->isCaptain())
                <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-zinc-900">
                    <div class="text-sm text-zinc-500">{{ __('My Teams') }}</div>
                    <div class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-white">{{ $ownedTeamsCount }}</div>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-zinc-900">
                    <div class="text-sm text-zinc-500">{{ __('Roster Members') }}</div>
                    <div class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-white">{{ $rosterMembersCount }}</div>
                </div>
            @endif
            <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-zinc-900">
                <div class="text-sm text-zinc-500">{{ __('Active Tournaments') }}</div>
                <div class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-white">{{ $activeTournamentsCount }}</div>
            </div>
            @if ($user->isCaptain())
                <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-zinc-900">
                    <div class="text-sm text-zinc-500">{{ __('My Registrations') }}</div>
                    <div class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-white">{{ $myRegistrationsCount }}</div>
                </div>
            @endif
            <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-zinc-900">
                <div class="text-sm text-zinc-500">{{ __('Scheduled Matches') }}</div>
                <div class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-white">{{ $scheduledMatchesCount }}</div>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            @if ($user->isCaptain())
                <a
                    href="{{ route('teams.index') }}"
                    wire:navigate
                    class="rounded-xl border border-neutral-200 bg-white p-6 transition hover:border-zinc-400 dark:border-neutral-700 dark:bg-zinc-900 dark:hover:border-zinc-500"
                >
                    <div class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Captain Team Center') }}</div>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                        {{ __('Register teams, maintain rosters, and prepare team details for tournament registration.') }}
                    </p>
                </a>
            @else
                <div class="rounded-xl border border-dashed border-neutral-300 bg-zinc-50 p-6 text-sm text-zinc-600 dark:border-neutral-700 dark:bg-zinc-900 dark:text-zinc-300">
                    {{ __('Team management is limited to captain accounts.') }}
                </div>
            @endif

            @if ($user->isAdmin())
                <a
                    href="{{ route('admin.tournaments.index') }}"
                    wire:navigate
                    class="rounded-xl border border-neutral-200 bg-white p-6 transition hover:border-zinc-400 dark:border-neutral-700 dark:bg-zinc-900 dark:hover:border-zinc-500"
                >
                    <div class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Admin Tournament Setup') }}</div>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                        {{ __('Create tournaments, add pitches, and register teams into seedable tournament slots.') }}
                    </p>
                </a>
            @elseif (! $user->isScorekeeper())
                <div class="rounded-xl border border-dashed border-neutral-300 bg-zinc-50 p-6 text-sm text-zinc-600 dark:border-neutral-700 dark:bg-zinc-900 dark:text-zinc-300">
                    {{ __('Admin tools are available after a user is promoted to the admin role.') }}
                </div>
            @endif

            @if ($user->isScorekeeper())
                <a
                    href="{{ route('admin.tournaments.list') }}"
                    wire:navigate
                    class="rounded-xl border border-neutral-200 bg-white p-6 transition hover:border-zinc-400 dark:border-neutral-700 dark:bg-zinc-900 dark:hover:border-zinc-500"
                >
                    <div class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Scorekeeper Console') }}</div>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                        {{ __('Open the tournament directory, choose a tournament, and run live scoring without full admin setup access.') }}
                    </p>
                </a>
            @endif
        </section>
    </div>
</x-layouts::app>
