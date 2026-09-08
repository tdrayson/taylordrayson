<?php

namespace App\DynamicTags;

use App\Data\TagOption;
use App\Enums\Placement;

/**
 * One referenceable value. `resolve()` returns the typed value and `format()`
 * renders it, so a consumer can take either the number or the display string.
 */
abstract class DynamicTag
{
    /** The dotted path authors write, e.g. `entries.count`. */
    abstract public function name(): string;

    abstract public function label(): string;

    /** The heading the editor groups this tag under. */
    abstract public function group(): string;

    /**
     * @param  array<string, string>  $options
     */
    abstract public function resolve(array $options): mixed;

    /**
     * @return list<Placement>
     */
    public function supports(): array
    {
        return [Placement::Inline];
    }

    /**
     * @return list<TagOption>
     */
    public function options(): array
    {
        return [];
    }

    /**
     * @param  array<string, string>  $options
     */
    public function format(mixed $value, array $options): string
    {
        return is_int($value) ? number_format($value) : (string) $value;
    }
}
