<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * The stored copy of a replied-to post, as the entry page draws it under the byline.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class CitedPostData implements Arrayable, JsonSerializable
{
    public function __construct(
        public ?string $title,
        public ?string $authorName,
        /** A public path, only when a photo was actually stored. */
        public ?string $authorPhoto,
        /** The reply's own trimmed quote, or the fetched excerpt. */
        public ?string $quote,
        /** @var array{time: string, label: string, offset: string, iso: string}|null */
        public ?array $published,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'authorName' => $this->authorName,
            'authorPhoto' => $this->authorPhoto,
            'quote' => $this->quote,
            'published' => $this->published,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
