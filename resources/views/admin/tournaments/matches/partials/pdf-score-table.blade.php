{{--
    DomPDF-friendly team score table (mirrors admin scoring form: header, yellow columns, gender blocks, real players only).
    @var \App\Models\Team|null $team
    @var \Illuminate\Support\Collection<int, \App\Models\MatchPlayerStat> $playerStats
    @var int $totalScore
    @var string $side 'home'|'away'
--}}
@php
    $sheetTeam = $team;
    $sheetStatsByMember = $playerStats->keyBy('team_member_id');
    $sheetMembers = $sheetTeam?->members ?? collect();
    $sheetMaleMembers = $sheetMembers->filter(fn ($member) => strtolower((string) $member->gender) === 'male')->values();
    $sheetFemaleMembers = $sheetMembers->filter(fn ($member) => strtolower((string) $member->gender) === 'female')->values();
    $sheetOtherMembers = $sheetMembers
        ->filter(fn ($member) => ! in_array(strtolower((string) $member->gender), ['male', 'female'], true))
        ->values();
    $sheetGenderGroups = collect([
        ['label' => __('MALE'), 'roster' => $sheetMaleMembers],
        ['label' => __('FEMALE'), 'roster' => $sheetFemaleMembers],
    ])->filter(fn (array $g): bool => $g['roster']->isNotEmpty());

    if ($sheetOtherMembers->isNotEmpty()) {
        $sheetGenderGroups->push([
            'label' => __('OTHER'),
            'roster' => $sheetOtherMembers,
        ]);
    }
    $teamName = $sheetTeam?->name ?: ($side === 'home' ? __('Home Team') : __('Away Team'));
@endphp
<table class="score-table">
    <colgroup>
        <col class="col-number">
        <col class="col-name">
        <col class="col-blocks">
        <col class="col-assists">
        <col class="col-scores">
    </colgroup>
    <thead>
        <tr class="team-header-row">
            <th colspan="4" class="team-name">{{ $teamName }}</th>
            <th class="total-score-box">
                <div class="total-label">{{ __('TOTAL') }}<br>{{ __('SCORE') }}</div>
                <div class="total-number">{{ (int) $totalScore }}</div>
            </th>
        </tr>
        <tr class="column-header-row">
            <th>#</th>
            <th>{{ __('NAMES') }}</th>
            <th>{{ __('BLOCKS') }}</th>
            <th>{{ __('ASSISTS') }}</th>
            <th>{{ __('SCORES') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($sheetGenderGroups as $group)
            <tr class="gender-row">
                <td colspan="5">{{ $group['label'] }}</td>
            </tr>
            @foreach ($group['roster'] as $index => $member)
                @php
                    $stat = $sheetStatsByMember->get($member->id);
                @endphp
                <tr>
                    <td class="number-cell">{{ $index + 1 }}</td>
                    <td class="player-name-cell">{{ $member->name }}</td>
                    <td class="numeric-cell">{{ (int) ($stat?->blocks ?? 0) }}</td>
                    <td class="numeric-cell">{{ (int) ($stat?->assists ?? 0) }}</td>
                    <td class="numeric-cell">{{ (int) ($stat?->goals ?? 0) }}</td>
                </tr>
            @endforeach
        @endforeach
    </tbody>
</table>
