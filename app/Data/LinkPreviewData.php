<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * The card shown when hovering an internal link. One shape for every kind of
 * target, built through the named constructors rather than assembled by hand,
 * so a new field lands in one place instead of at every return site.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class LinkPreviewData implements Arrayable, JsonSerializable
{
    private function __construct(
        public string $url,
        public string $title,
        public ?string $excerpt,
        /** Drives the glyph, via entryTypes.js. */
        public string $type,
        /** The --color-* token key, which diverges from type for some types. */
        public string $accent,
        public ?string $date = null,
        public ?string $cover = null,
        /** Only maps have one, since only maps are rendered per theme. */
        public ?string $coverDark = null,
    ) {}

    /**
     * A timeline entry, from the same card the timeline itself draws.
     */
    public static function entry(
        string $url,
        string $title,
        ?string $excerpt,
        string $type,
        string $accent,
        ?string $date,
        ?string $cover,
        ?string $coverDark,
    ): self {
        return new self($url, $title, $excerpt, $type, $accent, $date, $cover, $coverDark);
    }

    public static function page(string $url, string $title, ?string $excerpt): self
    {
        return new self($url, $title, $excerpt, 'page', 'page');
    }

    public static function story(string $url, string $title, ?string $excerpt, string $accent): self
    {
        return new self($url, $title, $excerpt, 'story', $accent);
    }

    /**
     * A type archive (/activities), labelled with how much is in it.
     */
    public static function archive(string $url, string $label, string $type, string $accent, string $summary): self
    {
        return new self($url, $label, $summary, $type, $accent);
    }

    /**
     * A year, month or day page.
     */
    public static function period(string $url, string $label, string $summary): self
    {
        return new self($url, $label, $summary, 'period', 'article');
    }

    public static function tag(string $url, string $name, string $summary): self
    {
        return new self($url, $name, $summary, 'tag', 'article');
    }

    /**
     * The /now page, current as of the request that rendered the containing page.
     */
    public static function live(string $url, string $title, ?string $summary): self
    {
        return new self($url, $title, $summary, 'live', 'activity');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'type' => $this->type,
            'accent' => $this->accent,
            'date' => $this->date,
            'cover' => $this->cover,
            'coverDark' => $this->coverDark,
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
