<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * The playable media block on an appearance/podcast card. `thumbnail` is the
 * wide (16:9) card image; `audioCover` is the square artwork the bottom audio
 * player shows and is emitted only when set (podcasts). Appearance always
 * carries a (possibly null) responsive `srcset` for its stored cover; Podcast
 * has no such conversion, so the key is omitted entirely rather than emitted
 * as null.
 */
final readonly class MediaData implements Arrayable, JsonSerializable
{
    private function __construct(
        public int|string $id,
        public string $title,
        public ?string $audioUrl,
        public ?string $videoUrl,
        public ?string $thumbnail,
        public ?string $audioCover,
        public ?string $srcset,
        public ?int $duration,
        public string $url,
        private bool $withSrcset,
    ) {}

    /**
     * An appearance media block: carries the `srcset` key (possibly null).
     */
    public static function withSrcset(
        int|string $id,
        string $title,
        ?string $audioUrl,
        ?string $videoUrl,
        ?string $thumbnail,
        ?string $srcset,
        ?int $duration,
        string $url,
    ): self {
        return new self($id, $title, $audioUrl, $videoUrl, $thumbnail, null, $srcset, $duration, $url, true);
    }

    /**
     * A podcast media block: no `srcset` key at all. `audioCover` is the square
     * artwork for the audio player (the wide `thumbnail` fronts the card).
     */
    public static function withoutSrcset(
        int|string $id,
        string $title,
        ?string $audioUrl,
        ?string $videoUrl,
        ?string $thumbnail,
        ?string $audioCover,
        ?int $duration,
        string $url,
    ): self {
        return new self($id, $title, $audioUrl, $videoUrl, $thumbnail, $audioCover, null, $duration, $url, false);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'audioUrl' => $this->audioUrl,
            'videoUrl' => $this->videoUrl,
            'thumbnail' => $this->thumbnail,
        ];

        if ($this->audioCover !== null) {
            $data['audioCover'] = $this->audioCover;
        }

        if ($this->withSrcset) {
            $data['srcset'] = $this->srcset;
        }

        $data['duration'] = $this->duration;
        $data['url'] = $this->url;

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
