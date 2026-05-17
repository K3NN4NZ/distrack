<?php

namespace App\Support;

use App\Models\Team;

final class MatchSpiritPresentation
{
    public const STATUS_NOT_COMPLETED = 'not_completed';

    public const STATUS_NO_DATA = 'no_data';

    public const STATUS_READY = 'ready';

    /**
     * @param  array{team: Team, knowledge_rules_score: ?int, fouls_body_contact_score: ?int, fair_mindedness_score: ?int, positive_attitude_score: ?int, communication_respect_score: ?int, total_score: int}|null  $home
     * @param  array{team: Team, knowledge_rules_score: ?int, fouls_body_contact_score: ?int, fair_mindedness_score: ?int, positive_attitude_score: ?int, communication_respect_score: ?int, total_score: int}|null  $away
     */
    public function __construct(
        public string $status,
        public ?array $home = null,
        public ?array $away = null,
        public ?string $winnerMessage = null,
    ) {}
}
