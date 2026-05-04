<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tournament extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = ['created_by', 'name', 'slug', 'venue', 'description', 'registration_deadline', 'starts_at', 'ends_at', 'status', 'country_name', 'city', 'province', 'barangay', 'timezone', 'venue_google_map_link', 'thumbnail_path', 'event_type', 'division', 'surface', 'info_items', 'organizer_items', 'link_items', 'is_public'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'registration_deadline' => 'immutable_datetime',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'info_items' => 'array',
            'organizer_items' => 'array',
            'link_items' => 'array',
            'is_public' => 'boolean',
        ];
    }

    /**
     * Scope a query to tournaments intended for the public board.
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /**
     * Order tournaments with upcoming dated events first.
     */
    public function scopeUpcomingFirst(Builder $query): Builder
    {
        return $query
            ->orderByRaw('case when starts_at is null then 1 else 0 end')
            ->orderBy('starts_at')
            ->orderBy('name');
    }

    /**
     * Admin who created the tournament.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Pitches assigned to the tournament.
     */
    public function pitches(): HasMany
    {
        return $this->hasMany(Pitch::class)->orderBy('sort_order');
    }

    /**
     * Team registrations tied to the tournament.
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(TournamentRegistration::class);
    }

    /**
     * Scheduled matches for the tournament.
     */
    public function matches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class);
    }

    /**
     * Public crew directory entries assigned to the tournament.
     */
    public function crewMembers(): HasMany
    {
        return $this->hasMany(TournamentCrew::class)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * Public-facing metadata chips for cards and detail pages.
     *
     * @return list<string>
     */
    public function publicTags(): array
    {
        return array_values(array_filter([
            $this->division,
            $this->surface,
            $this->event_type,
        ]));
    }

    /**
     * Human-readable tournament location label.
     */
    public function locationLabel(): string
    {
        return collect([$this->venue, $this->addressLabel()])
            ->filter()
            ->implode(', ');
    }

    /**
     * Human-readable administrative address label.
     */
    public function addressLabel(): string
    {
        return collect([$this->barangay, $this->city, $this->province, $this->country_name])
            ->filter()
            ->implode(', ');
    }

    /**
     * Dynamic public information rows configured for the tournament.
     *
     * @return list<array{label: string, value: string}>
     */
    public function additionalInfoItems(): array
    {
        return $this->normalizeLabelValueItems($this->info_items ?? []);
    }

    /**
     * Public-facing organizer metadata rows configured for the tournament.
     *
     * @return list<array{label: string, value: string}>
     */
    public function organizerInfoItems(): array
    {
        return collect([
            [
                'label' => 'Organizer',
                'value' => $this->creator?->name ?: 'Organizer not listed',
            ],
            [
                'label' => 'Status',
                'value' => str($this->status ?: 'draft')->headline()->toString(),
            ],
        ])
            ->merge($this->normalizeLabelValueItems($this->organizer_items ?? []))
            ->values()
            ->all();
    }

    /**
     * Public-facing location and schedule rows configured for the tournament.
     *
     * @return list<array{label: string, value: string}>
     */
    public function locationScheduleItems(): array
    {
        return collect([
            ['label' => 'Venue', 'value' => $this->venue ?: 'Venue to be announced'],
            ['label' => 'Barangay', 'value' => $this->barangay],
            ['label' => 'City / Municipality', 'value' => $this->city],
            ['label' => 'Province', 'value' => $this->province],
            ['label' => 'Country', 'value' => $this->country_name ?: 'Philippines'],
            ['label' => 'Date window', 'value' => $this->dateRangeLabel()],
            ['label' => 'Timezone', 'value' => $this->timezone ?: 'Not listed'],
            [
                'label' => 'Registration deadline',
                'value' => $this->registration_deadline?->format('M j, Y g:i A') ?: 'Not published',
            ],
        ])
            ->filter(fn (array $row): bool => filled($row['value']))
            ->values()
            ->all();
    }

    /**
     * Public-facing links configured for the tournament.
     *
     * @return list<array{label: string, href: string}>
     */
    public function publicLinkItems(): array
    {
        return collect([
            $this->venue_google_map_link
                ? ['label' => 'Venue map', 'href' => $this->venue_google_map_link]
                : null,
        ])
            ->filter()
            ->merge($this->normalizeLabelValueItems($this->link_items ?? [], 'href'))
            ->unique(fn (array $item): string => strtolower($item['href']))
            ->values()
            ->all();
    }

    /**
     * Public-facing accordion data for the tournament info tab.
     *
     * @return list<array<string, mixed>>
     */
    public function publicProfileSections(): array
    {
        return [
            [
                'key' => 'about',
                'title' => 'About Event',
                'type' => 'text',
                'content' => $this->aboutEventText(),
            ],
            [
                'key' => 'organizer',
                'title' => 'Organizer',
                'type' => 'rows',
                'items' => $this->organizerInfoItems(),
            ],
            [
                'key' => 'location',
                'title' => 'Location & Schedule',
                'type' => 'rows',
                'items' => $this->locationScheduleItems(),
            ],
            [
                'key' => 'links',
                'title' => 'Public Links',
                'type' => 'links',
                'items' => $this->publicLinkItems(),
                'empty_message' => 'No public links have been published yet.',
            ],
            [
                'key' => 'additional',
                'title' => 'Additional Information',
                'type' => 'rows',
                'items' => $this->additionalInfoItems(),
                'empty_message' => 'No additional information has been published yet.',
            ],
        ];
    }

    /**
     * Dynamic about text for the public info tab.
     */
    public function aboutEventText(): string
    {
        if (filled($this->description)) {
            return $this->description;
        }

        $summary = collect([
            $this->event_type ? str($this->event_type)->headline()->toString() : 'Tournament',
            $this->division ? "for the {$this->division} division" : null,
            $this->surface ? "on {$this->surface} surfaces" : null,
        ])->filter()->implode(' ');

        return collect([
            $summary !== ''
                ? "{$summary} is published on the DISCTRACK board."
                : 'This tournament is published on the DISCTRACK board.',
            $this->locationLabel() ? "Venue: {$this->locationLabel()}." : null,
            ($this->starts_at || $this->ends_at) ? "Schedule: {$this->dateRangeLabel()}." : null,
            'A longer event description has not been added yet.',
        ])->filter()->implode(' ');
    }

    /**
     * Human-readable tournament date range.
     */
    public function dateRangeLabel(): string
    {
        if (! $this->starts_at && ! $this->ends_at) {
            return 'Schedule to be announced';
        }

        $start = $this->starts_at ?? $this->ends_at;
        $end = $this->ends_at ?? $this->starts_at;

        if ($start && $end && $start->isSameDay($end)) {
            return $start->format('M j, Y');
        }

        if ($start && $end && $start->year === $end->year) {
            return $start->format('M j').' - '.$end->format('M j, Y');
        }

        return $start->format('M j, Y').' - '.$end->format('M j, Y');
    }

    /**
     * Normalize dynamic public metadata rows before rendering.
     *
     * @return list<array<string, string>>
     */
    protected function normalizeLabelValueItems(?array $items, string $valueKey = 'value'): array
    {
        return collect($items ?? [])
            ->filter(fn ($item): bool => is_array($item))
            ->map(function (array $item) use ($valueKey): ?array {
                $label = trim((string) ($item['label'] ?? ''));
                $value = trim((string) ($item[$valueKey] ?? ''));

                if ($label === '' || $value === '') {
                    return null;
                }

                return [
                    'label' => $label,
                    $valueKey => $value,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
