<?php

namespace App\Models;

use App\Models\Concerns\NormalizesUtf8Attributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeamMember extends Model
{
    use HasFactory;
    use NormalizesUtf8Attributes;

    /**
     * @var list<string>
     */
    protected $fillable = ['team_id', 'user_id', 'name', 'nickname', 'gender', 'jersey_number', 'email', 'contact', 'age', 'address', 'role'];

    /**
     * @var list<string>
     */
    protected array $utf8Attributes = ['name', 'nickname', 'address', 'contact', 'email'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'age' => 'integer',
            'jersey_number' => 'integer',
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

    /**
     * Whether this roster row is referenced by scoring or spirit records.
     */
    public function isLinkedToMatchRecords(): bool
    {
        if ($this->matchStats()->exists()) {
            return true;
        }

        if (MatchScoreLog::query()
            ->where(function ($query): void {
                $query->where('team_member_id', $this->id)
                    ->orWhere('assist_team_member_id', $this->id);
            })
            ->exists()
        ) {
            return true;
        }

        return MatchSpiritScore::query()->where('spirit_captain_id', $this->id)->exists();
    }
}
