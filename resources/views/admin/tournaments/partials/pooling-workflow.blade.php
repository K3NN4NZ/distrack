@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\TournamentMatch> $crossoverMatches */
    /** @var \App\Models\Tournament $selectedTournament */
    $poolingData = \App\Support\TournamentPooling::buildPoolingAssignments($selectedTournament, $crossoverMatches);

    $usesCustomPoolingRules = $poolingData['rules_configured'];
    $poolingModeActive = $poolingData['pooling_mode'] ?? \App\Support\TournamentPooling::POOLING_MODE_AUTO;
    $poolingAllCrossoverFinal = $poolingData['all_crossover_finalized'];
    $poolingFinalizedMatchCount = $poolingData['finalized_match_count'];
    $duplicateWarnings = $poolingData['duplicate_team_warnings'];
    $poolGrid = $poolingData['pools'];

    $manualSlots = $selectedTournament->pooling_manual_slots ?? [];
    $manualSelectedA = array_map('intval', is_array($manualSlots['pool_a'] ?? null) ? $manualSlots['pool_a'] : []);
    $manualSelectedB = array_map('intval', is_array($manualSlots['pool_b'] ?? null) ? $manualSlots['pool_b'] : []);

    $qualifiedPoolingRegistrations = $crossoverMatches
        ->flatMap(fn ($match) => collect([$match->homeRegistration, $match->awayRegistration])->filter())
        ->unique('id')
        ->sortBy(fn ($registration) => [$registration->team?->name ?? '', $registration->id])
        ->values();

    $crossoverGameCountForDiagram = (int) ($poolingData['crossover_game_count'] ?? 0);
    $diagramSlotsTotal = (int) ($poolingData['diagram_slot_capacity'] ?? 0);
    $diagramBelowFullCrossover = (bool) ($poolingData['diagram_below_full_crossover_count'] ?? false);

    $dynamicDiagramPoolASlots = \App\Support\TournamentPooling::diagramPoolASlotCodesForCrossoverGameCount($crossoverGameCountForDiagram);
    $dynamicDiagramPoolBSlots = \App\Support\TournamentPooling::diagramPoolBSlotCodesForCrossoverGameCount($crossoverGameCountForDiagram);

    $qualifiedPoolingCount = $qualifiedPoolingRegistrations->count();

    $poolingResolvedSlots = collect($poolGrid)
        ->pluck('rows')
        ->flatten(1)
        ->where('pending', false)
        ->count();

    $poolingTotalSlots = collect($poolGrid)->sum(fn (array $p): int => count($p['rows']));
    $poolingPendingMatchCount = max($crossoverMatches->count() - $poolingFinalizedMatchCount, 0);
@endphp

