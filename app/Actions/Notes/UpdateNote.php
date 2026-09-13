<?php

namespace App\Actions\Notes;

use App\Models\Note;
use App\Support\PortableText;
use App\Support\TimelineUrlSlug;
use Illuminate\Validation\ValidationException;

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

        if (array_key_exists('slug', $attributes) || array_key_exists('content', $attributes)) {
            $slug = array_key_exists('slug', $attributes) ? $attributes['slug'] : ($note->getAttributes()['slug'] ?? null);
            $content = array_key_exists('content', $attributes) ? $attributes['content'] : ($note->getAttributes()['content'] ?? null);
            $candidate = $slug !== null && $slug !== '' ? $slug : Note::slugFrom($content);

            if (TimelineUrlSlug::isReserved($candidate)) {
                throw ValidationException::withMessages(['slug' => [TimelineUrlSlug::reservationMessage($candidate)]]);
            }
        }

        $note->fill($attributes)->save();

        return $note->refresh();
    }
}
