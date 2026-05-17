<?php

namespace App\Support;

use App\Models\MatchSpiritScore;
use App\Models\Team;
use App\Models\TournamentMatch;

final class MatchSpiritScores
{
    public const MAX_TOTAL = 15;

    /**
     * @return list<array{field: string, title: string}>
     */
    public static function criteria(): array
    {
        return [
            [
                'field' => 'knowledge_rules_score',
                'title' => 'Knowledge and Use of Rules',
            ],
            [
                'field' => 'fouls_body_contact_score',
                'title' => 'Fouls and Body Contact',
            ],
            [
                'field' => 'fair_mindedness_score',
                'title' => 'Fair Mindedness',
            ],
            [
                'field' => 'positive_attitude_score',
                'title' => 'Positive Attitude and Self-Control',
            ],
            [
                'field' => 'communication_respect_score',
                'title' => 'Communication and Respect',
            ],
        ];
    }

    /**
     * Spirit scores received by each team for a match.
     *
     * @return array{home: array{team: Team, knowledge_rules_score: ?int, fouls_body_contact_score: ?int, fair_mindedness_score: ?int, positive_attitude_score: ?int, communication_respect_score: ?int, total_score: int}|null, away: array{team: Team, knowledge_rules_score: ?int, fouls_body_contact_score: ?int, fair_mindedness_score: ?int, positive_attitude_score: ?int, communication_respect_score: ?int, total_score: int}|null}
     */
    public static function forMatch(TournamentMatch $match): array
    {
        $spiritByScoredTeamId = $match->spiritScores->keyBy('scored_team_id');
        $homeTeam = $match->homeRegistration?->team;
        $awayTeam = $match->awayRegistration?->team;

        return [
            'home' => self::forTeam($homeTeam, $spiritByScoredTeamId->get((int) $homeTeam?->id)),
            'away' => self::forTeam($awayTeam, $spiritByScoredTeamId->get((int) $awayTeam?->id)),
        ];
    }

    public static function presentationForMatch(TournamentMatch $match): MatchSpiritPresentation
    {
        if (! $match->isCompletedMatchStatus()) {
            return new MatchSpiritPresentation(MatchSpiritPresentation::STATUS_NOT_COMPLETED);
        }

        $scores = self::forMatch($match);

        if ($scores['home'] === null && $scores['away'] === null) {
            return new MatchSpiritPresentation(MatchSpiritPresentation::STATUS_NO_DATA);
        }

        return new MatchSpiritPresentation(
            MatchSpiritPresentation::STATUS_READY,
            $scores['home'],
            $scores['away'],
            self::winnerMessage($scores['home'], $scores['away']),
        );
    }

    /**
     * @return array{team: Team, knowledge_rules_score: ?int, fouls_body_contact_score: ?int, fair_mindedness_score: ?int, positive_attitude_score: ?int, communication_respect_score: ?int, total_score: int}|null
     */
    private static function forTeam(?Team $team, ?MatchSpiritScore $record): ?array
    {
        if ($team === null || ! self::recordHasData($record)) {
            return null;
        }

        return [
            'team' => $team,
            'knowledge_rules_score' => $record->knowledge_rules_score,
            'fouls_body_contact_score' => $record->fouls_body_contact_score,
            'fair_mindedness_score' => $record->fair_mindedness_score,
            'positive_attitude_score' => $record->positive_attitude_score,
            'communication_respect_score' => $record->communication_respect_score,
            'total_score' => self::resolveTotal($record),
        ];
    }

