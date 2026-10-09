<?php

namespace App\Data\Head;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/** One `<link>` tag. Optional attributes are left out when unset, so none renders empty. */
final readonly class LinkTagData implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $rel,
        public string $href,
        public ?string $type = null,
        public ?string $title = null,
        public ?string $hreflang = null,
    ) {}

    /**
     * @return array{rel: string, href: string, type?: string, title?: string, hreflang?: string}
     */
    public function toArray(): array
    {
        return array_filter([
            'rel' => $this->rel,
            'href' => $this->href,
            'type' => $this->type,
            'title' => $this->title,
            'hreflang' => $this->hreflang,
        ], fn (?string $value): bool => $value !== null);
    }

    /**
     * @return array{rel: string, href: string, type?: string, title?: string, hreflang?: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
