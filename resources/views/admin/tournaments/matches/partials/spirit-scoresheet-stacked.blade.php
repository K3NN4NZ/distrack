{{-- Used by spirit PDF; two side-by-side cut-out panels on one A4 landscape page. --}}
@php
    $isCompleted = $isCompleted ?? true;
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

<table class="two-sheet-layout">
    <tr>
        <td class="sheet-cell">
            <div class="sheet-panel">
                @include('admin.tournaments.matches.partials.spirit-panel-sheet', [
                    'tournament' => $tournament,
                    'match' => $match,
                    'homeTeam' => $homeTeam,
                    'awayTeam' => $awayTeam,
                    'matchStatusLabel' => $matchStatusLabel ?? null,
                    'sheetTeam' => $homeTeam,
                    'sheetRecord' => $homeSpirit,
                    'sheetCaptain' => $homeSpiritCaptain,
                    'sheetLabel' => __('Home team'),
                    'isCompleted' => $isCompleted,
                    'criteriaRows' => $criteriaRows,
                    'rubricLines' => $rubricLines,
                ])
            </div>
        </td>
        <td class="sheet-cell">
            <div class="sheet-panel">
                @include('admin.tournaments.matches.partials.spirit-panel-sheet', [
                    'tournament' => $tournament,
                    'match' => $match,
                    'homeTeam' => $homeTeam,
                    'awayTeam' => $awayTeam,
                    'matchStatusLabel' => $matchStatusLabel ?? null,
                    'sheetTeam' => $awayTeam,
                    'sheetRecord' => $awaySpirit,
                    'sheetCaptain' => $awaySpiritCaptain,
                    'sheetLabel' => __('Away team'),
                    'isCompleted' => $isCompleted,
                    'criteriaRows' => $criteriaRows,
                    'rubricLines' => $rubricLines,
                ])
            </div>
        </td>
    </tr>
</table>
