<?php

namespace App\Data\Hub;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * One thing waiting on a decision. `kind` is comment, failure or draft, which
 * is what the list tints its glyph on.
 *
 * @phpstan-type Action array{label: string, action: string, variant: string}
 */
final readonly class AttentionItem implements Arrayable, JsonSerializable
{
    /**
     * @param  list<Action>  $actions
     */
    public function __construct(
        public string $id,
        public string $kind,
        public string $icon,
        public string $title,
        public ?string $detail,
        public ?string $body,
        public string $age,
        public string $href,
        public array $actions = [],
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
            'title' => $this->title,
            'detail' => $this->detail,
            'body' => $this->body,
            'age' => $this->age,
            'href' => $this->href,
            'actions' => $this->actions,
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
