<?php

namespace App\Actions\Citations;

use App\Actions\Webmentions\ParseMentionSource;
use App\Data\CitationData;
use App\Support\PortableText;
use App\Support\SafeFetch;
use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Str;
use MensBeam\Microformats;

/**
 * Reads somebody else's post for the context a reply shows above itself.
 *
 * One request for the page, every field taken from the first source on it that
 * has one: the h-entry, an h-card for its author, OpenGraph, then `<title>`. A
 * second request is made only when the author is named by a link and nothing
 * on the page says who is at the end of it.
 */
final class FetchCitation
{
    private const MAX_BYTES = 2 * 1024 * 1024;

    private const TIMEOUT_SECONDS = 10;

    private const MAX_EXCERPT = 600;

    /** Past this an implied mf2 name is a paragraph, not a title. */
    private const MAX_TITLE = 255;

    public function __construct(private readonly ParseMentionSource $parse) {}

    public function __invoke(string $url): ?CitationData
    {
        $html = SafeFetch::body($url, self::MAX_BYTES, self::TIMEOUT_SECONDS, ['Accept' => 'text/html']);

        if ($html === null) {
            return null;
        }

        $entry = ($this->parse)($html, $url, $url);
        $meta = $this->metaIn($html);

        [$authorName, $authorPhoto] = $this->author($html, $url, $entry->authorName, $entry->authorUrl, $entry->authorPhoto);

        $excerpt = $entry->content !== null ? trim(PortableText::plainText($entry->content)) : null;

        return new CitationData(
            url: $url,
            site: Str::chopStart((string) parse_url($url, PHP_URL_HOST), 'www.'),
            title: $this->clean($entry->title ?? $meta['og:title'] ?? $meta['title'] ?? null, self::MAX_TITLE),
            authorName: $authorName ?? $this->clean($meta['article:author'] ?? null, self::MAX_TITLE),
            authorPhotoUrl: $authorPhoto,
            excerpt: $this->clean($excerpt ?: ($meta['og:description'] ?? $meta['description'] ?? null), self::MAX_EXCERPT),
            publishedAt: $entry->publishedAt ?? $this->date($meta['article:published_time'] ?? null),
            publishedTimezone: $entry->publishedTimezone ?? $this->offset($meta['article:published_time'] ?? null),
        );
    }

    /**
     * The author's name and photo. A name that is really a URL, or a missing name
     * beside an author URL, means the post only links to its author: look for an
     * h-card for that URL on this page, and only then fetch the URL itself.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function author(string $html, string $pageUrl, ?string $name, ?string $authorUrl, ?string $photo): array
    {
        $linkedOnly = $name !== null && filter_var($name, FILTER_VALIDATE_URL) !== false;

        if ($name !== null && ! $linkedOnly) {
            return [$name, $photo];
        }

        $authorUrl ??= $linkedOnly ? $name : null;

        if ($authorUrl === null) {
            return [null, null];
        }

        $card = $this->cardFor($html, $pageUrl, $authorUrl);

        if ($card === null) {
            $authorHtml = SafeFetch::body($authorUrl, self::MAX_BYTES, self::TIMEOUT_SECONDS, ['Accept' => 'text/html']);
            $card = $authorHtml === null ? null : $this->cardFor($authorHtml, $authorUrl, $authorUrl);
        }

        return $card ?? [null, null];
    }

    /**
     * An h-card whose url is the author's, never simply the first on the page: a
     * post page also carries h-cards for employers and groups.
     *
     * @return array{0: ?string, 1: ?string}|null
     */
    private function cardFor(string $html, string $pageUrl, string $authorUrl): ?array
    {
        $parsed = rescue(fn (): array => Microformats::fromString($html, 'text/html', $pageUrl), [], report: false);

        foreach ($this->cards($parsed['items'] ?? []) as $card) {
            $properties = $card['properties'] ?? [];
            $urls = array_map(fn (mixed $value): string => rtrim((string) (is_array($value) ? ($value['value'] ?? '') : $value), '/'), $properties['url'] ?? []);

            if (! in_array(rtrim($authorUrl, '/'), $urls, true)) {
                continue;
            }

            $name = $properties['name'][0] ?? null;
            $photo = $properties['photo'][0] ?? null;

            return [
                is_string($name) && $name !== '' ? $name : null,
                is_array($photo) ? ($photo['value'] ?? null) : (is_string($photo) ? $photo : null),
            ];
        }

        return null;
    }

    /**
     * Every h-card on the page, including nested ones.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function cards(array $items): array
    {
        $found = [];

        foreach ($items as $item) {
            if (in_array('h-card', $item['type'] ?? [], true)) {
                $found[] = $item;
            }

            foreach ($item['properties'] ?? [] as $values) {
                foreach ($values as $value) {
                    if (is_array($value) && isset($value['type'])) {
                        $found = [...$found, ...$this->cards([$value])];
                    }
                }
            }

            $found = [...$found, ...$this->cards($item['children'] ?? [])];
        }

        return $found;
    }

    /**
     * OpenGraph, description and `<title>`, keyed by property or name.
     *
     * @return array<string, string>
     */
    private function metaIn(string $html): array
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);

        $meta = [];

        foreach ($xpath->query('//meta[@property or @name]') ?: [] as $node) {
            $key = strtolower($node->getAttribute('property') ?: $node->getAttribute('name'));
            $meta[$key] ??= trim($node->getAttribute('content'));
        }

        $title = $xpath->query('//title')->item(0)?->textContent;

        if ($title !== null && trim($title) !== '') {
            $meta['title'] = trim($title);
        }

        return array_filter($meta, fn (string $value): bool => $value !== '');
    }

    private function clean(?string $value, int $max): ?string
    {
        $value = $value === null ? null : trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $value === null || $value === '' ? null : Str::limit($value, $max, '');
    }

    private function date(?string $value): ?Carbon
    {
        return $value === null ? null : rescue(fn (): Carbon => Carbon::parse($value), null, report: false);
    }

    /** The offset written in an ISO date, or null when it had none. */
    private function offset(?string $value): ?string
    {
        return $value !== null && preg_match('/([+-]\d{2}:\d{2})$/', $value, $matches) === 1 ? $matches[1] : null;
    }
}
