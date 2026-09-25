<?php

namespace App\Data\LinkPage;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * A headed group of links on a link-in-bio card.
 */
final readonly class LinkSection implements Arrayable, JsonSerializable
{
    /**
     * @param  list<LinkItem>  $links
     */
    public function __construct(
        public string $heading,
        public array $links,
    ) {}

    /**
     * @return array{heading: string, links: list<array<string, ?string>>}
     */
    public function toArray(): array
    {
        return [
            'heading' => $this->heading,
            'links' => array_map(fn (LinkItem $link): array => $link->toArray(), $this->links),
        ];
    }

    /**
     * @return array{heading: string, links: list<array<string, ?string>>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
