@php
    $readonly = $readonly ?? false;
    $sheetTeam = $sheet['team'];
    $sheetStatsByMember = $sheet['statsByMember'] ?? $sheet['stats']->keyBy('team_member_id');
    $sheetMembers = $sheetTeam?->members ?? collect();
    $sheetGenderGroups = \App\Support\MatchScoreSheetRosterGroups::fromMembers($sheetMembers);
    $sheetSide = $sheet['side'];
    $sheetRegistration = $sheet['registration'] ?? null;

    $displayStatValue = static function ($value): string {
        if ($value === null || $value === '') {
            return '';
        }

        return (string) (int) $value;
    };
@endphp

<section class="overflow-hidden rounded-xl border border-neutral-300 bg-white text-zinc-900 shadow-sm dark:border-neutral-700 dark:bg-zinc-900 dark:text-zinc-100">
    <table class="w-full border-collapse text-sm">
        <thead>
            <tr class="border-b border-neutral-300 dark:border-neutral-700">
                <th colspan="4" class="border-r border-neutral-300 px-3 py-2 text-center text-base font-semibold uppercase tracking-wide dark:border-neutral-700">
                    {{ $sheetTeam?->name ?: ($sheetSide === 'home' ? __('Home Team') : __('Away Team')) }}
                </th>
                <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide">
                    <div>{{ __('TOTAL SCORE') }}</div>
                    @if ($readonly)
                        <div class="mt-1 text-2xl font-bold tracking-tight">{{ $sheet['totalScore'] }}</div>
                    @else
                        <div class="mt-1 text-2xl font-bold tracking-tight" x-text="{{ $sheetSide === 'home' ? 'homeScore' : 'awayScore' }}">{{ $sheet['totalScore'] }}</div>
                    @endif
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
                            @if ($readonly)
                                <span class="inline-flex h-7 w-full items-center justify-center text-sm text-zinc-900 dark:text-zinc-100">
                                    {{ $displayStatValue($stat?->blocks) }}
                                </span>
                            @else
                                <input
                                    type="number"
                                    min="0"
                                    max="999"
                                    name="scores[{{ $sheetRegistration?->id }}][{{ $member->id }}][blocks]"
                                    value="{{ old('scores.'.($sheetRegistration?->id).'.'.$member->id.'.blocks', $stat?->blocks ?? '') }}"
                                    x-on:input="handleInput('{{ $member->id }}', 'blocks', $event.target.value)"
                                    class="h-7 w-full rounded border border-transparent bg-transparent px-1 py-0 text-center text-sm text-zinc-900 transition-colors focus:border-neutral-400 focus:bg-white focus:outline-none dark:text-zinc-100 dark:focus:border-neutral-500 dark:focus:bg-zinc-950"
                                >
                            @endif
                        </td>
                        <td class="w-20 border-r border-neutral-200 px-1 py-0.5 text-center dark:border-neutral-800">
                            @if ($readonly)
                                <span class="inline-flex h-7 w-full items-center justify-center text-sm text-zinc-900 dark:text-zinc-100">
                                    {{ $displayStatValue($stat?->assists) }}
                                </span>
                            @else
                                <input
                                    type="number"
                                    min="0"
                                    max="999"
                                    name="scores[{{ $sheetRegistration?->id }}][{{ $member->id }}][assists]"
                                    value="{{ old('scores.'.($sheetRegistration?->id).'.'.$member->id.'.assists', $stat?->assists ?? '') }}"
                                    x-on:input="handleInput('{{ $member->id }}', 'assists', $event.target.value)"
                                    class="h-7 w-full rounded border border-transparent bg-transparent px-1 py-0 text-center text-sm text-zinc-900 transition-colors focus:border-neutral-400 focus:bg-white focus:outline-none dark:text-zinc-100 dark:focus:border-neutral-500 dark:focus:bg-zinc-950"
                                >
                            @endif
                        </td>
                        <td class="w-20 px-1 py-0.5 text-center">
                            @if ($readonly)
                                <span class="inline-flex h-7 w-full items-center justify-center text-sm text-zinc-900 dark:text-zinc-100">
                                    {{ $displayStatValue($stat?->goals) }}
                                </span>
                            @else
                                <input
                                    type="number"
                                    min="0"
                                    max="999"
                                    name="scores[{{ $sheetRegistration?->id }}][{{ $member->id }}][scores]"
                                    value="{{ old('scores.'.($sheetRegistration?->id).'.'.$member->id.'.scores', $stat?->goals ?? '') }}"
                                    x-on:input="handleInput('{{ $member->id }}', 'scores', $event.target.value)"
                                    class="h-7 w-full rounded border border-transparent bg-transparent px-1 py-0 text-center text-sm text-zinc-900 transition-colors focus:border-neutral-400 focus:bg-white focus:outline-none dark:text-zinc-100 dark:focus:border-neutral-500 dark:focus:bg-zinc-950"
                                >
                            @endif
                        </td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
</section>
