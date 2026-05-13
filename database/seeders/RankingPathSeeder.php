<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Pitch;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentRegistration;
use App\Support\SmallDayTwoKnockoutBracket;
use App\Support\SmallFixedRoundRobinDayOneSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates Ranking Path matches (games 41–42) for a small-tournament Day 2 bracket.
 *
 * Schema (`matches`): team slots are {@see TournamentMatch::$home_registration_id} /
 * {@see TournamentMatch::$away_registration_id} (tournament registrations — not raw {@code team_id}).
 *
 * Stable keys (matches {@see SmallDayTwoKnockoutBracket::sync}): tournament_id + match_number + stage.
 * Target stage for 41–42 is {@code placement}.
 *
 * Source Quarter Finals (37–40) may use alternate {@code stage} strings in the DB; this seeder resolves them via
 * {@see findSourceQuarterFinalMatch()} (aliases + bracket-marker fallback).
 *
 * Run: {@code php artisan db:seed --class=RankingPathSeeder}
 */
class RankingPathSeeder extends Seeder
{
    public int $tournamentId = 1;

    /**
     * Canonical stage value for games 41–42 (see {@see SmallDayTwoKnockoutBracket::gameDefinitions}).
     */
    private const STAGE_PLACEMENT = 'placement';

    public function run(): void
    {
        $tournament = Tournament::query()->find($this->tournamentId);

        if ($tournament === null) {
            $this->command?->error("Tournament {$this->tournamentId} not found.");

            return;
        }

        DB::transaction(function () use ($tournament): void {
            $this->ensureTwoPitches($tournament);

            $tournament->unsetRelation('pitches');
            $pitches = $tournament->pitches()->orderBy('sort_order')->orderBy('id')->get();
            $pitch1 = $pitches->get(0);
            $pitch2 = $pitches->get(1);

            if ($pitch1 === null || $pitch2 === null) {
                throw new \RuntimeException('Two pitches are required for Ranking Path pitch assignment.');
            }

            $defs = SmallDayTwoKnockoutBracket::gameDefinitions();
            $tz = SmallFixedRoundRobinDayOneSchedule::tournamentTimezone($tournament);

            $roundLabelRanking21 = (string) __('Ranking 21');

            $this->command?->info('Source matches (37–40) — full row snapshot + resolved loser registration ID:');

            $src37 = $this->findSourceQuarterFinalMatch($tournament->id, 37);
            $src38 = $this->findSourceQuarterFinalMatch($tournament->id, 38);
            $src39 = $this->findSourceQuarterFinalMatch($tournament->id, 39);
            $src40 = $this->findSourceQuarterFinalMatch($tournament->id, 40);

            foreach ([37 => $src37, 38 => $src38, 39 => $src39, 40 => $src40] as $num => $m) {
                $this->dumpSourceQuarterFinalMatch($num, $m);
            }

            $loser37 = $src37 !== null ? $this->getMatchLoserRegistrationId($src37) : null;
            $loser38 = $src38 !== null ? $this->getMatchLoserRegistrationId($src38) : null;
            $loser39 = $src39 !== null ? $this->getMatchLoserRegistrationId($src39) : null;
            $loser40 = $src40 !== null ? $this->getMatchLoserRegistrationId($src40) : null;

            $this->command?->line('');
            $this->command?->info('Resolved loser registration IDs: L37='.$this->fmtReg($loser37).', L38='.$this->fmtReg($loser38).', L39='.$this->fmtReg($loser39).', L40='.$this->fmtReg($loser40));

            $allLosersKnown = $loser37 !== null && $loser38 !== null && $loser39 !== null && $loser40 !== null;

            if (! $allLosersKnown) {
                $this->command?->warn('Games 41–42 will keep null registrations until all four sources have completed/live status, both scores set (non-tie), and both home/away registration IDs.');
            }

            foreach ([41, 42] as $gameNumber) {
                $def = $defs[$gameNumber] ?? null;
                if ($def === null || ($def['stage'] ?? '') !== self::STAGE_PLACEMENT) {
                    throw new \RuntimeException("Missing placement definition for game {$gameNumber}.");
                }

                $pitch = ($def['pitch_slot'] ?? 1) === 1 ? $pitch1 : $pitch2;
                $scheduledAt = CarbonImmutable::parse((string) $def['scheduled_iso'], $tz);

                $criteria = [
                    'tournament_id' => $tournament->id,
                    'match_number' => $gameNumber,
                    'stage' => self::STAGE_PLACEMENT,
                ];

                $rowExistedBefore = TournamentMatch::query()->where($criteria)->exists();
                $existing = TournamentMatch::query()->where($criteria)->first();

                $homeRegId = null;
                $awayRegId = null;

                if ($allLosersKnown) {
                    if ($gameNumber === 41) {
                        $homeRegId = $loser37;
                        $awayRegId = $loser40;
                    } else {
                        $homeRegId = $loser38;
                        $awayRegId = $loser39;
                    }
                }

                $base = [
                    'pitch_id' => $pitch->id,
                    'pitch_assigned_by' => $existing?->pitch_assigned_by,
                    'stage' => self::STAGE_PLACEMENT,
                    'round_label' => $roundLabelRanking21,
                    'scheduled_at' => $scheduledAt,
                    'notes' => SmallDayTwoKnockoutBracket::marker($gameNumber),
                ];

                $keepScoreline = $existing !== null
                    && in_array($existing->status, ['completed', 'live'], true)
                    && $existing->home_score !== null
                    && $existing->away_score !== null;

                if ($allLosersKnown) {
                    $payload = array_merge($base, [
                        'home_registration_id' => $homeRegId,
                        'away_registration_id' => $awayRegId,
                        'status' => $existing?->status ?? 'scheduled',
                        'home_score' => $keepScoreline ? $existing->home_score : null,
                        'away_score' => $keepScoreline ? $existing->away_score : null,
                    ]);

                    if ($existing !== null && $existing->status === 'scheduled' && ! $keepScoreline) {
                        $payload['status'] = 'scheduled';
                    }
                } else {
                    $payload = array_merge($base, [
                        'home_registration_id' => null,
                        'away_registration_id' => null,
                        'status' => 'scheduled',
                        'home_score' => $keepScoreline ? $existing?->home_score : null,
                        'away_score' => $keepScoreline ? $existing?->away_score : null,
                    ]);

                    if ($existing !== null && $keepScoreline) {
                        $payload['home_score'] = $existing->home_score;
                        $payload['away_score'] = $existing->away_score;
                        $payload['status'] = $existing->status;
                    }
                }

                $match = TournamentMatch::query()->updateOrCreate($criteria, $payload);

                $verb = $rowExistedBefore ? 'updated' : 'created';
                $this->command?->info("Game {$gameNumber}: {$verb} (match id {$match->id}) stage={$match->stage}.");

                if ($allLosersKnown) {
                    $this->command?->info('  Teams: resolved — home_registration_id='.$match->home_registration_id.', away_registration_id='.$match->away_registration_id.'.');
                    $this->printRegistrationTeams($match->home_registration_id, 'home');
                    $this->printRegistrationTeams($match->away_registration_id, 'away');
                } else {
                    $this->command?->warn('  Teams: placeholders only — registration IDs null (UI: L37 vs L40 / L38 vs L39).');
                }
            }
        });

        $this->command?->info('RankingPathSeeder finished.');
    }

