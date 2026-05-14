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
     * Embedded in `notes` for crossover rows created by automatic crossover generation,
     * so they can be replaced idempotently without touching manual crossover games.
     */
    public const CROSSOVER_AUTO_GENERATED_MARKER = '[[crossover:auto-generated]]';

    /**
     * Embedded in `notes` for quarter-final rows created by “generate from pooling”, so they can be replaced idempotently.
     */
    public const QUARTER_FINAL_AUTO_GENERATED_MARKER = '[[quarterfinal:auto-generated]]';

    /**
     * Persisted {@code matches.status} values used across scheduling and scoring.
     */
    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_LIVE = 'live';

    public const STATUS_COMPLETED = 'completed';

    /**
     * @var string
     */
    protected $table = 'matches';

    /**
     * @var list<string>
     */
    protected $fillable = ['tournament_id', 'pitch_id', 'pitch_assigned_by', 'home_registration_id', 'away_registration_id', 'stage', 'round_label', 'match_number', 'scheduled_at', 'status', 'home_score', 'away_score', 'notes'];

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
     * Whether this match is completed for admin scoring exports and final score UX.
     */
    public function isCompletedMatchStatus(): bool
    {
        return strtolower((string) $this->status) === self::STATUS_COMPLETED;
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
     * User who last set or changed the pitch assignment (crossover / manual setup).
     */
    public function pitchAssignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pitch_assigned_by');
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

    /**
     * Spirit-of-the-game scoresheets (one row per team rated in this match).
     */
    public function spiritScores(): HasMany
    {
        return $this->hasMany(MatchSpiritScore::class, 'match_id');
    }

    /**
     * Next global game number for this tournament (highest existing match_number + 1).
     */
    public static function nextMatchNumberForTournament(int $tournamentId): int
    {
        $max = static::query()->where('tournament_id', $tournamentId)->max('match_number');

        return (int) ($max ?? 0) + 1;
    }
}
