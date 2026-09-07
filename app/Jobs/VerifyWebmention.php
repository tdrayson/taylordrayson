<?php

namespace App\Jobs;

use App\Actions\Webmentions\ParseMentionSource;
use App\Actions\Webmentions\StoreAuthorPhoto;
use App\Data\MentionData;
use App\Enums\CommentStatus;
use App\Enums\WebmentionKind;
use App\Models\Webmention;
use App\Support\Links;
use App\Support\SafeFetch;
use App\Support\WebmentionTarget;
use DOMDocument;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Fetches a claimed source, confirms it really does link to the target, and
 * files what it says.
 *
 * The confirmation is the whole point: without it anyone could attribute any
 * page to any URL. A source that no longer links here has the mention removed,
 * which is how the spec expects an edit or a deletion to be handled.
 */
class VerifyWebmention implements ShouldQueue
{
    use Queueable;

    private const TIMEOUT_SECONDS = 15;

    /** A page carrying an h-entry, not a file. Anything past this is not one. */
    private const MAX_BYTES = 2 * 1024 * 1024;

    public function __construct(private readonly int $webmentionId) {}

    public function handle(ParseMentionSource $parse): void
    {
        $mention = Webmention::query()->find($this->webmentionId);

        if ($mention === null) {
            return;
        }

        $target = WebmentionTarget::resolve($mention->target_url);

        // The target may have been unpublished between receipt and now.
        // The source is not checked here: SafeFetch validates it, and every
        // redirect it leads to.
        if ($target === null) {
            $mention->delete();

            return;
        }

        // Every redirect hop is re-checked and the body is capped: the sender
        // chose this URL, so they must not also get to choose where it leads or
        // how much we read.
        $html = SafeFetch::body(
            $mention->source_url,
            self::MAX_BYTES,
            self::TIMEOUT_SECONDS,
            ['Accept' => 'text/html'],
            $status,
        );

        // A source we could not reach has said nothing. A timeout, a 500, a
        // rate limit or a bot wall is their outage or their firewall, not a
        // retraction, and deleting on it meant a re-send during a blip
        // destroyed the mention for good.
        if ($html === null && ! in_array($status, [404, 410], strict: true)) {
            $mention->update(['last_checked_at' => now()]);

            return;
        }

        // Gone, or no longer linking here: retracted, whether or not the
        // sender said so.
        if ($html === null || ! $this->linksToTarget($html, $mention->target_url)) {
            $mention->delete();

            return;
        }

        $parsed = $parse($html, $mention->source_url, $mention->target_url);

        $mention->target()->associate($target);
        $mention->fill([
            'kind' => ($parsed->isReacji() ? WebmentionKind::Reacji : $parsed->kind)->value,
            'title' => $parsed->title,
            'author_name' => $parsed->authorName,
            'author_url' => $parsed->authorUrl,
            'author_photo_path' => app(StoreAuthorPhoto::class)($parsed->authorPhoto),
            // Kept so the file can be fetched again: the path is a hash of this.
            'author_photo_url' => $parsed->authorPhoto,
            'content' => $parsed->content,
            'published_at' => $parsed->publishedAt,
            'status' => $this->statusFor($parsed),
            'verified_at' => now(),
            'last_checked_at' => now(),
        ])->save();
    }

    /**
     * Whether the source page actually links to the target.
     *
     * The attributes are read rather than the raw HTML searched. A string
     * search passed on the target appearing anywhere at all: in a script, in a
     * comment, in prose, or as a prefix of a longer URL, so `/a-post` proved a
     * link to `/a`. The protocol's one requirement is a link, so that is what
     * is checked.
     */
    private function linksToTarget(string $html, string $target): bool
    {
        $wanted = self::normalise($target);

        foreach (self::linkedUrls($html) as $url) {
            if (self::normalise($url) === $wanted) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every URL the document points at through an attribute that makes it a
     * link rather than a mention of one.
     *
     * @return list<string>
     */
    private static function linkedUrls(string $html): array
    {
        // An empty body is a ValueError rather than a parse failure, and a
        // source answering 200 with nothing is a real thing that happens.
        if (trim($html) === '') {
            return [];
        }

        $document = new DOMDocument;

        // Someone else's markup is never going to parse cleanly, and a warning
        // per malformed tag would drown the log for no gain.
        $loaded = @$document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

        if (! $loaded) {
            return [];
        }

        $urls = [];

        foreach (['a' => 'href', 'link' => 'href', 'img' => 'src', 'video' => 'src', 'audio' => 'src'] as $tag => $attribute) {
            foreach ($document->getElementsByTagName($tag) as $element) {
                $value = trim($element->getAttribute($attribute));

                if ($value !== '') {
                    $urls[] = $value;
                }
            }
        }

        return $urls;
    }

    /**
     * Compared without the scheme and without a trailing slash, so http and
     * https forms of one address match and a link written either way counts.
     */
    private static function normalise(string $url): string
    {
        return rtrim((string) preg_replace('#^https?://#i', '', trim($url)), '/');
    }

    /**
     * Hold the first mention from a site, then trust that site. Same rule as
     * comments, keyed on the sending host rather than a name.
     *
     * Matched on the whole host. The old `like %host%` matched any substring,
     * so one approved mention from indieweb.org silently trusted dieweb.org,
     * and example.com trusted example.com.evil.tld. A host is either the same
     * host or a different one; there is no partial credit.
     */
    private function statusFor(MentionData $parsed): CommentStatus
    {
        $host = $parsed->authorUrl === null ? null : Links::host($parsed->authorUrl);

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
