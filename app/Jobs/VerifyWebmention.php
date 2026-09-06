<?php

namespace App\Jobs;

use App\Actions\Webmentions\ParseMentionSource;
use App\Actions\Webmentions\StoreAuthorPhoto;
use App\Data\MentionData;
use App\Enums\CommentStatus;
use App\Enums\WebmentionKind;
use App\Models\Webmention;
use App\Support\SafeUrl;
use App\Support\WebmentionTarget;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

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

    public function __construct(private readonly int $webmentionId) {}

    public function handle(ParseMentionSource $parse): void
    {
        $mention = Webmention::query()->find($this->webmentionId);

        if ($mention === null) {
            return;
        }

        $target = WebmentionTarget::resolve($mention->target_url);

        // The target may have been unpublished between receipt and now.
        if ($target === null || ! SafeUrl::fetchable($mention->source_url)) {
            $mention->delete();

            return;
        }

        $response = rescue(
            fn () => Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders(['Accept' => 'text/html'])
                ->get($mention->source_url),
            null,
            report: false,
        );

        // A source we could not reach has said nothing. A timeout, a 500, a
        // rate limit or a bot wall is their outage or their firewall, not a
        // retraction, and deleting on it meant a re-send during a blip
        // destroyed the mention for good.
        if ($response === null || ($response->failed() && ! $this->isGone($response))) {
            $mention->update(['last_checked_at' => now()]);

            return;
        }

        // Gone, or no longer linking here: retracted, whether or not the
        // sender said so.
        if ($this->isGone($response) || ! $this->linksToTarget($response->body(), $mention->target_url)) {
            $mention->delete();

            return;
        }

        $parsed = $parse($response->body(), $mention->source_url, $mention->target_url);

        $mention->target()->associate($target);
        $mention->fill([
            'kind' => ($parsed->isReacji() ? WebmentionKind::Reacji : $parsed->kind)->value,
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
     * Whether the source is definitively gone, as opposed to merely failing.
     * Only these two say the page no longer exists; everything else says the
     * server could not answer for it right now.
     */
    private function isGone(Response $response): bool
    {
        return in_array($response->status(), [404, 410], strict: true);
    }

    /**
     * Whether the source page actually links to the target, matched without
     * the scheme so an http/https mismatch does not read as a forgery.
     */
    private function linksToTarget(string $html, string $target): bool
    {
        return str_contains($html, (string) preg_replace('#^https?://#', '', rtrim($target, '/')));
    }

    /**
     * Hold the first mention from a site, then trust that site. Same rule as
     * comments, keyed on the sending domain rather than a name.
     */
    private function statusFor(MentionData $parsed): CommentStatus
    {
        $host = parse_url((string) $parsed->authorUrl, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return CommentStatus::Pending;
        }

        return Webmention::query()->approved()->where('author_url', 'like', '%'.$host.'%')->exists()
            ? CommentStatus::Approved
            : CommentStatus::Pending;
    }
}
