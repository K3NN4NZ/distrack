<section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Team Standing') }}</h2>
            <p class="mt-1 max-w-3xl text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('Totals from completed round robin games in this tournament. Until games finish with scores, wins and losses stay at zero and rank stays unset.') }}
            </p>
        </div>
    </div>

    @if ($teamStandingRows->isEmpty())
        <div class="mt-4 rounded-lg border border-dashed border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-950/60 dark:text-zinc-300">
            {{ __('No teams are registered for this tournament yet.') }}
        </div>
    @else
        <div class="mt-5 overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-700">
            <table class="min-w-[52rem] w-full text-left text-sm">
                <thead class="border-b border-neutral-200 bg-zinc-50 text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:border-neutral-700 dark:bg-zinc-900 dark:text-zinc-400">
                    <tr>
                        <th class="whitespace-nowrap px-4 py-2.5">{{ __('Seed / Number') }}</th>
                        <th class="px-4 py-2.5">{{ __('Team') }}</th>
                        <th class="whitespace-nowrap px-4 py-2.5">{{ __('Wins') }}</th>
                        <th class="whitespace-nowrap px-4 py-2.5">{{ __('Losses') }}</th>
                        <th class="whitespace-nowrap px-4 py-2.5">{{ __('Point differential') }}</th>
                        <th class="whitespace-nowrap px-4 py-2.5">{{ __('Accumulated score') }}</th>
                        <th class="whitespace-nowrap px-4 py-2.5">{{ __('Rank') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                    @foreach ($teamStandingRows as $row)
                        <tr class="bg-white dark:bg-zinc-900">
                            <td class="whitespace-nowrap px-4 py-2.5 font-medium text-zinc-900 dark:text-white">{{ $row['seed_display'] }}</td>
                            <td class="px-4 py-2.5 text-zinc-800 dark:text-zinc-100">{{ $row['team_name'] }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-zinc-600 dark:text-zinc-300">{{ $row['wins'] }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-zinc-600 dark:text-zinc-300">{{ $row['losses'] }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-zinc-600 dark:text-zinc-300">{{ $row['point_differential'] }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-zinc-600 dark:text-zinc-300">{{ $row['accumulated_score'] }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-zinc-600 dark:text-zinc-300">{{ $row['rank_display'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
