<?php

namespace App\Support;

use App\Models\Team;
use App\Models\TeamMember;

final class MatchMvpPresentation
{
    public const STATUS_NOT_COMPLETED = 'not_completed';

    public const STATUS_PENDING = 'pending';

    public const STATUS_NO_DATA = 'no_data';

    public const STATUS_READY = 'ready';

    /**
     * @param  array{player: TeamMember, team: Team, scores: int, assists: int, blocks: int, total: int}|null  $winningMvp
     * @param  array{player: TeamMember, team: Team, scores: int, assists: int, blocks: int, total: int}|null  $losingMvp
     */
    public function __construct(
        public string $status,
        public ?array $winningMvp = null,
        public ?array $losingMvp = null,
    ) {}
}
