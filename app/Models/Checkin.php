<?php

namespace App\Models;

use App\Contracts\DefinesContentSchema;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasFlatFile;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\TimelineEntryObserver;
use Database\Factories\CheckinFactory;
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
    'venue_name',
    'category',
    'address',
    'city',
    'county',
    'country',
    'latitude',
    'longitude',
    'description',
    'is_mayor',
    'source',
    'source_id',
    'timezone',
])]
class Checkin extends Model implements DefinesContentSchema, HasMedia, Timelineable
{
    /** @use HasFactory<CheckinFactory> */
    use HasAttachments, HasFactory, HasFlatFile, HasTimelineEntry;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'is_mayor' => 'boolean',
        ];
    }

    public static function schema(Blueprint $table): void
    {
        $table->id();
        $table->ulid('ulid')->nullable()->unique();
        $table->timestamp('occurred_at')->index();
        $table->string('timezone')->nullable();
        $table->string('venue_name');
        $table->string('category')->nullable();
        $table->string('address')->nullable();
        $table->string('city')->nullable();
        $table->string('county')->nullable();
        $table->string('country')->nullable();
        $table->decimal('latitude', 10, 7)->nullable();
        $table->decimal('longitude', 10, 7)->nullable();
        $table->boolean('is_mayor')->default(false);
        $table->string('source')->nullable();
        $table->string('source_id')->nullable();
        $table->text('description')->nullable();
        $table->timestamps();
    }

    public function getPlatformUrlAttribute(): ?string
    {
        if ($this->source === 'swarm' && $this->source_id) {
            return "https://www.swarmapp.com/checkin/{$this->source_id}";
        }

        return null;
    }

    public function slug(): string
    {
        return Str::slug($this->venue_name);
    }

    public function flatFileType(): string
    {
        return 'checkin';
    }

    /**
     * @return list<string>
     */
    public function flatFilePathAttributes(): array
    {
        return ['occurred_at', 'venue_name'];
    }

    public function flatFileBaseSlug(bool $original = false): string
    {
        $venue = $original
            ? ($this->getOriginal('venue_name') ?? $this->venue_name)
            : $this->venue_name;

        return Str::slug((string) $venue);
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
            'venue_name' => $attributes['venue_name'] ?? null,
            'category' => $attributes['category'] ?? null,
            'address' => $attributes['address'] ?? null,
            'city' => $attributes['city'] ?? null,
            'county' => $attributes['county'] ?? null,
            'country' => $attributes['country'] ?? null,
            'latitude' => $attributes['latitude'] ?? null,
            'longitude' => $attributes['longitude'] ?? null,
            'is_mayor' => (bool) ($attributes['is_mayor'] ?? false),
            'source' => $attributes['source'] ?? null,
            'source_id' => $attributes['source_id'] ?? null,
        ];
    }

    public function card(): array
    {
        $parts = array_filter([$this->category, $this->city]);

        return [
            'type' => 'checkin',
            'icon' => 'map-pin',
            'title' => $this->venue_name,
            'subtitle' => $parts ? implode(', ', $parts) : null,
            'occurred_at' => $this->occurred_at,
            'accent' => 'checkin',
            'meta' => [],
        ];
    }
}
