<x-layouts::app :title="$team->name">
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('admin.teams.index') }}" wire:navigate class="text-sm font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                ← {{ __('Back to Teams') }}
            </a>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.teams.edit', $team) }}" wire:navigate class="inline-flex rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-zinc-700 dark:border-neutral-600 dark:bg-zinc-900 dark:text-zinc-200">
                    {{ __('Edit Team') }}
                </a>
                <a href="{{ route('admin.teams.roster.index', $team) }}" wire:navigate class="inline-flex rounded-lg border border-[#c8d7f8] bg-[#e9f0ff] px-4 py-2 text-sm font-medium text-[#2f55b7]">
                    {{ __('Manage Roster') }}
                </a>
            </div>
        </div>

        <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex flex-col gap-6 md:flex-row md:items-start">
                <div class="shrink-0">
                    @if ($url = $team->logoUrl())
                        <img src="{{ $url }}" alt="" class="h-36 w-36 rounded-2xl border border-neutral-200 object-cover dark:border-neutral-700">
                    @else
                        <div class="flex h-36 w-36 items-center justify-center rounded-2xl border border-neutral-200 bg-zinc-100 text-2xl font-bold text-zinc-600 dark:border-neutral-700 dark:bg-zinc-800 dark:text-zinc-300">
                            {{ $team->initials() }}
                        </div>
                    @endif
                </div>
                <div class="min-w-0 flex-1 space-y-2">
                    <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ $team->name }}</h1>
                    @if ($team->short_name)
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Code: :code', ['code' => $team->short_name]) }}</p>
                    @endif
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ $team->locationLabel() ?? $team->address }}</p>
                    @if ($team->description)
                        <p class="text-sm leading-relaxed text-zinc-700 dark:text-zinc-200">{{ $team->description }}</p>
                    @endif
                    <p class="text-xs text-zinc-500">{{ __('Status: :s', ['s' => str($team->status)->headline()]) }}</p>
                </div>
            </div>
        </section>

        @foreach ([['members' => $maleMembers, 'title' => __('Male')], ['members' => $femaleMembers, 'title' => __('Female')], ['members' => $otherMembers, 'title' => __('Other')]] as $block)
            @if ($block['members']->isNotEmpty())
                <section class="overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900">
                    <div class="border-b border-neutral-200 bg-zinc-50 px-6 py-3 dark:border-neutral-700 dark:bg-zinc-800/60">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-700 dark:text-zinc-200">{{ $block['title'] }}</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                            <thead class="bg-zinc-50 text-left text-xs font-medium uppercase text-zinc-500 dark:bg-zinc-800/60 dark:text-zinc-400">
                                <tr>
                                    <th class="px-4 py-2">#</th>
                                    <th class="px-4 py-2">{{ __('Player') }}</th>
                                    <th class="px-4 py-2">{{ __('Gender') }}</th>
                                    <th class="px-4 py-2">{{ __('Jersey') }}</th>
                                    <th class="px-4 py-2">{{ __('Role') }}</th>
                                    <th class="px-4 py-2">{{ __('Leadership') }}</th>
                                    <th class="px-4 py-2">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                                @foreach ($block['members'] as $member)
                                    <tr class="text-zinc-800 dark:text-zinc-100">
                                        <td class="px-4 py-2">{{ $loop->iteration }}</td>
                                        <td class="px-4 py-2 font-medium">{{ $member->name }}</td>
                                        <td class="px-4 py-2">{{ str($member->gender ?? '')->headline() ?: '—' }}</td>
                                        <td class="px-4 py-2">{{ $member->jersey_number ?? '—' }}</td>
                                        <td class="px-4 py-2">{{ str($member->role)->replace('_', ' ')->headline() }}</td>
                                        <td class="px-4 py-2">
                                            @if ($member->role === 'captain')
                                                <span class="rounded bg-blue-100 px-2 py-0.5 text-xs text-blue-800 dark:bg-blue-950/50 dark:text-blue-200">{{ __('Captain') }}</span>
                                            @elseif ($member->role === 'spirit_captain')
                                                <span class="rounded bg-violet-100 px-2 py-0.5 text-xs text-violet-800 dark:bg-violet-950/50 dark:text-violet-200">{{ __('Spirit Captain') }}</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-4 py-2">
                                            <div class="flex flex-wrap items-center gap-3">
                                                <a href="{{ route('admin.teams.roster.index', $team) }}#member-{{ $member->id }}" wire:navigate class="text-[#2f55b7] hover:underline dark:text-sky-300">{{ __('Edit') }}</a>
                                                <form method="POST" action="{{ route('admin.teams.roster.destroy', [$team, $member]) }}" class="inline" onsubmit="return confirm('{{ __('Delete this player?') }}')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-sm font-medium text-red-600 hover:underline disabled:cursor-not-allowed disabled:opacity-40" @disabled($member->isLinkedToMatchRecords())>
                                                        {{ __('Delete') }}
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        @endforeach

        @if ($maleMembers->isEmpty() && $femaleMembers->isEmpty() && $otherMembers->isEmpty())
            <section class="rounded-xl border border-dashed border-neutral-300 p-6 text-sm text-zinc-600 dark:border-neutral-600 dark:text-zinc-300">
                {{ __('No roster members yet.') }}
                <a href="{{ route('admin.teams.roster.index', $team) }}" wire:navigate class="ms-1 font-medium text-[#2f55b7] hover:underline">{{ __('Manage roster') }}</a>
            </section>
        @endif
    </div>
</x-layouts::app>
