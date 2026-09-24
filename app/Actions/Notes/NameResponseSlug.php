<?php

namespace App\Actions\Notes;

use App\Actions\Mentions\ResolveInternalTarget;
use App\Enums\ResponseKind;
use App\Models\Note;
use App\Support\Links;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The slug a response note is stored with when none was written: what it did,
 * then what it answered, e.g. `replied-to-sending-your-first-webmention`, or
 * `liked-back-under-the-bar` for one of my own entries.
 *
 * The editor previews this, so responseSlug() in resources/js/lib/editor/defaults.js
 * has to apply the same rule.
 */
final class NameResponseSlug
{
    public function __construct(private ResolveInternalTarget $resolve) {}

    /**
     * @return string|null Null for a plain note, or when nothing usable names the target.
     */
    public function __invoke(Note $note): ?string
    {
        $kind = $note->responseKind();

        if ($kind === null) {
            return null;
        }

        $url = (string) $note->response_url;
        $path = Links::internalPath($url);
        $target = $path === null ? null : ($this->resolve)($path);

        $name = $target !== null
            ? self::own($target)
            : self::external($kind, $note, $url);

        return $name === '' ? null : self::prefix($kind).'-'.$name;
    }

    /** The last segment of one of my own entries' URLs, which is its slug. */
    private static function own(Model $target): string
    {
        return self::words(str_replace('-', ' ', Str::afterLast($target->url(), '/')));
    }

    /** The citation's title, or a reply's author when the post had none, else the domain. */
    private static function external(ResponseKind $kind, Note $note, string $url): string
    {
        $citation = $note->citation;

        $candidates = $kind === ResponseKind::Reply
            ? [$citation?->title, $citation?->author_name]
            : [$citation?->title];

        foreach ($candidates as $candidate) {
            $slug = self::words($candidate);

            if ($slug !== '') {
                return $slug;
            }
        }

        return Str::slug(str_replace('.', ' ', Links::host($url) ?? ''));
    }

    /** What the slug says I did, past tense, reading as the sentence a card speaks. */
    private static function prefix(ResponseKind $kind): string
    {
        return match ($kind) {
            ResponseKind::Reply => 'replied-to',
            ResponseKind::Like => 'liked',
            ResponseKind::Repost => 'reposted',
            ResponseKind::Rsvp => 'rsvp-to',
        };
    }

    /** A name cut to the words a note's own slug uses, slugged. */
    private static function words(?string $name): string
    {
        return Str::slug(Str::words((string) $name, Note::SLUG_WORDS, ''));
    }
}
