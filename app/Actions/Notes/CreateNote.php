<?php

namespace App\Actions\Notes;

use App\Models\Note;

class CreateNote
{
    /**
     * The timezone records where the note was captured (the client sends its
     * current zone); omitted means it was written from the home timezone.
     *
     * @param  array{content: string, occurred_at?: string|null, timezone?: string|null, tags?: list<string>}  $attributes
     */
    public function __invoke(array $attributes): Note
    {
        $note = Note::create([
            'content' => $attributes['content'],
            'occurred_at' => $attributes['occurred_at'] ?? now(),
            'timezone' => $attributes['timezone'] ?? config('app.home_timezone'),
        ]);

        if (array_key_exists('tags', $attributes)) {
            $note->syncTagNames($attributes['tags']);
        }

        return $note;
    }
}
