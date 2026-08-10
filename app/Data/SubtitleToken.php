<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * A structured subtitle token, composed client-side by FeedItem.vue so distance
 * and weight react to the visitor's unit toggle. Each variant serialises only its
 * own keys: dist {t,m,p}, wt {t,kg,p}, text {t,v}, plus an optional `sep`
 * connective replacing the default ', '.
 */
final readonly class SubtitleToken implements Arrayable, JsonSerializable
{
    private function __construct(
        public string $t,
        public ?int $m,
        public ?float $kg,
        public ?int $p,
        public ?string $v,
        public ?string $sep,
    ) {}

    /**
     * A distance token: whole metres at the given display precision.
     */
    public static function dist(int $m, int $p): self
    {
        return new self('dist', $m, null, $p, null, null);
    }

    /**
     * A weight token: kilograms at the given display precision.
     */
    public static function wt(float $kg, int $p): self
    {
        return new self('wt', null, $kg, $p, null, null);
    }

    /**
     * A plain pre-formatted text token. `sep` overrides the default ', '
     * joiner FeedItem.vue uses when composing it onto the previous token.
     */
    public static function text(?string $v, ?string $sep = null): self
    {
        return new self('text', null, null, null, $v, $sep);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = match ($this->t) {
            'dist' => ['t' => $this->t, 'm' => $this->m, 'p' => $this->p],
            'wt' => ['t' => $this->t, 'kg' => $this->kg, 'p' => $this->p],
            default => ['t' => $this->t, 'v' => $this->v],
        };

        if ($this->sep !== null) {
            $data['sep'] = $this->sep;
        }

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
