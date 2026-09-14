<?php

namespace App\Data;

use App\Support\BookProgress;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * One book from a Kindle snapshot, with the device's float noise rounded off.
 */
final readonly class KindleItem implements Arrayable, JsonSerializable
{
    private const BOOK_TYPES = ['EBOK', 'PDOC'];

    public function __construct(
        public string $cdeKey,
        public ?string $type,
        public string $title,
        public float $percent,
        public CarbonImmutable $lastOpenedAt,
    ) {}

    /**
     * @param  array{cde_key: string, type?: string|null, title: string, percent: int|float|string, last_open: int|string}  $item
     */
    public static function from(array $item): self
    {
        return new self(
            cdeKey: $item['cde_key'],
            type: $item['type'] ?? null,
            title: trim($item['title']),
            percent: BookProgress::round((float) $item['percent']),
            lastOpenedAt: CarbonImmutable::createFromTimestampUTC((int) $item['last_open']),
        );
    }

    /** Magazines, newspapers and untyped items sync too, and are not books. */
    public function isBook(): bool
    {
        return $this->type !== null && in_array($this->type, self::BOOK_TYPES, true);
    }

    /**
     * @return array{cde_key: string, type: string|null, title: string, percent: float, last_open: int}
     */
    public function toArray(): array
    {
        return [
            'cde_key' => $this->cdeKey,
            'type' => $this->type,
            'title' => $this->title,
            'percent' => $this->percent,
            'last_open' => $this->lastOpenedAt->getTimestamp(),
        ];
    }

    /**
     * @return array{cde_key: string, type: string|null, title: string, percent: float, last_open: int}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
