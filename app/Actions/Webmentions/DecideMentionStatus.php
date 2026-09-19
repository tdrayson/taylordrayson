<?php

namespace App\Actions\Webmentions;

use App\Enums\CommentStatus;
use App\Models\Webmention;
use App\Support\Links;

/**
 * Whether a mention appears straight away or waits to be read.
 *
 * Hold the first mention from a site, then trust that site. Keyed on the host
 * of the page we fetched, never on the author the page claims: a source that
 * genuinely links here can still name anybody it likes as the author, so
 * trusting that claim let a stranger publish here in a trusted person's name.
 * The fetched host is the one part of the exchange we establish ourselves.
 *
 * Matched on the whole host. A `like %host%` match would silently let
 * indieweb.org trust dieweb.org, and example.com trust example.com.evil.tld.
 */
final class DecideMentionStatus
{
    public function __invoke(?string $sourceUrl): CommentStatus
    {
        $host = $sourceUrl === null ? null : Links::host($sourceUrl);

        if ($host === null || $host === '') {
            return CommentStatus::Pending;
        }

        if (in_array($host, config('webmentions.trusted_hosts', []), strict: true)) {
            return CommentStatus::Approved;
        }

        // Parsed rather than stored, so there is no second copy of the host to
        // fall out of step with the URL, and compared for equality rather than
        // matched: a `like` would let example.com trust example.com.evil.tld,
        // and scheme and port variants make an anchored prefix awkward too.
        //
        // Only mentions somebody sent us, because only those had their source
        // fetched. A response read out of another page's thread carries a url
        // we never went to, so approving one must not vouch for its host.
        $approved = Webmention::query()
            ->approved()
            ->topLevel()
            ->distinct()
            ->pluck('source_url')
            ->contains(fn (?string $url): bool => is_string($url) && Links::host($url) === $host);

        return $approved ? CommentStatus::Approved : CommentStatus::Pending;
    }
}
