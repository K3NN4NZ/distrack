@php
    $homeSpirit = $homeTeam ? $spiritScoresByScoredTeamId->get($homeTeam->id) : null;
    $awaySpirit = $awayTeam ? $spiritScoresByScoredTeamId->get($awayTeam->id) : null;
    $homeSpiritCaptain = $homeTeam?->members?->firstWhere('role', 'spirit_captain');
    $awaySpiritCaptain = $awayTeam?->members?->firstWhere('role', 'spirit_captain');

    $criteriaRows = [
        [
            'field' => 'knowledge_rules_score',
            'title' => __('Knowledge and Use of Rules'),
            'lines' => [
                __('3 - Excellent understanding and fair application of rules'),
                __('2 - Average understanding, occasional disputes'),
                __('1 - Poor rule knowledge and unsportsmanlike use of rules'),
            ],
        ],
        [
            'field' => 'fouls_body_contact_score',
            'title' => __('Fouls and Body Contact'),
            'lines' => [
                __('3 - No dangerous plays, highly respectful gameplay'),
                __('2 - Average level of contact'),
                __('1 - Unsafe and unsportsmanlike behavior'),
            ],
        ],
        [
            'field' => 'fair_mindedness_score',
            'title' => __('Fair Mindedness'),
            'lines' => [
                __('3 - Outstanding honesty and fairness'),
                __('2 - Acceptable sportsmanship'),
                __('1 - Poor sportsmanship and unfair behavior'),
            ],
        ],
        [
            'field' => 'positive_attitude_score',
            'title' => __('Positive Attitude and Self-Control'),
            'lines' => [
                __('3 - Extremely positive and respectful throughout the game'),
                __('2 - Average behavior'),
                __('1 - Disrespectful or hostile conduct'),
            ],
        ],
        [
            'field' => 'communication_respect_score',
            'title' => __('Communication and Respect'),
            'lines' => [
                __('3 - Excellent communication and mutual respect'),
                __('2 - Average communication'),
                __('1 - Disrespectful communication'),
            ],
        ],
    ];
@endphp

