<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * What one Kindle snapshot did, per book.
 */
final readonly class KindleSyncResult implements Arrayable, JsonSerializable
{
    public function __construct(
        public int $created = 0,
        public int $updated = 0,
        public int $unchanged = 0,
        public int $skipped = 0,
        public int $published = 0,
    ) {}

    /**
     * @return array{created: int, updated: int, unchanged: int, skipped: int, published: int}
     */
    public function toArray(): array
    {
        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'unchanged' => $this->unchanged,
            'skipped' => $this->skipped,
            'published' => $this->published,
        ];
    }

    /**
     * @return array{created: int, updated: int, unchanged: int, skipped: int, published: int}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
