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

    /** A finer heading nested under `group()`, for a category with sub-groups; null when it has none. */
    public function subgroup(): ?string
    {
        return null;
    }

    /**
     * Null means the tag has nothing to report for these options.
     *
     * @param  array<string, string>  $options
     */
    abstract public function resolve(array $options): mixed;

    /**
     * Every tag defaults to inline-only; override to allow other placements.
     *
     * @return list<Placement>
     */
    public function supports(): array
    {
        return [Placement::Inline];
    }

    /**
     * The fields the tag editor renders; empty when the tag takes no options.
     *
     * @return list<TagOption>
     */
    public function options(): array
    {
        return [];
    }

    /**
     * Whether this tag can only resolve once an option is chosen, e.g. a
     * network for `site.social`, as opposed to having nothing to report
     * because the underlying data is simply absent. Drives the "Not set" vs
     * "No data" wording the menu shows for a preview that resolved to null.
     */
    public function needsOption(): bool
    {
        return false;
    }

    /**
     * Default rendering when a subclass doesn't override it. A bare `(string)`
     * cast turns `false` into `''` and `true` into `'1'`, so a boolean is
     * rendered as a word before it ever reaches that cast.
     *
     * @param  array<string, string>  $options
     */
    public function format(mixed $value, array $options): string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return is_int($value) ? number_format($value) : (string) $value;
    }

    /**
     * The URL a `dynamicHref` markDef or tagged image resolves to. Defaults to
     * {@see format()}; override when the link target differs from the display text.
     *
     * @param  array<string, string>  $options
     */
    public function href(mixed $value, array $options): string
    {
        return $this->format($value, $options);
    }
}
