<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Everything said in response to one entry: the reaction bar a reader can
 * click, and the thread of what everybody else did.
 */
final readonly class ConversationData implements Arrayable, JsonSerializable
{
    /**
     * @param  list<ReactionBucket>  $reactions  On-site emoji, one bucket per offered reaction.
     * @param  list<ConversationItem>  $responses  Comments, replies, likes and links, oldest first.
     */
    public function __construct(
        public string $type,
        public int $id,
        /** Absolute, because it is what the webmention box sends as `target`. */
        public string $url,
        public array $reactions,
        public array $responses,
    ) {}

    /** Whether nobody has said, sent or clicked anything at all. */
    public function isEmpty(): bool
    {
        return $this->responses === []
            && array_sum(array_map(fn (ReactionBucket $b): int => $b->count, $this->reactions)) === 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'url' => $this->url,
            'reactions' => array_map(fn (ReactionBucket $b): array => $b->toArray(), $this->reactions),
            'responses' => array_map(fn (ConversationItem $i): array => $i->toArray(), $this->responses),
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
