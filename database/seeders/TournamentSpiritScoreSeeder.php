<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MatchSpiritScore;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use Illuminate\Database\Seeder;

/**
 * Seeds {@see MatchSpiritScore} rows for tournament 1: two rows per eligible match
 * (home team scored by away, away team scored by home). Idempotent via
 * {@see MatchSpiritScore::updateOrCreate} keyed by {@code match_id} + {@code scored_team_id}.
 *
 * Persisted columns use {@code scoring_team_id} / {@code scored_team_id} (the team being rated
 * and the opponent giving the rating), which align with “scored” / “scoring” registration sides
 * when each registration has a {@code team_id}.
 *
 * Run: {@code php artisan db:seed --class=TournamentSpiritScoreSeeder}
 */
final class TournamentSpiritScoreSeeder extends Seeder
{
    private const TOURNAMENT_ID = 9;

    private const NOTES = 'Seeded demo spirit score';

    /**
     * Weighted 1–3: ~50% three, ~40% two, ~10% one.
     */
    private function randomCriterionScore(): int
    {
        $roll = random_int(1, 100);

        if ($roll <= 50) {
            return 3;
        }

        if ($roll <= 90) {
            return 2;
        }

        return 1;
    }

    /**
     * @return array{
     *     knowledge_rules_score: int,
     *     fouls_body_contact_score: int,
     *     fair_mindedness_score: int,
     *     positive_attitude_score: int,
     *     communication_respect_score: int,
     *     total_score: int,
     * }
     */
    private function randomCriteriaBundle(): array
    {
        $knowledge = $this->randomCriterionScore();
        $fouls = $this->randomCriterionScore();
        $fair = $this->randomCriterionScore();
        $attitude = $this->randomCriterionScore();
        $communication = $this->randomCriterionScore();

        return [
            'knowledge_rules_score' => $knowledge,
            'fouls_body_contact_score' => $fouls,
            'fair_mindedness_score' => $fair,
            'positive_attitude_score' => $attitude,
            'communication_respect_score' => $communication,
            'total_score' => $knowledge + $fouls + $fair + $attitude + $communication,
        ];
    }

    private function spiritCaptainMemberId(?Team $team): ?int
    {
        $team?->loadMissing('members');

        $member = $team?->members?->firstWhere('role', 'spirit_captain');

        return $member?->id;
    }

    public function run(): void
    {
        $tournament = Tournament::query()->find(self::TOURNAMENT_ID);

        if ($tournament === null) {
            $this->command?->error('Tournament '.self::TOURNAMENT_ID.' not found — aborting.');

            return;
        }

        $allMatches = TournamentMatch::query()
            ->where('tournament_id', self::TOURNAMENT_ID)
            ->count();

        $matches = TournamentMatch::query()
            ->where('tournament_id', self::TOURNAMENT_ID)
            ->whereNotNull('home_registration_id')
            ->whereNotNull('away_registration_id')
            ->with([
                'homeRegistration.team.members',
                'awayRegistration.team.members',
            ])
            ->orderBy('match_number')
            ->orderBy('id')
            ->get();

        $skippedMissingTeams = 0;
        $matchesSeeded = 0;
        $recordsTouched = 0;
        $matchesMissingHomeCaptain = 0;
        $matchesMissingAwayCaptain = 0;

        foreach ($matches as $match) {
            $homeTeam = $match->homeRegistration?->team;
            $awayTeam = $match->awayRegistration?->team;

            if (! $homeTeam || ! $awayTeam) {
                $skippedMissingTeams++;

                continue;
            }

            $homeCaptainId = $this->spiritCaptainMemberId($homeTeam);
            $awayCaptainId = $this->spiritCaptainMemberId($awayTeam);

            if ($homeCaptainId === null) {
                $matchesMissingHomeCaptain++;
            }
            if ($awayCaptainId === null) {
                $matchesMissingAwayCaptain++;
            }

            $homeBundle = $this->randomCriteriaBundle();
            $awayBundle = $this->randomCriteriaBundle();

            MatchSpiritScore::query()->updateOrCreate(
                [
                    'match_id' => $match->id,
                    'scored_team_id' => $homeTeam->id,
                ],
                [
                    'tournament_id' => self::TOURNAMENT_ID,
                    'scoring_team_id' => $awayTeam->id,
                    'spirit_captain_id' => $homeCaptainId,
                    'knowledge_rules_score' => $homeBundle['knowledge_rules_score'],
                    'fouls_body_contact_score' => $homeBundle['fouls_body_contact_score'],
                    'fair_mindedness_score' => $homeBundle['fair_mindedness_score'],
                    'positive_attitude_score' => $homeBundle['positive_attitude_score'],
                    'communication_respect_score' => $homeBundle['communication_respect_score'],
                    'total_score' => $homeBundle['total_score'],
                    'notes' => self::NOTES,
                ],
            );
            $recordsTouched++;

            MatchSpiritScore::query()->updateOrCreate(
                [
                    'match_id' => $match->id,
                    'scored_team_id' => $awayTeam->id,
                ],
                [
                    'tournament_id' => self::TOURNAMENT_ID,
                    'scoring_team_id' => $homeTeam->id,
                    'spirit_captain_id' => $awayCaptainId,
                    'knowledge_rules_score' => $awayBundle['knowledge_rules_score'],
                    'fouls_body_contact_score' => $awayBundle['fouls_body_contact_score'],
                    'fair_mindedness_score' => $awayBundle['fair_mindedness_score'],
                    'positive_attitude_score' => $awayBundle['positive_attitude_score'],
                    'communication_respect_score' => $awayBundle['communication_respect_score'],
                    'total_score' => $awayBundle['total_score'],
                    'notes' => self::NOTES,
                ],
            );
            $recordsTouched++;

            $matchesSeeded++;
        }

        $skippedRegOnly = $allMatches - $matches->count();

        $this->command?->info('TournamentSpiritScoreSeeder finished.');
        $this->command?->table(
            ['Metric', 'Count'],
            [
                ['Total matches in tournament (all rows)', $allMatches],
                ['Matches with both registration slots set', $matches->count()],
                ['Matches seeded (both teams resolved, 2 spirit rows each)', $matchesSeeded],
                ['Spirit score rows created or updated', $recordsTouched],
                ['Skipped: missing home/away team on registration', $skippedMissingTeams],
                ['Skipped: missing one or both registration IDs', $skippedRegOnly],
                ['Matches where home team has no spirit captain', $matchesMissingHomeCaptain],
                ['Matches where away team has no spirit captain', $matchesMissingAwayCaptain],
            ],
        );
    }
}
