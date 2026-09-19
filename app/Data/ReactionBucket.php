<?php

namespace App\Data;

use App\Enums\ReactionType;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * One emoji in the reaction bar: what it is, how many, and whether this
 * visitor is one of them.
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
        public bool $mine,
    ) {}

    public static function fromType(ReactionType $type, int $count, bool $mine): self
    {
        return new self($type->value, $type->emoji(), $type->label(), $count, $mine);
    }

    /**
     * @return array{key: string, emoji: string, label: string, count: int, mine: bool}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'emoji' => $this->emoji,
            'label' => $this->label,
            'count' => $this->count,
            'mine' => $this->mine,
        ];
    }

    /**
     * @return array{key: string, emoji: string, label: string, count: int, mine: bool}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
