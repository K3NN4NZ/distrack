<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentMatch extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'matches';

    /**
     * @var list<string>
     */
    protected $fillable = ['tournament_id', 'pitch_id', 'home_registration_id', 'away_registration_id', 'stage', 'round_label', 'match_number', 'scheduled_at', 'status', 'home_score', 'away_score', 'notes'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'match_number' => 'integer',
            'scheduled_at' => 'immutable_datetime',
            'home_score' => 'integer',
            'away_score' => 'integer',
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
     * Pitch the match is scheduled on.
     */
    public function pitch(): BelongsTo
    {
        return $this->belongsTo(Pitch::class);
    }

    /**
     * Home-side tournament registration.
     */
    public function homeRegistration(): BelongsTo
    {
        return $this->belongsTo(TournamentRegistration::class, 'home_registration_id');
    }

    /**
     * Away-side tournament registration.
     */
    public function awayRegistration(): BelongsTo
    {
        return $this->belongsTo(TournamentRegistration::class, 'away_registration_id');
    }

    /**
     * Player-level statistics for the match.
     */
    public function playerStats(): HasMany
    {
        return $this->hasMany(MatchPlayerStat::class, 'match_id');
    }

    /**
     * Point-by-point scoring log for public summary views.
     */
    public function scoreLogs(): HasMany
    {
        return $this->hasMany(MatchScoreLog::class, 'match_id')->orderBy('sequence');
    }
}