    /**
     * Quarter Final sources may use different {@code matches.stage} values depending on how rows were created.
     *
     * Fallback: row with {@see SmallDayTwoKnockoutBracket::marker()} for that game number (same as UI bracket sync).
     */
    protected function findSourceQuarterFinalMatch(int $tournamentId, int $gameNumber): ?TournamentMatch
    {
        $aliases = SmallDayTwoKnockoutBracket::quarterFinalStageAliases();

        $found = TournamentMatch::query()
            ->where('tournament_id', $tournamentId)
            ->where('match_number', $gameNumber)
            ->whereIn('stage', $aliases)
            ->orderBy('id')
            ->first();

        if ($found !== null) {
            return $found;
        }

        $marker = SmallDayTwoKnockoutBracket::marker($gameNumber);

        return TournamentMatch::query()
            ->where('tournament_id', $tournamentId)
            ->where('match_number', $gameNumber)
            ->where('notes', 'like', '%'.$marker.'%')
            ->orderBy('id')
            ->first();
    }

    protected function dumpSourceQuarterFinalMatch(int $gameNumber, ?TournamentMatch $m): void
    {
        if ($m === null) {
            $this->command?->warn(sprintf(
                '  Game %d: NOT FOUND (tried stages [%s] + notes marker %s)',
                $gameNumber,
                implode(', ', SmallDayTwoKnockoutBracket::quarterFinalStageAliases()),
                SmallDayTwoKnockoutBracket::marker($gameNumber),
            ));

            return;
        }

        $loserId = $this->getMatchLoserRegistrationId($m);

        $this->command?->line(sprintf(
            '  Game %d: id=%s stage=%s status=%s home_reg=%s away_reg=%s home_score=%s away_score=%s → loser_registration_id=%s',
            $gameNumber,
            $m->id,
            $m->stage,
            $m->status,
            $this->fmtReg($m->home_registration_id !== null ? (int) $m->home_registration_id : null),
            $this->fmtReg($m->away_registration_id !== null ? (int) $m->away_registration_id : null),
            $m->home_score === null ? 'null' : (string) $m->home_score,
            $m->away_score === null ? 'null' : (string) $m->away_score,
            $this->fmtReg($loserId),
        ));

        if ($loserId === null) {
            $why = [];
            if (! in_array($m->status, ['completed', 'live'], true)) {
                $why[] = 'status not completed/live';
            }
            if ($m->home_score === null || $m->away_score === null) {
                $why[] = 'missing score(s)';
            }
            if ($m->home_registration_id === null || $m->away_registration_id === null) {
                $why[] = 'missing home and/or away registration id';
            }
            if ($m->home_score !== null && $m->away_score !== null && (int) $m->home_score === (int) $m->away_score) {
                $why[] = 'tied score';
            }
            $this->command?->line('    (loser not resolved: '.implode('; ', $why ?: ['unknown']).')');
        }
    }

