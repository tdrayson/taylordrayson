<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * A single show in the series index poster grid.
 */
final readonly class SeriesSummary implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $slug,
        public string $title,
        public ?int $year,
        public ?string $poster,
        public ?int $progress,
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
            'poster' => $this->poster,
            'progress' => $this->progress,
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
