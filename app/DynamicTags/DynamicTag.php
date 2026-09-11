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
     * Whether this tag can show a supporting icon beside its value. Off by
     * default; a tag opts in by overriding this to `true` and adding
     * {@see iconOption()} to its own {@see options()}.
     */
    public function supportsIcon(): bool
    {
        return false;
    }

    /** The "Show icon" toggle a supporting tag appends to its own options list. */
    protected function iconOption(): TagOption
    {
        return new TagOption('icon', 'Icon', boolean: true);
    }

    /**
     * Extra data an enabled icon needs beyond the resolved `$value` and
     * `$options`, e.g. battery's charging state. Null means the client can
     * build the icon from `$value`/`$options` alone.
     *
     * @param  array<string, string>  $options
     */
    public function iconPayload(mixed $value, array $options): mixed
    {
        return null;
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
