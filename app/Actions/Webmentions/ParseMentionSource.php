<?php

namespace App\Actions\Webmentions;

use App\Data\MentionData;
use App\Enums\WebmentionKind;
use App\Support\HtmlToPortableText;
use App\Support\PortableText;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use MensBeam\Microformats;

/**
 * Reads a source page's microformats2 to work out what it is saying about one
 * of our URLs, and who is saying it.
 *
 * This is the difference between "somebody linked to this" and "Jo replied,
 * and here is what they said".
 */
final class ParseMentionSource
{
    /**
     * The mf2 properties that make a mention something more than a mention,
     * in the order they take precedence.
     *
     * @var array<string, WebmentionKind>
     */
    /**
     * How much of somebody else's post to show under ours.
     *
     * A mention quotes a response, it does not republish it. Without a cap a
     * source that marks its whole article as e-content puts the whole article
     * on our page, and "Read it on their site" stops meaning anything.
     */
    private const MAX_CONTENT = 600;

    /**
     * Past this, an mf2 name is not a title.
     *
     * A source that marks up an h-entry and nothing inside it gets an implied
     * name, which is the element's whole text rather than a heading. Measured
     * over 144 real names: median 19 characters, 90th percentile 43, and the
     * only one past this cap was a hidden h-card's styling note.
     */
    private const MAX_TITLE = 120;

    /**
     * @var array<string, WebmentionKind>
     */
    private const RESPONSE_PROPERTIES = [
        'in-reply-to' => WebmentionKind::Reply,
        'like-of' => WebmentionKind::Like,
        'repost-of' => WebmentionKind::Repost,
        'bookmark-of' => WebmentionKind::Bookmark,
    ];

    public function __invoke(string $html, string $sourceUrl, string $targetUrl): MentionData
    {
        $parsed = rescue(fn (): array => Microformats::fromString($html, 'text/html', $sourceUrl), [], report: false);

        $entry = $this->entryAbout($parsed['items'] ?? [], $targetUrl);

        if ($entry === null) {
            return MentionData::bare();
        }

        $properties = $entry['properties'] ?? [];
        $kind = $this->kindOf($properties, $targetUrl);
        $content = $this->content($properties, $kind);

        return new MentionData(
            kind: $kind,
            title: $this->title($properties),
            authorName: $this->authorField($properties, 'name'),
            authorUrl: $this->authorField($properties, 'url'),
            authorPhoto: $this->authorField($properties, 'photo'),
            content: $content,
            publishedAt: $this->published($properties),
            emoji: $kind === WebmentionKind::Reply ? $this->emojiIn(PortableText::plainText($content ?? [])) : null,
        );
    }

    /**
     * A reacji is an ordinary reply whose entire content is one emoji, so the
     * only way to spot one is to look at the body.
     *
     * Counted in graphemes, not codepoints: 👨‍👩‍👧 is five codepoints joined
     * by ZWJs and a skin tone is two, so mb_strlen reads both as long replies.
     */
    private function emojiIn(string $content): ?string
    {
        $trimmed = trim($content);

        return grapheme_strlen($trimmed) === 1 && preg_match('/^\p{Extended_Pictographic}/u', $trimmed) === 1
            ? $trimmed
            : null;
    }

    /**
     * The h-entry that references our URL, or the first one on the page. A
     * source is free to hold several; the one that names us is the one that is
     * about us.
     *
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>|null
     */
    private function entryAbout(array $items, string $targetUrl): ?array
    {
        $entries = $this->flatten($items);

        foreach ($entries as $entry) {
            if ($this->references($entry['properties'] ?? [], $targetUrl)) {
                return $entry;
            }
        }

        return $entries[0] ?? null;
    }

