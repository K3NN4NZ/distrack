{{--
    Single-team spirit sheet for PDF (DomPDF). Self-contained cut-out panel: header, criteria, captain, official remarks.
    @var \App\Models\Tournament|null $tournament
    @var \App\Models\TournamentMatch|null $match
    @var \App\Models\Team|null $homeTeam
    @var \App\Models\Team|null $awayTeam
    @var string|null $matchStatusLabel
    @var \App\Models\Team|null $sheetTeam
    @var \App\Models\MatchSpiritScore|null $sheetRecord
    @var \App\Models\TeamMember|null $sheetCaptain
    @var string $sheetLabel
    @var bool $isCompleted
    @var array<int, array{field: string, title: string}> $criteriaRows
    @var array<string, array<int, string>> $rubricLines
--}}
@php
    $isCompleted = $isCompleted ?? true;
    $spiritHeadingText = $isCompleted ? __('Spirit scoresheet') : __('MATCH/SPIRIT SCORE SHEETS');

    $showPerSheetHeader = isset($tournament, $match, $homeTeam, $awayTeam);
    if ($showPerSheetHeader) {
        $bannerHome = ($homeTeam?->name) ?: __('Home Team');
        $bannerAway = ($awayTeam?->name) ?: __('Away Team');
        $gameNumLabel = $match->match_number ?? '—';
        $stageHeadline = str($match->stage)->replace('_', ' ')->headline();
        $roundSuffix = $match->round_label ? ' · '.$match->round_label : '';
        $sheetTitleLine = $tournament->name.' | '.$bannerHome.' '.__('vs.').' '.$bannerAway.' — '.__('Game #:num', ['num' => $gameNumLabel]);
        $sheetMetaParts = array_filter([
            $stageHeadline.$roundSuffix,
            $match->pitch?->name ?? __('Pitch: Unassigned'),
            $match->scheduled_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') ?? __('TBD'),
            filled($matchStatusLabel ?? null) ? ($matchStatusLabel ?? null) : null,
        ]);
        $sheetMetaLine = implode(' · ', $sheetMetaParts);
    }
@endphp
@if ($showPerSheetHeader)
    <div class="spirit-sheet-header">
        <div class="spirit-logo-wrap">
            <img
                src="{{ asset('images/report_generation_header_logo.png') }}"
                class="spirit-sheet-header-logo"
                alt=""
            >
        </div>
        <div class="sheet-title-line">{{ $sheetTitleLine }}</div>
        @if (filled($sheetMetaLine))
            <div class="sheet-meta-line">{{ $sheetMetaLine }}</div>
        @endif
        <div class="sheet-spirit-doc-line spirit-title">{{ $spiritHeadingText }} · {{ $sheetTeam?->name ?: __('Team') }}</div>
    </div>
@endif
<table class="spirit-sheet">
    <colgroup>
        <col class="spirit-criteria-col">
        <col class="spirit-score-col">
    </colgroup>
    <thead>
        <tr class="spirit-col-header">
            <th class="spirit-criteria-col">{{ __('Criteria') }}</th>
            <th class="spirit-score-col">{{ __('Score') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($criteriaRows as $row)
            @php $field = $row['field']; @endphp
            <tr>
                <td class="spirit-criteria-col">
                    <div class="spirit-criteria-title">{{ $row['title'] }}</div>
                    @foreach ($rubricLines[$field] ?? [] as $line)
                        <div class="spirit-rubric">{{ $line }}</div>
                    @endforeach
                </td>
                <td class="spirit-score-col">{{ $isCompleted ? ($sheetRecord?->{$field} ?? '—') : '' }}</td>
            </tr>
        @endforeach
        <tr class="spirit-total-row">
            <td>{{ __('Total') }} ({{ __('max 15') }})</td>
            <td class="spirit-score-col">{{ $isCompleted ? ($sheetRecord?->total_score ?? '—') : '' }}</td>
        </tr>
    </tbody>
</table>
<div class="spirit-captain-section">
    @if ($sheetCaptain)
        <span class="captain-person">{{ $sheetCaptain->name }}</span>
        <br>
        <span class="spirit-captain-name">{{ __('SPIRIT CAPTAIN') }}</span>
        <br>
        <span class="spirit-signature-label">{{ __('Signature Above Printed Name') }}</span>
    @else
        <span class="muted">{{ __('No spirit captain assigned') }}</span>
        <br>
        <span class="spirit-captain-name">{{ __('SPIRIT CAPTAIN') }}</span>
    @endif
</div>
<div class="spirit-sheet-official-remarks">
    <div class="spirit-sheet-official-remarks-title">{{ __('Official remarks / signatures') }}</div>
    <div class="spirit-sheet-signature-line"></div>
    <div class="spirit-sheet-signature-caption">{{ __('Scorekeeper / organizer') }}</div>
</div>
