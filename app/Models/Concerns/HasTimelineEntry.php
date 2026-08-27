<?php

namespace App\Models\Concerns;

use App\Models\TimelineEntry;
use App\Support\EntryZone;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasTimelineEntry
{
    /**
     * Stamp an entry arriving without a zone with where it can be placed: the
     * phone's last reading, or failing that where flights say you were. On
     * `saving` so it lands in the same write.
     */
    public static function bootHasTimelineEntry(): void
    {
        static::saving(function (Model $model): void {
            if (! self::carriesOwnTimezone($model) || $model->occurred_at === null) {
                return;
            }

            // Read raw: models exposing timezone() as a method would send
            // getAttribute() looking for a relationship of that name.
            if (filled($model->getAttributes()['timezone'] ?? null)) {
                return;
            }

            $zone = app(EntryZone::class)->forEntryAt($model->occurred_at);

            if ($zone !== null) {
                $model->setAttribute('timezone', $zone);
            }
        });
    }

    /**
     * Whether the model has a timezone of its own to fill. Flight does not: it
     * keeps a departure and an arrival zone and resolves between them.
     */
    private static function carriesOwnTimezone(Model $model): bool
    {
        static $columns = [];

        return $columns[$model::class] ??= $model->getConnection()
            ->getSchemaBuilder()
            ->hasColumn($model->getTable(), 'timezone');
    }

    public function timelineEntry(): MorphOne
    {
        return $this->morphOne(TimelineEntry::class, 'timelineable');
    }

    /**
     * The URL slug lives on the spine row (assigned once at write time);
     * models without one yet (e.g. unpublished articles) fall back to the
     * bare slug.
     */
    public function url(): string
    {
        return '/'.$this->occurred_at->format('Y/m/d').'/'.($this->timelineEntry?->url_slug ?? $this->slug());
    }

    /**
     * The timezone the entry was captured in; null renders as home time.
     * Read from the raw attributes so unsaved models resolve to null.
     */
    public function timezone(): ?string
    {
        return $this->attributes['timezone'] ?? null;
    }

    /**
     * Every Timelineable appears on the spine by default; models with their
     * own publication gate (e.g. Article) override this.
     */
    public function shouldAppearOnTimeline(): bool
    {
        return true;
    }

    /** Most entries happened at a moment; a day-granular type overrides this. */
    public function hasClockTime(): bool
    {
        return true;
    }

    /**
     * The moment shown as the entry's timestamp. Defaults to occurred_at;
     * day-granular types with a more meaningful clock time (e.g. Sleep's
     * wake time) override this.
     */
    public function occurredAtForDisplay(): CarbonInterface
    {
        return $this->occurred_at;
    }
}
