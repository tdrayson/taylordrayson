<?php

namespace App\Actions\Webmentions;

use App\Enums\CommentStatus;
use App\Models\Webmention;
use App\Support\Links;

/**
 * Whether a mention appears straight away or waits to be read.
 *
 * Hold the first mention from a site, then trust that site. Same rule as
 * comments, keyed on the sending host rather than a name, and shared so a
 * response read out of somebody else's thread is moderated exactly as one sent
 * here directly.
 *
 * Matched on the whole host. A `like %host%` match would silently let
 * indieweb.org trust dieweb.org, and example.com trust example.com.evil.tld.
 */
final class DecideMentionStatus
{
    public function __invoke(?string $authorUrl): CommentStatus
    {
        $host = $authorUrl === null ? null : Links::host($authorUrl);

        if ($host === null || $host === '') {
            return CommentStatus::Pending;
        }

        if (in_array($host, config('webmentions.trusted_hosts', []), strict: true)) {
            return CommentStatus::Approved;
        }

        return Webmention::query()->approved()->where('author_host', $host)->exists()
            ? CommentStatus::Approved
            : CommentStatus::Pending;
    }
}
