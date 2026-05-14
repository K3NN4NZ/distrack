@php
    $isCompleted = $isCompleted ?? true;
    $spiritPdfHeading = $isCompleted ? __('Spirit scoresheet') : __('MATCH/SPIRIT SCORE SHEETS');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $spiritPdfHeading }} — {{ $tournament->name }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 3mm;
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
        }
        table {
            border-collapse: collapse;
        }
        .muted {
            color: #52525b;
        }

        .spirit-page {
            width: 100%;
            max-width: 100%;
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

        .spirit-sheet-header {
            text-align: center;
            margin: 0 0 2px 0;
            padding: 0 0 2px 0;
            border-bottom: 1px solid #d9d9d9;
            line-height: 1.1;
        }

        .spirit-logo-wrap {
            margin: 0;
            padding: 0;
            line-height: 1;
        }

        .spirit-sheet-header-logo {
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

        .sheet-spirit-doc-line {
            font-weight: bold;
            text-transform: uppercase;
            white-space: nowrap;
            margin-top: 3px;
            letter-spacing: 0.06em;
        }

        .spirit-title {
            font-size: 11px;
            font-weight: bold;
        }

        .spirit-sheet {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            page-break-inside: avoid;
            break-inside: avoid;
            margin-top: 3px;
        }

        .spirit-sheet th,
        .spirit-sheet td {
            border: 1px solid #333;
            padding: 2px;
            font-size: 8.5px;
            line-height: 1.12;
            vertical-align: top;
        }

        .spirit-criteria-col {
            width: 84%;
        }

        .spirit-score-col {
            width: 16%;
            text-align: center;
            font-size: 10px;
            font-weight: bold;
        }

        .spirit-col-header th {
            background: #fafafa;
            font-weight: bold;
            text-align: center;
            font-size: 8.5px;
            padding: 2px;
        }

        .spirit-criteria-title {
            font-weight: bold;
            font-size: 8.5px;
            margin-bottom: 0;
        }

        .spirit-rubric {
            font-size: 7.5px;
            line-height: 1.1;
            color: #222;
        }

        .spirit-rubric + .spirit-rubric {
            margin-top: 0;
        }

        .spirit-total-row td {
            font-weight: bold;
            background: #f2f2f2;
            font-size: 8.5px;
            padding: 2px;
        }

        .spirit-captain-section {
            margin-top: 8px;
            text-align: center;
            font-size: 8px;
            line-height: 1.15;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .spirit-captain-section .captain-person {
            font-weight: bold;
            font-size: 8.5px;
            text-transform: none;
            letter-spacing: normal;
        }

        .spirit-captain-name {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 0.03em;
        }

        .spirit-signature-label {
            font-style: italic;
            font-size: 7.5px;
            color: #52525b;
        }

        .spirit-sheet-official-remarks {
            margin-top: 3px;
            font-size: 8px;
            line-height: 1.15;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .spirit-sheet-official-remarks-title {
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .spirit-sheet-signature-line {
            border-top: 1px solid #777;
            height: 8px;
            margin-top: 8px;
        }

        .spirit-sheet-signature-caption {
            font-size: 7.5px;
            margin-top: 1px;
            color: #52525b;
        }
    </style>
</head>
<body>

<div class="pdf-sheet-page spirit-page">
    @include('admin.tournaments.matches.partials.spirit-scoresheet-stacked', [
        'tournament' => $tournament,
        'match' => $match,
        'homeTeam' => $homeTeam,
        'awayTeam' => $awayTeam,
        'spiritScoresByScoredTeamId' => $spiritScoresByScoredTeamId,
        'matchStatusLabel' => $matchStatusLabel ?? null,
        'isCompleted' => $isCompleted,
    ])
</div>

</body>
</html>
