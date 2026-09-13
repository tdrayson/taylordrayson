<?php

namespace App\Actions\Notes;

use App\Enums\EntryStatus;
use App\Models\Note;
use App\Support\PortableText;
use App\Support\TimelineUrlSlug;
use Illuminate\Validation\ValidationException;

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
     * @param  array{content: string|array<int, mixed>, occurred_at?: string|null, slug?: string|null, timezone?: string|null, tags?: list<string>, status?: string, password?: string|null}  $attributes
     */
    public function __invoke(array $attributes): Note
    {
        $content = is_string($attributes['content'])
            ? PortableText::fromPlainText($attributes['content'])
            : $attributes['content'];

        $slug = $attributes['slug'] ?? null;
        $candidate = $slug !== null && $slug !== '' ? $slug : Note::slugFrom($content);

        if (TimelineUrlSlug::isReserved($candidate)) {
            throw ValidationException::withMessages(['slug' => [TimelineUrlSlug::reservationMessage($candidate)]]);
        }

        $note = Note::create([
            'content' => $content,
            'occurred_at' => $attributes['occurred_at'] ?? null,
            'slug' => $slug,
            'timezone' => $attributes['timezone'] ?? config('app.home_timezone'),
            'status' => $attributes['status'] ?? EntryStatus::Published,
            'password' => $attributes['password'] ?? null,
        ]);

        if (array_key_exists('tags', $attributes)) {
            $note->syncTagNames($attributes['tags']);
        }

        return $note;
    }
}
