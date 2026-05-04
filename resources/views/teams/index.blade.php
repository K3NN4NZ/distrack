<x-layouts::app :title="__('My Teams')">
    <div class="space-y-6">
        <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <flux:heading size="xl">{{ __('My Teams') }}</flux:heading>
            <flux:text class="mt-2 max-w-3xl">
                {{ __('Create your team profile, assign its address/logo, and maintain the roster that will be used for tournament registration and scoring.') }}
            </flux:text>

            @if (session('status'))
                <div class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-950/40 dark:text-green-300">
                    @switch(session('status'))
                        @case('team-created')
                            {{ __('Team registered successfully.') }}
                            @break
                        @case('member-created')
                            {{ __('Team member added successfully.') }}
                            @break
                        @case('member-deleted')
                            {{ __('Team member deleted successfully.') }}
                            @break
                        @case('member-delete-blocked')
                            {{ __('This roster member already has recorded match stats and cannot be deleted.') }}
                            @break
                        @default
                            {{ __('Saved.') }}
                    @endswitch
                </div>
            @endif
        </section>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.25fr)_minmax(0,1.75fr)]">
            <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                <div class="mb-4">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Register Team') }}</h2>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ __('This matches the first captain flow in your diagram: register a team before entering members.') }}</p>
                </div>

                <form method="POST" action="{{ route('teams.store') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf

                    <flux:input
                        name="name"
                        :label="__('Team Name')"
                        :value="old('name')"
                        type="text"
                        required
                        autocomplete="organization"
                    />

                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ __('Team Address') }}
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
                                class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                            />
                            @error('city')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </label>

                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            {{ __('Country') }}
                            <input
                                name="country_name"
                                type="text"
                                value="{{ old('country_name') }}"
                                class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                            />
                            @error('country_name')
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
                        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Upload a square JPG, PNG, SVG, or WEBP logo up to 2 MB.') }}
                        </p>
                        @error('logo')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </label>

                    <flux:button type="submit" variant="primary" class="w-full">
                        {{ __('Register Team') }}
                    </flux:button>
                </form>
            </section>

            <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Team Directory') }}</h2>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ __('Select a team to manage its roster.') }}</p>
                    </div>
                    <span class="rounded-md border border-neutral-200 px-3 py-1 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                        {{ trans_choice('{0} No teams|{1} :count team|[2,*] :count teams', $teams->count(), ['count' => $teams->count()]) }}
                    </span>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    @forelse ($teams as $team)
                        @php
                            $logoUrl = $team->logoUrl();
                            $badge = str($team->name)
                                ->explode(' ')
                                ->take(2)
                                ->map(fn ($word) => str($word)->substr(0, 1))
                                ->implode('');
                        @endphp
                        <a
                            href="{{ route('teams.index', ['team' => $team->id]) }}"
                            wire:navigate
                            class="rounded-xl border p-4 transition {{ $selectedTeam?->id === $team->id
                                ? 'border-zinc-900 bg-zinc-50 dark:border-white dark:bg-zinc-950'
                                : 'border-neutral-200 bg-white hover:border-zinc-400 dark:border-neutral-700 dark:bg-zinc-900 dark:hover:border-zinc-500' }}"
                        >
                            <div class="flex items-center gap-4">
                                <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-neutral-200 bg-zinc-100 text-sm font-semibold text-zinc-700 dark:border-neutral-700 dark:bg-zinc-800 dark:text-zinc-200">
                                    @if ($logoUrl)
                                        <img
                                            src="{{ $logoUrl }}"
                                            alt="{{ $team->name }}"
                                            class="h-full w-full object-cover"
                                        >
                                    @else
                                        {{ $badge }}
                                    @endif
                                </div>

                                <div class="min-w-0">
                                    <div class="truncate text-base font-semibold text-zinc-900 dark:text-white">{{ $team->name }}</div>
                                    <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $team->locationLabel() }}</div>
                                    @if ($team->city || $team->country_name)
                                        <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $team->address }}</div>
                                    @endif
                                </div>
                            </div>
                            <div class="mt-4 flex gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                <span>{{ __('Members: :count', ['count' => $team->members_count]) }}</span>
                                <span>{{ __('Registrations: :count', ['count' => $team->registrations_count]) }}</span>
                            </div>
                        </a>
                    @empty
                        <div class="rounded-xl border border-dashed border-neutral-300 p-4 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                            {{ __('No teams yet. Register your first team to start building the roster.') }}
                        </div>
                    @endforelse
                </div>
            </section>
        </div>

        <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Input Team Members') }}</h2>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                    {{ $selectedTeam ? __('Adding players to :team.', ['team' => $selectedTeam->name]) : __('Choose a team first to manage its roster.') }}
                </p>
            </div>

            @if ($selectedTeam)
                <div class="grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,1.4fr)]">
                    <form method="POST" action="{{ route('teams.members.store') }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="team_id" value="{{ $selectedTeam->id }}">

                        <flux:input name="name" :label="__('Name')" :value="old('name')" type="text" required />
                        <flux:input name="nickname" :label="__('Nickname')" :value="old('nickname')" type="text" required />

                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                {{ __('Gender') }}
                                <select
                                    name="gender"
                                    required
                                    class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                                >
                                    <option value="">{{ __('Select gender') }}</option>
                                    @foreach (['Male', 'Female', 'Non-binary', 'Prefer not to say'] as $option)
                                        <option value="{{ $option }}" @selected(old('gender') === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                                @error('gender')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </label>

                            <flux:input name="age" :label="__('Age')" :value="old('age')" type="number" min="1" max="99" required />
                        </div>

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

                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            {{ __('Role') }}
                            <select
                                name="role"
                                required
                                class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                            >
                                <option value="captain" @selected(old('role') === 'captain')>{{ __('Captain') }}</option>
                                <option value="spirit_captain" @selected(old('role') === 'spirit_captain')>{{ __('Spirit Captain') }}</option>
                                <option value="member" @selected(old('role', 'member') === 'member')>{{ __('Member') }}</option>
                            </select>
                            @error('role')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </label>

                        <flux:button type="submit" variant="primary" class="w-full">
                            {{ __('Add Team Member') }}
                        </flux:button>
                    </form>

                    <div class="space-y-3">
                        @forelse ($selectedTeam->members as $member)
                            <div class="rounded-xl border border-neutral-200 bg-zinc-50 p-4 dark:border-neutral-700 dark:bg-zinc-950">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-semibold text-zinc-900 dark:text-white">{{ $member->name }}</div>
                                        <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                            {{ $member->nickname }} · {{ str($member->role)->replace('_', ' ')->headline() }}
                                        </div>
                                    </div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $member->gender }} · {{ __('Age :age', ['age' => $member->age]) }}
                                    </div>
                                </div>

                                <div class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">
                                    {{ $member->address }}
                                </div>

                                <div class="mt-4 flex justify-end">
                                    @if ($member->match_stats_count > 0)
                                        <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-[11px] font-medium text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-300">
                                            {{ __('Locked: has match stats') }}
                                        </span>
                                    @else
                                        <form
                                            method="POST"
                                            action="{{ route('teams.members.destroy', $member) }}"
                                            onsubmit="return confirm('{{ __('Delete this team member?') }}')"
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
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-neutral-300 p-4 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                                {{ __('No roster members yet for this team.') }}
                            </div>
                        @endforelse
                    </div>
                </div>
            @else
                <div class="rounded-xl border border-dashed border-neutral-300 p-4 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                    {{ __('Register a team first, then come back here to add roster members.') }}
                </div>
            @endif
        </section>
    </div>
</x-layouts::app>
