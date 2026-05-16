@php
    $isCompleted = $isCompleted ?? true;
    $pdfDocumentTitle = $isCompleted ? __('GAME SCORE') : __('MATCH/SPIRIT SCORE SHEETS');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $pdfDocumentTitle }} — {{ $tournament->name }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 0mm;
        }
        * {
            box-sizing: border-box;
        }
        html,
        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 9.5px;
            line-height: 1.15;
            color: #18181b;
            max-width: 100%;
        }
        table {
            border-collapse: collapse;
        }

        .two-sheet-layout {
            width: 100%;
            border-collapse: separate;
            border-spacing: 2px 0;
            table-layout: fixed;
        }

        .pdf-sheet-page {
            page-break-after: always;
            width: 100%;
        }

        .pdf-sheet-page:last-child {
            page-break-after: auto;
        }

        .sheet-cell {
            width: 50%;
            vertical-align: top;
        }

        .sheet-panel {
            width: 100%;
            padding: 2px;
            border: 1px solid #d9d9d9;
            box-sizing: border-box;
            background: #fff;
        }

        .sheet-header {
            text-align: center;
            margin: 0 0 2px 0;
            padding: 0 0 2px 0;
            border-bottom: 1px solid #d9d9d9;
            line-height: 1.1;
        }
        .sheet-logo-wrap {
            margin: 0;
            padding: 0;
            line-height: 1;
        }
        .sheet-header-logo {
            display: block;
            max-width: 2.8in;
            width: 100%;
            height: auto;
            margin: 0 auto 1px auto;
            padding: 0;
            object-fit: contain;
        }
        .sheet-title-line {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .sheet-meta-line {
            font-size: 8px;
            color: #333;
            white-space: nowrap;
            margin-top: 1px;
        }

        .score-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            /* Allow row tables to span pages — avoids DomPDF clipping when rosters grow after the match is created */
        }
        .score-table th,
        .score-table td {
            border: 1px solid #d9d9d9;
            padding: 2px;
            font-size: 9.5px;
            line-height: 1.15;
            vertical-align: middle;
        }
        .team-header-row th {
            height: 42px;
            padding: 2px;
            background: #fff;
        }
        .team-name {
            text-align: center;
            font-size: 13px;
            font-weight: bold;
            line-height: 1.1;
        }
        .total-score-box {
            text-align: center;
            vertical-align: middle;
        }
        .total-label {
            font-size: 8px;
            line-height: 1.05;
            font-weight: bold;
        }
        .total-number {
            font-size: 24px;
            line-height: 1.05;
            font-weight: bold;
        }
        .column-header-row th {
            background: #fff176;
            font-size: 9px;
            font-weight: bold;
            text-align: center;
            padding: 2px;
        }
        .gender-row td {
            font-size: 9px;
            font-style: italic;
            font-weight: bold;
            background: #fafafa;
            padding: 2px;
            text-transform: uppercase;
            line-height: 1.15;
        }
        .gender-row + tr > td {
            padding-top: 2px;
        }
        .player-name-cell {
            font-size: 9.5px;
            font-style: italic;
            text-align: left;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            word-wrap: normal;
            overflow-wrap: normal;
            word-break: normal;
        }
        .number-cell,
        .numeric-cell {
            font-size: 9.5px;
            text-align: center;
            white-space: nowrap;
        }

        .sheet-official-remarks {
            margin-top: 3px;
            font-size: 8px;
            line-height: 1.15;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .sheet-official-remarks-title {
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .sheet-signature-line {
            border-top: 1px solid #777;
            height: 8px;
            margin-top: 8px;
        }
        .sheet-signature-label {
            font-size: 7.5px;
            margin-top: 1px;
            color: #52525b;
        }

        .muted { color: #52525b; }
    </style>
</head>
<body>

<div class="pdf-sheet-page">
    <table class="two-sheet-layout">
        <tr>
            <td class="sheet-cell">
                <div class="sheet-panel">
                    @include('admin.tournaments.matches.partials.pdf-score-table', [
                        'tournament' => $tournament,
                        'match' => $match,
                        'homeTeam' => $homeTeam,
                        'awayTeam' => $awayTeam,
                        'winnerLabel' => $winnerLabel,
                        'matchStatusLabel' => $matchStatusLabel,
                        'team' => $homeTeam,
                        'playerStats' => $homePlayerStats,
                        'totalScore' => (int) ($match->home_score ?? 0),
                        'side' => 'home',
                        'isCompleted' => $isCompleted,
                    ])
                </div>
            </td>
            <td class="sheet-cell">
                <div class="sheet-panel">
                    @include('admin.tournaments.matches.partials.pdf-score-table', [
                        'tournament' => $tournament,
                        'match' => $match,
                        'homeTeam' => $homeTeam,
                        'awayTeam' => $awayTeam,
                        'winnerLabel' => $winnerLabel,
                        'matchStatusLabel' => $matchStatusLabel,
                        'team' => $awayTeam,
                        'playerStats' => $awayPlayerStats,
                        'totalScore' => (int) ($match->away_score ?? 0),
                        'side' => 'away',
                        'isCompleted' => $isCompleted,
                    ])
                </div>
            </td>
        </tr>
    </table>
</div>

</body>
</html>
