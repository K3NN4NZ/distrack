<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentRegistration extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = ['tournament_id', 'team_id', 'status', 'seed_number', 'bracket_code', 'bracket_rank', 'pool_name'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seed_number' => 'integer',
        ];
    }

    /**
     * Tournament being entered.
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * Team being entered.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Matches where this registration is the home side.
     */
    public function homeMatches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class, 'home_registration_id');
    }

    /**
     * Matches where this registration is the away side.
     */
    public function awayMatches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class, 'away_registration_id');
    }
}
