<?php

namespace App\Data;

use App\Enums\WebmentionKind;
use App\Models\Comment;
use App\Models\Webmention;
use App\Support\LocalTime;
use App\Support\PortableText;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use JsonSerializable;

/**
 * One thing somebody said, whichever table it came from.
 *
 * Everything lands here, gestures included. A facepile exists to compress the
 * wall of social likes a backfeed brings, and this site has no backfeed: the
 * realistic traffic is a few replies and links from other people's blogs, and
 * one stream in time order reads better than three lists. Weight comes from
 * whether there is prose, not from which kind it is.
 */
final readonly class ConversationItem implements Arrayable, JsonSerializable
{
    private function __construct(
        public string $id,
        public string $kind,
        public string $authorName,
        public ?string $authorUrl,
        public ?string $authorPhoto,
        /** @var array<int, array<string, mixed>>|null Portable Text, or null for a gesture. */
        public ?array $body,
        public CarbonInterface $occurredAt,
        public ?int $parentId,
        /** The row id, when replying to this is possible; null for a mention. */
        public ?int $commentId,
        /** Where the response lives, for a webmention; null for a comment. */
        public ?string $sourceUrl,
        /** The emoji actually sent, for a reacji; null for everything else. */
        public ?string $emoji,
    ) {}

    public static function fromComment(Comment $comment): self
    {
        return new self(
            id: $comment->fragment(),
            kind: 'comment',
            authorName: $comment->author_name,
            authorUrl: null,
            authorPhoto: null,
            body: $comment->body,
            occurredAt: $comment->created_at,
            parentId: $comment->parent_id,
            commentId: $comment->id,
            sourceUrl: null,
            emoji: null,
        );
    }

    public static function fromWebmention(Webmention $mention): self
    {
        $kind = $mention->kind()?->value ?? WebmentionKind::Mention->value;
        $isReacji = $kind === WebmentionKind::Reacji->value;

        return new self(
            id: 'mention-'.$mention->id,
            kind: $kind,
            // A source with no h-card still said something, so it is shown by
            // the only name it has.
            authorName: $mention->author_name ?: self::hostOf($mention->source_url),
            authorUrl: $mention->author_url,
            authorPhoto: $mention->author_photo_path === null
                ? null
                : '/'.ltrim($mention->author_photo_path, '/'),
            // A reacji's body is its emoji, which the marker already shows.
            // Converted here rather than stored converted: a mention's
            // content genuinely is text, since that is all mf2 gives us. The
            // stream renders one shape, so the shaping happens at its edge.
            body: $isReacji || blank($mention->content)
                ? null
                : PortableText::fromPlainText((string) $mention->content),
            occurredAt: $mention->published_at ?? $mention->created_at,
            parentId: null,
            commentId: null,
            sourceUrl: $mention->source_url,
            emoji: $isReacji ? trim((string) $mention->content) : null,
        );
    }

    /**
     * The site a response came from, as somebody would say it out loud. The
     * `www.` is dropped: it is how the URL is written, not what the site is
     * called, and "via www.strava.com" reads as an address rather than a place.
     */
    private static function hostOf(string $url): string
    {
        $host = (string) (parse_url($url, PHP_URL_HOST) ?: $url);

        return Str::chopStart($host, 'www.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'authorName' => $this->authorName,
            'authorUrl' => $this->authorUrl,
            'authorPhoto' => $this->authorPhoto,
            'body' => $this->body,
            // The site's timestamp shape, formatted server-side like every
            // other one: a comment is a real instant, shown in home time.
            'occurredAt' => LocalTime::for($this->occurredAt, null),
            'parentId' => $this->parentId,
            'commentId' => $this->commentId,
            'sourceUrl' => $this->sourceUrl,
            // The bare host: "linked to this from robin.example" reads better
            // than the full URL, which the link itself carries anyway.
            'sourceHost' => $this->sourceUrl === null ? null : self::hostOf($this->sourceUrl),
            'emoji' => $this->emoji,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
