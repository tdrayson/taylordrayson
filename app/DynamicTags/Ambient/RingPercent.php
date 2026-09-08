<?php

namespace App\DynamicTags\Ambient;

use App\Data\TagOption;
use App\DynamicTags\DynamicTag;
use App\Queries\NowState;
use App\Support\StateStore;

/** How far through a ring today is, as a percentage of its goal. */
class RingPercent extends DynamicTag
{
    public function __construct(private readonly string $ring) {}

    public function name(): string
    {
        return "ambient.rings.{$this->ring}.percent";
    }

    public function label(): string
    {
        return ucfirst($this->ring).' percent';
    }

    public function group(): string
    {
        return 'Ambient';
    }

    /**
     * @return list<TagOption>
     */
    public function options(): array
    {
        return [new TagOption('precision', 'Decimal places', ['0', '1'], '0')];
    }

    /**
     * Null when the ring has never been written, or its goal is missing or
     * zero, so the division stays safe.
     *
     * @param  array<string, string>  $options
     */
    public function resolve(array $options): ?float
    {
        $rings = (new NowState(new StateStore))()['rings'] ?? null;
        $goal = $rings[$this->ring.'Goal'] ?? null;

        if ($goal === null || $goal <= 0 || ! isset($rings[$this->ring])) {
            return null;
        }

        return $rings[$this->ring] / $goal * 100;
    }

    /**
     * @param  array<string, string>  $options
     */
    public function format(mixed $value, array $options): string
    {
        return number_format($value, (int) ($options['precision'] ?? 0)).'%';
    }
}
