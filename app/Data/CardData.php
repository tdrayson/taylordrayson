<?php

namespace App\Data;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * The timeline card payload every Timelineable model's card() returns. Mirrors
 * the pre-DTO array shape byte-for-byte when serialised: `titleLabel`,
 * `subtitleTokens` and `range` are only emitted when the producer set them
 * (matching the old array, which simply never carried those keys for types
 * that didn't use them).
 */
final readonly class CardData implements Arrayable, JsonSerializable
{
    /**
     * @param  ?list<SubtitleToken>  $subtitleTokens
     */
    public function __construct(
        public string $type,
        public string $icon,
        public string $title,
        public ?string $titleLabel,
        public ?string $subtitle,
        public ?array $subtitleTokens,
        public CarbonInterface $occurredAt,
        public string $accent,
        public ?RangeData $range,
        public CardMeta $meta,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'type' => $this->type,
            'icon' => $this->icon,
            'title' => $this->title,
        ];

        if ($this->titleLabel !== null) {
            $data['titleLabel'] = $this->titleLabel;
        }

        $data['subtitle'] = $this->subtitle;

        if ($this->subtitleTokens !== null) {
            $data['subtitleTokens'] = array_map(fn (SubtitleToken $token): array => $token->toArray(), $this->subtitleTokens);
        }

        $data['occurred_at'] = $this->occurredAt;
        $data['accent'] = $this->accent;

        if ($this->range !== null) {
            $data['range'] = $this->range->toArray();
        }

        $data['meta'] = $this->meta->toArray();

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
