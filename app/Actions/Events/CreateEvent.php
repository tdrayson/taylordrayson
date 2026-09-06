<?php

namespace App\Actions\Events;

use App\Models\Event;
use App\Support\EntryInstant;

class CreateEvent
{
    /**
     * The category is a tag, not a column: events moved off a `type` column
     * when they became taggable, and the first tag is what renders as the
     * category.
     *
     * @param  array{name: string, occurred_at?: string|null, ends_at?: string|null, all_day?: bool, organiser?: string|null, venue_name?: string|null, city?: string|null, country?: string|null, latitude?: float|null, longitude?: float|null, url?: string|null, description?: string|null, timezone?: string|null, tags?: list<string>}  $attributes
     */
    public function __invoke(array $attributes): Event
    {
        $tags = $attributes['tags'] ?? null;
        unset($attributes['tags']);

        $event = Event::create([
            ...$attributes,
            'occurred_at' => $attributes['occurred_at'] ?? EntryInstant::nowLocal(),
            'timezone' => $attributes['timezone'] ?? config('app.home_timezone'),
        ]);

        if ($tags !== null) {
            $event->syncTagNames($tags);
        }

        return $event;
    }
}
