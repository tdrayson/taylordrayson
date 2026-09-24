<?php

namespace App\Data;

use App\Enums\ReactionType;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * One emoji in the reaction bar: what it is and how many.
 *
 * `key` is a ReactionType value for the five buckets the site offers, and the
 * emoji itself for one that arrived by webmention and matches none of them.
 * The frontend only ever renders it, so both shapes are the same to it.
 */
final readonly class ReactionBucket implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $key,
        public string $emoji,
        public string $label,
        public int $count,
    ) {}

    public static function fromType(ReactionType $type, int $count): self
    {
        return new self($type->value, $type->emoji(), $type->label(), $count);
    }

    /**
     * @return array{key: string, emoji: string, label: string, count: int}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'emoji' => $this->emoji,
            'label' => $this->label,
            'count' => $this->count,
        ];
    }

    /**
     * @return array{key: string, emoji: string, label: string, count: int}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
