<?php

namespace App\Actions\Notes;

use App\Models\Note;
use App\Support\PortableText;

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

        if (isset($attributes['content']) && is_string($attributes['content'])) {
            $attributes['content'] = PortableText::fromPlainText($attributes['content']);
        }

        $note->fill($attributes)->save();

        return $note->refresh();
    }
}
