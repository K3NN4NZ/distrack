<?php

namespace Database\Seeders;

use App\Models\MatchPlayerStat;
use App\Models\MatchScoreLog;
use App\Models\Pitch;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentRegistration;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds sample crossover (stage = crossover) matches with score data for local QA.
 *
 * Run after {@see TournamentFieldsAndRegistrationsSeeder} so tournaments, pitches,
 * and team registrations exist:
 *
 *   php artisan db:seed
 *   php artisan db:seed --class=CrossoverMatchesSeeder
 *
 * Idempotent per tournament: removes prior rows whose notes contain NOTES_MARKER.
 */
class CrossoverMatchesSeeder extends Seeder
{
    public const NOTES_MARKER = '[seed:crossover-matches]';

    public function run(): void
    {
        DB::transaction(function (): void {
            $tournament = Tournament::query()->orderBy('id')->first();

            if ($tournament === null) {
                $this->command?->warn('CrossoverMatchesSeeder: no tournament found. Create a tournament first.');

                return;
            }

            $admin = User::query()->where('role', User::ROLE_ADMIN)->orderBy('id')->first();

            if ($admin === null) {
                $this->command?->warn('CrossoverMatchesSeeder: no admin user found; run UserAccountSeeder first.');

                return;
            }

            $pitches = Pitch::query()
                ->where('tournament_id', $tournament->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            if ($pitches->isEmpty()) {
                $this->command?->warn('CrossoverMatchesSeeder: no pitches for tournament '.$tournament->id.'; run TournamentFieldsAndRegistrationsSeeder first.');

                return;
            }

            $registrations = TournamentRegistration::query()
                ->where('tournament_id', $tournament->id)
                ->where('status', 'approved')
                ->with(['team.members' => fn ($q) => $q->orderBy('id')])
                ->get()
                ->filter(fn (TournamentRegistration $r): bool => $r->team !== null && $r->team->members->isNotEmpty())
                ->values();

            if ($registrations->count() < 4) {
                $this->command?->warn('CrossoverMatchesSeeder: need at least four registered teams with roster members; run ParticipantTeamsSeeder and TournamentFieldsAndRegistrationsSeeder first.');

                return;
            }

            $this->removeSeededCrossoverMatches($tournament->id);

            $pitchA = $pitches->get(0);
            $pitchB = $pitches->get(1) ?? $pitchA;

            $nextMatchNumber = (int) (TournamentMatch::query()
                ->where('tournament_id', $tournament->id)
                ->max('match_number') ?? 0);

            $homeReg0 = $registrations[0];
            $awayReg0 = $registrations[1];
            $homeReg1 = $registrations[2];
            $awayReg1 = $registrations[3];

            $this->seedCompletedCrossoverWithPlayerStats(
                tournament: $tournament,
                adminId: $admin->id,
                pitch: $pitchA,
                matchNumber: ++$nextMatchNumber,
                homeRegistration: $homeReg0,
                awayRegistration: $awayReg0,
            );

            $this->seedLiveCrossoverWithScoreLogs(
                tournament: $tournament,
                adminId: $admin->id,
                pitch: $pitchB,
                matchNumber: ++$nextMatchNumber,
                homeRegistration: $homeReg1,
                awayRegistration: $awayReg1,
            );

            $this->command?->info(sprintf(
                'CrossoverMatchesSeeder: created sample crossover matches on tournament #%d (%s).',
                $tournament->id,
                $tournament->name,
            ));
        });
    }

    protected function removeSeededCrossoverMatches(int $tournamentId): void
    {
        TournamentMatch::query()
            ->where('tournament_id', $tournamentId)
            ->where('stage', 'crossover')
            ->where('notes', 'like', '%'.self::NOTES_MARKER.'%')
            ->delete();
    }

    protected function seedCompletedCrossoverWithPlayerStats(
        Tournament $tournament,
        int $adminId,
        Pitch $pitch,
        int $matchNumber,
        TournamentRegistration $homeRegistration,
        TournamentRegistration $awayRegistration,
    ): void {
        $homeMembers = $homeRegistration->team->members->values();
        $awayMembers = $awayRegistration->team->members->values();

        if ($homeMembers->count() < 3 || $awayMembers->count() < 3) {
            $this->command?->warn('CrossoverMatchesSeeder: skipping completed sample — rosters need at least three players per side.');

            return;
        }

        $marker = 'Sample crossover (completed · manual player stats). '.self::NOTES_MARKER;

        $match = TournamentMatch::query()->create([
            'tournament_id' => $tournament->id,
            'pitch_id' => $pitch->id,
            'pitch_assigned_by' => $adminId,
            'home_registration_id' => $homeRegistration->id,
            'away_registration_id' => $awayRegistration->id,
            'stage' => 'crossover',
            'round_label' => 'Crossover · Seeded bracket',
            'match_number' => $matchNumber,
            'scheduled_at' => now()->subDay()->setTime(14, 0),
            'status' => 'completed',
            'home_score' => 12,
            'away_score' => 10,
            'notes' => $marker,
        ]);

        MatchPlayerStat::query()->where('match_id', $match->id)->delete();

        $now = now();
        $rows = [
            [
                'match_id' => $match->id,
                'team_member_id' => $homeMembers[0]->id,
                'goals' => 8,
                'assists' => 2,
                'blocks' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'match_id' => $match->id,
                'team_member_id' => $homeMembers[1]->id,
                'goals' => 3,
                'assists' => 5,
                'blocks' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'match_id' => $match->id,
                'team_member_id' => $homeMembers[2]->id,
                'goals' => 1,
                'assists' => 5,
                'blocks' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'match_id' => $match->id,
                'team_member_id' => $awayMembers[0]->id,
                'goals' => 5,
                'assists' => 3,
                'blocks' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'match_id' => $match->id,
                'team_member_id' => $awayMembers[1]->id,
                'goals' => 4,
                'assists' => 4,
                'blocks' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'match_id' => $match->id,
                'team_member_id' => $awayMembers[2]->id,
                'goals' => 1,
                'assists' => 3,
                'blocks' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        MatchPlayerStat::query()->insert($rows);
    }

    protected function seedLiveCrossoverWithScoreLogs(
        Tournament $tournament,
        int $adminId,
        Pitch $pitch,
        int $matchNumber,
        TournamentRegistration $homeRegistration,
        TournamentRegistration $awayRegistration,
    ): void {
        $homeMembers = $homeRegistration->team->members->values();
        $awayMembers = $awayRegistration->team->members->values();

        if ($homeMembers->count() < 2 || $awayMembers->count() < 2) {
            $this->command?->warn('CrossoverMatchesSeeder: skipping live timeline sample — rosters need at least two players per side.');

            return;
        }

        $h0 = $homeMembers[0];
        $h1 = $homeMembers[1];
        $a0 = $awayMembers[0];
        $a1 = $awayMembers[1];

        $marker = 'Sample crossover (live · scoring timeline). '.self::NOTES_MARKER;

        $match = TournamentMatch::query()->create([
            'tournament_id' => $tournament->id,
            'pitch_id' => $pitch->id,
            'pitch_assigned_by' => $adminId,
            'home_registration_id' => $homeRegistration->id,
            'away_registration_id' => $awayRegistration->id,
            'stage' => 'crossover',
            'round_label' => 'Crossover · Seeded live',
            'match_number' => $matchNumber,
            'scheduled_at' => now()->setTime(16, 30),
            'status' => 'live',
            'home_score' => 0,
            'away_score' => 0,
            'notes' => $marker,
        ]);

        $plays = [
            ['reg' => $homeRegistration->id, 'scorer' => $h0->id, 'assist' => $h1->id, 'minute' => 3],
            ['reg' => $homeRegistration->id, 'scorer' => $h0->id, 'assist' => null, 'minute' => 8],
            ['reg' => $awayRegistration->id, 'scorer' => $a0->id, 'assist' => $a1->id, 'minute' => 12],
            ['reg' => $homeRegistration->id, 'scorer' => $h1->id, 'assist' => $h0->id, 'minute' => 18],
            ['reg' => $awayRegistration->id, 'scorer' => $a1->id, 'assist' => null, 'minute' => 22],
        ];

        foreach ($plays as $index => $play) {
            MatchScoreLog::query()->create([
                'match_id' => $match->id,
                'sequence' => $index + 1,
                'team_registration_id' => $play['reg'],
                'team_member_id' => $play['scorer'],
                'assist_team_member_id' => $play['assist'],
                'minute' => $play['minute'],
                'home_score' => 0,
                'away_score' => 0,
            ]);
        }

        $match->refresh();
        $this->syncMatchScoreTimeline($match);
    }

    /**
     * Same scoreline rebuild logic as the admin scoring controller for timeline rows.
     */
    protected function syncMatchScoreTimeline(TournamentMatch $match): void
    {
        $logs = MatchScoreLog::query()
            ->where('match_id', $match->id)
            ->orderBy('sequence')
            ->orderBy('id')
            ->get();

        if ($logs->isEmpty()) {
            $match->forceFill([
                'home_score' => $match->status === 'scheduled' ? null : 0,
                'away_score' => $match->status === 'scheduled' ? null : 0,
            ])->save();

            return;
        }

        $homeScore = 0;
        $awayScore = 0;

        foreach ($logs as $index => $log) {
            if ($log->team_registration_id === $match->home_registration_id) {
                $homeScore++;
            } elseif ($log->team_registration_id === $match->away_registration_id) {
                $awayScore++;
            }

            $expectedSequence = $index + 1;

            if (
                $log->sequence !== $expectedSequence
                || $log->home_score !== $homeScore
                || $log->away_score !== $awayScore
            ) {
                $log->forceFill([
                    'sequence' => $expectedSequence,
                    'home_score' => $homeScore,
                    'away_score' => $awayScore,
                ])->save();
            }
        }

        $match->forceFill([
            'home_score' => $homeScore,
            'away_score' => $awayScore,
        ])->save();
    }
}
