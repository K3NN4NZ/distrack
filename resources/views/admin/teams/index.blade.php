<x-layouts::app :title="__('Teams')">
    @php
        $showCreateModal = old('create_team_modal') === '1';
    @endphp

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-950/40 dark:text-green-300">
                @switch(session('status'))
                    @case('team-created')
                        {{ __('Team created successfully.') }}
                        @break
                    @case('team-updated')
                        {{ __('Team details updated successfully.') }}
                        @break
                    @case('team-deleted')
                        {{ __('Team deleted successfully.') }}
                        @break
                    @case('team-delete-blocked')
                        {{ __('This team is already tied to tournament records and cannot be deleted.') }}
                        @break
                    @default
                        {{ __('Saved.') }}
                @endswitch
            </div>
        @endif

        <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div>
                    <flux:heading size="xl">{{ __('Teams Directory') }}</flux:heading>
                    <flux:text class="mt-2 max-w-3xl">
                        {{ __('Search teams, open profiles, edit details, and manage rosters from one place.') }}
                    </flux:text>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <div class="rounded-lg border border-neutral-200 px-4 py-2 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                        {{ trans_choice('{1} :count team|[2,*] :count teams', $teams->count(), ['count' => $teams->count()]) }}
                    </div>

                    <div class="rounded-lg border border-neutral-200 px-4 py-2 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                        {{ trans_choice('{1} :count total roster|[2,*] :count total roster', $totalRosterCount, ['count' => $totalRosterCount]) }}
                    </div>

                    <flux:modal.trigger name="create-team-modal">
                        <flux:button variant="primary">
                            {{ __('+ Add Team') }}
                        </flux:button>
                    </flux:modal.trigger>
                </div>
            </div>

            <form method="GET" action="{{ route('admin.teams.index') }}" class="mt-6 grid gap-4 border-t border-neutral-200 pt-6 dark:border-neutral-700 md:grid-cols-[1fr_1fr_auto] md:items-end">
                <flux:input name="q" :label="__('Search by name')" :value="$filters['q'] ?? ''" type="search" placeholder="{{ __('Team name or code…') }}" />

                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Tournament') }}</label>
                    <select
                        name="tournament_id"
                        class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                    >
                        <option value="">{{ __('All teams') }}</option>
                        @foreach ($tournaments as $t)
                            <option value="{{ $t->id }}" @selected((string) ($filters['tournament_id'] ?? '') === (string) $t->id)>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex gap-2">
                    <flux:button type="submit" variant="filled">{{ __('Apply') }}</flux:button>
                    <a href="{{ route('admin.teams.index') }}" wire:navigate class="inline-flex items-center justify-center rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50 dark:border-neutral-600 dark:text-zinc-200 dark:hover:bg-zinc-800">
                        {{ __('Reset') }}
                    </a>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900">
            @if ($teams->isEmpty())
                <div class="p-6 text-sm text-zinc-600 dark:text-zinc-300">
                    {{ __('No teams match your filters.') }}
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/60">
                            <tr class="text-left text-sm text-zinc-600 dark:text-zinc-300">
                                <th class="px-4 py-3 font-medium">{{ __('Logo') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Team') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Code') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Captain') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Spirit Captain') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Roster') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Tournaments') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Location') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @foreach ($teams as $team)
                                @php
                                    $captain = $team->members->firstWhere('role', 'captain');
                                    $spiritCaptain = $team->members->firstWhere('role', 'spirit_captain');
                                    $teamDeleteLocked = $team->registrations_count > 0 || $team->members_with_match_stats;
                                    $logoUrl = $team->logoUrl();
                                @endphp
                                <tr class="text-sm text-zinc-700 dark:text-zinc-200">
                                    <td class="px-4 py-3">
                                        @if ($logoUrl)
                                            <img src="{{ $logoUrl }}" alt="" class="h-12 w-12 rounded-lg border border-neutral-200 object-cover dark:border-neutral-700">
                                        @else
                                            <div class="flex h-12 w-12 items-center justify-center rounded-lg border border-neutral-200 bg-zinc-100 text-xs font-bold text-zinc-600 dark:border-neutral-700 dark:bg-zinc-800 dark:text-zinc-300">
                                                {{ $team->initials() }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-zinc-900 dark:text-white">{{ $team->name }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">{{ $team->short_name ?: '—' }}</td>
                                    <td class="px-4 py-3">{{ $captain?->name ?? __('Not assigned') }}</td>
                                    <td class="px-4 py-3">{{ $spiritCaptain?->name ?? __('Not assigned') }}</td>
                                    <td class="px-4 py-3">{{ $team->members_count }}</td>
                                    <td class="px-4 py-3">{{ $team->registrations_count }}</td>
                                    <td class="px-4 py-3">{{ $team->locationLabel() ?? __('No location') }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                                            {{ str($team->status)->headline() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-col gap-1.5">
                                            <a href="{{ route('admin.teams.show', $team) }}" wire:navigate class="text-sm font-medium text-[#2f55b7] hover:underline dark:text-sky-300">{{ __('View') }}</a>
                                            <a href="{{ route('admin.teams.edit', $team) }}" wire:navigate class="text-sm font-medium text-[#2f55b7] hover:underline dark:text-sky-300">{{ __('Edit Team') }}</a>
                                            <a href="{{ route('admin.teams.roster.index', $team) }}" wire:navigate class="text-sm font-medium text-[#2f55b7] hover:underline dark:text-sky-300">{{ __('Manage Roster') }}</a>
                                            @if ($teamDeleteLocked)
                                                <span class="text-[11px] font-medium text-amber-700 dark:text-amber-300">{{ __('Delete locked') }}</span>
                                            @else
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.teams.destroy', $team) }}"
                                                    onsubmit="return confirm('{{ __('Delete this team and its roster?') }}')"
                                                    class="inline"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-left text-sm font-medium text-red-600 hover:underline dark:text-red-400">
                                                        {{ __('Delete') }}
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <flux:modal name="create-team-modal" :show="$showCreateModal" class="max-w-3xl">
            <div class="max-h-[85vh] space-y-6 overflow-y-auto pr-1">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <flux:heading size="lg">{{ __('Create Team') }}</flux:heading>
                        <flux:text class="mt-1">
                            {{ __('Register a new team with captain and optional spirit captain.') }}
                        </flux:text>
                    </div>
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Close') }}</flux:button>
                    </flux:modal.close>
                </div>

                <form method="POST" action="{{ route('admin.teams.store') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <input type="hidden" name="create_team_modal" value="1">

                    <flux:input name="name" :label="__('Team Name')" :value="old('name')" type="text" required />

                    <div class="grid gap-4 md:grid-cols-2">
                        <flux:input name="captain_name" :label="__('Captain')" :value="old('captain_name')" type="text" required autocomplete="name" />
                        <flux:input name="spirit_captain_name" :label="__('Spirit Captain')" :value="old('spirit_captain_name')" type="text" autocomplete="name" />
                    </div>

                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ __('Address') }}
                        <input name="address" type="text" value="{{ old('address') }}" required class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm dark:border-neutral-700 dark:bg-zinc-950 dark:text-white" />
                        @error('address')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </label>

                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            {{ __('City') }}
                            <input name="city" type="text" value="{{ old('city') }}" required class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-zinc-950 dark:text-white" />
                            @error('city')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </label>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            {{ __('Province') }}
                            <input name="province" type="text" value="{{ old('province') }}" required class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-zinc-950 dark:text-white" />
                            @error('province')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </label>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            {{ __('Country') }}
                            <input name="country_name" type="text" value="{{ old('country_name', 'Philippines') }}" class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-zinc-950 dark:text-white" />
                        </label>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            {{ __('Status') }}
                            <select name="status" required class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-zinc-950 dark:text-white">
                                @foreach (['active', 'inactive', 'archived'] as $status)
                                    <option value="{{ $status }}" @selected(old('status', 'active') === $status)>{{ str($status)->headline() }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ __('Team Logo') }}
                        <input name="logo" type="file" accept=".jpg,.jpeg,.png,.webp" class="mt-2 block w-full text-sm" />
                        <p class="mt-1 text-xs text-zinc-500">{{ __('JPG, PNG, or WEBP up to 2 MB.') }}</p>
                        @error('logo')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </label>

                    <flux:button type="submit" variant="primary" class="w-full">{{ __('Create Team') }}</flux:button>
                </form>
            </div>
        </flux:modal>
    </div>
</x-layouts::app>
