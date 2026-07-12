<?php

namespace App\Models;

use App\Contracts\DefinesContentSchema;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasFlatFile;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\TimelineEntryObserver;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;

#[ObservedBy(TimelineEntryObserver::class)]
#[Fillable([
    'ulid',
    'occurred_at',
    'ends_at',
    'all_day',
    'type',
    'name',
    'organiser',
    'venue_name',
    'city',
    'country',
    'latitude',
    'longitude',
    'url',
    'description',
    'timezone',
    'meta',
])]
class Event extends Model implements DefinesContentSchema, HasMedia, Timelineable
{
    /** @use HasFactory<EventFactory> */
    use HasAttachments, HasFactory, HasFlatFile, HasTimelineEntry;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
            'meta' => 'array',
        ];
    }

    public static function schema(Blueprint $table): void
    {
        $table->id();
        $table->ulid('ulid')->nullable()->unique();
        $table->timestamp('occurred_at')->index();
        $table->timestamp('ends_at')->nullable();
        $table->string('timezone')->nullable();
        $table->string('type');
        $table->string('name');
        $table->boolean('all_day')->default(false);
        $table->string('organiser')->nullable();
        $table->string('venue_name')->nullable();
        $table->string('city')->nullable();
        $table->string('country')->nullable();
        $table->decimal('latitude', 10, 7)->nullable();
        $table->decimal('longitude', 10, 7)->nullable();
        $table->string('url')->nullable();
        $table->json('meta')->nullable();
        $table->text('description')->nullable();
        $table->timestamps();
    }

    public function slug(): string
    {
        return Str::slug($this->name);
    }

    public function flatFileType(): string
    {
        return 'event';
    }

    /**
     * @return list<string>
     */
    public function flatFilePathAttributes(): array
    {
        return ['occurred_at', 'name'];
    }

    public function flatFileBaseSlug(bool $original = false): string
    {
        $name = $original
            ? ($this->getOriginal('name') ?? $this->name)
            : $this->name;

        return Str::slug((string) $name);
    }

    public function flatFileBody(): string
    {
        return (string) ($this->getAttributes()['description'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    public function flatFileMeta(): array
    {
        $attributes = $this->getAttributes();

        return [
            'kind' => $attributes['type'] ?? null,
            'name' => $attributes['name'] ?? null,
            'ends_at' => $this->ends_at?->toIso8601String(),
            'all_day' => (bool) ($attributes['all_day'] ?? false),
            'organiser' => $attributes['organiser'] ?? null,
            'venue_name' => $attributes['venue_name'] ?? null,
            'city' => $attributes['city'] ?? null,
            'country' => $attributes['country'] ?? null,
            'latitude' => $attributes['latitude'] ?? null,
            'longitude' => $attributes['longitude'] ?? null,
            'url' => $attributes['url'] ?? null,
            'meta' => $this->meta,
        ];
    }

    /**
     * The event's day span for multi-day display, or null when it is a single
     * day. `label` is the compact card form ("2-4 Jun 2022"); `long` is the
     * spelled-out detail form ("4th to 6th June 2026").
     *
     * @return array{start: string, end: string, days: int, label: string, long: string}|null
     */
    public function dateRange(): ?array
    {
        if ($this->ends_at === null || $this->ends_at->toDateString() === $this->occurred_at->toDateString()) {
            return null;
        }

        $start = $this->occurred_at->copy();
        $end = $this->ends_at->copy();
        $days = $start->startOfDay()->diffInDays($end->startOfDay()) + 1;

        $sameMonth = $start->format('n') === $end->format('n') && $start->format('Y') === $end->format('Y');
        $sameYear = $start->format('Y') === $end->format('Y');

        $label = $sameMonth
            ? $start->format('j').'-'.$end->format('j M Y')
            : $start->format('j M').' - '.$end->format('j M Y');

        if ($sameMonth) {
            $long = $start->format('jS').' to '.$end->format('jS F Y');
        } elseif ($sameYear) {
            $long = $start->format('jS F').' to '.$end->format('jS F Y');
        } else {
            $long = $start->format('jS F Y').' to '.$end->format('jS F Y');
        }

        return [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'days' => (int) $days,
            'label' => $label,
            'long' => $long,
        ];
    }

    public function card(): array
    {
        $parts = array_filter([$this->venue_name, $this->city]);
        $photos = $this->galleryPhotos();

        return [
            'type' => 'event',
            'icon' => 'music',
            'title' => $this->name,
            'subtitle' => $parts ? implode(', ', $parts) : null,
            'occurred_at' => $this->occurred_at,
            'accent' => 'event',
            'range' => $this->dateRange(),
            'meta' => [
                'photos' => $photos,
                // Fall back to the generated static location map only when there
                // is no photo to show instead (mirrors the activity route map).
                'map' => $photos === [] ? $this->getFirstMediaUrl('map') ?: null : null,
                // Dark twin of the same map, rendered by the frontend behind a
                // `dark:` class swap so the theme decides which PNG shows.
                'mapDark' => $photos === [] ? $this->getFirstMediaUrl('map_dark') ?: null : null,
            ],
        ];
    }
}
