<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeamMember extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = ['team_id', 'user_id', 'name', 'nickname', 'gender', 'age', 'address', 'role'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'age' => 'integer',
        ];
    }

    /**
     * Owning team.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Linked user when a roster member maps to a platform user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Match statistics for this roster member.
     */
    public function matchStats(): HasMany
    {
        return $this->hasMany(MatchPlayerStat::class);
    }
}
