<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Fetching a URL a stranger chose, without letting them choose what we fetch.
 *
 * SafeUrl answers whether one address is safe. That is not enough on its own:
 * the HTTP client follows redirects by default, so a host that passes the check
 * can answer `302 Location: http://169.254.169.254/` and the request is made
 * anyway. Every hop is therefore checked here, and redirects are followed by
 * hand rather than by the client.
 *
 * The body is read in chunks against a ceiling for the same reason. A source we
 * did not choose decides how much it sends, and a queue worker that buffers
 * whatever arrives is one large response away from being killed.
 */
final class SafeFetch
{
    /** Enough for a real chain of canonical redirects, short of a loop. */
    private const MAX_REDIRECTS = 5;

    private const CHUNK_BYTES = 8192;

    /**
     * The body at $url, or null when it could not be fetched safely.
     *
     * @param  array<string, string>  $headers
     * @param  int|null  $status  Set to the final response status, or null when
     *                            nothing was reached. Lets a caller tell "gone"
     *                            from "unreachable" without a second request.
     * @param  array<string, string>|null  $responseHeaders  Set to the final
     *                                                       response's headers, lowercased, for a caller
     *                                                       that needs a caching validator back.
     * @param  string|null  $finalUrl  Set to the URL actually answered, after
     *                                 any redirects, which is the base a relative
     *                                 link on that page resolves against.
     */
    public static function body(
        string $url,
        int $maxBytes,
        int $timeoutSeconds,
        array $headers = [],
        ?int &$status = null,
        ?array &$responseHeaders = null,
        ?string &$finalUrl = null,
    ): ?string {
        $status = null;
        $responseHeaders = [];
        $finalUrl = $url;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            if (! SafeUrl::fetchable($url)) {
                return null;
            }

            $response = rescue(
                fn () => Http::timeout($timeoutSeconds)
                    ->withHeaders($headers)
                    ->withOptions(['stream' => true, 'allow_redirects' => false])
                    ->get($url),
                null,
                report: false,
            );

            if ($response === null) {
                return null;
            }

            $status = $response->status();
            $finalUrl = $url;
            $responseHeaders = array_change_key_case(
                array_map(fn (array $values): string => $values[0] ?? '', $response->headers()),
            );
            $location = $response->header('location');

            if ($response->redirect() && $location !== '') {
                // Resolved against the URL that issued it, since a Location may
                // be a bare path. A hop that cannot be resolved ends the chain.
                $url = self::resolve($url, $location);

                if ($url === null) {
                    return null;
                }

                continue;
            }

            if (! $response->successful()) {
                return null;
            }

            return self::read($response, $maxBytes);
        }

        return null;
    }

    /** Read up to the ceiling, and refuse anything that reaches it. */
    private static function read(Response $response, int $maxBytes): ?string
    {
        $stream = $response->toPsrResponse()->getBody();

        // Reading consumes it, so a response that is read twice would come back
        // empty the second time.
        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        $body = '';

        while (! $stream->eof() && strlen($body) <= $maxBytes) {
            $body .= $stream->read(self::CHUNK_BYTES);
        }

        return strlen($body) > $maxBytes ? null : $body;
    }

    /**
     * An absolute URL for a Location header, which may be relative.
     *
     * Resolved by the microformats library rather than by hand: it implements
     * the RFC 3986 rules, including the dot segments and the query-only and
     * fragment-only forms that a hand-rolled version quietly gets wrong.
     */
    private static function resolve(string $from, string $location): ?string
    {
        $resolved = rescue(fn (): string => \Mf2\resolveUrl($from, $location), null, report: false);

        return $resolved === '' ? null : $resolved;
    }
}
