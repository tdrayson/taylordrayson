<?php

namespace App\Jobs;

use App\Actions\Webmentions\ParseMentionSource;
use App\Data\MentionData;
use App\Enums\CommentStatus;
use App\Enums\ReactionType;
use App\Enums\WebmentionKind;
use App\Models\Reaction;
use App\Models\Webmention;
use App\Support\SafeUrl;
use App\Support\WebmentionTarget;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
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

        // Gone, unreachable, or no longer linking here: the mention has been
        // retracted, whether or not the sender said so.
        if ($response === null || $response->failed() || ! $this->linksToTarget($response->body(), $mention->target_url)) {
            $mention->delete();

            return;
        }

        $parsed = $parse($response->body(), $mention->source_url, $mention->target_url);

        $mention->target()->associate($target);
        $mention->fill([
            'kind' => $parsed->isReacji() ? 'reacji' : $parsed->kind->value,
            'author_name' => $parsed->authorName,
            'author_url' => $parsed->authorUrl,
            'author_photo_path' => $parsed->authorPhoto,
            'content' => $parsed->content,
            'published_at' => $parsed->publishedAt,
            'status' => $this->statusFor($parsed),
            'verified_at' => now(),
            'last_checked_at' => now(),
        ])->save();

        $this->recordAsReaction($target, $parsed, $mention->source_url);
    }

    /**
     * A like, or a reacji whose emoji is one we offer, is counted in the
     * reaction bar rather than shown as a one-line reply. Keyed on the source
     * URL, so a given post can only ever count once.
     */
    private function recordAsReaction(Model $target, MentionData $parsed, string $sourceUrl): void
    {
        $type = match (true) {
            $parsed->kind === WebmentionKind::Like => ReactionType::Love,
            $parsed->isReacji() => ReactionType::fromEmoji((string) $parsed->emoji),
            default => null,
        };

        if ($type === null) {
            return;
        }

        // Through the relation, so the morph columns are set by Eloquent
        // rather than mass-assigned past the model's fillable list.
        $target->morphMany(Reaction::class, 'reactable')->firstOrCreate([
            'type' => $type,
            'identity_key' => hash('sha256', $sourceUrl),
        ]);
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
