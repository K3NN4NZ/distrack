@php
    $title = $title ?? 'MVP Breakdown';
    $leaderboard = $leaderboard ?? collect();
    $isPaginated = $leaderboard instanceof \Illuminate\Pagination\LengthAwarePaginator;
    $paginationUrl = $paginationUrl ?? null;
@endphp

<div class="overflow-hidden rounded-[1rem] border border-zinc-200 bg-white">
    <div class="border-b border-zinc-200 bg-zinc-50 px-5 py-3">
        <h3 class="text-base font-semibold text-zinc-900">{{ $title }}</h3>
    </div>

    @if ($leaderboard->isNotEmpty())
        <div class="overflow-x-auto">
            <div class="min-w-[52rem]">
                <div class="grid grid-cols-[5.5rem_minmax(0,1.2fr)_minmax(0,1fr)_5rem_5rem_5rem_6.5rem] items-center gap-3 border-b border-zinc-200 bg-zinc-50 px-5 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-zinc-600">
                    <div>Rank</div>
                    <div>Player</div>
                    <div>Team</div>
                    <div class="text-right">Scores</div>
                    <div class="text-right">Assists</div>
                    <div class="text-right">Blocks</div>
                    <div class="text-right">Total</div>
                </div>

                @foreach ($leaderboard as $row)
                    @php
                        $globalRank = (int) ($row['rank'] ?? 0);
                        $rank = $isPaginated
                            ? ($leaderboard->currentPage() - 1) * $leaderboard->perPage() + $loop->iteration
                            : $globalRank;
                        $isTopThree = $globalRank >= 1 && $globalRank <= 3;
                        $rowClasses = match ($globalRank) {
                            1 => 'border-yellow-300 bg-yellow-100 text-yellow-950',
                            2 => 'border-zinc-300 bg-zinc-100 text-zinc-950',
                            3 => 'border-orange-300 bg-orange-100 text-orange-950',
                            default => 'border-zinc-200 text-zinc-900',
                        };
                        $rankBadgeClasses = match ($globalRank) {
                            1 => 'bg-yellow-200 text-yellow-950 ring-yellow-300',
                            2 => 'bg-zinc-200 text-zinc-800 ring-zinc-300',
                            3 => 'bg-orange-200 text-orange-950 ring-orange-300',
                            default => '',
                        };
                        $topBadgeLabel = match ($globalRank) {
                            1 => '🥇 Top 1',
                            2 => '🥈 Top 2',
                            3 => '🥉 Top 3',
                            default => null,
                        };
                        $totalScoreClasses = $isTopThree ? 'text-inherit' : 'text-[#2f55b7]';
                    @endphp

                    <div class="grid grid-cols-[5.5rem_minmax(0,1.2fr)_minmax(0,1fr)_5rem_5rem_5rem_6.5rem] items-center gap-3 border-b px-5 py-3 text-sm last:border-b-0 {{ $rowClasses }}">
                        <div class="flex flex-col items-start gap-1">
                            <span class="font-semibold">{{ $rank }}</span>
                            @if ($topBadgeLabel)
                                <span class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[0.65rem] font-semibold leading-none ring-1 ring-inset {{ $rankBadgeClasses }}">
                                    {{ $topBadgeLabel }}
                                </span>
                            @endif
                        </div>
                        <div class="min-w-0 font-medium">{{ $row['display_name'] }}</div>
                        <div class="min-w-0 opacity-90">{{ $row['team']?->name ?: 'Team not listed' }}</div>
                        <div class="text-right font-semibold">{{ $row['scores'] }}</div>
                        <div class="text-right font-semibold">{{ $row['assists'] }}</div>
                        <div class="text-right font-semibold">{{ $row['blocks'] }}</div>
                        <div class="text-right font-semibold {{ $totalScoreClasses }}">{{ $row['total'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        @if ($isPaginated && $leaderboard->lastPage() > 1 && is_callable($paginationUrl))
            <div class="flex flex-wrap items-center justify-center gap-2 border-t border-zinc-200 px-5 py-4">
                @if ($leaderboard->onFirstPage())
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-full text-zinc-300">&laquo;</span>
                @else
                    <a
                        href="{{ $paginationUrl(['page' => $leaderboard->currentPage() - 1]) }}"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-full text-[#2f55b7] transition hover:bg-[#edf3ff]"
                        aria-label="Previous page"
                        wire:navigate.preserve-scroll
                    >&laquo;</a>
                @endif

                @foreach (range(1, $leaderboard->lastPage()) as $pageNumber)
                    <a
                        href="{{ $paginationUrl(['page' => $pageNumber === 1 ? null : $pageNumber]) }}"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-full text-sm font-medium transition {{ $leaderboard->currentPage() === $pageNumber
                            ? 'bg-[#2f55b7] text-white'
                            : 'text-zinc-900 hover:bg-zinc-100' }}"
                        @if ($leaderboard->currentPage() === $pageNumber) aria-current="page" @endif
                        wire:navigate.preserve-scroll
                    >{{ $pageNumber }}</a>
                @endforeach

                @if ($leaderboard->hasMorePages())
                    <a
                        href="{{ $paginationUrl(['page' => $leaderboard->currentPage() + 1]) }}"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-full text-[#2f55b7] transition hover:bg-[#edf3ff]"
                        aria-label="Next page"
                        wire:navigate.preserve-scroll
                    >&raquo;</a>
                @else
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-full text-zinc-300">&raquo;</span>
                @endif
            </div>
        @endif
    @else
        <div class="px-5 py-6 text-sm text-zinc-600">
            {{ $emptyMessage ?? 'No players in this breakdown yet.' }}
        </div>
    @endif
</div>
