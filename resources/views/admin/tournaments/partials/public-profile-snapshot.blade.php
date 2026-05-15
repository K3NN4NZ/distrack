<section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
    <div class="mb-4 flex items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Public Profile Snapshot') }}</h2>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('This mirrors what guests can see on the public tournament board.') }}
            </p>
        </div>

        @if ($tournament->is_public)
            <a
                href="{{ route('tournaments.show', $tournament) }}"
                class="rounded-lg border border-neutral-200 px-4 py-2 text-sm font-medium text-zinc-700 transition hover:border-zinc-400 hover:text-zinc-900 dark:border-neutral-700 dark:text-zinc-300 dark:hover:border-zinc-500 dark:hover:text-white"
            >
                {{ __('Open Public Page') }}
            </a>
        @endif
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
        <div class="space-y-4">
            <div class="rounded-xl border border-neutral-200 bg-zinc-50 p-4 dark:border-neutral-700 dark:bg-zinc-950">
                <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Date Window') }}</div>
                <div class="mt-2 text-lg font-semibold text-zinc-900 dark:text-white">
                    {{ $tournament->dateRangeLabel() }}
                </div>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-zinc-50 p-4 dark:border-neutral-700 dark:bg-zinc-950">
                <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Location') }}</div>
                <div class="mt-2 text-lg font-semibold text-zinc-900 dark:text-white">
                    {{ $tournament->addressLabel() ?: __('Location to be announced') }}
                </div>
                @if ($tournament->venue)
                    <div class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $tournament->venue }}</div>
                @endif
                @if ($tournament->timezone)
                    <div class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $tournament->timezone }}</div>
                @endif
            </div>

            <div class="rounded-xl border border-neutral-200 bg-zinc-50 p-4 dark:border-neutral-700 dark:bg-zinc-950">
                <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Organizer') }}</div>
                <div class="mt-3 space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
                    @forelse ($tournament->organizerInfoItems() as $item)
                        <div>
                            <span class="font-medium text-zinc-900 dark:text-white">{{ $item['label'] }}:</span>
                            {{ $item['value'] }}
                        </div>
                    @empty
                        <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No organizer details configured yet.') }}</span>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-zinc-50 p-4 dark:border-neutral-700 dark:bg-zinc-950">
                <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Public Tags') }}</div>
                <div class="mt-3 flex flex-wrap gap-2">
                    @forelse ($tournament->publicTags() as $tag)
                        <span class="rounded-full border border-neutral-200 px-3 py-1 text-xs font-medium text-zinc-700 dark:border-neutral-700 dark:text-zinc-300">
                            {{ $tag }}
                        </span>
                    @empty
                        <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No public tags configured yet.') }}</span>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-zinc-50 p-4 dark:border-neutral-700 dark:bg-zinc-950">
                <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Public Links') }}</div>
                <div class="mt-3 space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
                    @forelse ($tournament->publicLinkItems() as $item)
                        <div>
                            <a
                                href="{{ $item['href'] }}"
                                target="_blank"
                                rel="noreferrer"
                                class="font-medium text-zinc-900 underline underline-offset-4 dark:text-white"
                            >
                                {{ $item['label'] }}
                            </a>
                        </div>
                    @empty
                        <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No public links configured yet.') }}</span>
                    @endforelse
                </div>
            </div>

            @if ($tournament->additionalInfoItems() !== [])
                <div class="rounded-xl border border-neutral-200 bg-zinc-50 p-4 dark:border-neutral-700 dark:bg-zinc-950">
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Additional Info') }}</div>
                    <div class="mt-3 space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
                        @foreach ($tournament->additionalInfoItems() as $item)
                            <div>
                                <span class="font-medium text-zinc-900 dark:text-white">{{ $item['label'] }}:</span>
                                {{ $item['value'] }}
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="space-y-4">
            @if ($tournament->logoUrl())
                <div class="flex h-40 w-full items-center justify-center overflow-hidden">
                    <img
                        src="{{ $tournament->logoUrl() }}"
                        alt="{{ $tournament->name }} {{ __('logo') }}"
                        class="max-h-36 max-w-[min(100%,20rem)] object-contain"
                    >
                </div>
            @elseif ($tournament->thumbnail_path)
                <div class="overflow-hidden rounded-xl border border-neutral-200 bg-zinc-50 dark:border-neutral-700 dark:bg-zinc-950">
                    <img
                        src="{{ $tournament->thumbnail_path }}"
                        alt="{{ $tournament->name }}"
                        class="h-56 w-full object-cover"
                    >
                </div>
            @else
                <div class="overflow-hidden rounded-xl border border-neutral-200 bg-zinc-50 dark:border-neutral-700 dark:bg-zinc-950">
                    <div class="flex h-56 items-end bg-[linear-gradient(135deg,_#f97316_0%,_#facc15_60%,_#14b8a6_100%)] p-4">
                        <span class="rounded-full bg-black/20 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-white">
                            {{ $tournament->event_type ?: __('Tournament') }}
                        </span>
                    </div>
                </div>
            @endif

            <div class="rounded-xl border border-neutral-200 bg-zinc-50 p-4 dark:border-neutral-700 dark:bg-zinc-950">
                <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Visibility') }}</div>
                <div class="mt-2 text-lg font-semibold text-zinc-900 dark:text-white">
                    {{ $tournament->is_public ? __('Public') : __('Private') }}
                </div>
                <div class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">
                    {{ trans_choice('{1} :count profile link published|[2,*] :count profile links published', count($tournament->publicLinkItems()), ['count' => count($tournament->publicLinkItems())]) }}
                </div>
            </div>
        </div>
    </div>
</section>
