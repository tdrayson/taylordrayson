<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * A single card photo. The gallery shape always carries a route coordinate,
 * present or not; the cover-only shape omits the coordinate keys entirely.
 */
final readonly class PhotoData implements Arrayable, JsonSerializable
{
    private function __construct(
        public int $id,
        public string $src,
        public ?string $srcset,
        public string $full,
        public ?string $alt,
        public ?string $caption,
        public ?float $latitude,
        public ?float $longitude,
        private bool $withCoordinates,
    ) {}

    /**
     * A gallery photo (cover or gallery collection), always carrying the
     * (possibly null) route coordinate keys.
     */
    public static function gallery(int $id, string $src, ?string $srcset, string $full, ?string $alt, ?string $caption, ?float $latitude, ?float $longitude): self
    {
        return new self($id, $src, $srcset, $full, $alt, $caption, $latitude, $longitude, true);
    }

    /**
     * A cover-only photo (e.g. Article), with no coordinate keys at all.
     */
    public static function cover(int $id, string $src, ?string $srcset, string $full, ?string $alt, ?string $caption): self
    {
        return new self($id, $src, $srcset, $full, $alt, $caption, null, null, false);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'src' => $this->src,
            'srcset' => $this->srcset,
            'full' => $this->full,
            'alt' => $this->alt,
            'caption' => $this->caption,
        ];

        if ($this->withCoordinates) {
            $data['latitude'] = $this->latitude;
            $data['longitude'] = $this->longitude;
        }

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
