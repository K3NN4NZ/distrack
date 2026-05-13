<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ __('Game Score') }} — {{ $tournament->name }}</title>
    <style>
        @page {
            margin: 10mm 10mm;
            size: A4 landscape;
        }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8.5pt;
            color: #18181b;
            line-height: 1.25;
            margin: 0;
            padding: 0;
        }
        table { border-collapse: collapse; }

        .match-header-card {
            border: 1px solid #d9d9d9;
            background: #fff;
            text-align: center;
            padding: 10pt 12pt;
            margin-bottom: 8pt;
        }
        .match-header-card .teams {
            font-size: 13pt;
            font-weight: bold;
            color: #18181b;
        }
        .match-header-card .vs {
            display: inline-block;
            margin: 0 8pt;
            font-size: 8pt;
            font-weight: bold;
            letter-spacing: 0.18em;
            color: #71717a;
            vertical-align: middle;
        }
        .match-header-meta {
            margin-top: 6pt;
            font-size: 7.5pt;
            color: #52525b;
        }
        /* Side-by-side team tables (nested tables — DomPDF-friendly) */
        .pdf-score-layout {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .pdf-score-layout > tbody > tr > td {
            border: none;
            vertical-align: top;
            padding: 0;
        }
        .pdf-team-cell {
            width: 49%;
            vertical-align: top;
        }
        .pdf-gap-cell {
            width: 2%;
        }

        .score-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .score-table th,
        .score-table td {
            border: 1px solid #ddd;
            padding: 6px 8px;
            font-size: 11px;
        }
        .col-number {
            width: 7%;
        }
        .col-name {
            width: 52%;
        }
        .col-blocks,
        .col-assists {
            width: 14%;
        }
        .col-scores {
            width: 13%;
        }
        .team-header-row th {
            height: 70px;
            background: #fff;
            vertical-align: middle;
        }
        .team-name {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 0.5px;
            vertical-align: middle;
        }
        .total-score-box {
            text-align: center;
            vertical-align: middle;
        }
        .total-label {
            font-size: 10px;
            font-weight: bold;
            line-height: 1.1;
        }
        .total-number {
            font-size: 26px;
            font-weight: bold;
            line-height: 1.1;
        }
        .column-header-row th {
            background: #fff176;
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
        }
        .score-table tbody td {
            vertical-align: top;
        }
        .score-table tbody td.numeric-cell,
        .score-table tbody td.number-cell {
            vertical-align: middle;
            white-space: nowrap;
        }
        .gender-row td {
            font-style: italic;
            font-weight: bold;
            background: #fafafa;
            text-align: left;
            text-transform: uppercase;
        }
        .player-name-cell {
            font-style: italic;
            text-align: left;
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
            width: 52%;
            max-width: 52%;
        }
        .numeric-cell,
        .number-cell {
            text-align: center;
        }

        .sig-block {
            margin-top: 10pt;
            border: 1px solid #d9d9d9;
            padding: 8pt;
        }
        .sig-line {
            border-bottom: 1px solid #a1a1aa;
            height: 16pt;
            margin-bottom: 6pt;
        }

        .spirit-page {
            page-break-before: always;
        }
        .spirit-page h1 {
            margin: 0 0 6pt 0;
            font-size: 14pt;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        .muted { color: #52525b; font-size: 8pt; }
        .spirit-grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6pt;
        }
        .spirit-grid > tbody > tr > td {
            width: 50%;
            vertical-align: top;
            padding: 0 6pt 0 0;
            border: none !important;
        }
        .spirit-grid > tbody > tr > td:last-child { padding: 0 0 0 6pt; }
        .spirit-card {
            border: 1px solid #333;
            margin-bottom: 8pt;
        }
        .spirit-card-head {
            text-align: center;
            padding: 6pt;
            border-bottom: 1px solid #333;
            background: #f4f4f5;
        }
        .spirit-card-head .small-cap {
            font-size: 7.5pt;
            font-weight: bold;
            letter-spacing: 0.15em;
        }
        .spirit-crit {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
        }
        .spirit-crit th, .spirit-crit td {
            border: 1px solid #333;
            padding: 4pt;
            vertical-align: top;
        }
        .spirit-crit .score-col { width: 14%; text-align: center; font-weight: bold; font-size: 9pt; }
        .spirit-crit .crit-title { font-weight: bold; }
        .spirit-crit .rubric { font-size: 7pt; color: #222; }
        .spirit-total td { font-weight: bold; background: #eee; }
        .spirit-footer {
            text-align: center;
            padding: 8pt 6pt;
            border-top: 1px solid #333;
            font-size: 8.5pt;
        }
    </style>
</head>
<body>

{{-- PAGE 1: mirrors admin scoring player sheets --}}
<div class="page-scores">
    <div class="match-header-card">
        <div class="teams">
            <span>{{ ($homeTeam?->name) ?: __('Home Team') }}</span>
            <span class="vs">{{ __('vs') }}</span>
            <span>{{ ($awayTeam?->name) ?: __('Away Team') }}</span>
        </div>
        <div class="match-header-meta">
            {{ $tournament->name }}
            &nbsp;|&nbsp;
            {{ __('Game #:num', ['num' => $match->match_number ?? '—']) }}
            &nbsp;|&nbsp;
            {{ str($match->stage)->replace('_', ' ')->headline() }}
            @if ($match->round_label)
                &nbsp;·&nbsp;{{ $match->round_label }}
            @endif
            &nbsp;|&nbsp;
            {{ $match->pitch?->name ?? __('Pitch: Unassigned') }}
            &nbsp;|&nbsp;
            {{ $match->scheduled_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') ?? __('TBD') }}
            &nbsp;|&nbsp;
            {{ $matchStatusLabel }}
        </div>
        @if ($winnerLabel)
            <div style="margin-top:6pt;font-size:9pt;font-weight:bold;">{{ __('Winner') }}: {{ $winnerLabel }}</div>
        @endif
    </div>

    <table class="pdf-score-layout">
        <tbody>
        <tr>
            <td class="pdf-team-cell">
                @include('admin.tournaments.matches.partials.pdf-score-table', [
                    'team' => $homeTeam,
                    'playerStats' => $homePlayerStats,
                    'totalScore' => (int) ($match->home_score ?? 0),
                    'side' => 'home',
                ])
            </td>
            <td class="pdf-gap-cell"></td>
            <td class="pdf-team-cell">
                @include('admin.tournaments.matches.partials.pdf-score-table', [
                    'team' => $awayTeam,
                    'playerStats' => $awayPlayerStats,
                    'totalScore' => (int) ($match->away_score ?? 0),
                    'side' => 'away',
                ])
            </td>
        </tr>
        </tbody>
    </table>

    <div class="sig-block">
        <div style="font-size:7.5pt;font-weight:bold;text-transform:uppercase;margin-bottom:6pt;">{{ __('Official remarks / signatures') }}</div>
        <div class="sig-line"></div>
        <div class="sig-line"></div>
        <div class="muted" style="margin:0;">{{ __('Scorekeeper / organizer') }}</div>
    </div>
</div>

@if ($includeSpiritPage)
    @php
        $homeSpirit = $homeTeam ? $spiritScoresByScoredTeamId->get($homeTeam->id) : null;
        $awaySpirit = $awayTeam ? $spiritScoresByScoredTeamId->get($awayTeam->id) : null;
        $homeSpiritCaptain = $homeTeam?->members?->firstWhere('role', 'spirit_captain');
        $awaySpiritCaptain = $awayTeam?->members?->firstWhere('role', 'spirit_captain');

        $criteriaRows = [
            ['field' => 'knowledge_rules_score', 'title' => __('Knowledge and Use of Rules')],
            ['field' => 'fouls_body_contact_score', 'title' => __('Fouls and Body Contact')],
            ['field' => 'fair_mindedness_score', 'title' => __('Fair Mindedness')],
            ['field' => 'positive_attitude_score', 'title' => __('Positive Attitude and Self-Control')],
            ['field' => 'communication_respect_score', 'title' => __('Communication and Respect')],
        ];

        $rubricLines = [
            'knowledge_rules_score' => [
                __('3 - Excellent understanding and fair application of rules'),
                __('2 - Average understanding, occasional disputes'),
                __('1 - Poor rule knowledge and unsportsmanlike use of rules'),
            ],
            'fouls_body_contact_score' => [
                __('3 - No dangerous plays, highly respectful gameplay'),
                __('2 - Average level of contact'),
                __('1 - Unsafe and unsportsmanlike behavior'),
            ],
            'fair_mindedness_score' => [
                __('3 - Outstanding honesty and fairness'),
                __('2 - Acceptable sportsmanship'),
                __('1 - Poor sportsmanship and unfair behavior'),
            ],
            'positive_attitude_score' => [
                __('3 - Extremely positive and respectful throughout the game'),
                __('2 - Average behavior'),
                __('1 - Disrespectful or hostile conduct'),
            ],
            'communication_respect_score' => [
                __('3 - Excellent communication and mutual respect'),
                __('2 - Average communication'),
                __('1 - Disrespectful communication'),
            ],
        ];
    @endphp

    <div class="spirit-page">
        <h1>{{ __('Spirit scoresheet') }}</h1>
        <p class="muted" style="text-align:center;margin:0 0 8pt 0;">
            {{ ($homeTeam?->name) ?: __('Team A') }} {{ __('vs.') }} {{ ($awayTeam?->name) ?: __('Team B') }}
            — {{ __('Game #:num', ['num' => $match->match_number ?? '—']) }}
        </p>

        <table class="spirit-grid">
            <tbody>
            <tr>
                @foreach ([
                    ['team' => $homeTeam, 'record' => $homeSpirit, 'captain' => $homeSpiritCaptain],
                    ['team' => $awayTeam, 'record' => $awaySpirit, 'captain' => $awaySpiritCaptain],
                ] as $panel)
                    <td>
                        <div class="spirit-card">
                            <div class="spirit-card-head">
                                <div class="small-cap">{{ __('Spirit scoresheet') }}</div>
                                <div style="font-weight:bold;margin-top:4pt;">{{ $panel['team']?->name ?: __('Team') }}</div>
                                <div class="muted" style="margin-top:2pt;">
                                    {{ ($homeTeam?->name) ?: '' }} {{ __('vs.') }} {{ ($awayTeam?->name) ?: '' }}
                                    ({{ __('Game #:num', ['num' => $match->match_number ?? '—']) }})
                                </div>
                            </div>
                            <table class="spirit-crit">
                                <thead>
                                    <tr>
                                        <th>{{ __('Criteria') }}</th>
                                        <th class="score-col">{{ __('Score') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($criteriaRows as $row)
                                        @php $field = $row['field']; @endphp
                                        <tr>
                                            <td>
                                                <div class="crit-title">{{ $row['title'] }}</div>
                                                @foreach ($rubricLines[$field] ?? [] as $line)
                                                    <div class="rubric">{{ $line }}</div>
                                                @endforeach
                                            </td>
                                            <td class="score-col">{{ $panel['record']?->{$field} ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                    <tr class="spirit-total">
                                        <td>{{ __('Total') }} ({{ __('max 15') }})</td>
                                        <td class="score-col">{{ $panel['record']?->total_score ?? '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="spirit-footer">
                                @if ($panel['captain'])
                                    <strong>{{ $panel['captain']->name }}</strong><br>
                                    <span style="font-size:7.5pt;font-weight:bold;letter-spacing:0.1em;">{{ __('SPIRIT CAPTAIN') }}</span><br>
                                    <span class="muted">{{ __('Signature Above Printed Name') }}</span>
                                @else
                                    <span class="muted">{{ __('No spirit captain assigned') }}</span><br>
                                    <span style="font-size:7.5pt;font-weight:bold;letter-spacing:0.1em;">{{ __('SPIRIT CAPTAIN') }}</span>
                                @endif
                            </div>
                        </div>
                    </td>
                @endforeach
            </tr>
            </tbody>
        </table>
    </div>
@endif

</body>
</html>
