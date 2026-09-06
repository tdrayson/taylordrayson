<?php

namespace App\Support;

use GuzzleHttp\Psr7\Header;

/**
 * Where to send a webmention for a given page.
 *
 * The spec's order: the Link header wins over the document, and the first
 * `rel="webmention"` wins over any later one. A page that advertises none
 * simply does not take mentions.
 */
final class WebmentionEndpoint
{
    private const TIMEOUT_SECONDS = 10;

    /** A page advertising an endpoint, not a download. */
    private const MAX_BYTES = 2 * 1024 * 1024;

    /** An absolute endpoint URL, or null when the target advertises none. */
    public static function discover(string $url): ?string
    {
        $html = SafeFetch::body(
            $url,
            self::MAX_BYTES,
            self::TIMEOUT_SECONDS,
            ['Accept' => 'text/html'],
            $status,
            $headers,
            $finalUrl,
        );

        if ($html === null) {
            return null;
        }

        // Resolved against the URL actually answered, so a relative endpoint on
        // a redirected page still points somewhere real.
        return self::fromHeaders($headers, $finalUrl)
            ?? self::fromDocument($html, $finalUrl);
    }

    /**
     * `Link: <https://example.com/wm>; rel="webmention"`, where rel may be a
     * space-separated list that webmention is one of.
     *
     * Parsed by Guzzle's header parser rather than by pattern: it already
     * handles the comma-separated list, the quoting and the parameter split,
     * which is three edge cases not worth owning.
     *
     * @param  array<string, string>  $headers
     */
    private static function fromHeaders(array $headers, string $base): ?string
    {
        foreach (Header::parse($headers['link'] ?? '') as $link) {
            $rel = preg_split('/\s+/', trim((string) ($link['rel'] ?? ''))) ?: [];

            if (! in_array('webmention', array_map('strtolower', $rel), true)) {
                continue;
            }

            $target = trim((string) ($link[0] ?? ''), '<> ');

            if ($target !== '') {
                return \Mf2\resolveUrl($base, $target);
            }
        }

        return null;
    }

    /**
     * The first `rel="webmention"` in the document.
     *
     * Read from the microformats parser's own rel index rather than by walking
     * the DOM here. It already collects every rel on the page, in order, across
     * both `<link>` and `<a>`, and resolves each against the document's base.
     * That was previously reimplemented, base resolution included.
     */
    private static function fromDocument(string $html, string $base): ?string
    {
        $parsed = rescue(fn (): array => \Mf2\parse($html, $base), [], report: false);

        return $parsed['rels']['webmention'][0] ?? null;
    }
}
