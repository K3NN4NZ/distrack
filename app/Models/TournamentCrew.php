<?php

namespace App\Models;

use App\Models\Concerns\NormalizesUtf8Attributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TournamentCrew extends Model
{
    use HasFactory;
    use NormalizesUtf8Attributes;

    /**
     * @var list<string>
     */
    protected $fillable = ['tournament_id', 'category', 'title', 'name', 'photo_path', 'sort_order'];

    /**
     * @var list<string>
     */
    protected array $utf8Attributes = ['category', 'title', 'name'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * Tournament that owns the crew entry.
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * Resolved avatar URL for uploaded files or remote paths.
     */
    public function photoUrl(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        if (Str::startsWith($this->photo_path, ['http://', 'https://'])) {
            return $this->photo_path;
        }

        return Storage::disk('public')->url($this->photo_path);
    }

    /**
     * Crew initials for avatar fallbacks.
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }
}
