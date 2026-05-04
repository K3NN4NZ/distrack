@php
    $homeRegistration = $match->homeRegistration;
    $awayRegistration = $match->awayRegistration;
    $homeTeam = $homeRegistration?->team;
    $awayTeam = $awayRegistration?->team;
    $homeLogo = $homeTeam?->logoUrl();
    $awayLogo = $awayTeam?->logoUrl();
    $homeBadge = $homeTeam
        ? str($homeTeam->name)->explode(' ')->take(2)->map(fn ($word) => str($word)->substr(0, 1))->implode('')
        : 'HM';
    $awayBadge = $awayTeam
        ? str($awayTeam->name)->explode(' ')->take(2)->map(fn ($word) => str($word)->substr(0, 1))->implode('')
        : 'AW';
    $statusTone = match ($match->status) {
        'completed' => 'bg-emerald-500 text-white',
        'live' => 'bg-[#2f55b7] text-white',
        default => 'bg-zinc-100 text-zinc-700',
    };
    $homeStats = $match->playerStats
        ->filter(fn ($stat) => $stat->teamMember?->team_id === $homeTeam?->id)
        ->values();
    $awayStats = $match->playerStats
        ->filter(fn ($stat) => $stat->teamMember?->team_id === $awayTeam?->id)
        ->values();
    $loggedAssistsCount = $match->scoreLogs->whereNotNull('assist_team_member_id')->count();
    $initialRegistrationId = (string) old('team_registration_id', $homeRegistration?->id);
    $initialScorerId = (string) old('team_member_id');
    $initialAssisterId = (string) old('assist_team_member_id');
@endphp

