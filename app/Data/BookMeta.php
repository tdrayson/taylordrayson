<?php

namespace App\Data;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use JsonSerializable;

/**
 * The `books.meta` column, typed. No vendor ids are kept: the ISBN is what
 * identifies a book, whichever service filled it in.
 */
final readonly class BookMeta implements Arrayable, Castable, JsonSerializable
{
    /** Keys spelled out below. Everything else survives via `$extra`. */
    private const NAMED = ['author', 'year', 'isbn'];

    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public ?string $author = null,
        public ?int $year = null,
        public ?string $isbn = null,
        public array $extra = [],
    ) {}

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public static function from(?array $meta): self
    {
        $meta ??= [];

        return new self(
            author: MetaValue::string($meta['author'] ?? null),
            year: MetaValue::int($meta['year'] ?? null),
            isbn: MetaValue::string($meta['isbn'] ?? null),
            extra: Arr::except($meta, self::NAMED),
        );
    }

    /**
     * A copy with `$changes` (in stored spelling) laid over the top, so a
     * writer can update one key without restating the rest.
     *
     * @param  array<string, mixed>  $changes
     */
    public function merge(array $changes): self
    {
        return self::from([...$this->toArray(), ...$changes]);
    }

    /**
     * The stored spelling. Keys the source never set stay absent rather than
     * reappearing as null.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return MetaValue::compact([
            'author' => $this->author,
            'year' => $this->year,
            'isbn' => $this->isbn,
        ]) + $this->extra;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param  array<int, string>  $arguments
     */
    public static function castUsing(array $arguments): CastsAttributes
    {
        return new MetaCast(self::class);
    }
}
