<?php

namespace App\Support;

use App\Models\TournamentMatch;
use Illuminate\Support\Collection;

final class MatchScoreSheetContext
{
    /**
     * @param  Collection<int, array{team: ?\App\Models\Team, stats: Collection, totalScore: int, side: string, registration: ?\App\Models\TournamentRegistration, statsByMember: Collection}>  $scoreSheetConfigs
     * @param  array<string, array{blocks: mixed, assists: mixed, scores: mixed}>  $matchScoreInputState
     * @param  array<string, string>  $matchScoreMemberSides
     */
    public function __construct(
        public TournamentMatch $match,
        public Collection $scoreSheetConfigs,
        public array $matchScoreInputState,
        public array $matchScoreMemberSides,
        public int $matchScorePreviewHome,
        public int $matchScorePreviewAway,
    ) {}

    public static function fromMatch(TournamentMatch $match): self
    {
        $homeRegistration = $match->homeRegistration;
        $awayRegistration = $match->awayRegistration;
        $homeTeam = $homeRegistration?->team;
        $awayTeam = $awayRegistration?->team;

        $homeStats = $match->playerStats
            ->filter(fn ($stat) => $stat->teamMember?->team_id === $homeTeam?->id)
            ->values();

        $awayStats = $match->playerStats
            ->filter(fn ($stat) => $stat->teamMember?->team_id === $awayTeam?->id)
            ->values();

        $scoreSheetConfigs = collect([
            ['team' => $homeTeam, 'stats' => $homeStats, 'totalScore' => (int) ($match->home_score ?? 0), 'side' => 'home', 'registration' => $homeRegistration],
            ['team' => $awayTeam, 'stats' => $awayStats, 'totalScore' => (int) ($match->away_score ?? 0), 'side' => 'away', 'registration' => $awayRegistration],
        ]);

        $matchScoreInputState = [];
        $matchScoreMemberSides = [];

        foreach ($scoreSheetConfigs as &$scoreSheetConfig) {
            $statsByMember = $scoreSheetConfig['stats']->keyBy('team_member_id');
            $scoreSheetConfig['statsByMember'] = $statsByMember;

            foreach (($scoreSheetConfig['team']?->members ?? collect()) as $member) {
                $memberId = (string) $member->id;
                $registrationId = (string) ($scoreSheetConfig['registration']?->id ?? '');
                $stat = $statsByMember->get($member->id);

                $matchScoreInputState[$memberId] = [
                    'blocks' => old("scores.{$registrationId}.{$memberId}.blocks", $stat?->blocks ?? ''),
                    'assists' => old("scores.{$registrationId}.{$memberId}.assists", $stat?->assists ?? ''),
                    'scores' => old("scores.{$registrationId}.{$memberId}.scores", $stat?->goals ?? ''),
                ];
                $matchScoreMemberSides[$memberId] = $scoreSheetConfig['side'];
            }
        }
        unset($scoreSheetConfig);

        $matchScorePreviewHome = 0;
        $matchScorePreviewAway = 0;

        foreach ($matchScoreInputState as $memberId => $inputRow) {
            $goals = $inputRow['scores'];
            $goalTotal = $goals === '' || $goals === null ? 0 : (int) $goals;

            if (($matchScoreMemberSides[$memberId] ?? null) === 'home') {
                $matchScorePreviewHome += $goalTotal;
            } elseif (($matchScoreMemberSides[$memberId] ?? null) === 'away') {
                $matchScorePreviewAway += $goalTotal;
            }
        }

        return new self(
            match: $match,
            scoreSheetConfigs: $scoreSheetConfigs,
            matchScoreInputState: $matchScoreInputState,
            matchScoreMemberSides: $matchScoreMemberSides,
            matchScorePreviewHome: $matchScorePreviewHome,
            matchScorePreviewAway: $matchScorePreviewAway,
        );
    }

    /**
     * Whether the public match page should render a score sheet (vs. empty state).
     */
    public function hasPublishedScoreSheet(): bool
    {
        if ($this->match->playerStats->isNotEmpty()) {
            return true;
        }

        return $this->match->home_score !== null && $this->match->away_score !== null;
    }
}
