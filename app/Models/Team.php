<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Team extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = ['owner_user_id', 'name', 'address', 'city', 'province', 'country_name', 'logo_path', 'status'];

    /**
     * Team owner.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * Roster members for the team.
     */
    public function members(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    /**
     * Tournament registrations tied to this team.
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(TournamentRegistration::class);
    }

    /**
     * Determine whether the team already has tournament-linked records.
     */
    public function hasRecordedActivity(): bool
    {
        return $this->registrations()->exists()
            || $this->members()->whereHas('matchStats')->exists();
    }

    /**
     * Remove an uploaded logo file when the team is being deleted.
     */
    public function deleteStoredLogo(): void
    {
        if (! $this->logo_path || Str::startsWith($this->logo_path, ['http://', 'https://'])) {
            return;
        }

        Storage::disk('public')->delete($this->logo_path);
    }

    /**
     * Resolved logo URL for uploaded files or remote paths.
     */
    public function logoUrl(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        if (Str::startsWith($this->logo_path, ['http://', 'https://'])) {
            return $this->logo_path;
        }

        return Storage::disk('public')->url($this->logo_path);
    }

    /**
     * Public-facing team location label.
     */
    public function locationLabel(): ?string
    {
        $parts = array_values(array_filter([
            $this->city,
            $this->province,
            $this->country_name,
        ]));

        if ($parts !== []) {
            return implode(', ', $parts);
        }

        return $this->address ?: null;
    }
}
