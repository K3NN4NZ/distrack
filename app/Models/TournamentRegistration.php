<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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
     * Letter seed derived from {@see $seed_number}: 1 → A, 2 → B, … up to 26 → Z.
     */
    public static function seedNumberToLetter(?int $seedNumber): ?string
    {
        if ($seedNumber === null || $seedNumber < 1 || $seedNumber > 26) {
            return null;
        }

        return chr(64 + $seedNumber);
    }

    protected function seedLetter(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => self::seedNumberToLetter($this->seed_number),
        );
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

    /**
     * True when the tournament has at least one registration and every row has a distinct
     * {@see $seed_number} covering exactly 1 … N (N = registration count).
     */
    public static function tournamentHasCompleteUniqueSeeds(int $tournamentId): bool
    {
        /** @var Collection<int, int|null> $seeds */
        $seeds = self::query()
            ->where('tournament_id', $tournamentId)
            ->orderBy('id')
            ->pluck('seed_number');

        $n = $seeds->count();

        if ($n === 0) {
            return true;
        }

        if ($seeds->contains(null)) {
            return false;
        }

        if ($seeds->unique()->count() !== $n) {
            return false;
        }

        $sorted = $seeds->sort()->values();

        for ($i = 0; $i < $n; $i++) {
            if ((int) $sorted[$i] !== $i + 1) {
                return false;
            }
        }

        return true;
    }
}
