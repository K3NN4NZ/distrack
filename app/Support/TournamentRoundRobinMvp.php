<?php

namespace App\Support;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use Illuminate\Support\Collection;

final class TournamentRoundRobinMvp
{
    /**
     * @param  array{search?: string, team?: int|null, gender?: string|null}  $filters
     */
    public static function presentationForTournament(Tournament $tournament, array $filters = []): TournamentRoundRobinMvpPresentation
    {
        $completedRoundRobinMatches = RoundRobinStage::completedMatchesForTournament($tournament);

        if ($completedRoundRobinMatches->isEmpty()) {
            return new TournamentRoundRobinMvpPresentation(
                TournamentRoundRobinMvpPresentation::STATUS_NO_COMPLETED_MATCHES,
            );
        }

        $registrationByTeamId = $tournament->registrations->keyBy('team_id');
        $overall = self::leaderboard($completedRoundRobinMatches, $registrationByTeamId);

        if ($overall->isEmpty()) {
            return new TournamentRoundRobinMvpPresentation(
                TournamentRoundRobinMvpPresentation::STATUS_NO_STATS,
            );
        }

        $search = trim((string) ($filters['search'] ?? ''));
        $teamRegistrationId = isset($filters['team']) && $filters['team'] !== null && $filters['team'] !== ''
            ? (int) $filters['team']
            : null;
        $genderFilter = self::normalizeGenderFilter($filters['gender'] ?? null);

        $filteredOverall = self::applyFilters($overall, $search, $teamRegistrationId, $genderFilter);
        $male = $filteredOverall->filter(fn (array $row): bool => $row['gender'] === 'men')->values();
        $female = $filteredOverall->filter(fn (array $row): bool => $row['gender'] === 'women')->values();
        $showSplitSections = in_array($genderFilter, ['all', 'mix'], true);

        return new TournamentRoundRobinMvpPresentation(
            TournamentRoundRobinMvpPresentation::STATUS_READY,
            overall: self::withRanks($filteredOverall),
            male: self::withRanks($male),
            female: self::withRanks($female),
            showMaleSection: $showSplitSections && $male->isNotEmpty(),
            showFemaleSection: $showSplitSections && $female->isNotEmpty(),
            hasUnfilteredResults: $overall->isNotEmpty(),
        );
    }

    /**
     * @param  Collection<int, TournamentMatch>  $completedRoundRobinMatches
     * @param  Collection<int, \App\Models\TournamentRegistration>  $registrationByTeamId
     * @return Collection<int, array{member: TeamMember, team: Team, registration_id: int|null, display_name: string, gender: string, scores: int, assists: int, blocks: int, total: int}>
     */
    public static function leaderboard(Collection $completedRoundRobinMatches, Collection $registrationByTeamId): Collection
    {
        return $completedRoundRobinMatches
            ->flatMap(fn ($match) => $match->playerStats)
            ->groupBy('team_member_id')
            ->map(function (Collection $stats) use ($registrationByTeamId): ?array {
                $member = $stats->first()?->teamMember;
                $team = $member?->team;

                if ($member === null || $team === null) {
                    return null;
                }

                $scores = (int) $stats->sum('goals');
                $assists = (int) $stats->sum('assists');
                $blocks = (int) $stats->sum('blocks');
                $total = $scores + $assists + $blocks;

                if ($total <= 0) {
                    return null;
                }

                return [
                    'member' => $member,
                    'team' => $team,
                    'registration_id' => $registrationByTeamId->get($team->id)?->id,
                    'display_name' => $member->name,
                    'gender' => self::normalizeGender($member->gender),
                    'scores' => $scores,
                    'assists' => $assists,
                    'blocks' => $blocks,
                    'total' => $total,
                ];
            })
            ->filter()
            ->sortBy(fn (array $row): array => [
                -$row['total'],
                -$row['scores'],
                -$row['assists'],
                -$row['blocks'],
                strtolower($row['display_name']),
            ])
            ->values();
    }

    /**
     * @param  Collection<int, array{member: TeamMember, team: Team, registration_id: int|null, display_name: string, gender: string, scores: int, assists: int, blocks: int, total: int, rank?: int}>  $rows
     * @return Collection<int, array{member: TeamMember, team: Team, registration_id: int|null, display_name: string, gender: string, scores: int, assists: int, blocks: int, total: int, rank?: int}>
     */
    public static function applyFilters(
        Collection $rows,
        string $search,
        ?int $teamRegistrationId,
        string $genderFilter = 'all',
    ): Collection {
        $needle = strtolower(trim($search));

        return $rows
            ->filter(function (array $row) use ($needle, $teamRegistrationId, $genderFilter): bool {
                if ($teamRegistrationId !== null && (int) ($row['registration_id'] ?? 0) !== $teamRegistrationId) {
                    return false;
                }

                if ($needle !== '' && ! str_contains(strtolower($row['display_name']), $needle)) {
                    return false;
                }

                if ($genderFilter === 'men' && $row['gender'] !== 'men') {
                    return false;
                }

                if ($genderFilter === 'women' && $row['gender'] !== 'women') {
                    return false;
                }

                return true;
            })
            ->values();
    }

    private static function normalizeGenderFilter(?string $gender): string
    {
        $normalized = strtolower(trim((string) $gender));

        return in_array($normalized, ['mix', 'men', 'women'], true) ? $normalized : 'all';
    }

    /**
     * @param  Collection<int, array{member: TeamMember, team: Team, display_name: string, gender: string, scores: int, assists: int, blocks: int, total: int}>  $rows
     * @return Collection<int, array{member: TeamMember, team: Team, display_name: string, gender: string, scores: int, assists: int, blocks: int, total: int, rank: int}>
     */
    private static function withRanks(Collection $rows): Collection
    {
        return $rows->values()->map(function (array $row, int $index): array {
            $row['rank'] = $index + 1;

            return $row;
        });
    }

    private static function normalizeGender(?string $gender): string
    {
        return match (strtolower(trim((string) $gender))) {
            'male', 'm', 'man', 'men' => 'men',
            'female', 'f', 'woman', 'women' => 'women',
            default => 'other',
        };
    }
}
