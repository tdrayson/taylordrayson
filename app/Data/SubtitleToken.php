<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * A structured subtitle token, composed client-side by FeedItem.vue so distance
 * and weight react to the visitor's unit toggle. Each variant serialises only its
 * own keys: dist {t,m,p}, wt {t,kg,p}, dur {t,s,u?}, kcal {t,kcal,u?}, gbp {t,gbp},
 * ppl {t,ppl}, text {t,v}, plus an optional `sep` connective replacing the default
 * ', '. `u` names the unit spelled out, e.g. "minutes" rather than "2h 49m".
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
        public ?int $s = null,
        public ?int $kcal = null,
        public ?float $amount = null,
        public ?string $u = null,
    ) {}

    /**
     * A distance token: whole metres at the given display precision. `sep`
     * overrides the default ', ' joiner, for a token sitting inside a sentence
     * rather than in a list.
     */
    public static function dist(int $m, int $p, ?string $sep = null): self
    {
        return new self('dist', $m, null, $p, null, $sep);
    }

    /**
     * A weight token: kilograms at the given display precision. `sep` overrides
     * the default ', ' joiner, as on dist().
     */
    public static function wt(float $kg, int $p, ?string $sep = null): self
    {
        return new self('wt', null, $kg, $p, null, $sep);
    }

    /**
     * A duration token in whole seconds, shown as "3h 20m" unless silly units swap it.
     */
    public static function dur(int $seconds, ?string $sep = null, ?string $unit = null): self
    {
        return new self('dur', null, null, null, null, $sep, s: $seconds, u: $unit);
    }

    /**
     * An energy token in kilocalories, shown as "528 kcal" unless silly units swap it.
     */
    public static function kcal(int $kcal, ?string $sep = null, ?string $unit = null): self
    {
        return new self('kcal', null, null, null, null, $sep, kcal: $kcal, u: $unit);
    }

    /**
     * A money token in pounds, shown as "£45.67" unless silly units swap it.
     */
    public static function gbp(float $pounds, ?string $sep = null): self
    {
        return new self('gbp', null, null, null, null, $sep, amount: $pounds);
    }

    /**
     * A fuel price in pounds per litre, shown as "145.9p/L" unless silly units swap it.
     */
    public static function ppl(float $pounds, ?string $sep = null): self
    {
        return new self('ppl', null, null, null, null, $sep, amount: $pounds);
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
            'dur' => ['t' => $this->t, 's' => $this->s, ...($this->u !== null ? ['u' => $this->u] : [])],
            'kcal' => ['t' => $this->t, 'kcal' => $this->kcal, ...($this->u !== null ? ['u' => $this->u] : [])],
            'gbp' => ['t' => $this->t, 'gbp' => $this->amount],
            'ppl' => ['t' => $this->t, 'ppl' => $this->amount],
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
