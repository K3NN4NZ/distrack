@php
    $label = $label ?? '';
    $mvp = $mvp ?? null;
@endphp

<div class="rounded-[0.9rem] border border-zinc-200 bg-zinc-50 p-6 text-left">
    <h3 class="text-sm font-semibold uppercase tracking-[0.12em] text-zinc-500">{{ $label }}</h3>

    @if ($mvp)
        <p class="mt-4 text-xl font-semibold text-zinc-900">
            {{ $mvp['player']->name }} – {{ $mvp['team']->name }}
        </p>
        <p class="mt-3 text-base text-zinc-700">
            Scores {{ $mvp['scores'] }} · Assists {{ $mvp['assists'] }} · Blocks {{ $mvp['blocks'] }} · Total {{ $mvp['total'] }}
        </p>
    @else
        <p class="mt-4 text-base text-zinc-600">
            No player stats recorded yet.
        </p>
    @endif
</div>
