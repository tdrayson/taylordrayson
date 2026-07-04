<?php

namespace App\Actions\Notes;

use App\Models\Note;

class CreateNote
{
    /**
     * @param  array{content: string, occurred_at?: string|null}  $attributes
     */
    public function __invoke(array $attributes): Note
    {
        return Note::create([
            'content' => $attributes['content'],
            'occurred_at' => $attributes['occurred_at'] ?? now(),
        ]);
    }
}
