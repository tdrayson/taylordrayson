<?php

namespace App\Data;

use App\Enums\Source;
use App\Enums\WebmentionKind;
use App\Models\Comment;
use App\Models\Concerns\Timelineable;
use App\Models\Mention;
use App\Models\SyndicatedResponse;
use App\Models\Webmention;
use App\Support\EntryInstant;
use App\Support\EntryName;
use App\Support\LocalTime;
use App\Support\PortableText;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
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
        /** The name of the post a mention came from; null for a comment. */
        public ?string $title,
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
        /** The entry's timezone, so every response in its stream renders on the same clock; null renders as home time. */
        public ?string $timezone,
        /** The service a syndicated response came from; null for everything else. */
        public ?string $source = null,
        /** The same service, as it is said out loud ("Strava"); null for everything else. */
        public ?string $sourceName = null,
    ) {}

    public static function fromComment(Comment $comment, ?string $timezone): self
    {
        return new self(
            id: $comment->fragment(),
            kind: 'comment',
            authorName: $comment->author_name,
            authorUrl: null,
            authorPhoto: null,
            title: null,
            body: $comment->body,
            occurredAt: $comment->created_at,
            parentId: $comment->parent_id,
            commentId: $comment->id,
            sourceUrl: null,
            emoji: null,
            timezone: $timezone,
        );
    }

    public static function fromWebmention(Webmention $mention, ?string $timezone): self
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
            title: $mention->title,
            // A reacji's body is its emoji, which the marker already shows.
            body: $isReacji || blank($mention->content) ? null : $mention->content,
            occurredAt: $mention->published_at ?? $mention->created_at,
            parentId: null,
            commentId: null,
            sourceUrl: $mention->source_url,
            emoji: $isReacji ? trim(PortableText::plainText($mention->content ?? [])) : null,
            timezone: $timezone,
        );
    }

    /**
     * A response left on one of my posts somewhere else.
     *
     * Carries no microformats: nothing here linked back, so publishing it as an
     * h-cite would tell a parser something that is not true.
     */
    public static function fromSyndicated(SyndicatedResponse $response, ?string $timezone): self
    {
        return new self(
            id: 'syndicated-'.$response->id,
            kind: $response->kind->value,
            authorName: $response->author_name,
            authorUrl: null,
            authorPhoto: $response->author_photo_path === null
                ? null
                : '/'.ltrim($response->author_photo_path, '/'),
            title: null,
            body: $response->body,
            occurredAt: $response->occurred_at,
            parentId: null,
            commentId: null,
            sourceUrl: $response->url,
            emoji: $response->emoji,
            timezone: $timezone,
            source: $response->source,
            sourceName: Source::tryFrom($response->source)?->label() ?? $response->source,
        );
    }

    /**
     * One of my own entries linking to another.
     *
     * Carries no body. The prose lives on the source, and copying it here would
     * publish the same words on two pages and in two feeds; the title and the
     * link are what the reader needs to get to it.
     */
    public static function fromMention(Mention $mention, ?string $timezone): self
    {
        $source = $mention->source;
        $sourceTimezone = $source instanceof Timelineable ? $source->timezone() : null;

        // occurred_at is a wall-clock reading, not an instant: it must be
        // converted using the source's own timezone, which may differ from
        // the timezone this item is rendered in.
        $occurredAt = $source->occurred_at !== null
            ? EntryInstant::utc($source->occurred_at, $sourceTimezone)
            : null;

        return new self(
            id: 'linked-'.$mention->id,
            kind: 'mention-internal',
            authorName: (string) config('feed.author_name'),
            // No author URL: linking my own name back to my own site, from a
            // page on it, gives the reader nowhere new to go.
            authorUrl: null,
            authorPhoto: (string) config('feed.author_photo'),
            title: self::titleOf($source),
            body: null,
            occurredAt: $occurredAt ?? $source->created_at,
            parentId: null,
            commentId: null,
            sourceUrl: $source->url(),
            emoji: null,
            timezone: $timezone,
        );
    }

    /**
     * What to call the entry a mention came from.
     *
     * Not possessive: this byline already names who wrote it, so "Taylor
     * Drayson mentioned this in my note" would be talking about himself in two
     * voices at once.
     */
    private static function titleOf(Model $source): string
    {
        return EntryName::for($source);
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
     * The site to name a response by: "linked to this from robin.example" reads
     * better than the full URL, which the link itself carries anyway.
     *
     * Null for one of my own entries, whose source URL is a path on this site.
     * Naming the host there would close the sentence with my own address.
     */
    private function sourceHost(): ?string
    {
        if ($this->sourceUrl === null || parse_url($this->sourceUrl, PHP_URL_HOST) === null) {
            return null;
        }

        return self::hostOf($this->sourceUrl);
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
            'title' => $this->title,
            'body' => $this->body,
            // The site's timestamp shape, formatted server-side like every
            // other one: every response here is a real instant, converted
            // into the entry's own timezone so one stream shares one clock.
            'occurredAt' => LocalTime::forInstant($this->occurredAt, $this->timezone),
            'parentId' => $this->parentId,
            'commentId' => $this->commentId,
            'sourceUrl' => $this->sourceUrl,
            'sourceHost' => $this->sourceHost(),
            'emoji' => $this->emoji,
            'source' => $this->source,
            'sourceName' => $this->sourceName,
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
