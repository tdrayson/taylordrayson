<?php

namespace App\Actions\Notes;

use App\Models\Note;
use App\Support\PortableText;

class CreateNote
{
    /**
     * The timezone records where the note was captured (the client sends its
     * current zone); omitted means it was written from the home timezone.
     *
     * Content arrives either as Portable Text from the editor or as a plain
     * string from a client that only knows how to send one (Shortcuts,
     * Micropub); a string is wrapped into a single block.
     *
     * @param  array{content: string|array<int, mixed>, occurred_at?: string|null, slug?: string|null, timezone?: string|null, tags?: list<string>}  $attributes
     */
    public function __invoke(array $attributes): Note
    {
        $note = Note::create([
            'content' => is_string($attributes['content'])
                ? PortableText::fromPlainText($attributes['content'])
                : $attributes['content'],
            'occurred_at' => $attributes['occurred_at'] ?? now(),
            'slug' => $attributes['slug'] ?? null,
            'timezone' => $attributes['timezone'] ?? config('app.home_timezone'),
        ]);

        if (array_key_exists('tags', $attributes)) {
            $note->syncTagNames($attributes['tags']);
        }

        return $note;
    }
}
