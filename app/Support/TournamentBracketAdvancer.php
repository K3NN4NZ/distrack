<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Controllers\Admin\TournamentController;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use Illuminate\Support\Facades\DB;

/**
 * Propagates small-tournament Day 2 knockout slots (games 37–48) from completed source matches.
 */
final class TournamentBracketAdvancer
{
    private const SLOT_PREFIX = '[[bracket-slot-';

    public static function syncFromCompletedMatches(Tournament $tournament): void
    {
        if ($tournament->registrations()->count() >= TournamentController::MINIMUM_BRACKET_TEAM_COUNT) {
            return;
        }

        DB::transaction(function () use ($tournament): void {
            self::maybeAssignRankingPathFromQuarterFinalLosers($tournament);
            self::maybeAssignSemiFinalsFromQuarterFinalWinners($tournament);
            self::propagateDownstreamSlots($tournament);
        });
    }

    /**
     * Game 41: L37 vs L40. Game 42: L38 vs L39.
     */
    private static function maybeAssignRankingPathFromQuarterFinalLosers(Tournament $tournament): void
    {
        $assignments = [
            41 => ['home' => ['37', 'loser'], 'away' => ['40', 'loser']],
            42 => ['home' => ['38', 'loser'], 'away' => ['39', 'loser']],
        ];

        foreach ($assignments as $targetGame => $slots) {
            self::applySlotAssignments($tournament, $targetGame, $slots);
        }
    }

    /**
     * Game 43: W37 vs W40. Game 44: W38 vs W39.
     */
    private static function maybeAssignSemiFinalsFromQuarterFinalWinners(Tournament $tournament): void
    {
        $assignments = [
            43 => ['home' => ['37', 'winner'], 'away' => ['40', 'winner']],
            44 => ['home' => ['38', 'winner'], 'away' => ['39', 'winner']],
        ];

        foreach ($assignments as $targetGame => $slots) {
            self::applySlotAssignments($tournament, $targetGame, $slots);
        }
    }

    /**
     * Games 45–48 from Ranking Path / Semi Final results.
     */
    private static function propagateDownstreamSlots(Tournament $tournament): void
    {
        $downstream = [
            45 => ['home' => ['41', 'winner'], 'away' => ['42', 'winner']],
            46 => ['home' => ['41', 'loser'], 'away' => ['42', 'loser']],
            47 => ['home' => ['43', 'loser'], 'away' => ['44', 'loser']],
            48 => ['home' => ['43', 'winner'], 'away' => ['44', 'winner']],
        ];

        foreach ($downstream as $targetGame => $slots) {
            self::applySlotAssignments($tournament, $targetGame, $slots);
        }
    }

    /**
     * @param  array<string, array{0: string, 1: 'winner'|'loser'}>  $slots
     */
    private static function applySlotAssignments(Tournament $tournament, int $targetGame, array $slots): void
    {
        $target = self::resolveBracketMatchForGame($tournament, $targetGame);
        if ($target === null) {
            return;
        }

        foreach ($slots as $side => [$sourceGame, $role]) {
            $sourceKey = ($role === 'winner' ? 'W' : 'L').$sourceGame;
            $source = self::resolveBracketMatchForGame($tournament, (int) $sourceGame);
            if ($source === null) {
                continue;
            }

            $registrationId = $role === 'winner'
                ? self::winnerRegistrationId($source)
                : self::loserRegistrationId($source);

            self::assignBracketSlot($target, $side, $registrationId, $sourceKey);
        }
    }

    public static function resolveBracketMatchForGame(Tournament $tournament, int $gameNumber): ?TournamentMatch
    {
        $defs = SmallDayTwoKnockoutBracket::gameDefinitions();
        $canonicalStage = $defs[$gameNumber]['stage'] ?? null;
        $marker = SmallDayTwoKnockoutBracket::marker($gameNumber);

        $candidates = TournamentMatch::query()
            ->where('tournament_id', $tournament->id)
            ->where('match_number', $gameNumber)
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates
            ->sortBy(fn (TournamentMatch $match): array => [
                self::matchHasDecisiveScoreline($match) ? 0 : 1,
                str_contains((string) ($match->notes ?? ''), $marker) ? 0 : 1,
                $canonicalStage !== null && (string) $match->stage === $canonicalStage ? 0 : 1,
                $match->id,
            ])
            ->first();
    }

    public static function winnerRegistrationId(TournamentMatch $match): ?int
    {
        if ($match->home_registration_id === null || $match->away_registration_id === null) {
            return null;
        }

        if (! self::matchHasDecisiveScoreline($match)) {
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

    public static function loserRegistrationId(TournamentMatch $match): ?int
    {
        if ($match->home_registration_id === null || $match->away_registration_id === null) {
            return null;
        }

        if (! self::matchHasDecisiveScoreline($match)) {
            return null;
        }

        if ((int) $match->home_score > (int) $match->away_score) {
            return (int) $match->away_registration_id;
        }

        if ((int) $match->away_score > (int) $match->home_score) {
            return (int) $match->home_registration_id;
        }

        return null;
    }

    public static function matchHasDecisiveScoreline(TournamentMatch $match): bool
    {
        if ($match->status !== 'completed' && $match->status !== 'live') {
            return false;
        }

        if ($match->home_score === null || $match->away_score === null) {
            return false;
        }

        return (int) $match->home_score !== (int) $match->away_score;
    }

    private static function assignBracketSlot(
        TournamentMatch $target,
        string $side,
        ?int $registrationId,
        string $sourceKey,
    ): void {
        if ($registrationId === null) {
            return;
        }

        $field = $side === 'home' ? 'home_registration_id' : 'away_registration_id';
        $current = $target->{$field} !== null ? (int) $target->{$field} : null;

        if ($current === $registrationId) {
            self::stampSlotSource($target, $side, $sourceKey);

            return;
        }

        if ($current !== null && ! self::slotMatchesSource($target, $side, $sourceKey)) {
            return;
        }

        if (! self::canReplaceTeamSlot($target, $side, $current)) {
            return;
        }

        $notes = self::stampSlotSourceNotes((string) ($target->notes ?? ''), $side, $sourceKey);

        $target->forceFill([
            $field => $registrationId,
            'notes' => $notes,
        ])->save();
    }

    private static function canReplaceTeamSlot(TournamentMatch $match, string $side, ?int $current): bool
    {
        if ($current !== null) {
            return true;
        }

        if ($match->status !== 'scheduled') {
            return false;
        }

        if ($match->home_score !== null || $match->away_score !== null) {
            return false;
        }

        return true;
    }

    private static function slotMatchesSource(TournamentMatch $match, string $side, string $sourceKey): bool
    {
        $token = self::slotToken($side, $sourceKey);

        return str_contains((string) ($match->notes ?? ''), $token);
    }

    private static function stampSlotSource(TournamentMatch $match, string $side, string $sourceKey): void
    {
        $notes = self::stampSlotSourceNotes((string) ($match->notes ?? ''), $side, $sourceKey);
        if ($notes !== (string) ($match->notes ?? '')) {
            $match->forceFill(['notes' => $notes])->save();
        }
    }

    private static function stampSlotSourceNotes(string $notes, string $side, string $sourceKey): string
    {
        $token = self::slotToken($side, $sourceKey);
        if (str_contains($notes, $token)) {
            return $notes;
        }

        return rtrim($notes).$token;
    }

    private static function slotToken(string $side, string $sourceKey): string
    {
        return self::SLOT_PREFIX.$side.'='.$sourceKey.']]';
    }
}
