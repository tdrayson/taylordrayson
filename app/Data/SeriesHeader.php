<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Identity and artwork for a series show page header.
 */
final readonly class SeriesHeader implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $slug,
        public string $title,
        public ?int $year,
        public ?string $overview,
        public ?string $poster,
        public ?string $backdrop,
        public ?string $logo,
        public ?string $network,
        public int|float|string|null $rating,
        public ?string $platformUrl,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'year' => $this->year,
            'overview' => $this->overview,
            'poster' => $this->poster,
            'backdrop' => $this->backdrop,
            'logo' => $this->logo,
            'network' => $this->network,
            'rating' => $this->rating,
            'platformUrl' => $this->platformUrl,
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