    protected function fmtReg(?int $id): string
    {
        return $id === null ? 'null' : (string) $id;
    }

    /**
     * Find a match by tournament, game number, and exact stage (legacy helper).
     */
    protected function findMatchByGameNumber(int $tournamentId, int $gameNumber, string $stage): ?TournamentMatch
    {
        return TournamentMatch::query()
            ->where('tournament_id', $tournamentId)
            ->where('match_number', $gameNumber)
            ->where('stage', $stage)
            ->first();
    }

    /**
     * Winner registration id — requires decisive scoreline and both sides assigned.
     */
    protected function getMatchWinnerRegistrationId(TournamentMatch $match): ?int
    {
        if ($match->home_registration_id === null || $match->away_registration_id === null) {
            return null;
        }

        if (! in_array($match->status, ['completed', 'live'], true)
            || $match->home_score === null
            || $match->away_score === null
            || (int) $match->home_score === (int) $match->away_score) {
            return null;
        }

        if ((int) $match->home_score > (int) $match->away_score) {
            return (int) $match->home_registration_id;
        }

        if ((int) $match->away_score > (int) $match->home_score) {
            return (int) $match->away_registration_id;
        }

        return null;
    }

    /**
     * Loser registration id — same rules as {@see SmallDayTwoKnockoutBracket::loserRegistrationId}.
     */
    protected function getMatchLoserRegistrationId(TournamentMatch $match): ?int
    {
        if ($match->home_registration_id === null || $match->away_registration_id === null) {
            return null;
        }

        if (! in_array($match->status, ['completed', 'live'], true)
            || $match->home_score === null
            || $match->away_score === null
            || (int) $match->home_score === (int) $match->away_score) {
            return null;
        }

        if ((int) $match->home_score > (int) $match->away_score) {
            return (int) $match->away_registration_id;
        }

        return (int) $match->home_registration_id;
    }

    protected function getMatchWinnerTeamId(TournamentMatch $match): ?int
    {
        $regId = $this->getMatchWinnerRegistrationId($match);

        return $regId !== null ? TournamentRegistration::query()->whereKey($regId)->value('team_id') : null;
    }

    protected function getMatchLoserTeamId(TournamentMatch $match): ?int
    {
        $regId = $this->getMatchLoserRegistrationId($match);

        return $regId !== null ? TournamentRegistration::query()->whereKey($regId)->value('team_id') : null;
    }

    protected function printRegistrationTeams(?int $registrationId, string $side): void
    {
        if ($registrationId === null) {
            return;
        }

        $reg = TournamentRegistration::query()->with('team:id,name')->find($registrationId);
        $teamName = $reg?->team?->name ?? '(unknown team)';
        $this->command?->line("  {$side}: registration {$registrationId} → team: {$teamName}");
    }

    private function ensureTwoPitches(Tournament $tournament): void
    {
        while ($tournament->pitches()->count() < 2) {
            $nextOrder = (int) ($tournament->pitches()->max('sort_order') ?? 0) + 1;

            Pitch::query()->create([
                'tournament_id' => $tournament->id,
                'name' => 'Pitch '.$nextOrder,
                'sort_order' => $nextOrder,
                'scorekeeper_user_id' => null,
                'is_active' => true,
            ]);
        }
    }
}
