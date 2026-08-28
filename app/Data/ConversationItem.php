<?php

namespace App\Data;

use App\Enums\WebmentionKind;
use App\Models\Comment;
use App\Models\Webmention;
use App\Support\LocalTime;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * One thing somebody said, whichever table it came from.
 *
 * The frontend renders these without knowing whether a first-party comment or
 * a webmention is behind it, which is the whole point of merging them here.
 */
final readonly class ConversationItem implements Arrayable, JsonSerializable
{
    private function __construct(
        public string $id,
        public string $kind,
        public string $authorName,
        public ?string $authorUrl,
        public ?string $authorPhoto,
        public ?string $body,
        public CarbonInterface $occurredAt,
        public ?int $parentId,
        /** The row id, when replying to this is possible; null for a mention. */
        public ?int $commentId,
        /** Where the response lives, for a webmention; null for a comment. */
        public ?string $sourceUrl,
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
        );
    }

    public static function fromWebmention(Webmention $mention): self
    {
        return new self(
            id: 'mention-'.$mention->id,
            kind: $mention->kind()?->value ?? WebmentionKind::Mention->value,
            // A source with no h-card still said something, so it is shown by
            // the only name it has.
            authorName: $mention->author_name ?: self::hostOf($mention->source_url),
            authorUrl: $mention->author_url,
            authorPhoto: $mention->author_photo_path,
            body: $mention->content,
            occurredAt: $mention->published_at ?? $mention->created_at,
            parentId: null,
            commentId: null,
            sourceUrl: $mention->source_url,
        );
    }

    private static function hostOf(string $url): string
    {
        return (string) (parse_url($url, PHP_URL_HOST) ?: $url);
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
