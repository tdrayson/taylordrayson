<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * A Zight share page resolved to something a `<video>` tag can play: the file
 * itself, plus the poster frame Zight renders for it.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class ZightVideo implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $url,
        public ?string $poster = null,
        /** A GIF rather than a video, so it belongs in an image node. */
        public bool $isImage = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'poster' => $this->poster,
            'isImage' => $this->isImage,
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
