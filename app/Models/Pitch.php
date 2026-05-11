<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pitch extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = ['tournament_id', 'scorekeeper_user_id', 'name', 'location', 'sort_order', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Parent tournament.
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * Scorekeeper assigned to manage games on this pitch.
     */
    public function assignedScorekeeper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scorekeeper_user_id');
    }

    /**
     * Matches played on this pitch.
     */
    public function matches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class);
    }

    /**
     * Whether a scorekeeper user may enter scores for games on this pitch.
     * Unassigned pitches are open to any scorekeeper; assigned pitches are exclusive.
     */
    public function allowsScorekeeperUserId(int $userId): bool
    {
        if ($this->scorekeeper_user_id === null) {
            return true;
        }

        return (int) $this->scorekeeper_user_id === $userId;
    }
}
