@php
    /** @var \App\Models\Tournament $selectedTournament */
    $teamAwardValues = data_get($tournamentReport ?? [], 'team_awards', []);

    $teamAwards = [
        ['letter' => 'a', 'key' => 'most_spirited_team', 'label' => __('Most Spirited Team')],
        ['letter' => 'b', 'key' => 'champion', 'label' => __('Champion')],
        ['letter' => 'c', 'key' => 'first_runner_up', 'label' => __('1st Runner Up')],
        ['letter' => 'd', 'key' => 'second_runner_up', 'label' => __('2nd Runner Up')],
        ['letter' => 'e', 'key' => 'third_runner_up', 'label' => __('3rd Runner Up')],
    ];

    $individualAwardValues = data_get($tournamentReport ?? [], 'individual_awards', []);
    $m7m = array_values((array) data_get($individualAwardValues, 'mythical_7_male', []));
    $m7f = array_values((array) data_get($individualAwardValues, 'mythical_7_female', []));

    $individualAwards = [
        ['letter' => 'a', 'label' => __('Most Blocks (Male)'), 'fill' => data_get($individualAwardValues, 'most_blocks_male')],
        ['letter' => 'b', 'label' => __('Most Blocks (Female)'), 'fill' => data_get($individualAwardValues, 'most_blocks_female')],
        ['letter' => 'c', 'label' => __('Most Assists (Male)'), 'fill' => data_get($individualAwardValues, 'most_assists_male')],
        ['letter' => 'd', 'label' => __('Most Assists (Female)'), 'fill' => data_get($individualAwardValues, 'most_assists_female')],
        ['letter' => 'e', 'label' => __('Most Scores (Male)'), 'fill' => data_get($individualAwardValues, 'most_scores_male')],
        ['letter' => 'f', 'label' => __('Most Scores (Female)'), 'fill' => data_get($individualAwardValues, 'most_scores_female')],
        ['letter' => 'g', 'label' => __('Mythical 7 (Male)'), 'fill' => data_get($m7m, 0)],
        ['letter' => 'h', 'label' => __('Mythical 7 (Male)'), 'fill' => data_get($m7m, 1)],
        ['letter' => 'i', 'label' => __('Mythical 7 (Male)'), 'fill' => data_get($m7m, 2)],
        ['letter' => 'j', 'label' => __('Mythical 7 (Male)'), 'fill' => data_get($m7m, 3)],
        ['letter' => 'k', 'label' => __('Mythical 7 (Female)'), 'fill' => data_get($m7f, 0)],
        ['letter' => 'l', 'label' => __('Mythical 7 (Female)'), 'fill' => data_get($m7f, 1)],
        ['letter' => 'm', 'label' => __('Mythical 7 (Female)'), 'fill' => data_get($m7f, 2)],
        ['letter' => 'n', 'label' => __('Finals MVP (Male)'), 'fill' => data_get($individualAwardValues, 'finals_mvp_male')],
        ['letter' => 'o', 'label' => __('Finals MVP (Female)'), 'fill' => data_get($individualAwardValues, 'finals_mvp_female')],
        ['letter' => 'p', 'label' => __('Tournament MVP (Male)'), 'fill' => data_get($individualAwardValues, 'tournament_mvp_male')],
        ['letter' => 'q', 'label' => __('Tournament MVP (Female)'), 'fill' => data_get($individualAwardValues, 'tournament_mvp_female')],
    ];
@endphp

