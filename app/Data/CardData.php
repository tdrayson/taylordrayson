<?php

namespace App\Data;

use App\Enums\TimelineType;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * The timeline card payload a presenter returns. `titleLabel`, `subtitleTokens`
 * and `range` are emitted only when the producer set them, and `type` serialises
 * to the enum's backed string value.
 */
final readonly class CardData implements Arrayable, JsonSerializable
{
    /**
     * @param  ?list<SubtitleToken>  $subtitleTokens
     */
    public function __construct(
        public TimelineType $type,
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
            'type' => $this->type->value,
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
