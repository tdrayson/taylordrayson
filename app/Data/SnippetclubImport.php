<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * What importing one post did, for the command to report and total up.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class SnippetclubImport implements Arrayable, JsonSerializable
{
    /**
     * @param  list<string>  $tags
     * @param  list<string>  $notes
     */
    public function __construct(
        public string $slug,
        public bool $created,
        public int $nodes,
        public array $tags = [],
        public array $notes = [],
    ) {}

    /**
     * @return array{slug: string, created: bool, nodes: int, tags: list<string>, notes: list<string>}
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'created' => $this->created,
            'nodes' => $this->nodes,
            'tags' => $this->tags,
            'notes' => $this->notes,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
