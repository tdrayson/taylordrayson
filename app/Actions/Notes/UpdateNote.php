<?php

namespace App\Actions\Notes;

use App\Models\Note;

class UpdateNote
{
    /**
     * @param  array{content?: string, occurred_at?: string}  $attributes
     */
    public function __invoke(Note $note, array $attributes): Note
    {
        $note->fill($attributes)->save();

        return $note->refresh();
    }
}
