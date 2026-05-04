<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchPlayerStat extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = ['match_id', 'team_member_id', 'goals', 'assists', 'blocks'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'goals' => 'integer',
            'assists' => 'integer',
            'blocks' => 'integer',
        ];
    }

    /**
     * Match this stat belongs to.
     */
    public function match(): BelongsTo
    {
        return $this->belongsTo(TournamentMatch::class, 'match_id');
    }

    /**
     * Team member this stat belongs to.
     */
    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }
}
