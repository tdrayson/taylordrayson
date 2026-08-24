<?php

namespace App\Data;

use App\Models\Tag;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * A tag as the frontend renders it: what to call it, and where it goes.
 *
 * The `url` is here so no template builds `/tags/{slug}` itself. `slug` stays
 * in the payload because components key their `v-for` on it.
 */
final readonly class TagLink implements Arrayable, JsonSerializable
{
    private function __construct(
        public string $name,
        public string $slug,
        public string $url,
    ) {}

    public static function for(Tag $tag): self
    {
        return new self($tag->name, $tag->slug, $tag->url());
    }

    /**
     * @return array{name: string, slug: string, url: string}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'slug' => $this->slug, 'url' => $this->url];
    }

    /**
     * @return array{name: string, slug: string, url: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
