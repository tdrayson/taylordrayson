<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * One row of App\Support\TypeCatalogue: how a single data type is drawn and
 * named wherever it appears. `toArray()` is what reaches the frontend, so the
 * optional fields stay conditional rather than serialising as nulls.
 */
final readonly class TypeMeta implements Arrayable, JsonSerializable
{
    /**
     * @param  string  $key  The type key, as used by cards, link previews and /new.
     * @param  string  $icon  An icon-registry name, resolved by Icon.vue.
     * @param  string  $accent  The --color-* token key.
     * @param  ?string  $plural  Set for the types with an archive page.
     * @param  ?string  $href  Where the type's archive lives, when it has one.
     * @param  ?string  $keywords  Command-palette synonyms.
     * @param  ?string  $eyebrow  The OG card's section label, when it is not the label.
     */
    public function __construct(
        public string $key,
        public string $icon,
        public string $label,
        public string $accent,
        public ?string $plural = null,
        public ?string $href = null,
        public ?string $keywords = null,
        private ?string $eyebrow = null,
    ) {}

    /**
     * The section label an OG card leads with, which is the plural for Places
     * and the label everywhere else.
     */
    public function eyebrow(): string
    {
        return $this->eyebrow ?? $this->label;
    }

    /**
     * The archive slug, e.g. 'this-week-with'. Null for types with no archive.
     */
    public function slug(): ?string
    {
        return $this->href !== null ? ltrim($this->href, '/') : null;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter([
            'icon' => $this->icon,
            'label' => $this->label,
            'plural' => $this->plural,
            'href' => $this->href,
            'accent' => $this->accent,
            'keywords' => $this->keywords,
        ], fn (?string $value): bool => $value !== null);
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
