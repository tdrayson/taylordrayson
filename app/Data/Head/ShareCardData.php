<?php

namespace App\Data\Head;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/** What a page's generated share card shows, as opposed to its head tags. */
final readonly class ShareCardData implements Arrayable, JsonSerializable
{
    /**
     * @param  string|null  $heading  The card's headline, when it differs from the page title.
     * @param  string|null  $accent  A hex colour or type token for the card's accent.
     * @param  string|null  $variant  The card layout, e.g. 'home'.
     */
    public function __construct(
        public ?string $heading = null,
        public ?string $eyebrow = null,
        public ?string $accent = null,
        public ?string $variant = null,
    ) {}

    /**
     * @return array{heading: ?string, eyebrow: ?string, accent: ?string, variant: ?string}
     */
    public function toArray(): array
    {
        return [
            'heading' => $this->heading,
            'eyebrow' => $this->eyebrow,
            'accent' => $this->accent,
            'variant' => $this->variant,
        ];
    }

    /**
     * @return array{heading: ?string, eyebrow: ?string, accent: ?string, variant: ?string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
