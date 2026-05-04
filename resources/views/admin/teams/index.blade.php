<x-layouts::app :title="__('Teams')">
    @php
        $editModalTeamId = old('edit_team_id')
            ? (int) old('edit_team_id')
            : null;
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
                        {{ __('Create, edit, and delete team profiles while keeping owner assignments, roster leadership, and tournament usage visible in one admin page.') }}
                    </flux:text>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <div class="rounded-lg border border-neutral-200 px-4 py-2 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                        {{ trans_choice('{1} :count team|[2,*] :count teams', $teams->count(), ['count' => $teams->count()]) }}
                    </div>

                    <flux:modal.trigger name="create-team-modal">
                        <flux:button variant="primary">
                            {{ __('+ Add Team') }}
                        </flux:button>
                    </flux:modal.trigger>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900">
            @if ($teams->isEmpty())
                <div class="p-6 text-sm text-zinc-600 dark:text-zinc-300">
                    {{ __('No teams have been created yet.') }}
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/60">
                            <tr class="text-left text-sm text-zinc-600 dark:text-zinc-300">
                                <th class="px-5 py-3 font-medium">{{ __('Team') }}</th>
                                <th class="px-5 py-3 font-medium">{{ __('Owner') }}</th>
                                <th class="px-5 py-3 font-medium">{{ __('Captain') }}</th>
                                <th class="px-5 py-3 font-medium">{{ __('Spirit Captain') }}</th>
                                <th class="px-5 py-3 font-medium">{{ __('Roster') }}</th>
                                <th class="px-5 py-3 font-medium">{{ __('Tournaments') }}</th>
                                <th class="px-5 py-3 font-medium">{{ __('Location') }}</th>
                                <th class="px-5 py-3 font-medium">{{ __('Status') }}</th>
                                <th class="px-5 py-3 font-medium">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @foreach ($teams as $team)
                                @php
                                    $captain = $team->members->firstWhere('role', 'captain');
                                    $spiritCaptain = $team->members->firstWhere('role', 'spirit_captain');
                                    $teamDeleteLocked = $team->registrations_count > 0 || $team->members_with_match_stats;
                                @endphp
                                <tr class="text-sm text-zinc-700 dark:text-zinc-200">
                                    <td class="px-5 py-4">
                                        <div class="font-semibold text-zinc-900 dark:text-white">{{ $team->name }}</div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div>{{ $team->owner?->name ?? __('No owner') }}</div>
                                        @if ($team->owner?->email)
                                            <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $team->owner->email }}</div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4">{{ $captain?->name ?? __('Not assigned') }}</td>
                                    <td class="px-5 py-4">{{ $spiritCaptain?->name ?? __('Not assigned') }}</td>
                                    <td class="px-5 py-4">{{ $team->members_count }}</td>
                                    <td class="px-5 py-4">{{ $team->registrations_count }}</td>
                                    <td class="px-5 py-4">{{ $team->locationLabel() ?? __('No location') }}</td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                                            {{ str($team->status)->headline() }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex flex-wrap gap-2">
                                            <flux:modal.trigger name="edit-team-modal-{{ $team->id }}">
                                                <flux:button variant="ghost" size="sm">
                                                    {{ __('Edit') }}
                                                </flux:button>
                                            </flux:modal.trigger>

                                            @if ($teamDeleteLocked)
                                                <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-[11px] font-medium text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-300">
                                                    {{ __('Locked') }}
                                                </span>
                                            @else
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.teams.destroy', $team) }}"
                                                    onsubmit="return confirm('{{ __('Delete this team and its roster?') }}')"
                                                >
                                                    @csrf
                                                    @method('DELETE')

                                                    <button
                                                        type="submit"
                                                        class="rounded-full border border-red-200 px-3 py-1 text-[11px] font-medium text-red-600 transition hover:border-red-300 hover:bg-red-50 dark:border-red-900/70 dark:text-red-300 dark:hover:bg-red-950/40"
                                                    >
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

        @foreach ($teams as $team)
            @php
                $teamLogo = $team->logoUrl();
                $isEditingThisTeam = $editModalTeamId === $team->id;
            @endphp

            <flux:modal
                name="edit-team-modal-{{ $team->id }}"
                :show="$isEditingThisTeam"
                class="max-w-3xl"
            >
                <div class="max-h-[85vh] space-y-6 overflow-y-auto pr-1">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <flux:heading size="lg">{{ __('Edit Team') }}</flux:heading>
                            <flux:text class="mt-1">
                                {{ __('Update :team without leaving the teams directory.', ['team' => $team->name]) }}
                            </flux:text>
                        </div>

                        <flux:modal.close>
                            <flux:button variant="ghost">
                                {{ __('Close') }}
                            </flux:button>
                        </flux:modal.close>
                    </div>

                    <form method="POST" action="{{ route('admin.teams.update', $team) }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="edit_team_id" value="{{ $team->id }}">

                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            {{ __('Owner') }}
                            <select
                                name="edit_owner_user_id"
                                required
                                class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                            >
                                @foreach ($owners as $owner)
                                    <option value="{{ $owner->id }}" @selected($isEditingThisTeam ? old('edit_owner_user_id') == $owner->id : $team->owner_user_id == $owner->id)>
                                        {{ $owner->name }} ({{ $owner->email }}) - {{ str($owner->role)->headline() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('edit_owner_user_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </label>

                        <flux:input
                            name="edit_name"
                            :label="__('Team Name')"
                            :value="$isEditingThisTeam ? old('edit_name') : $team->name"
                            type="text"
                            required
                        />

                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            {{ __('Address') }}
                            <input
                                name="edit_address"
                                type="text"
                                value="{{ $isEditingThisTeam ? old('edit_address') : $team->address }}"
                                required
                                class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                            />
                            @error('edit_address')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </label>

                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                {{ __('City') }}
                                <input
                                    name="edit_city"
                                    type="text"
                                    value="{{ $isEditingThisTeam ? old('edit_city') : $team->city }}"
                                    required
                                    class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                                />
                                @error('edit_city')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </label>

                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                {{ __('Province') }}
                                <input
                                    name="edit_province"
                                    type="text"
                                    value="{{ $isEditingThisTeam ? old('edit_province') : $team->province }}"
                                    required
                                    class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                                />
                                @error('edit_province')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </label>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                {{ __('Country') }}
                                <input
                                    name="edit_country_name"
                                    type="text"
                                    value="{{ $isEditingThisTeam ? old('edit_country_name') : ($team->country_name ?: 'Philippines') }}"
                                    class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                                />
                                @error('edit_country_name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </label>

                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                {{ __('Status') }}
                                <select
                                    name="edit_status"
                                    required
                                    class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                                >
                                    @foreach (['active', 'inactive', 'archived'] as $status)
                                        <option value="{{ $status }}" @selected($isEditingThisTeam ? old('edit_status') === $status : $team->status === $status)>{{ str($status)->headline() }}</option>
                                    @endforeach
                                </select>
                                @error('edit_status')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </label>
                        </div>

                        @if ($teamLogo)
                            <div class="rounded-xl border border-neutral-200 bg-zinc-50 p-4 dark:border-neutral-700 dark:bg-zinc-950">
                                <div class="mb-3 text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Current Logo') }}</div>
                                <img
                                    src="{{ $teamLogo }}"
                                    alt="{{ $team->name }}"
                                    class="h-20 w-20 rounded-2xl border border-neutral-200 object-cover dark:border-neutral-700"
                                >
                            </div>
                        @endif

                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            {{ __('Replace Logo') }}
                            <input
                                name="edit_logo"
                                type="file"
                                accept="image/*"
                                class="mt-2 block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm file:mr-4 file:rounded-md file:border-0 file:bg-zinc-900 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white dark:file:bg-white dark:file:text-zinc-900"
                            />
                            @error('edit_logo')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </label>

                        @if ($teamLogo)
                            <label class="flex items-center gap-3 rounded-lg border border-neutral-200 px-4 py-3 text-sm text-zinc-700 dark:border-neutral-700 dark:text-zinc-300">
                                <input
                                    type="checkbox"
                                    name="edit_remove_logo"
                                    value="1"
                                    @checked($isEditingThisTeam && old('edit_remove_logo'))
                                    class="rounded border-neutral-300 text-zinc-900 focus:ring-zinc-500 dark:border-neutral-700 dark:bg-zinc-950"
                                >
                                <span>{{ __('Remove current logo if no replacement is uploaded') }}</span>
                            </label>
                        @endif

                        <flux:button type="submit" variant="primary" class="w-full">
                            {{ __('Save Team Changes') }}
                        </flux:button>
                    </form>
                </div>
            </flux:modal>
        @endforeach

        <flux:modal
            name="create-team-modal"
            :show="$showCreateModal"
            class="max-w-3xl"
        >
            <div class="max-h-[85vh] space-y-6 overflow-y-auto pr-1">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <flux:heading size="lg">{{ __('Create Team') }}</flux:heading>
                        <flux:text class="mt-1">
                            {{ __('Register a new team without leaving the teams directory.') }}
                        </flux:text>
                    </div>

                    <flux:modal.close>
                        <flux:button variant="ghost">
                            {{ __('Close') }}
                        </flux:button>
                    </flux:modal.close>
                </div>

                <form method="POST" action="{{ route('admin.teams.store') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <input type="hidden" name="create_team_modal" value="1">

                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ __('Owner') }}
                        <select
                            name="owner_user_id"
                            required
                            class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                        >
                            <option value="">{{ __('Select owner') }}</option>
                            @foreach ($owners as $owner)
                                <option value="{{ $owner->id }}" @selected(old('owner_user_id') == $owner->id)>
                                    {{ $owner->name }} ({{ $owner->email }}) - {{ str($owner->role)->headline() }}
                                </option>
                            @endforeach
                        </select>
                        @error('owner_user_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </label>

                    <flux:input name="name" :label="__('Team Name')" :value="old('name')" type="text" required />

                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ __('Address') }}
                        <input
                            name="address"
                            type="text"
                            value="{{ old('address') }}"
                            required
                            class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                        />
                        @error('address')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </label>

                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            {{ __('City') }}
                            <input
                                name="city"
                                type="text"
                                value="{{ old('city') }}"
                                required
                                class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                            />
                            @error('city')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </label>

                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            {{ __('Province') }}
                            <input
                                name="province"
                                type="text"
                                value="{{ old('province') }}"
                                required
                                class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                            />
                            @error('province')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </label>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            {{ __('Country') }}
                            <input
                                name="country_name"
                                type="text"
                                value="{{ old('country_name', 'Philippines') }}"
                                class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                            />
                            @error('country_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </label>

                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            {{ __('Status') }}
                            <select
                                name="status"
                                required
                                class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                            >
                                @foreach (['active', 'inactive', 'archived'] as $status)
                                    <option value="{{ $status }}" @selected(old('status', 'active') === $status)>{{ str($status)->headline() }}</option>
                                @endforeach
                            </select>
                            @error('status')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </label>
                    </div>

                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ __('Team Logo') }}
                        <input
                            name="logo"
                            type="file"
                            accept="image/*"
                            class="mt-2 block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm file:mr-4 file:rounded-md file:border-0 file:bg-zinc-900 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white dark:file:bg-white dark:file:text-zinc-900"
                        />
                        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Optional square JPG, PNG, SVG, or WEBP up to 2 MB.') }}</p>
                        @error('logo')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </label>

                    <flux:button type="submit" variant="primary" class="w-full">
                        {{ __('Create Team') }}
                    </flux:button>
                </form>
            </div>
        </flux:modal>
    </div>
</x-layouts::app>
