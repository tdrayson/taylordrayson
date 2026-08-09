<?php

namespace App\Actions\Events;

use App\Models\Event;

class UpdateEvent
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(Event $event, array $attributes): Event
    {
        if (array_key_exists('tags', $attributes)) {
            $event->syncTagNames($attributes['tags']);
            unset($attributes['tags']);
        }

        $event->fill($attributes)->save();

        return $event->refresh();
    }
}