    /**
     * Every h-entry on the page, including ones nested inside another item's
     * children, which is how a feed of them is marked up.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function flatten(array $items): array
    {
        $found = [];

        foreach ($items as $item) {
            if (in_array('h-entry', $item['type'] ?? [], true)) {
                $found[] = $item;
            }

            $found = array_merge($found, $this->flatten($item['children'] ?? []));
        }

        return $found;
    }

    /**
     * Which response property names our URL. An entry replying to someone else
     * that merely links to us in passing is a mention, not a reply, so the
     * property has to contain the target rather than merely exist.
     *
     * @param  array<string, mixed>  $properties
     */
    private function kindOf(array $properties, string $targetUrl): WebmentionKind
    {
        // An RSVP is an in-reply-to carrying a p-rsvp value, so it has to be
        // recognised before the reply it would otherwise read as. Still only
        // when the reply points here: an RSVP to someone else's event that
        // happens to link here is a mention.
        if (isset($properties['rsvp']) && $this->contains($properties['in-reply-to'] ?? [], $targetUrl)) {
            return WebmentionKind::Rsvp;
        }

        foreach (self::RESPONSE_PROPERTIES as $property => $kind) {
            if ($this->contains($properties[$property] ?? [], $targetUrl)) {
                return $kind;
            }
        }

        return WebmentionKind::Mention;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function references(array $properties, string $targetUrl): bool
    {
        foreach ([...array_keys(self::RESPONSE_PROPERTIES), 'content'] as $property) {
            if ($this->contains($properties[$property] ?? [], $targetUrl)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a property's values point at the target. Values arrive as plain
     * URLs, as {url: ...} objects, or as embedded h-cites, so all three are
     * flattened and searched.
     */
    private function contains(mixed $values, string $targetUrl): bool
    {
        // Slashes unescaped, or the needle's `//` would never match the
        // encoded `\/\/` and every response would read as a bare mention.
        $encoded = json_encode(Arr::wrap($values), JSON_UNESCAPED_SLASHES) ?: '';

        return str_contains($encoded, $this->normalise($targetUrl));
    }

    /** Compared without the scheme, so http and https forms of a URL match. */
    private function normalise(string $url): string
    {
        return preg_replace('#^https?://#', '', rtrim($url, '/')) ?? $url;
    }

    /**
     * The response's own words, as Portable Text.
     *
     * mf2 hands back both a `html` and a flattened `value` for e-content. The
     * HTML is taken because the flattened form drops every link, quote and
     * list; it is never held as HTML, only walked into blocks by an allowlist.
     *
     * @param  array<string, mixed>  $properties
     * @return array<int, array<string, mixed>>|null
     */
    private function content(array $properties, WebmentionKind $kind): ?array
    {
        $content = $properties['content'][0] ?? null;

        if (is_array($content) && is_string($content['html'] ?? null)) {
            $document = HtmlToPortableText::convert($content['html']);

            if ($document !== []) {
                return $this->withinLength($document, $properties);
            }
        }

        $text = match (true) {
            is_array($content) => $content['value'] ?? null,
            is_string($content) => $content,
            default => null,
        };

        // A summary is prose the author wrote about their own post, so it
        // stands in for content. The name never does: it is a title, it has a
        // column of its own, and putting it here would publish it as e-content.
        if ($text === null && ! $kind->isGesture()) {
            $text = $properties['summary'][0] ?? null;
        }

        return is_string($text) && trim($text) !== ''
            ? PortableText::truncate(PortableText::fromPlainText(trim($text)), self::MAX_CONTENT)
            : null;
    }

    /**
     * The name of the source post, when it reads like one.
     *
     * @param  array<string, mixed>  $properties
     */
    private function title(array $properties): ?string
    {
        $name = $properties['name'][0] ?? null;

        if (! is_string($name)) {
            return null;
        }

        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        return $name !== '' && mb_strlen($name) <= self::MAX_TITLE ? $name : null;
    }

    /**
     * A long response shown at a length that suits our page.
     *
     * A hand-written p-summary is preferred over our own cut, because the
     * author summarised their own post better than a truncation can. Whatever
     * is used is still capped: a summary can be long too.
     *
     * @param  array<int, array<string, mixed>>  $document
     * @param  array<string, mixed>  $properties
     * @return array<int, array<string, mixed>>
     */
    private function withinLength(array $document, array $properties): array
    {
        if (mb_strlen(PortableText::plainText($document)) <= self::MAX_CONTENT) {
            return $document;
        }

        $summary = $properties['summary'][0] ?? null;

        if (is_string($summary) && trim($summary) !== '') {
            return PortableText::truncate(PortableText::fromPlainText(trim($summary)), self::MAX_CONTENT);
        }

        return PortableText::truncate($document, self::MAX_CONTENT);
    }

    /**
     * A field off the entry's h-card author, falling back to a bare string
     * author, which is what a source with only `p-author` gives.
     *
     * @param  array<string, mixed>  $properties
     */
    private function authorField(array $properties, string $field): ?string
    {
        $author = $properties['author'][0] ?? null;

        if (is_string($author)) {
            return $field === 'name' ? $author : null;
        }

        $value = is_array($author) ? ($author['properties'][$field][0] ?? null) : null;

        // A photo can itself be an object carrying alt text.
        if (is_array($value)) {
            $value = $value['value'] ?? null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function published(array $properties): ?Carbon
    {
        $published = $properties['published'][0] ?? null;

        return is_string($published)
            ? rescue(fn (): Carbon => Carbon::parse($published), null, report: false)
            : null;
    }
}
