@props([
    'listRoute' => 'admin.tournaments.index',
    'selectionRoute' => 'admin.tournaments.index',
    'setupRoute' => null,
    'setupLabel' => __('Setup'),
    'showSetupButton' => true,
    'showRegisterButton' => false,
    'showEditButton' => true,
    'showDeleteButton' => false,
    'heading' => __('Tournaments'),
    'description' => __('Select a tournament to configure pitches and registrations.'),
])

@php
    $normalizeStoredRows = function (?array $items, string $valueKey = 'value'): array {
        return collect($items ?? [])
            ->filter(fn ($item): bool => is_array($item))
            ->map(function (array $item) use ($valueKey): ?array {
                $label = trim((string) ($item['label'] ?? ''));
                $value = trim((string) ($item[$valueKey] ?? ''));

                if ($label === '' || $value === '') {
                    return null;
                }

                return [
                    'label' => $label,
                    $valueKey => $value,
                ];
            })
            ->filter()
            ->values()
            ->all();
    };

    $buildTournamentDefaults = function ($tournament) use ($normalizeStoredRows): array {
        return [
            'name' => $tournament->name,
            'venue' => $tournament->venue,
            'description' => $tournament->description,
            'registration_deadline' => $tournament->registration_deadline,
            'starts_at' => $tournament->starts_at,
            'ends_at' => $tournament->ends_at,
            'status' => $tournament->status,
            'country_name' => $tournament->country_name ?: 'Philippines',
            'province_code' => '',
            'province' => $tournament->province,
            'city_code' => '',
            'city' => $tournament->city,
            'barangay_code' => '',
            'barangay' => $tournament->barangay,
            'timezone' => $tournament->timezone,
            'venue_google_map_link' => $tournament->venue_google_map_link,
            'thumbnail_path' => $tournament->thumbnail_path,
            'event_type' => $tournament->event_type,
            'division' => $tournament->division,
            'surface' => $tournament->surface,
            'info_items' => $tournament->additionalInfoItems(),
            'organizer_items' => $normalizeStoredRows($tournament->organizer_items ?? []),
            'link_items' => $normalizeStoredRows($tournament->link_items ?? [], 'href'),
            'is_public' => (bool) $tournament->is_public,
        ];
    };

    $editModalTournamentId = old('edit_tournament_id')
        ? (int) old('edit_tournament_id')
        : null;
    $registerModalTournamentId = old('registration_tournament_id')
        ? (int) old('registration_tournament_id')
        : null;
    $selectedTournamentId = $registerModalTournamentId
        ?? $editModalTournamentId
        ?? (($selectedTournament ?? null)?->id ?? request()->integer('tournament'));
@endphp