<section class="space-y-6">
    <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Pooling') }}</h2>
                <p class="mt-1 max-w-3xl text-sm text-zinc-600 dark:text-zinc-300">
                    @if ($crossoverMatches->isEmpty())
                        {{ __('Automatic pooling uses the standard diagram — Pool A: :full_a · Pool B: :full_b — but only builds slots for crossover games that actually exist (game numbers from schedule labels / match numbers). Manual pooling is available after crossover finishes.', [
                            'full_a' => implode(', ', \App\Support\TournamentPooling::DIAGRAM_POOL_A_SLOT_CODES),
                            'full_b' => implode(', ', \App\Support\TournamentPooling::DIAGRAM_POOL_B_SLOT_CODES),
                        ]) }}
                    @elseif ($crossoverGameCountForDiagram > 0)
                        {{ __('For :games crossover game(s), Pool A uses :a and Pool B uses :b. Slots that reference games which do not exist (for example W7 when there is no game 7) are omitted.', [
                            'games' => $crossoverGameCountForDiagram,
                            'a' => implode(', ', $dynamicDiagramPoolASlots) ?: '—',
                            'b' => implode(', ', $dynamicDiagramPoolBSlots) ?: '—',
                        ]) }}
                    @else
                        {{ __('Automatic pooling follows the tournament diagram: Pool A uses :a and Pool B uses :b.', [
                            'a' => implode(', ', \App\Support\TournamentPooling::DIAGRAM_POOL_A_SLOT_CODES),
                            'b' => implode(', ', \App\Support\TournamentPooling::DIAGRAM_POOL_B_SLOT_CODES),
                        ]) }}
                    @endif
                </p>
                @if ($diagramBelowFullCrossover && ! $usesCustomPoolingRules && $crossoverMatches->isNotEmpty())
                    <p class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/35 dark:text-amber-100">
                        {{ __('Standard format uses up to :full crossover games; this schedule has :current. Extra diagram slots are hidden so nothing shows as pending for games that were never created.', [
                            'full' => \App\Support\TournamentPooling::DIAGRAM_FULL_CROSSOVER_GAME_COUNT,
                            'current' => $crossoverGameCountForDiagram,
                        ]) }}
                    </p>
                @endif
                @if ($usesCustomPoolingRules)
                    <p class="mt-2 text-xs font-medium text-[#2f55b7] dark:text-sky-300">{{ __('Custom pooling_rules JSON layout takes priority over the diagram when configured.') }}</p>
                @endif
                @if ($poolingModeActive === \App\Support\TournamentPooling::POOLING_MODE_MANUAL)
                    <p class="mt-2 text-xs font-semibold text-amber-800 dark:text-amber-200">{{ __('Manual pooling mode is active—crossover scoring will not overwrite saved pool tags.') }}</p>
                @endif
            </div>

            @if ($crossoverMatches->isNotEmpty())
                <div class="rounded-xl border border-neutral-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-700 dark:border-neutral-700 dark:bg-zinc-950 dark:text-zinc-300">
                    <div class="font-semibold text-zinc-900 dark:text-white">{{ __('Crossover progress') }}</div>
                    <div class="mt-1">{{ __(':finalized of :total decisive results', ['finalized' => $poolingFinalizedMatchCount, 'total' => $crossoverMatches->count()]) }}</div>
                    @if ($poolingTotalSlots > 0)
                        <div class="mt-2 border-t border-neutral-200 pt-2 text-xs text-zinc-600 dark:border-neutral-700 dark:text-zinc-400">
                            {{ __('Slots resolved: :resolved / :total', ['resolved' => $poolingResolvedSlots, 'total' => $poolingTotalSlots]) }}
                        </div>
                    @endif
                </div>
            @endif
        </div>

        @if ($errors->has('pooling'))
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-200">
                {{ $errors->first('pooling') }}
            </div>
        @endif

        @if ($errors->has('pool_a_registration_ids') || $errors->has('pool_b_registration_ids'))
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-200">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->get('pool_a_registration_ids') as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                    @foreach ($errors->get('pool_b_registration_ids') as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($crossoverMatches->isEmpty())
            <div class="mt-5 rounded-lg border border-dashed border-neutral-300 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-neutral-700 dark:bg-zinc-950 dark:text-zinc-300">
                {{ __('No crossover matches found yet. Generate crossover games first—Pooling will populate automatically as results are recorded.') }}
            </div>
        @elseif ($poolingFinalizedMatchCount === 0)
            <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200">
                {{ __('No completed crossover games found yet. Pooling will be completed once crossover results are available.') }}
            </div>
        @elseif (! $poolingAllCrossoverFinal)
            <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200">
                {{ __('Pooling will be completed once crossover results are available. :count crossover games still need a decisive completed result. Pending slots stay in place below.', ['count' => $poolingPendingMatchCount]) }}
            </div>
        @else
            <div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-200">
                {{ __('Crossover is complete. Registration pool tags reflect automatic diagram pooling unless you enable manual mode.') }}
            </div>
        @endif

        @if ($duplicateWarnings !== [])
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-200">
                <div class="font-semibold">{{ __('Duplicate team assignments') }}</div>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($duplicateWarnings as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($crossoverMatches->isNotEmpty())
            <section class="mt-6 space-y-4 rounded-xl border border-neutral-200 bg-zinc-50 p-5 dark:border-neutral-700 dark:bg-zinc-950/40">
                <div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">{{ __('Pooling controls') }}</h3>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                        {{ __('Choose automatic diagram pooling or manual Pool A / Pool B assignments after crossover is finished.') }}
                    </p>
                </div>

                <div class="space-y-2">
                    <h4 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Auto-generate pooling') }}</h4>
                    <p class="text-xs text-zinc-600 dark:text-zinc-300">
                        @if ($crossoverGameCountForDiagram > 0)
                            {{ __('Uses diagram slots for your :games crossover games — Pool A (:a) · Pool B (:b).', [
                                'games' => $crossoverGameCountForDiagram,
                                'a' => implode(', ', $dynamicDiagramPoolASlots) ?: '—',
                                'b' => implode(', ', $dynamicDiagramPoolBSlots) ?: '—',
                            ]) }}
                        @else
                            {{ __('Uses the standard diagram when crossover games exist (Pool A / Pool B slot lists adjust automatically).') }}
                        @endif
                    </p>
                    <form method="POST" action="{{ route('admin.tournaments.pooling.auto', $selectedTournament) }}" class="inline">
                        @csrf
                        <input type="hidden" name="redirect_tab" value="pooling">
                        <flux:button type="submit" variant="primary">
                            {{ __('Apply auto-generated pooling') }}
                        </flux:button>
                    </form>
                </div>

                <div class="border-t border-neutral-200 pt-4 dark:border-neutral-700">
                    <h4 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Manual pooling') }}</h4>
                    <p class="mt-1 text-xs text-zinc-600 dark:text-zinc-300">{{ __('Manual selections override diagram pooling and are saved for later stages until you switch back to auto.') }}</p>
                    @if ($diagramSlotsTotal > 0 && $qualifiedPoolingCount === $diagramSlotsTotal)
                        <p class="mt-1 text-xs text-zinc-600 dark:text-zinc-300">
                            {{ __('Select exactly :a teams for Pool A and :b teams for Pool B (hold Ctrl / ⌘ to multi-select). Every crossover team must appear once.', [
                                'a' => count($dynamicDiagramPoolASlots),
                                'b' => count($dynamicDiagramPoolBSlots),
                            ]) }}
                        </p>
                    @else
                        <p class="mt-1 text-xs text-zinc-600 dark:text-zinc-300">
                            {{ __('Select overlapping-free teams for each pool until every crossover registrant (:total teams) is assigned.', ['total' => $qualifiedPoolingCount]) }}
                        </p>
                    @endif

                    <form method="POST" action="{{ route('admin.tournaments.pooling.manual', $selectedTournament) }}" class="mt-4 space-y-4">
                        @csrf
                        <input type="hidden" name="redirect_tab" value="pooling">

                        <fieldset @disabled(! $poolingAllCrossoverFinal) class="min-w-0 space-y-4 border-0 p-0 disabled:opacity-60">
                            @if (! $poolingAllCrossoverFinal)
                                <p class="text-xs font-medium text-amber-700 dark:text-amber-300">{{ __('Finish crossover scoring before saving manual pools.') }}</p>
                            @endif

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-zinc-800 dark:text-zinc-200" for="pool-a-registration-ids">{{ __('Pool A registrations') }}</label>
                                    <select
                                        id="pool-a-registration-ids"
                                        name="pool_a_registration_ids[]"
                                        multiple
                                        size="10"
                                        class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-[#2f55b7] focus:outline-none focus:ring-1 focus:ring-[#2f55b7] dark:border-neutral-600 dark:bg-zinc-900 dark:text-zinc-100"
                                    >
                                        @foreach ($qualifiedPoolingRegistrations as $registration)
                                            <option value="{{ $registration->id }}" @selected(in_array((int) $registration->id, $manualSelectedA, true))>
                                                {{ $registration->team?->name ?? __('Registration #:id', ['id' => $registration->id]) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-zinc-800 dark:text-zinc-200" for="pool-b-registration-ids">{{ __('Pool B registrations') }}</label>
                                    <select
                                        id="pool-b-registration-ids"
                                        name="pool_b_registration_ids[]"
                                        multiple
                                        size="10"
                                        class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-[#2f55b7] focus:outline-none focus:ring-1 focus:ring-[#2f55b7] dark:border-neutral-600 dark:bg-zinc-900 dark:text-zinc-100"
                                    >
                                        @foreach ($qualifiedPoolingRegistrations as $registration)
                                            <option value="{{ $registration->id }}" @selected(in_array((int) $registration->id, $manualSelectedB, true))>
                                                {{ $registration->team?->name ?? __('Registration #:id', ['id' => $registration->id]) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <flux:button type="submit" variant="ghost">
                                    {{ __('Save manual pooling') }}
                                </flux:button>
                                <flux:button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="text-zinc-600 dark:text-zinc-400"
                                    onclick="(() => { ['pool-a-registration-ids','pool-b-registration-ids'].forEach((id) => { const el = document.getElementById(id); if (el) { Array.from(el.options).forEach((o) => { o.selected = false; }); } }); })()"
                                >
                                    {{ __('Clear multi-select (unsaved)') }}
                                </flux:button>
                            </div>
                        </fieldset>
                    </form>
                </div>

                <div class="border-t border-neutral-200 pt-4 dark:border-neutral-700">
                    <h4 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Clear saved pool assignments') }}</h4>
                    <p class="mt-1 text-xs text-zinc-600 dark:text-zinc-300">
                        {{ __('Removes Pool A / Pool B tags from crossover teams and deletes the manual pooling snapshot. Crossover games and field placements are not affected. Use Apply auto-generated pooling afterward if you want diagram-based tags again.') }}
                    </p>
                    <form
                        method="POST"
                        action="{{ route('admin.tournaments.pooling.clear', $selectedTournament) }}"
                        class="mt-3 inline"
                        onsubmit="return confirm(@json(__('Clear all saved pool assignments for crossover teams? You can re-apply diagram pooling or save manual pools again afterward.')))"
                    >
                        @csrf
                        <input type="hidden" name="redirect_tab" value="pooling">
                        <flux:button type="submit" variant="ghost" class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300">
                            {{ __('Clear saved pools') }}
                        </flux:button>
                    </form>
                </div>
            </section>
        @endif
    </section>

    @if ($crossoverMatches->isNotEmpty() && $poolGrid !== [])
        <section class="grid gap-6 lg:grid-cols-2">
            @foreach ($poolGrid as $poolSection)
                @php
                    $rows = $poolSection['rows'];
                @endphp

                <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-xl font-semibold uppercase tracking-wide text-zinc-900 dark:text-white">{{ $poolSection['name'] }}</h3>
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $poolSection['subtitle'] }}</p>
                        </div>

                        @if ($rows !== [])
                            <span class="rounded-full border border-neutral-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-700 dark:border-neutral-700 dark:bg-zinc-950 dark:text-zinc-300">
                                {{ trans_choice('{0} No resolved slots|{1} :count slot filled|[2,*] :count slots filled', collect($rows)->where('pending', false)->count(), ['count' => collect($rows)->where('pending', false)->count()]) }}
                            </span>
                        @endif
                    </div>

                    @if ($rows === [])
                        <div class="mt-5 rounded-lg border border-dashed border-neutral-300 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-neutral-700 dark:bg-zinc-950 dark:text-zinc-300">
                            {{ __('No slots yet—add crossover games to populate this pool.') }}
                        </div>
                    @else
                        <div class="mt-5 overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                            <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                                <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:bg-zinc-950 dark:text-zinc-400">
                                    <tr>
                                        <th class="px-4 py-3">{{ __('#') }}</th>
                                        <th class="px-4 py-3">{{ __('Slot') }}</th>
                                        <th class="px-4 py-3">{{ __('Source') }}</th>
                                        <th class="px-4 py-3">{{ __('Team') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-neutral-200 bg-white dark:divide-neutral-700 dark:bg-zinc-900">
                                    @foreach ($rows as $row)
                                        @php
                                            if (($row['outcome'] ?? '') === 'manual') {
                                                $sourceLong = __('Manual assignment');
                                            } elseif (($row['game_number'] ?? null) !== null) {
                                                $sourceLong = $row['outcome'] === 'winner'
                                                    ? __('Winner of crossover game #:n', ['n' => $row['game_number']])
                                                    : __('Loser of crossover game #:n', ['n' => $row['game_number']]);
                                            } else {
                                                $sourceLong = __('Unknown crossover game number');
                                            }
                                        @endphp
                                        <tr class="{{ $row['pending'] ? 'bg-amber-50/60 dark:bg-amber-950/15' : '' }}">
                                            <td class="whitespace-nowrap px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">
                                                {{ $row['position'] }}.
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3 font-mono text-xs font-semibold text-[#2f55b7] dark:text-sky-300">
                                                {{ $row['slot_code'] }}
                                            </td>
                                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">
                                                <div>{{ $sourceLong }}</div>
                                                @if (! empty($row['crossover_round_label']))
                                                    <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-500">{{ $row['crossover_round_label'] }}</div>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-zinc-900 dark:text-zinc-100">
                                                @if ($row['pending'])
                                                    <span class="text-amber-800 dark:text-amber-200">{{ __('Pending result') }}</span>
                                                @else
                                                    {{ $row['team_name'] ?? __('Team # :id', ['id' => $row['team_id'] ?? $row['registration_id']]) }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @endforeach
        </section>
    @endif
</section>
