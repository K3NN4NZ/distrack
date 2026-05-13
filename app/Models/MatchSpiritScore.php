<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchSpiritScore extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'tournament_id',
        'match_id',
        'scoring_team_id',
        'scored_team_id',
        'spirit_captain_id',
        'knowledge_rules_score',
        'fouls_body_contact_score',
        'fair_mindedness_score',
        'positive_attitude_score',
        'communication_respect_score',
        'total_score',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'knowledge_rules_score' => 'integer',
            'fouls_body_contact_score' => 'integer',
            'fair_mindedness_score' => 'integer',
            'positive_attitude_score' => 'integer',
            'communication_respect_score' => 'integer',
            'total_score' => 'integer',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(TournamentMatch::class, 'match_id');
    }

    public function scoringTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'scoring_team_id');
    }

    public function scoredTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'scored_team_id');
    }

    public function spiritCaptain(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'spirit_captain_id');
    }
}
