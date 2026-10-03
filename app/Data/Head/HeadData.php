<?php

namespace App\Data\Head;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/** A page's document head, rendered by AppHead.vue. */
final readonly class HeadData implements Arrayable, JsonSerializable
{
    /**
     * @param  string|null  $title  The page title; AppHead appends the site name.
     * @param  string|null  $canonical  Null for the page's own URL.
     * @param  string  $type  The og:type, 'website' or 'article'.
     * @param  string|null  $image  An absolute share image URL, or null for none.
     * @param  list<MetaTagData>  $meta
     * @param  list<LinkTagData>  $links
     */
    public function __construct(
        public ?string $title,
        public string $description,
        public ?string $canonical = null,
        public bool $noindex = false,
        public string $type = 'website',
        public ?string $image = null,
        public ShareCardData $card = new ShareCardData,
        public array $meta = [],
        public array $links = [],
    ) {}

    /** A copy with the given fields replaced, named as the constructor names them. */
    public function with(mixed ...$changes): self
    {
        return new self(...[...get_object_vars($this), ...$changes]);
    }

    /**
     * @return array{title: ?string, description: string, canonical: ?string, noindex: bool, type: string, image: ?string, card: array<string, ?string>, meta: list<array<string, string>>, links: list<array<string, string>>}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'canonical' => $this->canonical,
            'noindex' => $this->noindex,
            'type' => $this->type,
            'image' => $this->image,
            'card' => $this->card->toArray(),
            'meta' => array_map(fn (MetaTagData $tag): array => $tag->toArray(), $this->meta),
            'links' => array_map(fn (LinkTagData $link): array => $link->toArray(), $this->links),
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
