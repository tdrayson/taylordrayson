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
        public string $src,
        public ?string $srcset,
        public string $full,
        public ?float $latitude,
        public ?float $longitude,
        private bool $withCoordinates,
    ) {}

    /**
     * A gallery photo (cover or gallery collection), always carrying the
     * (possibly null) route coordinate keys.
     */
    public static function gallery(string $src, ?string $srcset, string $full, ?float $latitude, ?float $longitude): self
    {
        return new self($src, $srcset, $full, $latitude, $longitude, true);
    }

    /**
     * A cover-only photo (e.g. Article), with no coordinate keys at all.
     */
    public static function cover(string $src, ?string $srcset, string $full): self
    {
        return new self($src, $srcset, $full, null, null, false);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'src' => $this->src,
            'srcset' => $this->srcset,
            'full' => $this->full,
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
