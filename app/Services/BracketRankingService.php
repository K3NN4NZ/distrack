<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Support\BracketCodes;
use Illuminate\Support\Facades\DB;

class BracketRankingService
{
    /**
     * Preview round-robin standings per bracket with proposed A1/A2/… ranks.
     *
     * @return array{
     *     brackets: list<array{
     *         code: string,
     *         letter: string,
     *         standings: list<array<string, mixed>>,
     *         completed_matches: int,
     *         registered_teams: int,
     *     }>,
     *     empty_reason: string|null,
     * }
     */
    public function preview(Tournament $tournament): array
    {
        $tournament->loadMissing([
            'registrations.team',
            'matches' => fn ($q) => $q
                ->with(['homeRegistration', 'awayRegistration'])
                ->orderByRaw('case when scheduled_at is null then 1 else 0 end')
                ->orderBy('scheduled_at')
                ->orderBy('match_number'),
        ]);

        $groupedRegs = $tournament->registrations
            ->filter(fn (TournamentRegistration $r): bool => filled(BracketCodes::normalize($r->bracket_code ?? null)))
            ->groupBy(fn (TournamentRegistration $r): string => BracketCodes::normalize($r->bracket_code) ?? '')
            ->sortKeys();

        if ($groupedRegs->isEmpty()) {
            return [
                'brackets' => [],
                'empty_reason' => 'Assign teams to brackets under Seeding first.',
            ];
        }

        $roundRobinMatches = $tournament->matches
            ->filter(fn ($m): bool => $m->stage === 'round_robin')
            ->values();

        $brackets = [];

        foreach ($groupedRegs as $code => $registrations) {
            $registrations = $registrations->values();
            $letter = BracketCodes::rankPrefixFromCode($code);

            $registrationIds = $registrations->pluck('id')->all();

            $rows = $registrations
                ->mapWithKeys(fn (TournamentRegistration $registration): array => [
                    $registration->id => [
                        'registration_id' => $registration->id,
                        'team_name' => $registration->team?->name ?? 'Team',
                        'played' => 0,
                        'wins' => 0,
                        'losses' => 0,
                        'ties' => 0,
                        'points' => 0,
                        'goals_for' => 0,
                        'goals_against' => 0,
                        'seed_number' => $registration->seed_number,
                    ],
                ])
                ->all();

            $relevantMatches = $roundRobinMatches
                ->filter(function ($match) use ($code, $registrationIds): bool {
                    $homeId = $match->home_registration_id;
                    $awayId = $match->away_registration_id;
                    if (! $homeId || ! $awayId) {
                        return false;
                    }
                    $homeCode = BracketCodes::normalize($match->homeRegistration?->bracket_code);
                    $awayCode = BracketCodes::normalize($match->awayRegistration?->bracket_code);
                    if ($homeCode !== $code || $awayCode !== $code) {
                        return false;
                    }
                    if (! in_array($homeId, $registrationIds, true) || ! in_array($awayId, $registrationIds, true)) {
                        return false;
                    }

                    return true;
                })
                ->values();

            $completedMatches = $relevantMatches
                ->filter(fn ($m): bool => $m->status === 'completed'
                    && $m->home_score !== null
                    && $m->away_score !== null)
                ->values();

            foreach ($completedMatches as $match) {
                $homeId = (int) $match->home_registration_id;
                $awayId = (int) $match->away_registration_id;

                if (! isset($rows[$homeId], $rows[$awayId])) {
                    continue;
                }

                $rows[$homeId]['played']++;
                $rows[$awayId]['played']++;

                $rows[$homeId]['goals_for'] += (int) $match->home_score;
                $rows[$homeId]['goals_against'] += (int) $match->away_score;
                $rows[$awayId]['goals_for'] += (int) $match->away_score;
                $rows[$awayId]['goals_against'] += (int) $match->home_score;

                if ($match->home_score > $match->away_score) {
                    $rows[$homeId]['wins']++;
                    $rows[$homeId]['points'] += 3;
                    $rows[$awayId]['losses']++;
                } elseif ($match->home_score < $match->away_score) {
                    $rows[$awayId]['wins']++;
                    $rows[$awayId]['points'] += 3;
                    $rows[$homeId]['losses']++;
                } else {
                    $rows[$homeId]['ties']++;
                    $rows[$awayId]['ties']++;
                    $rows[$homeId]['points']++;
                    $rows[$awayId]['points']++;
                }
            }

            $standings = collect($rows)
                ->map(function (array $row): array {
                    $row['goal_difference'] = $row['goals_for'] - $row['goals_against'];

                    return $row;
                })
                ->sort(function (array $left, array $right): int {
                    foreach (['points', 'goal_difference', 'goals_for', 'wins'] as $metric) {
                        $c = $right[$metric] <=> $left[$metric];
                        if ($c !== 0) {
                            return $c;
                        }
                    }

                    return ($left['seed_number'] ?? PHP_INT_MAX) <=> ($right['seed_number'] ?? PHP_INT_MAX);
                })
                ->values();

            $position = 0;
            $standings = $standings
                ->map(function (array $row) use ($letter, &$position): array {
                    $position++;
                    $row['proposed_rank'] = $letter.$position;

                    return $row;
                })
                ->all();

            $brackets[] = [
                'code' => $code,
                'letter' => $letter,
                'standings' => $standings,
                'completed_matches' => $completedMatches->count(),
                'registered_teams' => $registrations->count(),
            ];
        }

        return [
            'brackets' => $brackets,
            'empty_reason' => null,
        ];
    }

    /**
     * Persist proposed ranks onto each registration.
     */
    public function apply(Tournament $tournament): int
    {
        $preview = $this->preview($tournament);
        $updated = 0;

        DB::transaction(function () use ($preview, &$updated): void {
            foreach ($preview['brackets'] as $bracket) {
                foreach ($bracket['standings'] as $row) {
                    $registration = TournamentRegistration::query()->find($row['registration_id']);
                    if (! $registration) {
                        continue;
                    }
                    $proposed = $row['proposed_rank'];
                    if ($registration->bracket_rank !== $proposed) {
                        $registration->update(['bracket_rank' => $proposed]);
                        $updated++;
                    }
                }
            }
        });

        return $updated;
    }
}
