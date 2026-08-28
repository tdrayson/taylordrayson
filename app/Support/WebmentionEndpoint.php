<?php

namespace App\Support;

use DOMDocument;
use DOMXPath;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Finds where to send a webmention for a given URL.
 *
 * Rediscovered on every send rather than cached, as the spec asks: an endpoint
 * can move, and a stored one is a hint rather than a fact.
 *
 * Discovery order is the spec's, and the order matters: a Link header wins
 * over anything in the body, and the first match wins over later ones.
 */
final class WebmentionEndpoint
{
    private const TIMEOUT_SECONDS = 10;

    /** An absolute endpoint URL, or null when the target advertises none. */
    public static function discover(string $url): ?string
    {
        if (! SafeUrl::fetchable($url)) {
            return null;
        }

        $response = rescue(
            fn () => Http::timeout(self::TIMEOUT_SECONDS)->withOptions(['allow_redirects' => ['max' => 5]])->get($url),
            null,
            report: false,
        );

        if ($response === null || $response->failed()) {
            return null;
        }

        $found = self::fromHeaders(self::linkHeaders($response))
            ?? self::fromBody($response->body());

        // Resolved against the URL actually fetched, so a relative endpoint on
        // a redirected page still points somewhere real.
        return $found === null ? null : self::absolute($found, (string) ($response->effectiveUri() ?? $url));
    }

    /**
     * Every Link header, unjoined. Header names arrive in whatever case the
     * server sent, so they are matched insensitively rather than looked up.
     *
     * @return list<string>
     */
    private static function linkHeaders(Response $response): array
    {
        foreach ($response->headers() as $name => $values) {
            if (strcasecmp($name, 'Link') === 0) {
                return array_values($values);
            }
        }

        return [];
    }

    /**
     * `Link: <https://example.com/wm>; rel="webmention"`, with rel being a
     * space-separated list that webmention may be one of.
     *
     * @param  list<string>  $headers
     */
    private static function fromHeaders(array $headers): ?string
    {
        foreach ($headers as $header) {
            foreach (explode(',', $header) as $link) {
                if (preg_match('/<([^>]*)>\s*;\s*(.*)/', trim($link), $matches) !== 1) {
                    continue;
                }

                if (preg_match('/rel\s*=\s*"?([^";]*)"?/i', $matches[2], $rel) === 1
                    && in_array('webmention', preg_split('/\s+/', trim($rel[1])) ?: [], true)) {
                    return trim($matches[1]);
                }
            }
        }

        return null;
    }

    /**
     * The first <link> or <a> carrying rel="webmention", in document order,
     * which is what the spec makes authoritative when several are present.
     */
    private static function fromBody(string $html): ?string
    {
        $document = new DOMDocument;

        if (! @$document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING)) {
            return null;
        }

        foreach ((new DOMXPath($document))->query('//link[@rel]|//a[@rel]') ?: [] as $node) {
            $rel = preg_split('/\s+/', trim((string) $node->getAttribute('rel'))) ?: [];

            if (in_array('webmention', array_map('strtolower', $rel), true)) {
                // An empty href is legal and means "this page".
                return $node->getAttribute('href');
            }
        }

        return null;
    }

    private static function absolute(string $href, string $base): string
    {
        if ($href === '') {
            return $base;
        }

        if (parse_url($href, PHP_URL_SCHEME) !== null) {
            return $href;
        }

        $parts = parse_url($base);
        $root = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '')
            .(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($href, '/')) {
            return $root.$href;
        }

        return $root.rtrim(dirname($parts['path'] ?? '/'), '/').'/'.$href;
    }
}
