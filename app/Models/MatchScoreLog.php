<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchScoreLog extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = ['match_id', 'sequence', 'team_registration_id', 'team_member_id', 'assist_team_member_id', 'minute', 'home_score', 'away_score'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'minute' => 'integer',
            'home_score' => 'integer',
            'away_score' => 'integer',
        ];
    }

    /**
     * Match that owns the score log.
     */
    public function match(): BelongsTo
    {
        return $this->belongsTo(TournamentMatch::class, 'match_id');
    }

    /**
     * Registration credited for the score.
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(TournamentRegistration::class, 'team_registration_id');
    }

    /**
     * Scoring player.
     */
    public function scorer(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'team_member_id');
    }

    /**
     * Assisting player.
     */
    public function assister(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'assist_team_member_id');
    }
}
