{{-- Back-compat wrapper: match PDF uses pdf-score-table directly. --}}
@include('admin.tournaments.matches.partials.pdf-score-table', [
    'team' => $team,
    'playerStats' => $playerStats,
    'totalScore' => $totalScore,
    'side' => $side,
])
