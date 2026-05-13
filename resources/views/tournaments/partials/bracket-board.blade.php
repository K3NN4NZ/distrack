<div class="mt-6 overflow-x-auto pb-3">
    <div class="flex min-w-[62rem] items-start gap-6 [--bracket-card-height:7.75rem] [--bracket-gutter:2rem] [--bracket-slot-height:8.5rem] [--bracket-connector-gap:2px]">
        @foreach ($bracketColumns as $column)
            @php
                $isFirstBracketColumn = $loop->first;
                $isLastBracketColumn = $loop->last;
            @endphp

            <div class="w-[16.8rem] shrink-0">
                <div class="pl-3 text-[1.7rem] font-semibold tracking-tight text-zinc-900">{{ $column['label'] }}</div>

                <div
                    class="relative mt-2"
                    style="height: calc({{ $bracketTotalRows }} * var(--bracket-slot-height) + var(--bracket-card-height));"
                >
                    @foreach ($column['connectors'] as $connector)
                        <div
                            class="pointer-events-none absolute z-0 block"
                            style="
                                right: calc(var(--bracket-gutter) / -2);
                                top: calc(({{ $connector['from_slot'] }} - 1) * var(--bracket-slot-height) + (var(--bracket-card-height) / 2));
                                height: calc(({{ $connector['to_slot'] - $connector['from_slot'] }}) * var(--bracket-slot-height));
                            "
                        >
                            <div class="h-full w-px bg-zinc-300"></div>
                        </div>
                    @endforeach

                    @foreach ($column['cards'] as $card)
                        @php
                            $match = $card['match'];
                            $homeTeam = $match->homeRegistration?->team;
                            $awayTeam = $match->awayRegistration?->team;
                            $homeTeamLogo = $homeTeam?->logoUrl();
                            $awayTeamLogo = $awayTeam?->logoUrl();
                            $homeBadge = $homeTeam
                                ? str($homeTeam->name)->explode(' ')->take(2)->map(fn ($word) => str($word)->substr(0, 1))->implode('')
                                : 'TBD';
                            $awayBadge = $awayTeam
                                ? str($awayTeam->name)->explode(' ')->take(2)->map(fn ($word) => str($word)->substr(0, 1))->implode('')
                                : 'TBD';
                            $statusClasses = match ($match->status) {
                                'completed' => 'bg-[#61d9a5] text-white',
                                'live' => 'bg-[#2f55b7] text-white',
                                default => 'bg-zinc-100 text-zinc-600',
                            };
                            $statusLabel = match ($match->status) {
                                'completed' => 'Ended',
                                'live' => 'Live',
                                default => 'Scheduled',
                            };
                            $matchChip = $match->match_number ? 'M'.$match->match_number : null;
                        @endphp

                        <div
                            class="absolute inset-x-0"
                            style="top: calc(({{ $card['slot'] }} - 1) * var(--bracket-slot-height));"
                        >
                            <div class="relative">
                                @if (! $isFirstBracketColumn)
                                    <div
                                        class="pointer-events-none absolute top-1/2 z-0 block h-px -translate-y-1/2 bg-zinc-300"
                                        style="
                                            left: calc(var(--bracket-gutter) / -2);
                                            width: calc((var(--bracket-gutter) / 2) - var(--bracket-connector-gap));
                                        "
                                    ></div>
                                @endif

                                @if (! $isLastBracketColumn || $column['key'] === 'finals')
                                    <div
                                        class="pointer-events-none absolute top-1/2 z-0 block h-px -translate-y-1/2 bg-zinc-300"
                                        style="
                                            right: calc(var(--bracket-gutter) / -2);
                                            width: calc((var(--bracket-gutter) / 2) - var(--bracket-connector-gap));
                                        "
                                    ></div>
                                @endif

                                <a
                                    href="{{ route('tournaments.matches.show', ['tournament' => $tournament, 'match' => $match]) }}"
                                    class="relative z-10 block h-[var(--bracket-card-height)] rounded-[1rem] border border-zinc-200 bg-white px-3.5 py-2 shadow-sm transition hover:border-zinc-300 hover:shadow-md"
                                    wire:navigate
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <div class="truncate text-[11px] font-semibold uppercase leading-4 text-zinc-900">
                                                {{ $match->round_label ?: str($match->stage)->replace('_', ' ')->headline() }}
                                            </div>
                                            <div class="mt-0.5 text-xs font-medium text-zinc-500">
                                                {{ $match->pitch?->name ?: 'Field TBD' }}
                                            </div>
                                        </div>

                                        @if ($matchChip)
                                            <span class="shrink-0 rounded-full border border-zinc-200 bg-zinc-50 px-2 py-0.5 text-[10px] font-semibold text-zinc-400">
                                                {{ $matchChip }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="mt-2 grid grid-cols-[3.35rem_minmax(0,1fr)] gap-3">
                                        <div>
                                            <div class="text-[11px] font-semibold uppercase text-zinc-500">
                                                {{ $match->scheduled_at ? strtoupper($match->scheduled_at->format('d M')) : 'TBD' }}
                                            </div>
                                            <div class="mt-0.5 text-sm font-semibold text-zinc-400">
                                                {{ $match->scheduled_at ? $match->scheduled_at->format('H:i') : 'TBD' }}
                                            </div>

                                            <span class="mt-2 inline-flex rounded-[0.55rem] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.04em] {{ $statusClasses }}">
                                                {{ $statusLabel }}
                                            </span>
                                        </div>

                                        <div class="space-y-1.5">
                                            <div class="flex items-center gap-2">
                                                <div class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full border border-zinc-200 bg-white text-[10px] font-semibold text-zinc-700">
                                                    @if ($homeTeamLogo)
                                                        <img src="{{ $homeTeamLogo }}" alt="{{ $homeTeam?->name }}" class="h-full w-full object-cover">
                                                    @else
                                                        {{ $homeBadge }}
                                                    @endif
                                                </div>
                                                <div class="min-w-0 flex-1 truncate text-[0.96rem] font-semibold text-zinc-900">{{ $homeTeam?->name ?: 'TBD' }}</div>
                                                <div class="text-[1.1rem] font-semibold text-zinc-900">{{ $match->home_score ?? '-' }}</div>
                                            </div>

                                            <div class="flex items-center gap-2">
                                                <div class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full border border-zinc-200 bg-white text-[10px] font-semibold text-zinc-700">
                                                    @if ($awayTeamLogo)
                                                        <img src="{{ $awayTeamLogo }}" alt="{{ $awayTeam?->name }}" class="h-full w-full object-cover">
                                                    @else
                                                        {{ $awayBadge }}
                                                    @endif
                                                </div>
                                                <div class="min-w-0 flex-1 truncate text-[0.96rem] font-semibold text-zinc-900">{{ $awayTeam?->name ?: 'TBD' }}</div>
                                                <div class="text-[1.1rem] font-semibold text-zinc-900">{{ $match->away_score ?? '-' }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    @endforeach

                    @if ($column['key'] === 'finals' && $bracketPlacements->isNotEmpty())
                        @foreach ($bracketPlacements as $card)
                            @php
                                $match = $card['match'];
                                $homeTeam = $match->homeRegistration?->team;
                                $awayTeam = $match->awayRegistration?->team;
                                $homeTeamLogo = $homeTeam?->logoUrl();
                                $awayTeamLogo = $awayTeam?->logoUrl();
                                $homeBadge = $homeTeam
                                    ? str($homeTeam->name)->explode(' ')->take(2)->map(fn ($word) => str($word)->substr(0, 1))->implode('')
                                    : 'TBD';
                                $awayBadge = $awayTeam
                                    ? str($awayTeam->name)->explode(' ')->take(2)->map(fn ($word) => str($word)->substr(0, 1))->implode('')
                                    : 'TBD';
                                $statusClasses = match ($match->status) {
                                    'completed' => 'bg-[#61d9a5] text-white',
                                    'live' => 'bg-[#2f55b7] text-white',
                                    default => 'bg-zinc-100 text-zinc-600',
                                };
                                $statusLabel = match ($match->status) {
                                    'completed' => 'Ended',
                                    'live' => 'Live',
                                    default => 'Scheduled',
                                };
                                $matchChip = $match->match_number ? 'M'.$match->match_number : null;
                            @endphp

                            <div
                                class="absolute inset-x-0"
                                style="top: calc(({{ $card['slot'] }} - 1) * var(--bracket-slot-height));"
                            >
                                <div class="relative">
                                    <div
                                        class="pointer-events-none absolute top-1/2 z-0 block h-px -translate-y-1/2 bg-zinc-300"
                                        style="
                                            left: calc(var(--bracket-gutter) / -2);
                                            width: calc((var(--bracket-gutter) / 2) - var(--bracket-connector-gap));
                                        "
                                    ></div>
                                    <div
                                        class="pointer-events-none absolute top-1/2 z-0 block h-px -translate-y-1/2 bg-zinc-300"
                                        style="
                                            right: calc(var(--bracket-gutter) / -2);
                                            width: calc((var(--bracket-gutter) / 2) - var(--bracket-connector-gap));
                                        "
                                    ></div>

                                    <a
                                        href="{{ route('tournaments.matches.show', ['tournament' => $tournament, 'match' => $match]) }}"
                                        class="relative z-10 block h-[var(--bracket-card-height)] rounded-[1rem] border border-zinc-200 bg-white px-3.5 py-2 shadow-sm transition hover:border-zinc-300 hover:shadow-md"
                                        wire:navigate
                                    >
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <div class="truncate text-[11px] font-semibold uppercase leading-4 text-zinc-900">
                                                    {{ $match->round_label ?: str($match->stage)->replace('_', ' ')->headline() }}
                                                </div>
                                                <div class="mt-0.5 text-xs font-medium text-zinc-500">
                                                    {{ $match->pitch?->name ?: 'Field TBD' }}
                                                </div>
                                            </div>

                                            @if ($matchChip)
                                                <span class="shrink-0 rounded-full border border-zinc-200 bg-zinc-50 px-2 py-0.5 text-[10px] font-semibold text-zinc-400">
                                                    {{ $matchChip }}
                                                </span>
                                            @endif
                                        </div>

                                        <div class="mt-2 grid grid-cols-[3.35rem_minmax(0,1fr)] gap-3">
                                            <div>
                                                <div class="text-[11px] font-semibold uppercase text-zinc-500">
                                                    {{ $match->scheduled_at ? strtoupper($match->scheduled_at->format('d M')) : 'TBD' }}
                                                </div>
                                                <div class="mt-0.5 text-sm font-semibold text-zinc-400">
                                                    {{ $match->scheduled_at ? $match->scheduled_at->format('H:i') : 'TBD' }}
                                                </div>

                                                <span class="mt-2 inline-flex rounded-[0.55rem] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.04em] {{ $statusClasses }}">
                                                    {{ $statusLabel }}
                                                </span>
                                            </div>

                                            <div class="space-y-1.5">
                                                <div class="flex items-center gap-2">
                                                    <div class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full border border-zinc-200 bg-white text-[10px] font-semibold text-zinc-700">
                                                        @if ($homeTeamLogo)
                                                            <img src="{{ $homeTeamLogo }}" alt="{{ $homeTeam?->name }}" class="h-full w-full object-cover">
                                                        @else
                                                            {{ $homeBadge }}
                                                        @endif
                                                    </div>
                                                    <div class="min-w-0 flex-1 truncate text-[0.96rem] font-semibold text-zinc-900">{{ $homeTeam?->name ?: 'TBD' }}</div>
                                                    <div class="text-[1.1rem] font-semibold text-zinc-900">{{ $match->home_score ?? '-' }}</div>
                                                </div>

                                                <div class="flex items-center gap-2">
                                                    <div class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full border border-zinc-200 bg-white text-[10px] font-semibold text-zinc-700">
                                                        @if ($awayTeamLogo)
                                                            <img src="{{ $awayTeamLogo }}" alt="{{ $awayTeam?->name }}" class="h-full w-full object-cover">
                                                        @else
                                                            {{ $awayBadge }}
                                                        @endif
                                                    </div>
                                                    <div class="min-w-0 flex-1 truncate text-[0.96rem] font-semibold text-zinc-900">{{ $awayTeam?->name ?: 'TBD' }}</div>
                                                    <div class="text-[1.1rem] font-semibold text-zinc-900">{{ $match->away_score ?? '-' }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
