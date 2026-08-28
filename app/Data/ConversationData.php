<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Everything said in response to one entry: the reaction bar, the thread, and
 * the links that are only links.
 */
final readonly class ConversationData implements Arrayable, JsonSerializable
{
    /**
     * @param  list<ReactionBucket>  $reactions
     * @param  list<ConversationItem>  $replies  Comments and reply-shaped mentions, oldest first.
     * @param  list<ConversationItem>  $mentions  Bare links, which have no thread position.
     */
    public function __construct(
        public string $type,
        public int $id,
        public array $reactions,
        public array $replies,
        public array $mentions,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'reactions' => array_map(fn (ReactionBucket $b): array => $b->toArray(), $this->reactions),
            'replies' => array_map(fn (ConversationItem $i): array => $i->toArray(), $this->replies),
            'mentions' => array_map(fn (ConversationItem $i): array => $i->toArray(), $this->mentions),
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
