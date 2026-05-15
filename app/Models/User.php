<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_CAPTAIN = 'captain';

    public const ROLE_SCOREKEEPER = 'scorekeeper';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'email', 'role', 'password'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * Determine whether the user can access admin-only tools.
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Determine whether the user is a captain account.
     */
    public function isCaptain(): bool
    {
        return $this->role === self::ROLE_CAPTAIN;
    }

    /**
     * Determine whether the user is a scorekeeper account.
     */
    public function isScorekeeper(): bool
    {
        return $this->role === self::ROLE_SCOREKEEPER;
    }

    /**
     * Determine whether the user can access tournament scoring pages.
     */
    public function canAccessScoring(): bool
    {
        return $this->isAdmin() || $this->isScorekeeper();
    }

    /**
     * Determine whether the user can enter match scores.
     */
    public function canEnterScores(): bool
    {
        return $this->isAdmin() || $this->isScorekeeper();
    }

    /**
     * Teams owned by this user.
     */
    public function ownedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'owner_user_id');
    }

    /**
     * Team member records linked to this user.
     */
    public function teamMemberEntries(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    /**
     * Tournaments created by this user.
     */
    public function createdTournaments(): HasMany
    {
        return $this->hasMany(Tournament::class, 'created_by');
    }

    /**
     * Playing fields this scorekeeper is assigned to manage.
     */
    public function assignedScorekeeperPitches(): HasMany
    {
        return $this->hasMany(Pitch::class, 'scorekeeper_user_id');
    }
}
