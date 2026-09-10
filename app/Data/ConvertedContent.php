<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * A converted document plus what the conversion could not decide alone.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class ConvertedContent implements Arrayable, JsonSerializable
{
    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  list<string>  $notes  Places a human should look, not failures.
     */
    public function __construct(
        public array $nodes = [],
        public array $notes = [],
    ) {}

    /**
     * @return array{nodes: list<array<string, mixed>>, notes: list<string>}
     */
    public function toArray(): array
    {
        return ['nodes' => $this->nodes, 'notes' => $this->notes];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
