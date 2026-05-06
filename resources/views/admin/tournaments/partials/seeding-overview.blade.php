<section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
    @if (filled($asyncStatusMessage ?? null))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-950/40 dark:text-green-300">
            {{ $asyncStatusMessage }}
        </div>
    @endif

    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <span class="inline-flex items-center rounded-full bg-[#eef4ff] px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-[#2f55b7]">
                {{ __('Step 1') }}
            </span>
            <h2 class="mt-3 text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Auto Seed') }}</h2>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('Use this when you want the system to randomize the team order automatically. Brackets only start once there are at least :minimum teams, and each bracket holds :count teams.', ['minimum' => $minimumBracketTeamCount, 'count' => $bracketTeamLimit]) }}
            </p>
        </div>

        <form method="POST" action="{{ route('admin.tournaments.registrations.seed') }}" data-seeding-randomize-form>
            @csrf
            <input type="hidden" name="tournament_id" value="{{ $selectedTournament->id }}">
            <input type="hidden" name="redirect_route" value="admin.tournaments.index">
            <input type="hidden" name="redirect_tab" value="overview">

            <flux:button type="submit" variant="primary" :disabled="$teamCount === 0">
                {{ __('Auto Seed Teams') }}
            </flux:button>
        </form>
    </div>

    <div class="mt-5 grid gap-3 md:grid-cols-3">
        <div class="rounded-xl border border-neutral-200 bg-zinc-50 px-4 py-3 dark:border-neutral-700 dark:bg-zinc-950">
            <div class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500 dark:text-zinc-400">{{ __('Teams') }}</div>
            <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-white">{{ $teamCount }}</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-zinc-50 px-4 py-3 dark:border-neutral-700 dark:bg-zinc-950">
            <div class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500 dark:text-zinc-400">{{ __('Brackets') }}</div>
            <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-white">{{ $seededBracketGroups->count() }}</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-zinc-50 px-4 py-3 dark:border-neutral-700 dark:bg-zinc-950">
            <div class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500 dark:text-zinc-400">{{ __('Unassigned') }}</div>
            <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-white">{{ $unassignedSeededCount }}</div>
        </div>
    </div>

    <div class="mt-4 rounded-xl border border-dashed border-neutral-300 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-neutral-700 dark:bg-zinc-950 dark:text-zinc-300">
        {{ __('Every click shuffles the teams and bracket placements again. If there are fewer than :minimum teams, no bracket is created. Once bracket play starts, teams are grouped :count per bracket and any leftovers stay unassigned.', ['minimum' => $minimumBracketTeamCount, 'count' => $bracketTeamLimit]) }}
    </div>

    @if ($seededBracketGroups->isNotEmpty())
        <div class="mt-4 rounded-xl border border-neutral-200 bg-zinc-50 p-4 dark:border-neutral-700 dark:bg-zinc-950">
            <div class="flex items-start justify-between gap-3">
                <div class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Current Brackets') }}</div>

                <form method="POST" action="{{ route('admin.tournaments.registrations.seed') }}" data-seeding-randomize-form>
                    @csrf
                    <input type="hidden" name="tournament_id" value="{{ $selectedTournament->id }}">
                    <input type="hidden" name="redirect_route" value="admin.tournaments.index">
                    <input type="hidden" name="redirect_tab" value="overview">

                    <button
                        type="submit"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-neutral-300 bg-white text-zinc-700 shadow-sm transition hover:border-neutral-400 hover:bg-zinc-100 dark:border-neutral-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
                        title="{{ __('Randomize current brackets') }}"
                        aria-label="{{ __('Randomize current brackets') }}"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M16 4v4h-4" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M4 14V10h4" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M15.5 8a5 5 0 0 0-8.9-2" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M4.5 12a5 5 0 0 0 8.9 2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                </form>
            </div>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($seededBracketGroups as $group)
                    <div class="rounded-xl border border-neutral-200 bg-white px-4 py-3 dark:border-neutral-700 dark:bg-zinc-900">
                        <div class="flex items-start justify-between gap-3">
                            <div class="font-semibold text-zinc-900 dark:text-white">{{ $group['code'] }}</div>

                            <flux:modal.trigger name="edit-bracket-seeds-{{ $selectedTournament->id }}-{{ Str::slug($group['code']) }}">
                                <button
                                    type="button"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-neutral-300 bg-white text-zinc-700 shadow-sm transition hover:border-neutral-400 hover:bg-zinc-100 dark:border-neutral-700 dark:bg-zinc-950 dark:text-zinc-200 dark:hover:bg-zinc-800"
                                    title="{{ __('Edit :bracket seed order', ['bracket' => $group['code']]) }}"
                                    aria-label="{{ __('Edit :bracket seed order', ['bracket' => $group['code']]) }}"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path d="M13.586 3.586a2 2 0 1 1 2.828 2.828l-8.9 8.9a2 2 0 0 1-.878.497l-2.54.726a.75.75 0 0 1-.926-.926l.726-2.54a2 2 0 0 1 .497-.878l8.9-8.9Z" />
                                    </svg>
                                </button>
                            </flux:modal.trigger>
                        </div>

                        <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ trans_choice('{1} :count team|[2,*] :count teams', $group['count'], ['count' => $group['count']]) }}
                        </div>
                        @if (collect($group['registrations'])->isNotEmpty())
                            <div class="mt-3 space-y-1 border-t border-neutral-200 pt-3 text-sm text-zinc-700 dark:border-neutral-700 dark:text-zinc-300">
                                @foreach ($group['registrations'] as $registration)
                                    <div>{{ ($registration->seed_number ?? '-') . ' - ' . $registration->team->name }}</div>
                                @endforeach
                            </div>
                        @endif
                        @if ($group['count'] < $bracketTeamLimit)
                            <div class="mt-2 text-xs font-medium text-amber-600 dark:text-amber-300">
                                {{ __('Needs :count more team(s) to complete the bracket.', ['count' => $bracketTeamLimit - $group['count']]) }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @foreach ($seededBracketGroups as $group)
        <flux:modal
            name="edit-bracket-seeds-{{ $selectedTournament->id }}-{{ Str::slug($group['code']) }}"
            :show="$seedOrderBracketModalCode === $group['code']"
            class="max-w-3xl"
        >
            <div class="space-y-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <flux:heading size="lg">{{ __('Edit Seed Order') }}</flux:heading>
                        <flux:text class="mt-1">
                            {{ __('Update the seed order for :bracket. Teams stay in the same bracket unless you change them somewhere else.', ['bracket' => $group['code']]) }}
                        </flux:text>
                    </div>

                    <flux:modal.close>
                        <flux:button variant="ghost">
                            {{ __('Close') }}
                        </flux:button>
                    </flux:modal.close>
                </div>

                @if ($seedOrderBracketModalCode === $group['code'] && $errors->has('registrations'))
                    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
                        {{ $errors->first('registrations') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.tournaments.registrations.seeding.update') }}" class="space-y-4">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="tournament_id" value="{{ $selectedTournament->id }}">
                    <input type="hidden" name="redirect_route" value="admin.tournaments.index">
                    <input type="hidden" name="redirect_tab" value="overview">
                    <input type="hidden" name="seed_order_bracket_code" value="{{ $group['code'] }}">

                    <div class="space-y-3">
                        @foreach ($group['registrations'] as $index => $registration)
                            <div class="rounded-xl border border-neutral-200 bg-zinc-50 px-4 py-4 dark:border-neutral-700 dark:bg-zinc-950">
                                <input type="hidden" name="registrations[{{ $index }}][id]" value="{{ $registration->id }}">
                                <input type="hidden" name="registrations[{{ $index }}][bracket_code]" value="{{ $registration->bracket_code }}">

                                <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_140px] sm:items-center">
                                    <div>
                                        <div class="font-semibold text-zinc-900 dark:text-white">{{ $registration->team->name }}</div>
                                        <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                            {{ __('Current bracket: :bracket', ['bracket' => $registration->bracket_code]) }}
                                        </div>
                                    </div>

                                    <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                        {{ __('Seed') }}
                                        <input
                                            type="number"
                                            min="1"
                                            name="registrations[{{ $index }}][seed_number]"
                                            value="{{ old("registrations.$index.seed_number", $registration->seed_number) }}"
                                            class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-900 dark:text-white"
                                        >
                                        @error("registrations.$index.seed_number")
                                            <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                                        @enderror
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <flux:button type="submit" variant="primary" class="w-full">
                        {{ __('Save Seed Order') }}
                    </flux:button>
                </form>
            </div>
        </flux:modal>
    @endforeach
</section>
