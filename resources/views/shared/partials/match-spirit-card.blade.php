@php
    $spirit = $spirit ?? null;
    $criteria = \App\Support\MatchSpiritScores::criteria();
    $maxTotal = \App\Support\MatchSpiritScores::MAX_TOTAL;
@endphp

<div class="rounded-[0.9rem] border border-zinc-200 bg-zinc-50 p-6 text-left">
    @if ($spirit)
        <h3 class="text-xl font-semibold text-zinc-900">{{ $spirit['team']->name }}</h3>
        <p class="mt-3 text-base font-semibold text-zinc-800">
            Total Spirit Score: {{ $spirit['total_score'] }} / {{ $maxTotal }}
        </p>

        <dl class="mt-5 space-y-2 text-base text-zinc-700">
            @foreach ($criteria as $criterion)
                <div class="flex flex-col gap-0.5 sm:flex-row sm:justify-between sm:gap-4">
                    <dt>{{ $criterion['title'] }}:</dt>
                    <dd class="font-semibold text-zinc-900 sm:text-right">
                        {{ $spirit[$criterion['field']] ?? '—' }}
                    </dd>
                </div>
            @endforeach
        </dl>
    @else
        <p class="text-base text-zinc-600">
            No spirit score recorded yet.
        </p>
    @endif
</div>