<style>
    .report-print-area {
        font-family: 'Times New Roman', Times, serif;
        max-width: 7.2in;
        margin: 0 auto;
        padding: 0;
        color: #000;
    }

    .report-header {
        text-align: center;
        margin: 0 0 8px 0;
        padding: 0;
    }

    .report-header-logo {
        width: 100%;
        max-width: 5.8in;
        height: auto;
        display: block;
        margin: 0 auto 6px auto;
        object-fit: contain;
    }

    .report-title {
        text-align: center;
        font-family: 'Times New Roman', Times, serif;
        font-size: 20pt;
        font-weight: bold;
        text-transform: uppercase;
        margin: 8px 0 22px 0;
        padding: 0;
    }

    .report-section {
        display: grid;
        grid-template-columns: 28px 1fr;
        column-gap: 12px;
        margin-bottom: 16px;
    }

    .section-number {
        font-weight: bold;
        font-size: 10.5pt;
        line-height: 1.22;
    }

    .section-title {
        font-weight: bold;
        font-size: 10.5pt;
        line-height: 1.22;
        margin-bottom: 3px;
    }

    .award-line {
        display: flex;
        align-items: baseline;
        font-size: 10.5pt;
        line-height: 1.22;
        margin: 0;
    }

    .award-label {
        white-space: nowrap;
    }

    .award-blank {
        border-bottom: 1px solid #000;
        flex: 1;
        margin-left: 4px;
        height: 0.8em;
        min-height: 0.8em;
    }

    .award-blank--filled {
        padding-left: 2px;
        white-space: normal;
        word-break: break-word;
        height: auto;
        min-height: 0.8em;
    }

    .prepared-by {
        margin-top: 20px;
        font-size: 10.5pt;
        line-height: 1.22;
    }

    .prepared-name {
        margin-top: 24px;
        font-weight: bold;
        font-size: 10.5pt;
        line-height: 1.22;
    }

    .prepared-role {
        font-style: italic;
        margin-left: 60px;
        font-size: 10.5pt;
        line-height: 1.22;
    }

    @media print {
        @page {
            size: letter portrait;
            margin: 0.25in 0.5in 0.35in 0.5in;
        }

        html,
        body {
            margin: 0 !important;
            padding: 0 !important;
            min-height: 0 !important;
            height: auto !important;
            width: 100% !important;
            background: #fff !important;
            font-family: 'Times New Roman', Times, serif;
            color: #000;
        }

        body * {
            visibility: hidden !important;
        }

        #tournament-report-print-area,
        #tournament-report-print-area * {
            visibility: visible !important;
        }

        #tournament-report-print-area {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            min-height: 0 !important;
            height: auto !important;
            box-sizing: border-box !important;
            background: #fff !important;
            border: none !important;
            box-shadow: none !important;
            border-radius: 0 !important;
            z-index: 2147483647 !important;
        }

        .tournament-report-shell {
            margin: 0 !important;
            padding: 0 !important;
            min-height: 0 !important;
            height: auto !important;
            display: block !important;
        }

        .report-print-area {
            margin: 0 auto !important;
            padding: 0 !important;
            max-width: 7.2in !important;
        }

        .report-print-area > :first-child,
        .report-header:first-child,
        .report-header-logo:first-child,
        .report-title:first-child {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }

        .report-header {
            margin: 0 0 8px 0 !important;
            padding: 0 !important;
            text-align: center !important;
        }

        .report-header-logo {
            display: block !important;
            margin: 0 auto 6px auto !important;
            padding: 0 !important;
            max-width: 5.8in !important;
            width: 100% !important;
            height: auto !important;
        }

        .report-title {
            margin: 6px 0 16px 0 !important;
            padding: 0 !important;
            text-align: center !important;
            font-size: 20pt !important;
            font-weight: 700 !important;
        }

        .report-section {
            margin-bottom: 12px !important;
        }

        .award-line {
            font-size: 10pt !important;
            line-height: 1.15 !important;
        }

        .section-number,
        .section-title,
        .prepared-by,
        .prepared-name,
        .prepared-role {
            font-size: 10pt !important;
            line-height: 1.15 !important;
        }

        .prepared-by {
            margin-top: 14px !important;
        }

        .prepared-name {
            margin-top: 16px !important;
        }

        .prepared-role {
            margin-left: 52px !important;
        }

        .no-print {
            display: none !important;
        }
    }
</style>

<div class="tournament-report-shell flex flex-col gap-4">
    <div class="no-print flex justify-end">
        <button
            type="button"
            class="inline-flex items-center justify-center rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-zinc-700 transition hover:border-neutral-400 hover:bg-zinc-50 dark:border-neutral-600 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800"
            onclick="window.print()"
        >
            {{ __('Print Report') }}
        </button>
    </div>

    <div
        id="tournament-report-print-area"
        class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-zinc-900"
    >
        <div class="report-print-area text-left">
            @include('admin.tournaments.partials.report-header')
            <h1 class="report-title">{{ __('Tournament Result') }}</h1>

            <div class="report-section">
                <span class="section-number">{{ __('I.') }}</span>
                <div>
                    <div class="section-title">{{ __('Team Awards') }}</div>
                    @foreach ($teamAwards as $row)
                        @php
                            $fill = data_get($teamAwardValues, $row['key']);
                        @endphp
                        <div class="award-line">
                            <span class="award-label">{{ $row['letter'] }}. {{ $row['label'] }}:</span>
                            @if (filled($fill))
                                <span class="award-blank award-blank--filled">{{ $fill }}</span>
                            @else
                                <span class="award-blank" aria-hidden="true"></span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="report-section">
                <span class="section-number">{{ __('II.') }}</span>
                <div>
                    <div class="section-title">{{ __('Individual Awards') }}</div>
                    @foreach ($individualAwards as $row)
                        @php
                            $fill = $row['fill'] ?? null;
                        @endphp
                        <div class="award-line">
                            <span class="award-label">{{ $row['letter'] }}. {{ $row['label'] }}:</span>
                            @if (filled($fill))
                                <span class="award-blank award-blank--filled">{{ $fill }}</span>
                            @else
                                <span class="award-blank" aria-hidden="true"></span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="prepared-by">{{ __('Prepared by:') }}</div>
            <div class="prepared-name">{{ __('MARY ANTONNETTE S. RAMBONANZA') }}</div>
            <div class="prepared-role">{{ __('MISO Personnel') }}</div>
        </div>
    </div>
</div>
