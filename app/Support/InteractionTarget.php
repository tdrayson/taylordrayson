<?php

namespace App\Support;

use App\Enums\TimelineType;
use App\Models\Concerns\Timelineable;
use App\Models\Page;
use App\Timeline\TypeRegistry;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves what a comment, reaction or webmention is allowed to be attached to.
 *
 * An allowlist rather than "any model with an id", because the webmention spec
 * requires an endpoint to reject a target it does not accept mentions for, and
 * the same rule should decide what the on-site form can post at. Listings
 * (archives, tags, trips, the timeline itself) are deliberately absent: a
 * response to a filtered view has nobody to notify and nothing to thread under.
 */
final class InteractionTarget
{
    /**
     * A model that accepts interactions, or null when the type is not on the
     * allowlist, the row is missing, or it is not publicly visible.
     */
    public static function resolve(string $type, int $id): ?Model
    {
        $class = self::types()[$type] ?? null;

        if ($class === null) {
            return null;
        }

        $model = $class::query()->find($id);

        return $model !== null && self::isVisible($model) ? $model : null;
    }

    /** Whether this model takes comments, reactions and mentions right now. */
    public static function accepts(Model $model): bool
    {
        return self::keyFor($model) !== null && self::isVisible($model);
    }

    /** The public type key for a model, or null when it accepts no interactions. */
    public static function keyFor(Model $model): ?string
    {
        $key = array_search($model::class, self::types(), strict: true);

        return $key === false ? null : $key;
    }

    /**
     * Type key to model class. Timeline types are read from the registry that
     * already drives the archive pages, so a new type is accepted here the
     * moment it is added there.
     *
     * @return array<string, class-string<Model>>
     */
    private static function types(): array
    {
        $types = array_map(
            fn (array $type): string => $type['model'],
            TypeRegistry::all(),
        );

        // A project is a standing description of a thing that exists, not a
        // post about a moment, so there is no occasion to respond to.
        unset($types[TimelineType::Project->value]);

        // Pages are not timeline entries, and are the whole reason a guestbook
        // works: /guestbook is a page like any other.
        return $types + ['page' => Page::class];
    }

    /**
     * Whether the public can see this right now. A draft must be rejected
     * rather than 404'd on write alone, or a mention could confirm that an
     * unpublished URL exists.
     */
    private static function isVisible(Model $model): bool
    {
        if ($model instanceof Page) {
            return (bool) $model->published;
        }

        return $model instanceof Timelineable && $model->shouldAppearOnTimeline();
    }
}