<section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Spirit Scoring') }}</h2>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('Spirit scores save automatically when you change a value (short delay). Maximum total is 15 when all five criteria are set.') }}
            </p>
        </div>
        <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400" aria-live="polite">
            {{ __('Auto-save') }}
        </p>
    </div>

    @php
        $spiritErrorMessages = collect($errors->getMessages())
            ->filter(fn (array $msgs, string $key): bool => str_starts_with($key, 'spirit.'))
            ->flatten()
            ->merge(
                $errors->has('spirit')
                    ? collect([$errors->first('spirit')])
                    : collect(),
            )
            ->unique()
            ->values();
    @endphp

    @if ($spiritErrorMessages->isNotEmpty())
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($spiritErrorMessages as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        @foreach ([
            'home' => [
                'team' => $homeTeam,
                'opponent' => $awayTeam,
                'record' => $homeSpirit,
                'captain' => $homeSpiritCaptain,
                'registration' => $homeRegistration,
            ],
            'away' => [
                'team' => $awayTeam,
                'opponent' => $homeTeam,
                'record' => $awaySpirit,
                'captain' => $awaySpiritCaptain,
                'registration' => $awayRegistration,
            ],
        ] as $sideKey => $side)
            @php
                $team = $side['team'];
                $opponent = $side['opponent'];
                $record = $side['record'];
                $captain = $side['captain'];
                $registration = $side['registration'];
            @endphp

            <div
                class="flex flex-col overflow-hidden rounded-xl border border-neutral-300 bg-zinc-50 shadow-sm dark:border-neutral-600 dark:bg-zinc-950"
                data-match-id="{{ $match->id }}"
                data-scored-registration-id="{{ $registration?->id }}"
                data-scoring-registration-id="{{ $sideKey === 'home' ? $awayRegistration?->id : $homeRegistration?->id }}"
                data-scored-team-id="{{ $team?->id }}"
                data-scoring-team-id="{{ $opponent?->id }}"
                x-data="window.adminSpiritTeamSheet({
                    patchUrl: @js(route('admin.tournaments.matches.scoring.spirit-scores.patch', ['tournament' => $tournament, 'match' => $match])),
                    csrf: @js(csrf_token()),
                    scoredTeamId: {{ (int) ($team?->id ?? 0) }},
                    scoringTeamId: {{ (int) ($opponent?->id ?? 0) }},
                    initial: {
                        knowledge_rules_score: @js(old('spirit.'.$sideKey.'.knowledge_rules_score', $record?->knowledge_rules_score !== null ? (string) $record->knowledge_rules_score : '')),
                        fouls_body_contact_score: @js(old('spirit.'.$sideKey.'.fouls_body_contact_score', $record?->fouls_body_contact_score !== null ? (string) $record->fouls_body_contact_score : '')),
                        fair_mindedness_score: @js(old('spirit.'.$sideKey.'.fair_mindedness_score', $record?->fair_mindedness_score !== null ? (string) $record->fair_mindedness_score : '')),
                        positive_attitude_score: @js(old('spirit.'.$sideKey.'.positive_attitude_score', $record?->positive_attitude_score !== null ? (string) $record->positive_attitude_score : '')),
                        communication_respect_score: @js(old('spirit.'.$sideKey.'.communication_respect_score', $record?->communication_respect_score !== null ? (string) $record->communication_respect_score : '')),
                        notes: @js(old('spirit.'.$sideKey.'.notes', $record?->notes ?? '')),
                    },
                    savingText: @js(__('Saving…')),
                    savedText: @js(__('Saved')),
                    errorText: @js(__('Error saving')),
                })"
            >
                <div class="flex items-start justify-between gap-3 border-b border-neutral-300 bg-white px-4 py-3 dark:border-neutral-600 dark:bg-zinc-900">
                    <div class="min-w-0 flex-1 text-center">
                        <div class="text-xs font-bold uppercase tracking-[0.2em] text-zinc-700 dark:text-zinc-200">{{ __('Spirit Scoresheet') }}</div>
                        <div class="mt-2 text-sm font-semibold text-zinc-900 dark:text-white">
                            {{ $team?->name ?: __('Team') }} {{ __('vs.') }} {{ $opponent?->name ?: __('Opponent') }}
                        </div>
                        <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Game #:num', ['num' => $match->match_number ?? '—']) }}
                        </div>
                    </div>
                    <div
                        class="shrink-0 text-right text-xs font-medium"
                        :class="{
                            'text-zinc-500': saveStatus === 'idle',
                            'text-amber-600': saveStatus === 'saving',
                            'text-emerald-600': saveStatus === 'saved',
                            'text-red-600': saveStatus === 'error',
                        }"
                        x-text="saveMessage"
                        x-show="saveMessage"
                        x-cloak
                    ></div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left text-sm">
                        <thead>
                            <tr class="border-b border-neutral-300 bg-white dark:border-neutral-600 dark:bg-zinc-900">
                                <th class="px-3 py-2 text-xs font-bold uppercase tracking-wide text-zinc-800 dark:text-zinc-100">{{ __('Criteria') }}</th>
                                <th class="w-24 px-2 py-2 text-center text-xs font-bold uppercase tracking-wide text-zinc-800 dark:text-zinc-100">{{ __('Score') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($criteriaRows as $row)
                                @php $field = $row['field']; @endphp
                                <tr class="border-b border-neutral-200 bg-white last:border-b-0 dark:border-neutral-700 dark:bg-zinc-900">
                                    <td class="px-3 py-2 align-top text-zinc-800 dark:text-zinc-100">
                                        <div class="font-medium">{{ $row['title'] }}</div>
                                        <ul class="mt-1 list-none space-y-0.5 text-xs leading-snug text-zinc-600 dark:text-zinc-400">
                                            @foreach ($row['lines'] as $line)
                                                <li>{{ $line }}</li>
                                            @endforeach
                                        </ul>
                                    </td>
                                    <td class="px-2 py-2 align-middle text-center">
                                        @php
                                            $currentScore = old('spirit.'.$sideKey.'.'.$field, $record?->{$field});
                                        @endphp
                                        <select
                                            x-model="{{ $field }}"
                                            class="h-9 w-full max-w-[5.5rem] rounded-md border border-neutral-300 bg-white px-2 text-center text-sm font-medium text-zinc-900 focus:border-neutral-500 focus:outline-none focus:ring-1 focus:ring-neutral-400 dark:border-neutral-600 dark:bg-zinc-950 dark:text-zinc-100"
                                        >
                                            <option value="" @selected($currentScore === null || $currentScore === '')>—</option>
                                            @foreach ([1, 2, 3] as $opt)
                                                <option value="{{ $opt }}" @selected((string) $currentScore === (string) $opt)>{{ $opt }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                            <tr class="border-t-2 border-neutral-400 bg-zinc-100 font-semibold dark:border-neutral-500 dark:bg-zinc-800">
                                <td class="px-3 py-2 text-zinc-900 dark:text-white">{{ __('Total') }} <span class="text-xs font-normal text-zinc-500">({{ __('max 15') }})</span></td>
                                <td class="px-2 py-2 text-center text-base text-zinc-900 dark:text-white">
                                    <span x-text="spiritTotalDisplay"></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-neutral-300 bg-zinc-50 px-4 py-3 dark:border-neutral-600 dark:bg-zinc-950">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-400">{{ __('Notes (optional)') }}</label>
                    <textarea
                        x-model="notes"
                        rows="2"
                        maxlength="1000"
                        class="mt-1 w-full rounded-md border border-neutral-300 bg-white px-2 py-1.5 text-sm text-zinc-900 focus:border-neutral-500 focus:outline-none dark:border-neutral-600 dark:bg-zinc-900 dark:text-zinc-100"
                        placeholder="{{ __('Short comment…') }}"
                    ></textarea>
                </div>

                <div class="mt-auto border-t border-neutral-300 bg-white px-4 py-4 text-center dark:border-neutral-600 dark:bg-zinc-900">
                    @if ($captain)
                        <div class="text-base font-semibold text-zinc-900 dark:text-white">{{ $captain->name }}</div>
                        <div class="mt-1 text-xs font-bold uppercase tracking-[0.15em] text-zinc-600 dark:text-zinc-300">{{ __('Spirit Captain') }}</div>
                        <div class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Signature Above Printed Name') }}</div>
                    @else
                        <div class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('No spirit captain assigned') }}</div>
                        <div class="mt-1 text-xs font-bold uppercase tracking-[0.15em] text-zinc-500 dark:text-zinc-400">{{ __('Spirit Captain') }}</div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</section>
