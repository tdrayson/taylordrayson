<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * The book on the go, as the /now reading widget draws it.
 */
final readonly class ReadingData implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $title,
        public string $author,
        public ?string $cover,
        public ?int $percent,
        public bool $finished,
    ) {}

    /**
     * @return array{title: string, author: string, cover: string|null, percent: int|null, finished: bool}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'author' => $this->author,
            'cover' => $this->cover,
            'percent' => $this->percent,
            'finished' => $this->finished,
        ];
    }

    /**
     * @return array{title: string, author: string, cover: string|null, percent: int|null, finished: bool}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
