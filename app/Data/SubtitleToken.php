<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * A single structured subtitle token, consumed by FeedItem.vue to compose the
 * card subtitle client-side (so distance/weight react to the visitor's unit
 * toggle instead of being baked into a pre-formatted string).
 *
 * Only the keys relevant to the token's variant are serialised: dist emits
 * {t,m,p}, wt emits {t,kg,p}, text emits {t,v}.
 */
final readonly class SubtitleToken implements Arrayable, JsonSerializable
{
    private function __construct(
        public string $t,
        public ?int $m,
        public ?float $kg,
        public ?int $p,
        public ?string $v,
    ) {}

    /**
     * A distance token: whole metres at the given display precision.
     */
    public static function dist(int $m, int $p): self
    {
        return new self('dist', $m, null, $p, null);
    }

    /**
     * A weight token: kilograms at the given display precision.
     */
    public static function wt(float $kg, int $p): self
    {
        return new self('wt', null, $kg, $p, null);
    }

    /**
     * A plain pre-formatted text token.
     */
    public static function text(?string $v): self
    {
        return new self('text', null, null, null, $v);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return match ($this->t) {
            'dist' => ['t' => $this->t, 'm' => $this->m, 'p' => $this->p],
            'wt' => ['t' => $this->t, 'kg' => $this->kg, 'p' => $this->p],
            default => ['t' => $this->t, 'v' => $this->v],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