<section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
    <div class="mb-4 flex items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ $heading }}</h2>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $description }}</p>
        </div>
        <span class="rounded-md border border-neutral-200 px-3 py-1 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
            @if ($tournaments->count() !== $totalTournamentCount)
                {{ __(':visible of :total tournaments', ['visible' => $tournaments->count(), 'total' => $totalTournamentCount]) }}
            @else
                {{ trans_choice('{0} No tournaments|{1} :count tournament|[2,*] :count tournaments', $tournaments->count(), ['count' => $tournaments->count()]) }}
            @endif
        </span>
    </div>

    <form method="GET" action="{{ route($listRoute) }}" class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end" x-data="{}">
        <label class="block flex-1 text-sm font-medium text-zinc-700 dark:text-zinc-300">
            {{ __('Search') }}
            <input
                type="search"
                name="search"
                value="{{ $listFilters['search'] }}"
                placeholder="{{ __('Search name, venue, division, surface, or status') }}"
                x-on:input.debounce.300ms="$el.form.requestSubmit()"
                x-on:search="$el.form.requestSubmit()"
                class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
            >
        </label>

        @if ($listFilters['search'] !== '')
            <a
                href="{{ route($listRoute) }}"
                wire:navigate
                class="inline-flex items-center justify-center rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-zinc-700 transition hover:border-neutral-400 hover:bg-zinc-100 dark:border-neutral-700 dark:text-zinc-200 dark:hover:bg-zinc-800"
            >
                {{ __('Clear') }}
            </a>
        @endif
    </form>

    <div class="grid gap-4 md:grid-cols-2">
        @forelse ($tournaments as $tournament)
            <div
                class="relative rounded-xl border p-4 transition {{ $selectedTournamentId === $tournament->id
                    ? 'border-[#2f55b7] bg-[#eef4ff] shadow-sm ring-1 ring-[#c8d7f8] dark:border-sky-400 dark:bg-sky-950/30 dark:ring-sky-500/30'
                    : 'border-neutral-200 bg-white hover:border-zinc-400 dark:border-neutral-700 dark:bg-zinc-900 dark:hover:border-zinc-500' }}"
            >
                @php
                    $showCompactActions = $showRegisterButton || $showEditButton || $showDeleteButton;
                @endphp

                @if ($showCompactActions)
                    <div class="absolute right-4 top-4 flex items-center gap-2">
                        @if ($setupRoute && $showSetupButton)
                            <a
                                href="{{ route($setupRoute, array_merge($tournamentListQueryParams, ['tournament' => $tournament->id, 'tab' => 'overview'])) }}"
                                wire:navigate
                                class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-neutral-300 bg-white text-zinc-700 shadow-sm transition hover:border-neutral-400 hover:bg-zinc-100 dark:border-neutral-700 dark:bg-zinc-950 dark:text-zinc-200 dark:hover:bg-zinc-800"
                                title="{{ $setupLabel }}"
                                aria-label="{{ $setupLabel }}"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M7.05 3.046A1 1 0 0 1 8 2.25h4a1 1 0 0 1 .95.796l.245 1.178c.26.106.51.23.75.372l1.108-.39a1 1 0 0 1 1.166.43l2 3.464a1 1 0 0 1-.216 1.223l-.863.837c.02.14.03.28.03.418 0 .14-.01.279-.03.418l.863.837a1 1 0 0 1 .216 1.223l-2 3.464a1 1 0 0 1-1.166.43l-1.108-.39a5.99 5.99 0 0 1-.75.372l-.245 1.178A1 1 0 0 1 12 17.75H8a1 1 0 0 1-.95-.796l-.245-1.178a5.986 5.986 0 0 1-.75-.372l-1.108.39a1 1 0 0 1-1.166-.43l-2-3.464a1 1 0 0 1 .216-1.223l.863-.837A3.53 3.53 0 0 1 2.83 10c0-.14.01-.279.03-.418l-.863-.837a1 1 0 0 1-.216-1.223l2-3.464a1 1 0 0 1 1.166-.43l1.108.39c.24-.142.49-.266.75-.372l.245-1.178ZM10 7.25A2.75 2.75 0 1 0 10 12.75 2.75 2.75 0 0 0 10 7.25Z" clip-rule="evenodd" />
                                </svg>
                            </a>
                        @endif

                        @if ($showEditButton)
                            <flux:modal.trigger name="edit-tournament-modal-{{ $tournament->id }}">
                                <button
                                    type="button"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-neutral-300 bg-white text-zinc-700 shadow-sm transition hover:border-neutral-400 hover:bg-zinc-100 dark:border-neutral-700 dark:bg-zinc-950 dark:text-zinc-200 dark:hover:bg-zinc-800"
                                    title="{{ __('Edit Tournament') }}"
                                    aria-label="{{ __('Edit Tournament') }}"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path d="M13.586 3.586a2 2 0 1 1 2.828 2.828l-8.9 8.9a2 2 0 0 1-.878.497l-2.54.726a.75.75 0 0 1-.926-.926l.726-2.54a2 2 0 0 1 .497-.878l8.9-8.9Z" />
                                    </svg>
                                </button>
                            </flux:modal.trigger>
                        @endif

                        @if ($showDeleteButton)
                            @php
                                $deleteConfirmation = __('Delete :tournament and all its teams, pitches, matches, and crew?', [
                                    'tournament' => $tournament->name,
                                ]);
                            @endphp
                            <form
                                method="POST"
                                action="{{ route('admin.tournaments.destroy', $tournament) }}"
                                onsubmit="return confirm({{ \Illuminate\Support\Js::from($deleteConfirmation) }});"
                            >
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="redirect_search" value="{{ $listFilters['search'] }}">

                                <button
                                    type="submit"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-red-200 bg-white text-red-700 shadow-sm transition hover:border-red-300 hover:bg-red-50 dark:border-red-900/60 dark:bg-zinc-950 dark:text-red-300 dark:hover:bg-red-950/40"
                                    title="{{ __('Delete Tournament') }}"
                                    aria-label="{{ __('Delete Tournament') }}"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M8.75 2.5a1.75 1.75 0 0 0-1.75 1.75V5H5.5a.75.75 0 0 0 0 1.5h.47l.64 8.32A2.25 2.25 0 0 0 8.85 17h2.3a2.25 2.25 0 0 0 2.24-2.18l.64-8.32h.47a.75.75 0 0 0 0-1.5H13V4.25A1.75 1.75 0 0 0 11.25 2.5h-2.5ZM8.5 5V4.25a.25.25 0 0 1 .25-.25h2.5a.25.25 0 0 1 .25.25V5h-3Zm.72 3.22a.75.75 0 0 1 .75.72v4.5a.75.75 0 0 1-1.5 0v-4.5a.75.75 0 0 1 .75-.72Zm3.03.72a.75.75 0 0 0-1.5 0v4.5a.75.75 0 0 0 1.5 0v-4.5Z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </form>
                        @endif
                    </div>
                @endif

                @if ($selectionRoute)
                    <a
                        href="{{ route($selectionRoute, array_merge($tournamentListQueryParams, ['tournament' => $tournament->id])) }}"
                        wire:navigate
                        class="{{ $showCompactActions ? 'block pr-36' : 'block' }}"
                    >
                @else
                    <div class="{{ $showCompactActions ? 'block pr-36' : 'block' }}">
                @endif
                    <div class="text-base font-semibold text-zinc-900 dark:text-white">{{ $tournament->name }}</div>
                    <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $tournament->venue }}</div>
                    <div class="mt-2 flex flex-wrap gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                        @if ($tournament->country_name)
                            <span>{{ $tournament->country_name }}</span>
                        @endif
                        @if ($tournament->division)
                            <span>{{ $tournament->division }}</span>
                        @endif
                        @if ($tournament->surface)
                            <span>{{ $tournament->surface }}</span>
                        @endif
                        <span>{{ $tournament->is_public ? __('Public') : __('Private') }}</span>
                    </div>
                @if ($selectionRoute)
                    </a>
                @else
                    </div>
                @endif

                <div class="mt-4 flex items-end justify-between gap-3">
                    <div class="flex flex-wrap gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                        <span>{{ __('Pitches: :count', ['count' => $tournament->pitches_count]) }}</span>
                        <span>{{ __('Teams: :count', ['count' => $tournament->registrations_count]) }}</span>
                        <span>{{ __('Matches: :count', ['count' => $tournament->matches_count]) }}</span>
                        <span>{{ __('Crew: :count', ['count' => $tournament->crew_members_count]) }}</span>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-2">
                        @if (! $showCompactActions && $setupRoute && $showSetupButton)
                            <a
                                href="{{ route($setupRoute, array_merge($tournamentListQueryParams, ['tournament' => $tournament->id, 'tab' => 'overview'])) }}"
                                wire:navigate
                                class="inline-flex items-center justify-center rounded-lg border border-neutral-300 px-3 py-2 text-sm font-medium text-zinc-700 transition hover:border-neutral-400 hover:bg-zinc-100 dark:border-neutral-700 dark:text-zinc-200 dark:hover:bg-zinc-800"
                            >
                                {{ $setupLabel }}
                            </a>
                        @endif

                        @if ($showRegisterButton)
                            <flux:modal.trigger name="register-team-modal-{{ $tournament->id }}">
                                <flux:button variant="primary" size="sm">
                                    {{ __('Register Teams') }}
                                </flux:button>
                            </flux:modal.trigger>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-neutral-300 p-4 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                {{ $totalTournamentCount > 0
                    ? __('No tournaments matched the current search.')
                    : __('No tournaments yet. Create one to begin tournament setup.') }}
            </div>
        @endforelse
    </div>

    @if ($showEditButton)
        @foreach ($tournaments as $tournament)
            <flux:modal
                name="edit-tournament-modal-{{ $tournament->id }}"
                :show="$editModalTournamentId === $tournament->id"
                class="max-w-5xl"
            >
                <div class="max-h-[85vh] overflow-y-auto pr-1">
                    <div class="mb-6 flex items-start justify-between gap-4">
                        <div>
                            <flux:heading size="lg">{{ __('Edit Tournament') }}</flux:heading>
                            <flux:text class="mt-1">
                                {{ __('Update :tournament without leaving the tournament directory.', ['tournament' => $tournament->name]) }}
                            </flux:text>
                        </div>

                        <flux:modal.close>
                            <flux:button variant="ghost">
                                {{ __('Close') }}
                            </flux:button>
                        </flux:modal.close>
                    </div>

                    @include('admin.tournaments.partials.form', [
                        'action' => route('admin.tournaments.update', $tournament),
                        'method' => 'PUT',
                        'submitLabel' => __('Save Tournament Changes'),
                        'fieldPrefix' => 'edit_',
                        'defaults' => $buildTournamentDefaults($tournament),
                        'hiddenFields' => [
                            'edit_tournament_id' => $tournament->id,
                            'redirect_route' => $listRoute,
                            'redirect_search' => $listFilters['search'],
                        ],
                    ])
                </div>
            </flux:modal>
        @endforeach
    @endif

    @if ($showRegisterButton)
        @foreach ($tournaments as $tournament)
            @php
                $registeredTeamIds = $tournament->registrations
                    ->pluck('team_id')
                    ->map(fn ($teamId): int => (int) $teamId)
                    ->all();

                $selectableTeamIds = $availableTeams
                    ->reject(fn ($team): bool => in_array((int) $team->id, $registeredTeamIds, true))
                    ->pluck('id')
                    ->map(fn ($teamId): int => (int) $teamId)
                    ->values()
                    ->all();

                $oldTeamIds = collect(old('team_ids', []))
                    ->map(fn ($id): int => (int) $id)
                    ->all();
            @endphp

            <flux:modal
                name="register-team-modal-{{ $tournament->id }}"
                :show="$registerModalTournamentId === $tournament->id"
                class="max-w-3xl"
            >
                <div class="space-y-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <flux:heading size="lg">{{ __('Register Teams') }}</flux:heading>
                            <flux:text class="mt-1">
                                {{ __('Attach one or more existing teams to :tournament without leaving the tournament directory.', ['tournament' => $tournament->name]) }}
                            </flux:text>
                        </div>

                        <flux:modal.close>
                            <flux:button variant="ghost">
                                {{ __('Close') }}
                            </flux:button>
                        </flux:modal.close>
                    </div>

                    <form
                        method="POST"
                        action="{{ route('admin.tournaments.registrations.store') }}"
                        class="space-y-4"
                        x-data="{
                            selectableIds: @js($selectableTeamIds),
                            selected: @js($oldTeamIds),
                            isSelected(id) { return this.selected.includes(Number(id)); },
                            toggle(id, checked) {
                                const value = Number(id);
                                if (checked) {
                                    if (! this.selected.includes(value)) {
                                        this.selected.push(value);
                                    }
                                } else {
                                    this.selected = this.selected.filter((teamId) => teamId !== value);
                                }
                            },
                            get allSelected() {
                                return this.selectableIds.length > 0
                                    && this.selectableIds.every((id) => this.selected.includes(id));
                            },
                            get someSelected() {
                                return this.selected.length > 0 && ! this.allSelected;
                            },
                            toggleAll(checked) {
                                this.selected = checked ? [...this.selectableIds] : [];
                            },
                        }"
                    >
                        @csrf
                        <input type="hidden" name="tournament_id" value="{{ $tournament->id }}">
                        <input type="hidden" name="registration_tournament_id" value="{{ $tournament->id }}">
                        <input type="hidden" name="redirect_route" value="{{ $listRoute }}">
                        <input type="hidden" name="redirect_search" value="{{ $listFilters['search'] }}">

                        <div>
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                    {{ __('Teams') }}
                                </span>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                    <span x-text="selected.length"></span>
                                    {{ __('of') }}
                                    {{ count($selectableTeamIds) }}
                                    {{ __('selected') }}
                                </span>
                            </div>

                            <div class="mt-2 overflow-hidden rounded-lg border border-neutral-300 dark:border-neutral-700">
                                @if (count($selectableTeamIds) > 0)
                                    <label class="flex cursor-pointer items-center gap-3 border-b border-neutral-200 bg-zinc-50 px-3 py-2 text-sm font-medium text-zinc-800 dark:border-neutral-700 dark:bg-zinc-950 dark:text-zinc-100">
                                        <input
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-neutral-300 text-[#2f55b7] focus:ring-[#2f55b7] dark:border-neutral-600 dark:bg-zinc-900"
                                            :checked="allSelected"
                                            x-bind:indeterminate="someSelected"
                                            x-on:change="toggleAll($event.target.checked)"
                                        >
                                        <span>{{ __('Select all') }}</span>
                                    </label>
                                @endif

                                <div class="max-h-72 divide-y divide-neutral-200 overflow-y-auto dark:divide-neutral-700">
                                    @forelse ($availableTeams as $team)
                                        @php($isRegistered = in_array((int) $team->id, $registeredTeamIds, true))
                                        <label class="flex items-start gap-3 px-3 py-2 text-sm {{ $isRegistered ? 'cursor-not-allowed bg-zinc-50 text-zinc-400 dark:bg-zinc-950 dark:text-zinc-500' : 'cursor-pointer text-zinc-800 hover:bg-zinc-50 dark:text-zinc-100 dark:hover:bg-zinc-800/60' }}">
                                            <input
                                                type="checkbox"
                                                name="team_ids[]"
                                                value="{{ $team->id }}"
                                                class="mt-0.5 h-4 w-4 rounded border-neutral-300 text-[#2f55b7] focus:ring-[#2f55b7] disabled:opacity-60 dark:border-neutral-600 dark:bg-zinc-900"
                                                @disabled($isRegistered)
                                                @checked(! $isRegistered && in_array((int) $team->id, $oldTeamIds, true))
                                                @if (! $isRegistered)
                                                    :checked="isSelected({{ $team->id }})"
                                                    x-on:change="toggle({{ $team->id }}, $event.target.checked)"
                                                @endif
                                            >
                                            <span class="flex flex-1 flex-wrap items-center gap-x-2 gap-y-1">
                                                <span class="font-medium">{{ $team->name }}</span>
                                                <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                                    {{ trans_choice('{1} :count member|[2,*] :count members', $team->members_count, ['count' => $team->members_count]) }}
                                                </span>
                                                @if ($isRegistered)
                                                    <span class="rounded-full border border-neutral-300 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-zinc-500 dark:border-neutral-600 dark:text-zinc-400">
                                                        {{ __('Already registered') }}
                                                    </span>
                                                @endif
                                            </span>
                                        </label>
                                    @empty
                                        <div class="px-3 py-4 text-sm text-zinc-500 dark:text-zinc-400">
                                            {{ __('No teams are available to register yet.') }}
                                        </div>
                                    @endforelse
                                </div>
                            </div>

                            <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ __('Disabled teams are already registered in this tournament.') }}
                            </p>

                            @error('team_ids')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            @error('team_ids.*')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <flux:button
                            type="submit"
                            variant="primary"
                            class="w-full"
                            x-bind:disabled="selected.length === 0"
                        >
                            <span x-show="selected.length <= 1">{{ __('Register Team') }}</span>
                            <span x-show="selected.length > 1" x-cloak>
                                {{ __('Register Teams') }} (<span x-text="selected.length"></span>)
                            </span>
                        </flux:button>
                    </form>
                </div>
            </flux:modal>
        @endforeach
    @endif
</section>
