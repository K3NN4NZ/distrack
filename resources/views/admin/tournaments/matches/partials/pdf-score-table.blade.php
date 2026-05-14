{{--
    DomPDF-friendly team score table (mirrors admin scoring form: per-sheet header, yellow columns, gender blocks, real players only).
    @var \App\Models\Team|null $team
    @var \Illuminate\Support\Collection<int, \App\Models\MatchPlayerStat> $playerStats
    @var int $totalScore
    @var string $side 'home'|'away'
    @var bool $isCompleted When false, numeric cells and team total are left blank (printable template).
    @var \App\Models\Tournament|null $tournament
    @var \App\Models\TournamentMatch|null $match
    @var \App\Models\Team|null $homeTeam
    @var \App\Models\Team|null $awayTeam
    @var string|null $winnerLabel
    @var string|null $matchStatusLabel
--}}
@php
    $isCompleted = $isCompleted ?? true;
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

    $showPerSheetHeader = isset($tournament, $match, $homeTeam, $awayTeam);
    if ($showPerSheetHeader) {
        $bannerHome = ($homeTeam?->name) ?: __('Home Team');
        $bannerAway = ($awayTeam?->name) ?: __('Away Team');
        $gameNumLabel = $match->match_number ?? '—';
        $stageHeadline = str($match->stage)->replace('_', ' ')->headline();
        $roundSuffix = $match->round_label ? ' · '.$match->round_label : '';
        $sheetMetaParts = array_filter([
            $stageHeadline.$roundSuffix,
            $match->pitch?->name ?? __('Pitch: Unassigned'),
            $match->scheduled_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') ?? __('TBD'),
            filled($matchStatusLabel ?? null) ? ($matchStatusLabel ?? null) : null,
            ($isCompleted && filled($winnerLabel ?? null)) ? (__('Winner').': '.$winnerLabel) : null,
        ]);
        $sheetMetaLine = implode(' · ', $sheetMetaParts);
        $sheetTitleLine = $tournament->name.' | '.$bannerHome.' '.__('vs.').' '.$bannerAway.' — '.__('Game #:num', ['num' => $gameNumLabel]);
    }
@endphp
@if ($showPerSheetHeader)
    <div class="sheet-header">
        <div class="sheet-logo-wrap">
            <img
                src="{{ asset('images/report_generation_header_logo.png') }}"
                class="sheet-header-logo"
                alt=""
            >
        </div>
        <div class="sheet-title-line">{{ $sheetTitleLine }}</div>
        @if (filled($sheetMetaLine))
            <div class="sheet-meta-line">{{ $sheetMetaLine }}</div>
        @endif
    </div>
@endif
<table class="score-table">
    <colgroup>
        <col style="width: 6%;">
        <col style="width: 58%;">
        <col style="width: 12%;">
        <col style="width: 12%;">
        <col style="width: 12%;">
    </colgroup>
    <thead>
        <tr class="team-header-row">
            <th colspan="4" class="team-name">{{ $teamName }}</th>
            <th class="total-score-box">
                <div class="total-label">{{ __('TOTAL') }}<br>{{ __('SCORE') }}</div>
                <div class="total-number">{{ $isCompleted ? (int) $totalScore : '' }}</div>
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
                    <td class="numeric-cell">{{ $isCompleted ? (int) ($stat?->blocks ?? 0) : '' }}</td>
                    <td class="numeric-cell">{{ $isCompleted ? (int) ($stat?->assists ?? 0) : '' }}</td>
                    <td class="numeric-cell">{{ $isCompleted ? (int) ($stat?->goals ?? 0) : '' }}</td>
                </tr>
            @endforeach
        @endforeach
    </tbody>
</table>
<div class="sheet-official-remarks">
    <div class="sheet-official-remarks-title">{{ __('Official remarks / signatures') }}</div>
    <div class="sheet-signature-line"></div>
    <div class="sheet-signature-label">{{ __('Scorekeeper / organizer') }}</div>
</div>
