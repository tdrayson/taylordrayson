<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * What a share of an entry would show, drawn from the editor's unsaved values.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class SharePreviewData implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $title,
        public ?string $description,
        /** The card as a PNG data URI. */
        public string $image,
    ) {}

    /**
     * @return array{title: string, description: ?string, image: string}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'image' => $this->image,
        ];
    }

    /**
     * @return array{title: string, description: ?string, image: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