<x-layouts::app :title="__('Live Scoring')">
    <div class="space-y-6">
        <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <a
                        href="{{ route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => 'matches']) }}"
                        wire:navigate
                        class="inline-flex items-center gap-2 text-sm font-medium text-zinc-600 transition hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-white"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M11.78 4.22a.75.75 0 0 1 0 1.06L7.06 10l4.72 4.72a.75.75 0 1 1-1.06 1.06l-5.25-5.25a.75.75 0 0 1 0-1.06l5.25-5.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" />
                        </svg>
                        {{ __('Back to Tournament Setup') }}
                    </a>

                    <h1 class="mt-3 text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ __('Live Scoring') }}</h1>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                        {{ __('Log each scoring play with the scorer, optional assister, and minute. The match scoreline and player totals update automatically from the timeline.') }}
                    </p>
                    <div class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ $tournament->name }}
                        <span class="mx-2">|</span>
                        {{ str($match->stage)->replace('_', ' ')->headline() }}
                        @if ($match->round_label)
                            <span class="mx-2">|</span>
                            {{ $match->round_label }}
                        @endif
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    @if ($tournament->is_public)
                        <a
                            href="{{ route('tournaments.matches.show', ['tournament' => $tournament, 'match' => $match]) }}"
                            target="_blank"
                            rel="noreferrer"
                            class="inline-flex items-center justify-center rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-zinc-700 transition hover:border-neutral-400 hover:bg-zinc-100 dark:border-neutral-700 dark:text-zinc-200 dark:hover:bg-zinc-800"
                        >
                            {{ __('Open Public Match') }}
                        </a>
                    @endif
                </div>
            </div>

            @if (session('status'))
                <div class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-950/40 dark:text-green-300">
                    @switch(session('status'))
                        @case('score-play-added')
                            {{ __('Scoring play added successfully.') }}
                            @break
                        @case('score-play-deleted')
                            {{ __('Scoring play removed and totals rebuilt successfully.') }}
                            @break
                        @case('match-scoring-updated')
                            {{ __('Match control settings updated successfully.') }}
                            @break
                        @default
                            {{ __('Saved.') }}
                    @endswitch
                </div>
            @endif
        </section>

        <section class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-zinc-900">
            <div class="grid gap-6 px-6 py-8 lg:grid-cols-[minmax(0,1fr)_14rem_minmax(0,1fr)] lg:items-center">
                <div class="text-center lg:text-left">
                    <div class="mx-auto flex h-20 w-20 items-center justify-center overflow-hidden rounded-full border border-neutral-200 bg-zinc-50 text-lg font-semibold text-zinc-700 lg:mx-0 dark:border-neutral-700 dark:bg-zinc-950 dark:text-zinc-100">
                        @if ($homeLogo)
                            <img src="{{ $homeLogo }}" alt="{{ $homeTeam?->name }}" class="h-full w-full object-cover">
                        @else
                            {{ $homeBadge }}
                        @endif
                    </div>
                    <div class="mt-4 text-3xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ $homeTeam?->name ?: __('Home Team') }}</div>
                    <div class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ $homeTeam?->locationLabel() ?: __('Awaiting registration details') }}</div>
                </div>

                <div class="text-center">
                    <div class="text-5xl font-semibold tracking-tight text-zinc-900 dark:text-white">
                        {{ $match->home_score ?? 0 }}
                        <span class="mx-2 text-zinc-400">-</span>
                        {{ $match->away_score ?? 0 }}
                    </div>

                    <div class="mt-4">
                        <span class="inline-flex rounded-[0.7rem] px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.08em] {{ $statusTone }}">
                            {{ str($match->status)->headline() }}
                        </span>
                    </div>

                    <div class="mt-4 space-y-1 text-sm text-zinc-500 dark:text-zinc-400">
                        <div>{{ __('Scoring Plays: :count', ['count' => $match->scoreLogs->count()]) }}</div>
                        <div>{{ __('Assists Logged: :count', ['count' => $loggedAssistsCount]) }}</div>
                        <div>{{ __('Pitch: :pitch', ['pitch' => $match->pitch?->name ?? 'Unassigned']) }}</div>
                    </div>
                </div>

                <div class="text-center lg:text-right">
                    <div class="mx-auto flex h-20 w-20 items-center justify-center overflow-hidden rounded-full border border-neutral-200 bg-zinc-50 text-lg font-semibold text-zinc-700 lg:mx-0 lg:ml-auto dark:border-neutral-700 dark:bg-zinc-950 dark:text-zinc-100">
                        @if ($awayLogo)
                            <img src="{{ $awayLogo }}" alt="{{ $awayTeam?->name }}" class="h-full w-full object-cover">
                        @else
                            {{ $awayBadge }}
                        @endif
                    </div>
                    <div class="mt-4 text-3xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ $awayTeam?->name ?: __('Away Team') }}</div>
                    <div class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ $awayTeam?->locationLabel() ?: __('Awaiting registration details') }}</div>
                </div>
            </div>

            <div class="border-t border-neutral-200 bg-zinc-50 px-6 py-4 text-sm text-zinc-600 dark:border-neutral-700 dark:bg-zinc-950 dark:text-zinc-300">
                <div class="flex flex-wrap gap-x-5 gap-y-2">
                    <span>{{ __('Match #: :value', ['value' => $match->match_number ?? 'TBD']) }}</span>
                    <span>{{ __('Scheduled: :value', ['value' => $match->scheduled_at?->format('M j, Y g:i A') ?? 'TBD']) }}</span>
                    <span>{{ __('Round: :value', ['value' => $match->round_label ?? 'Not set']) }}</span>
                </div>
            </div>
        </section>

        @if (! $homeRegistration || ! $awayRegistration)
            <section class="rounded-xl border border-dashed border-neutral-300 bg-zinc-50 p-6 text-sm text-zinc-600 dark:border-neutral-700 dark:bg-zinc-900 dark:text-zinc-300">
                {{ __('Both home and away registrations must be attached to this match before live scoring can begin.') }}
            </section>
        @else
            @if ($matchHasManualScorelineWithoutLog)
                <section class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200">
                    {{ __('This match currently has a manual scoreline without a scoring timeline. The first live-scoring play will replace that manual score with the new automatic log-based total.') }}
                </section>
            @endif

            <div class="grid gap-6 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
                <div class="space-y-6">
                    <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                        <div class="mb-4">
                            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Match Control') }}</h2>
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ __('Set the public match state and keep any operator notes beside the scoring timeline.') }}</p>
                        </div>

                        <form method="POST" action="{{ route('admin.tournaments.matches.scoring.update', ['tournament' => $tournament, 'match' => $match]) }}" class="space-y-4">
                            @csrf
                            @method('PATCH')

                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                {{ __('Status') }}
                                <select
                                    name="status"
                                    class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                                >
                                    @foreach (['scheduled' => 'Scheduled', 'live' => 'Live', 'completed' => 'Completed'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('status', $match->status) === $value)>{{ __($label) }}</option>
                                    @endforeach
                                </select>
                                @error('status')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </label>

                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                {{ __('Operator Notes') }}
                                <textarea
                                    name="notes"
                                    rows="5"
                                    class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                                >{{ old('notes', $match->notes) }}</textarea>
                                @error('notes')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </label>

                            <button
                                type="submit"
                                class="inline-flex w-full items-center justify-center rounded-lg bg-zinc-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-black dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200"
                            >
                                {{ __('Save Match Control') }}
                            </button>
                        </form>
                    </section>

                    <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                        <div class="mb-4">
                            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Add Scoring Play') }}</h2>
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ __('Each play updates the scoreline, the public score breakdown, and the scorer/assister totals automatically.') }}</p>
                        </div>

                        <form
                            method="POST"
                            action="{{ route('admin.tournaments.matches.scoring.store', ['tournament' => $tournament, 'match' => $match]) }}"
                            class="space-y-4"
                            x-data="{
                                memberDirectory: @js($memberDirectory),
                                teamRegistrationId: @js($initialRegistrationId),
                                scorerId: @js($initialScorerId),
                                assisterId: @js($initialAssisterId),
                                get activeMembers() {
                                    return this.memberDirectory[this.teamRegistrationId] ?? [];
                                },
                                syncSelections() {
                                    if (! this.activeMembers.some((member) => String(member.id) === String(this.scorerId))) {
                                        this.scorerId = '';
                                    }

                                    if (
                                        ! this.activeMembers.some((member) => String(member.id) === String(this.assisterId))
                                        || String(this.assisterId) === String(this.scorerId)
                                    ) {
                                        this.assisterId = '';
                                    }
                                },
                            }"
                            x-init="syncSelections()"
                        >
                            @csrf

                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                {{ __('Scoring Team') }}
                                <select
                                    name="team_registration_id"
                                    x-model="teamRegistrationId"
                                    x-on:change="syncSelections()"
                                    class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                                >
                                    <option value="">{{ __('Select team') }}</option>
                                    @foreach ([$homeRegistration, $awayRegistration] as $registration)
                                        <option value="{{ $registration->id }}">
                                            {{ $registration->team->name }}
                                            {{ $registration->seed_number ? '· Seed '.$registration->seed_number : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('team_registration_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </label>

                            <div class="grid gap-4 md:grid-cols-2">
                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                    {{ __('Scorer') }}
                                    <select
                                        name="team_member_id"
                                        x-model="scorerId"
                                        class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                                    >
                                        <option value="">{{ __('Select scorer') }}</option>
                                        <template x-for="member in activeMembers" :key="member.id">
                                            <option x-bind:value="member.id" x-text="`${member.name} (${member.role.replace('_', ' ')})`"></option>
                                        </template>
                                    </select>
                                    @error('team_member_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </label>

                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                    {{ __('Assister') }}
                                    <select
                                        name="assist_team_member_id"
                                        x-model="assisterId"
                                        class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                                    >
                                        <option value="">{{ __('No assist recorded') }}</option>
                                        <template x-for="member in activeMembers.filter((member) => String(member.id) !== String(scorerId))" :key="member.id">
                                            <option x-bind:value="member.id" x-text="`${member.name} (${member.role.replace('_', ' ')})`"></option>
                                        </template>
                                    </select>
                                    @error('assist_team_member_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </label>
                            </div>

                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                {{ __('Minute') }}
                                <input
                                    name="minute"
                                    type="number"
                                    min="0"
                                    max="999"
                                    value="{{ old('minute') }}"
                                    class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                                >
                                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Optional. Leave blank if the exact minute was not tracked.') }}</p>
                                @error('minute')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </label>

                            @if ($matchHasManualScorelineWithoutLog)
                                <label class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200">
                                    <input
                                        type="checkbox"
                                        name="replace_manual_scoreline"
                                        value="1"
                                        @checked(old('replace_manual_scoreline'))
                                        class="mt-0.5 rounded border-amber-300 text-amber-600 focus:ring-amber-500"
                                    >
                                    <span>{{ __('I understand that the first scoring play will replace the current manual scoreline with the new automatic live-scoring total.') }}</span>
                                </label>
                                @error('replace_manual_scoreline')
                                    <p class="text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            @endif

                            <button
                                type="submit"
                                class="inline-flex w-full items-center justify-center rounded-lg bg-[#2f55b7] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#244591]"
                            >
                                {{ __('Add Scoring Play') }}
                            </button>
                        </form>
                    </section>
                </div>

                <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Scoring Timeline') }}</h2>
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ __('Newest plays appear at the bottom. Removing a play rebuilds the sequence and scoreline automatically.') }}</p>
                        </div>
                        <div class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ trans_choice('{1} :count scoring play logged|[2,*] :count scoring plays logged', $match->scoreLogs->count(), ['count' => $match->scoreLogs->count()]) }}
                        </div>
                    </div>

                    <div class="space-y-3">
                        @forelse ($match->scoreLogs as $scoreLog)
                            @php
                                $isHomeScore = $scoreLog->team_registration_id === $match->home_registration_id;
                                $teamName = $scoreLog->registration?->team?->name ?? __('Team');
                            @endphp
                            <div class="rounded-xl border border-neutral-200 bg-zinc-50 p-4 dark:border-neutral-700 dark:bg-zinc-950">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.08em] text-zinc-500 dark:text-zinc-400">
                                            <span>{{ __('Play #:sequence', ['sequence' => $scoreLog->sequence]) }}</span>
                                            <span class="rounded-full px-2.5 py-1 {{ $isHomeScore ? 'bg-[#e6efff] text-[#2f55b7]' : 'bg-[#fff1e8] text-[#b45309]' }}">
                                                {{ $teamName }}
                                            </span>
                                            @if (! is_null($scoreLog->minute))
                                                <span>{{ __('Minute :minute', ['minute' => $scoreLog->minute]) }}</span>
                                            @endif
                                        </div>

                                        <div class="mt-3 text-lg font-semibold text-zinc-900 dark:text-white">
                                            {{ $scoreLog->scorer?->name ?? $teamName }}
                                        </div>

                                        @if ($scoreLog->assister)
                                            <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                                {{ __('Assist: :name', ['name' => $scoreLog->assister->name]) }}
                                            </div>
                                        @endif

                                        <div class="mt-3 text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">
                                            {{ $scoreLog->home_score }} - {{ $scoreLog->away_score }}
                                        </div>
                                    </div>

                                    <form
                                        method="POST"
                                        action="{{ route('admin.tournaments.matches.scoring.destroy', ['tournament' => $tournament, 'match' => $match, 'scoreLog' => $scoreLog]) }}"
                                        onsubmit="return confirm('Remove this scoring play and rebuild the scoreline?')"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="inline-flex items-center justify-center rounded-lg border border-red-200 px-3 py-2 text-sm font-medium text-red-600 transition hover:border-red-300 hover:bg-red-50 dark:border-red-900/60 dark:text-red-300 dark:hover:bg-red-950/30"
                                        >
                                            {{ __('Delete') }}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-neutral-300 p-6 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                                {{ __('No scoring plays have been logged yet. The scoreline will start updating automatically once you add the first point.') }}
                            </div>
                        @endforelse
                    </div>
                </section>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                @foreach ([
                    ['team' => $homeTeam, 'stats' => $homeStats, 'accent' => 'text-[#2f55b7]'],
                    ['team' => $awayTeam, 'stats' => $awayStats, 'accent' => 'text-[#b45309]'],
                ] as $panel)
                    <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                        <div class="mb-4">
                            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ $panel['team']?->name ?: __('Team Totals') }}</h2>
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ __('Goals and assists are rebuilt from the scoring timeline. Blocks remain preserved if they were logged elsewhere.') }}</p>
                        </div>

                        @if ($panel['stats']->isNotEmpty())
                            <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                                <div class="grid grid-cols-[minmax(0,1fr)_4rem_4rem_4rem] items-center gap-3 border-b border-neutral-200 bg-zinc-50 px-4 py-3 text-sm font-semibold text-zinc-800 dark:border-neutral-700 dark:bg-zinc-950 dark:text-zinc-100">
                                    <div>{{ __('Player') }}</div>
                                    <div class="text-center">{{ __('G') }}</div>
                                    <div class="text-center">{{ __('A') }}</div>
                                    <div class="text-center">{{ __('B') }}</div>
                                </div>

                                @foreach ($panel['stats'] as $stat)
                                    <div class="grid grid-cols-[minmax(0,1fr)_4rem_4rem_4rem] items-center gap-3 border-b border-neutral-200 px-4 py-3 text-sm text-zinc-900 last:border-b-0 dark:border-neutral-700 dark:text-white">
                                        <div class="min-w-0">
                                            <div class="truncate font-medium">{{ $stat->teamMember?->name }}</div>
                                            <div class="mt-1 text-xs {{ $panel['accent'] }}">{{ str($stat->teamMember?->role)->replace('_', ' ')->headline() }}</div>
                                        </div>
                                        <div class="text-center">{{ $stat->goals }}</div>
                                        <div class="text-center">{{ $stat->assists }}</div>
                                        <div class="text-center">{{ $stat->blocks }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="rounded-xl border border-dashed border-neutral-300 p-5 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                                {{ __('No player totals have been generated for this side yet.') }}
                            </div>
                        @endif
                    </section>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts::app>
