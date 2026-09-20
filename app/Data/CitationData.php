<?php

namespace App\Data;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * What one read of somebody else's post found, before it is stored.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class CitationData implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $url,
        public string $site,
        public ?string $title,
        public ?string $authorName,
        public ?string $authorPhotoUrl,
        public ?string $excerpt,
        public ?CarbonInterface $publishedAt,
        /** The offset the author's date carried, such as "-07:00". */
        public ?string $publishedTimezone,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'site' => $this->site,
            'title' => $this->title,
            'author_name' => $this->authorName,
            'author_photo_url' => $this->authorPhotoUrl,
            'excerpt' => $this->excerpt,
            'published_at' => $this->publishedAt,
            'published_timezone' => $this->publishedTimezone,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
