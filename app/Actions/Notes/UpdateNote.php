<?php

namespace App\Actions\Notes;

use App\Models\Note;

class UpdateNote
{
    /**
     * @param  array{content?: string, occurred_at?: string, tags?: list<string>}  $attributes
     */
    public function __invoke(Note $note, array $attributes): Note
    {
        if (array_key_exists('tags', $attributes)) {
            $note->syncTagNames($attributes['tags']);
            unset($attributes['tags']);
        }

        $note->fill($attributes)->save();

        return $note->refresh();
    }
}
