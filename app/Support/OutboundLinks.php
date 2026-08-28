<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * The external URLs an entry points at, and a fingerprint of the text they sit
 * in, for deciding whether a webmention needs re-sending.
 *
 * Two shapes of source, because the site stores prose two ways: Portable Text
 * in `content` (notes, articles, pages) and a plain string in `description`
 * (a Strava activity, a check-in note). The plain form is run through
 * PortableText::fromPlainText(), which autolinks bare URLs, so both end up in
 * the same structure and one extractor covers them.
 */
final class OutboundLinks
{
    /**
     * Fields worth reading, in the order they are checked. `content` is already
     * Portable Text; the rest are strings a URL may have been pasted into.
     */
    private const SOURCES = ['content', 'description'];

    /**
     * Every external URL the entry links to.
     *
     * @return list<string>
     */
    public static function for(Model $model): array
    {
        $urls = [];

        foreach (self::documents($model) as $document) {
            foreach (Links::urlsIn($document) as $url) {
                $urls[$url] = true;
            }
        }

        return array_keys($urls);
    }

    /**
     * A fingerprint of what a receiver would re-fetch.
     *
     * The title is included: a receiver parses our `p-name` as well as the
     * body, so renaming a post leaves its copy stale and must re-notify.
     */
    public static function fingerprint(Model $model): string
    {
        $parts = [(string) $model->getAttribute('title')];

        foreach (self::SOURCES as $field) {
            $value = $model->getAttribute($field);

            $parts[] = is_string($value) ? $value : json_encode($value);
        }

        return hash('sha256', implode('|', $parts));
    }

    /**
     * Each readable field as a Portable Text document.
     *
     * @return list<array<int, mixed>>
     */
    private static function documents(Model $model): array
    {
        $documents = [];

        foreach (self::SOURCES as $field) {
            $value = $model->getAttribute($field);

            if (is_array($value)) {
                $documents[] = $value;

                continue;
            }

            if (is_string($value) && trim($value) !== '') {
                $documents[] = PortableText::fromPlainText($value);
            }
        }

        return $documents;
    }
}
