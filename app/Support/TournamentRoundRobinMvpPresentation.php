<?php

namespace App\Support;

use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Support\Collection;

final class TournamentRoundRobinMvpPresentation
{
    public const STATUS_NO_COMPLETED_MATCHES = 'no_completed_matches';

    public const STATUS_NO_STATS = 'no_stats';

    public const STATUS_READY = 'ready';

    /**
     * @param  Collection<int, array{member: TeamMember, team: Team, display_name: string, gender: string, scores: int, assists: int, blocks: int, total: int, rank: int}>  $overall
     * @param  Collection<int, array{member: TeamMember, team: Team, display_name: string, gender: string, scores: int, assists: int, blocks: int, total: int, rank: int}>  $male
     * @param  Collection<int, array{member: TeamMember, team: Team, display_name: string, gender: string, scores: int, assists: int, blocks: int, total: int, rank: int}>  $female
     */
    public function __construct(
        public string $status,
        public Collection $overall = new Collection,
        public Collection $male = new Collection,
        public Collection $female = new Collection,
        public bool $showMaleSection = false,
        public bool $showFemaleSection = false,
        public bool $hasUnfilteredResults = false,
    ) {}
}
