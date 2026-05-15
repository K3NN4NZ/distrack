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
    $scoreInputVisible = $match->isCompletedMatchStatus();
    $isSmallDayOneTrackedRow = \App\Support\SmallFixedRoundRobinDayOneSchedule::isTrackedMatch($match)
        || \App\Support\SmallFixedRoundRobinDayTwoSchedule::isTrackedMatch($match);
    $scheduleStatusLabel = match ($match->status) {
        'scheduled' => __('Upcoming'),
        'live' => __('Live'),
        'completed' => __('Completed'),
        default => (string) str($match->status)->headline(),
    };
    $scoringWaitMessage = match (true) {
        $isSmallDayOneTrackedRow && $match->status === 'scheduled' => __('This Round Robin time slot is upcoming. Scoring will be available after the row is marked Completed.'),
        $isSmallDayOneTrackedRow && $match->status === 'live' => __('This Round Robin time slot is live. Scoring will be available after the row is marked Completed.'),
        $match->status === 'scheduled' => __('This match has not started yet. Scoring will be available once the match is completed.'),
        $match->status === 'live' => __('This match is currently live. Scoring will be available after the match is completed.'),
        default => __('Score input is available only after the schedule marks this game as Completed.'),
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
    $matchNumber = (int) ($match->match_number ?? 0);
    $setupBackTab = match (true) {
        $matchNumber === 48 && \App\Support\SmallDayTwoKnockoutBracket::isSmallDayTwoKnockoutScheduleRow($match) => 'championship',
        $matchNumber >= 45 && $matchNumber <= 47 && \App\Support\SmallDayTwoKnockoutBracket::isSmallDayTwoKnockoutScheduleRow($match) => 'semi-finals',
        default => match ($match->stage) {
            'crossover' => 'crossover',
            'pool', 'pool_play', 'pooling' => 'pooling',
            'round_robin' => 'round-robin',
            'semifinal', 'semi-final', 'semi_final', 'sf', 'semis' => 'semi-finals',
            default => 'quarter-final',
        },
    };
    $spiritScoresByScoredTeamId = $spiritScoresByScoredTeamId ?? collect();
    $matchPdfExportIsFinal = $match->isCompletedMatchStatus();
    $scoreSheetConfigs = [
        ['team' => $homeTeam, 'stats' => $homeStats, 'totalScore' => $match->home_score ?? 0, 'side' => 'home', 'registration' => $homeRegistration],
        ['team' => $awayTeam, 'stats' => $awayStats, 'totalScore' => $match->away_score ?? 0, 'side' => 'away', 'registration' => $awayRegistration],
    ];
    $matchScoreInputState = [];
    $matchScoreMemberSides = [];

    foreach ($scoreSheetConfigs as &$scoreSheetConfig) {
        $statsByMember = $scoreSheetConfig['stats']->keyBy('team_member_id');
        $scoreSheetConfig['statsByMember'] = $statsByMember;

        foreach (($scoreSheetConfig['team']?->members ?? collect()) as $member) {
            $memberId = (string) $member->id;
            $registrationId = (string) ($scoreSheetConfig['registration']?->id ?? '');
            $stat = $statsByMember->get($member->id);

            $matchScoreInputState[$memberId] = [
                'blocks' => old("scores.{$registrationId}.{$memberId}.blocks", $stat?->blocks ?? ''),
                'assists' => old("scores.{$registrationId}.{$memberId}.assists", $stat?->assists ?? ''),
                'scores' => old("scores.{$registrationId}.{$memberId}.scores", $stat?->goals ?? ''),
            ];
            $matchScoreMemberSides[$memberId] = $scoreSheetConfig['side'];
        }
    }
    unset($scoreSheetConfig);

    $matchScorePreviewHome = 0;
    $matchScorePreviewAway = 0;
    foreach ($matchScoreInputState as $memberId => $inputRow) {
        $goals = $inputRow['scores'];
        $goalTotal = $goals === '' || $goals === null ? 0 : (int) $goals;

        if (($matchScoreMemberSides[$memberId] ?? null) === 'home') {
            $matchScorePreviewHome += $goalTotal;
        } elseif (($matchScoreMemberSides[$memberId] ?? null) === 'away') {
            $matchScorePreviewAway += $goalTotal;
        }
    }
@endphp

<x-layouts::app :title="__('Game Score')">
    <div class="space-y-6">
        <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between lg:gap-6">
                <div class="min-w-0 flex-1">
                    <a
                        href="{{ route('admin.tournaments.index', ['tournament' => $tournament->id, 'tab' => $setupBackTab]) }}"
                        wire:navigate
                        class="inline-flex items-center gap-2 text-sm font-medium text-zinc-600 transition hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-white"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M11.78 4.22a.75.75 0 0 1 0 1.06L7.06 10l4.72 4.72a.75.75 0 1 1-1.06 1.06l-5.25-5.25a.75.75 0 0 1 0-1.06l5.25-5.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" />
                        </svg>
                        {{ __('Back to Tournament Setup') }}
                    </a>

                    <h1 class="mt-3 text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ __('Game Score') }}</h1>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                        {{ $isSmallDayOneTrackedRow
                            ? __('Round Robin row status is set on the schedule (one status per time slot for the two games in that slot). Player scores can be entered after that row is marked Completed.')
                            : __('Match status is controlled from the tournament schedule. Player scores can be entered once the game is marked Completed.') }}
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

                <div class="flex w-full shrink-0 flex-col gap-2 lg:w-[13.5rem] lg:items-stretch">
                    @if ($tournament->is_public)
                        <a
                            href="{{ route('tournaments.matches.show', ['tournament' => $tournament, 'match' => $match]) }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex w-full items-center justify-center rounded-lg border border-neutral-300 px-4 py-2 text-center text-sm font-medium text-zinc-700 transition hover:border-neutral-400 hover:bg-zinc-100 dark:border-neutral-700 dark:text-zinc-200 dark:hover:bg-zinc-800"
                        >
                            {{ __('Open Public Match') }}
                        </a>
                    @endif
                    <a
                        href="{{ route('admin.tournaments.matches.pdf', ['tournament' => $tournament, 'match' => $match]) }}"
                        rel="noopener noreferrer"
                        title="{{ $matchPdfExportIsFinal
                            ? __('Final match result PDF. Includes spirit scores when they have been entered.')
                            : __('Printable match and spirit score sheets (blank or with any scores entered so far).') }}"
                        class="inline-flex w-full items-center justify-center rounded-lg border border-neutral-300 bg-white px-4 py-2 text-center text-sm font-semibold text-zinc-800 transition hover:bg-zinc-50 dark:border-neutral-600 dark:bg-zinc-800 dark:text-zinc-100 dark:hover:bg-zinc-700"
                    >
                        {{ $matchPdfExportIsFinal ? __('Export Match PDF') : __('Download MATCH/SPIRIT SCORE SHEETS') }}
                    </a>
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
                        @case('match-score-saved')
                            {{ __('Match score saved.') }}
                            @break
                        @case('spirit-saved')
                            {{ __('Spirit scores saved successfully.') }}
                            @break
                        @case('spirit-score-saved')
                            {{ __('Spirit score saved.') }}
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

                <div
                    class="text-center"
                    x-data="{
                        headerHomeScore: {{ (int) $matchScorePreviewHome }},
                        headerAwayScore: {{ (int) $matchScorePreviewAway }},
                    }"
                    x-on:match-score-updated.window="headerHomeScore = $event.detail.home; headerAwayScore = $event.detail.away"
                >
                    <div class="text-5xl font-semibold tracking-tight text-zinc-900 dark:text-white">
                        <span x-text="headerHomeScore">{{ $match->home_score ?? 0 }}</span>
                        <span class="mx-2 text-zinc-400">-</span>
                        <span x-text="headerAwayScore">{{ $match->away_score ?? 0 }}</span>
                    </div>

                    <div class="mt-4">
                        <span class="inline-flex rounded-[0.7rem] px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.08em] {{ $statusTone }}">
                            {{ $scheduleStatusLabel }}
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
                {{ __('Both home and away registrations must be attached to this game before scores can be entered.') }}
            </section>
        @else
            @if ($scoreInputVisible && $matchHasManualScorelineWithoutLog)
                <section class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200">
                    {{ __('This match currently has a manual scoreline without a scoring timeline. The first live-scoring play will replace that manual score with the new automatic log-based total.') }}
                </section>
            @endif

            @if ($errors->has('score_log'))
                <section class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-200">
                    {{ $errors->first('score_log') }}
                </section>
            @endif

            <section class="rounded-xl border border-neutral-200 bg-white px-6 py-5 text-center dark:border-neutral-700 dark:bg-zinc-900">
                <div class="flex flex-col items-center justify-center gap-2 text-xl font-semibold text-zinc-900 sm:flex-row dark:text-white">
                    <span>{{ $homeTeam?->name ?: __('Home Team') }}</span>
                    <span class="text-sm font-bold uppercase tracking-[0.18em] text-zinc-500 dark:text-zinc-400">{{ __('vs') }}</span>
                    <span>{{ $awayTeam?->name ?: __('Away Team') }}</span>
                </div>
            </section>

            @if ($scoreInputVisible)
            @php
                $matchScoreErrorMessages = collect($errors->getMessages())
                    ->filter(fn (array $msgs, string $key): bool => str_starts_with($key, 'scores.') || $key === 'match_score')
                    ->flatten()
                    ->merge(
                        $errors->has('match_score')
                            ? collect([$errors->first('match_score')])
                            : collect(),
                    )
                    ->unique()
                    ->values();
            @endphp

            @if ($matchScoreErrorMessages->isNotEmpty())
                <section class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-200">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($matchScoreErrorMessages as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <script>
                window.adminMatchScoreSheet = function (config) {
                    return {
                        homeScore: Number(config.initialHomeScore ?? 0),
                        awayScore: Number(config.initialAwayScore ?? 0),
                        playerStats: config.playerStats ?? {},
                        memberSides: config.memberSides ?? {},
                        init() {
                            this.syncPreview();
                        },
                        normalizeValue(value) {
                            if (value === '' || value === null || value === undefined) {
                                return 0;
                            }

                            const n = Number(value);

                            return Number.isNaN(n) ? 0 : n;
                        },
                        handleInput(memberId, field, rawValue) {
                            if (! this.playerStats[memberId]) {
                                this.playerStats[memberId] = {
                                    blocks: '',
                                    assists: '',
                                    scores: '',
                                };
                            }

                            this.playerStats[memberId][field] = rawValue;
                            this.syncPreview();
                        },
                        syncPreview() {
                            let home = 0;
                            let away = 0;

                            for (const [memberId, fields] of Object.entries(this.playerStats)) {
                                const score = this.normalizeValue(fields?.scores);

                                if (this.memberSides[memberId] === 'home') {
                                    home += score;
                                } else if (this.memberSides[memberId] === 'away') {
                                    away += score;
                                }
                            }

                            this.homeScore = home;
                            this.awayScore = away;
                            window.dispatchEvent(new CustomEvent('match-score-updated', {
                                detail: { home, away },
                            }));
                        },
                    };
                };
            </script>

            <form
                method="POST"
                action="{{ route('admin.tournaments.matches.scoring.match-score.update', ['tournament' => $tournament, 'match' => $match]) }}"
                class="space-y-6"
                x-data="window.adminMatchScoreSheet({
                    initialHomeScore: {{ (int) $matchScorePreviewHome }},
                    initialAwayScore: {{ (int) $matchScorePreviewAway }},
                    playerStats: @js($matchScoreInputState),
                    memberSides: @js($matchScoreMemberSides),
                })"
            >
                @csrf
                @method('PATCH')

                <div class="grid gap-6 sm:grid-cols-2">
                @foreach ($scoreSheetConfigs as $sheet)
                    @php
                        $sheetTeam = $sheet['team'];
                        $sheetStatsByMember = $sheet['stats']->keyBy('team_member_id');
                        $sheetMembers = $sheetTeam?->members ?? collect();
                        $sheetMaleMembers = $sheetMembers->filter(fn ($member) => strtolower((string) $member->gender) === 'male')->values();
                        $sheetFemaleMembers = $sheetMembers->filter(fn ($member) => strtolower((string) $member->gender) === 'female')->values();
                        $sheetOtherMembers = $sheetMembers
                            ->filter(fn ($member) => ! in_array(strtolower((string) $member->gender), ['male', 'female'], true))
                            ->values();
                        $sheetGenderGroups = collect([
                            ['label' => __('MALE'), 'roster' => $sheetMaleMembers],
                            ['label' => __('FEMALE'), 'roster' => $sheetFemaleMembers],
                        ]);

                        if ($sheetOtherMembers->isNotEmpty()) {
                            $sheetGenderGroups->push([
                                'label' => __('OTHER'),
                                'roster' => $sheetOtherMembers,
                            ]);
                        }
                    @endphp

                    <section class="overflow-hidden rounded-xl border border-neutral-300 bg-white text-zinc-900 shadow-sm dark:border-neutral-700 dark:bg-zinc-900 dark:text-zinc-100">
                        <table class="w-full border-collapse text-sm">
                            <thead>
                                <tr class="border-b border-neutral-300 dark:border-neutral-700">
                                    <th colspan="4" class="border-r border-neutral-300 px-3 py-2 text-center text-base font-semibold uppercase tracking-wide dark:border-neutral-700">
                                        {{ $sheetTeam?->name ?: ($sheet['side'] === 'home' ? __('Home Team') : __('Away Team')) }}
                                    </th>
                                    <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide">
                                        <div>{{ __('TOTAL SCORE') }}</div>
                                        <div class="mt-1 text-2xl font-bold tracking-tight" x-text="{{ $sheet['side'] === 'home' ? 'homeScore' : 'awayScore' }}">{{ $sheet['totalScore'] }}</div>
                                    </th>
                                </tr>
                                <tr class="border-b border-neutral-300 bg-yellow-200 text-zinc-900 dark:border-neutral-700 dark:bg-yellow-300 dark:text-zinc-900">
                                    <th class="w-10 border-r border-neutral-300 px-2 py-1.5 text-center text-xs font-bold uppercase">#</th>
                                    <th class="border-r border-neutral-300 px-3 py-1.5 text-center text-xs font-bold uppercase">{{ __('NAMES') }}</th>
                                    <th class="w-20 border-r border-neutral-300 px-2 py-1.5 text-center text-xs font-bold uppercase">{{ __('BLOCKS') }}</th>
                                    <th class="w-20 border-r border-neutral-300 px-2 py-1.5 text-center text-xs font-bold uppercase">{{ __('ASSISTS') }}</th>
                                    <th class="w-20 px-2 py-1.5 text-center text-xs font-bold uppercase">{{ __('SCORES') }}</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($sheetGenderGroups as $group)
                                    <tr class="border-b border-neutral-300 bg-zinc-50 dark:border-neutral-700 dark:bg-zinc-950">
                                        <td colspan="5" class="px-3 py-1 text-xs font-semibold italic uppercase tracking-wide text-zinc-700 dark:text-zinc-300">
                                            {{ $group['label'] }}
                                        </td>
                                    </tr>

                                    @foreach ($group['roster'] as $member)
                                        @php
                                            $stat = $sheetStatsByMember->get($member->id);
                                        @endphp
                                        <tr class="border-b border-neutral-200 last:border-b-0 dark:border-neutral-800">
                                            <td class="w-10 border-r border-neutral-200 px-2 py-1 text-center text-xs text-zinc-500 dark:border-neutral-800 dark:text-zinc-400">
                                                {{ $loop->iteration }}
                                            </td>
                                            <td class="border-r border-neutral-200 px-3 py-1 text-sm italic text-zinc-800 dark:border-neutral-800 dark:text-zinc-100">
                                                {{ $member->name }}
                                            </td>
                                            <td class="w-20 border-r border-neutral-200 px-1 py-0.5 text-center dark:border-neutral-800">
                                                <input
                                                    type="number"
                                                    min="0"
                                                    max="999"
                                                    name="scores[{{ $sheet['registration']?->id }}][{{ $member->id }}][blocks]"
                                                    value="{{ old('scores.'.($sheet['registration']?->id).'.'.$member->id.'.blocks', $stat?->blocks ?? '') }}"
                                                    x-on:input="handleInput('{{ $member->id }}', 'blocks', $event.target.value)"
                                                    class="h-7 w-full rounded border border-transparent bg-transparent px-1 py-0 text-center text-sm text-zinc-900 transition-colors focus:border-neutral-400 focus:bg-white focus:outline-none dark:text-zinc-100 dark:focus:border-neutral-500 dark:focus:bg-zinc-950"
                                                >
                                            </td>
                                            <td class="w-20 border-r border-neutral-200 px-1 py-0.5 text-center dark:border-neutral-800">
                                                <input
                                                    type="number"
                                                    min="0"
                                                    max="999"
                                                    name="scores[{{ $sheet['registration']?->id }}][{{ $member->id }}][assists]"
                                                    value="{{ old('scores.'.($sheet['registration']?->id).'.'.$member->id.'.assists', $stat?->assists ?? '') }}"
                                                    x-on:input="handleInput('{{ $member->id }}', 'assists', $event.target.value)"
                                                    class="h-7 w-full rounded border border-transparent bg-transparent px-1 py-0 text-center text-sm text-zinc-900 transition-colors focus:border-neutral-400 focus:bg-white focus:outline-none dark:text-zinc-100 dark:focus:border-neutral-500 dark:focus:bg-zinc-950"
                                                >
                                            </td>
                                            <td class="w-20 px-1 py-0.5 text-center">
                                                <input
                                                    type="number"
                                                    min="0"
                                                    max="999"
                                                    name="scores[{{ $sheet['registration']?->id }}][{{ $member->id }}][scores]"
                                                    value="{{ old('scores.'.($sheet['registration']?->id).'.'.$member->id.'.scores', $stat?->goals ?? '') }}"
                                                    x-on:input="handleInput('{{ $member->id }}', 'scores', $event.target.value)"
                                                    class="h-7 w-full rounded border border-transparent bg-transparent px-1 py-0 text-center text-sm text-zinc-900 transition-colors focus:border-neutral-400 focus:bg-white focus:outline-none dark:text-zinc-100 dark:focus:border-neutral-500 dark:focus:bg-zinc-950"
                                                >
                                            </td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </section>
                @endforeach
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-[#2f55b7] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#26479b] focus:outline-none focus:ring-2 focus:ring-[#2f55b7]/40"
                    >
                        {{ __('Save Match Score') }}
                    </button>
                </div>
            </form>

            @include('admin.tournaments.partials.spirit-scoring-form', [
                'tournament' => $tournament,
                'match' => $match,
                'homeTeam' => $homeTeam,
                'awayTeam' => $awayTeam,
                'homeRegistration' => $homeRegistration,
                'awayRegistration' => $awayRegistration,
                'spiritScoresByScoredTeamId' => $spiritScoresByScoredTeamId,
            ])
            @else
                <section class="rounded-xl border border-dashed border-neutral-300 bg-zinc-50 p-6 text-center text-sm text-zinc-600 dark:border-neutral-700 dark:bg-zinc-900 dark:text-zinc-300">
                    {{ $scoringWaitMessage }}
                </section>
            @endif

            @if ($match->scoreLogs->isNotEmpty())
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

                                    @if ($scoreInputVisible)
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
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-neutral-300 p-6 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                                {{ __('No scoring plays have been logged yet. The scoreline will start updating automatically once you add the first point.') }}
                            </div>
                        @endforelse
                    </div>
                </section>
            @endif

            @if ($match->scoreLogs->isNotEmpty())
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
        @endif
    </div>
</x-layouts::app>

