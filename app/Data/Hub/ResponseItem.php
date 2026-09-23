<?php

namespace App\Data\Hub;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * One thing a person said, as a sentence said out loud plus the entry it was
 * said on.
 */
final readonly class ResponseItem implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $id,
        public string $kind,
        public string $icon,
        public string $sentence,
        public string $entryTitle,
        public string $entryHref,
        public ?string $body,
        public string $age,
        public bool $isNew,
        public bool $markable = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'icon' => $this->icon,
            'sentence' => $this->sentence,
            'entry' => ['title' => $this->entryTitle, 'href' => $this->entryHref],
            'body' => $this->body,
            'age' => $this->age,
            'isNew' => $this->isNew,
            'markable' => $this->markable,
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
