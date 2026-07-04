<?php

namespace App\Actions\Notes;

use App\Models\Note;

class CreateNote
{
    /**
     * The timezone records where the note was captured (the client sends its
     * current zone); omitted means it was written from the home timezone.
     *
     * @param  array{content: string, occurred_at?: string|null, timezone?: string|null}  $attributes
     */
    public function __invoke(array $attributes): Note
    {
        return Note::create([
            'content' => $attributes['content'],
            'occurred_at' => $attributes['occurred_at'] ?? now(),
            'timezone' => $attributes['timezone'] ?? config('app.home_timezone'),
        ]);
    }
}
