<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * One printing of a book offered in the cover dialog: its cover, plus the ISBN,
 * year and page count that picking it fills in.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class BookEdition implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $cover,
        public ?string $isbn = null,
        public ?int $year = null,
        public ?int $pages = null,
    ) {}

    /**
     * @return array{cover: string, isbn: string|null, year: int|null, pages: int|null}
     */
    public function toArray(): array
    {
        return [
            'cover' => $this->cover,
            'isbn' => $this->isbn,
            'year' => $this->year,
            'pages' => $this->pages,
        ];
    }

    /**
     * @return array{cover: string, isbn: string|null, year: int|null, pages: int|null}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