    private static function recordHasData(?MatchSpiritScore $record): bool
    {
        if ($record === null) {
            return false;
        }

        if ($record->total_score !== null) {
            return true;
        }

        foreach (self::criteria() as $criterion) {
            if ($record->{$criterion['field']} !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Spirit score received by one side of a completed match (persisted row or legacy notes JSON).
     *
     * @return array{rules:int,fouls:int,fair:int,attitude:int,communication:int,total:int}|null
     */
    public static function receivedBreakdownForMatchSide(TournamentMatch $match, bool $isHome): ?array
    {
        $scoredTeamId = $isHome
            ? $match->homeRegistration?->team_id
            : $match->awayRegistration?->team_id;

        if ($scoredTeamId) {
            $record = $match->relationLoaded('spiritScores')
                ? $match->spiritScores->firstWhere('scored_team_id', (int) $scoredTeamId)
                : MatchSpiritScore::query()
                    ->where('match_id', $match->id)
                    ->where('scored_team_id', $scoredTeamId)
                    ->first();

            if ($record instanceof MatchSpiritScore && self::recordHasData($record)) {
                return [
                    'rules' => $record->knowledge_rules_score ?? 0,
                    'fouls' => $record->fouls_body_contact_score ?? 0,
                    'fair' => $record->fair_mindedness_score ?? 0,
                    'attitude' => $record->positive_attitude_score ?? 0,
                    'communication' => $record->communication_respect_score ?? 0,
                    'total' => self::resolveTotal($record),
                ];
            }
        }

        if (! filled($match->notes)) {
            return null;
        }

        $payload = json_decode($match->notes, true);

        if (! is_array($payload)) {
            return null;
        }

        $scopes = array_filter([
            data_get($payload, 'spirit_scores'),
            data_get($payload, 'spirit'),
            $payload,
        ], 'is_array');

        $keys = $isHome
            ? ['home_received', 'home', 'home_team', (string) $match->home_registration_id]
            : ['away_received', 'away', 'away_team', (string) $match->away_registration_id];

        foreach ($scopes as $scope) {
            foreach ($keys as $key) {
                $candidate = data_get($scope, $key);

                if (! is_array($candidate)) {
                    continue;
                }

                $normalized = self::normalizeLegacyPayload($candidate);

                if ($normalized !== null) {
                    return $normalized;
                }
            }
        }

        return null;
    }

    public static function resolveTotal(MatchSpiritScore $record): int
    {
        if ($record->total_score !== null) {
            return (int) $record->total_score;
        }

        return (int) collect(self::criteria())
            ->sum(fn (array $criterion): int => (int) ($record->{$criterion['field']} ?? 0));
    }

    /**
     * @return array{rules:int,fouls:int,fair:int,attitude:int,communication:int,total:int}|null
     */
    private static function normalizeLegacyPayload(array $payload): ?array
    {
        $rules = self::extractLegacyMetricValue($payload, ['rules', 'knowledge', 'knowledge_and_use']);
        $fouls = self::extractLegacyMetricValue($payload, ['fouls', 'fouls_and_body', 'body_contact']);
        $fair = self::extractLegacyMetricValue($payload, ['fair', 'fair_mindedness', 'fairness']);
        $attitude = self::extractLegacyMetricValue($payload, ['attitude', 'attit', 'positive_attitude', 'self_control']);
        $communication = self::extractLegacyMetricValue($payload, ['communication', 'comm']);

        if ($rules === null && $fouls === null && $fair === null && $attitude === null && $communication === null) {
            return null;
        }

        return [
            'rules' => $rules ?? 0,
            'fouls' => $fouls ?? 0,
            'fair' => $fair ?? 0,
            'attitude' => $attitude ?? 0,
            'communication' => $communication ?? 0,
            'total' => (int) (($rules ?? 0) + ($fouls ?? 0) + ($fair ?? 0) + ($attitude ?? 0) + ($communication ?? 0)),
        ];
    }

    /**
     * @param  list<string>  $keys
     */
    private static function extractLegacyMetricValue(array $payload, array $keys): ?int
    {
        foreach ($keys as $key) {
            $value = data_get($payload, $key);

            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    /**
     * @param  array{team: Team, knowledge_rules_score: ?int, fouls_body_contact_score: ?int, fair_mindedness_score: ?int, positive_attitude_score: ?int, communication_respect_score: ?int, total_score: int}|null  $home
     * @param  array{team: Team, knowledge_rules_score: ?int, fouls_body_contact_score: ?int, fair_mindedness_score: ?int, positive_attitude_score: ?int, communication_respect_score: ?int, total_score: int}|null  $away
     */
    private static function winnerMessage(?array $home, ?array $away): ?string
    {
        if ($home === null || $away === null) {
            return null;
        }

        $homeTotal = $home['total_score'];
        $awayTotal = $away['total_score'];

        if ($homeTotal === $awayTotal) {
            return 'Spirit scores are tied.';
        }

        $winner = $homeTotal > $awayTotal ? $home['team'] : $away['team'];

        return 'More spirited team: '.$winner->name;
    }
}
