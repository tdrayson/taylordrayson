<?php

namespace App\Support;

use App\Enums\EntryStatus;
use App\Models\Concerns\Timelineable;
use App\Models\Page;
use App\Presenters\CardPresenter;
use App\Timeline\TypeRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Resolves what a comment, reaction or webmention is allowed to be attached to,
 * and in which direction.
 *
 * An allowlist rather than "any model with an id", because the webmention spec
 * requires an endpoint to reject a target it does not accept mentions for, and
 * the same rule should decide what the on-site form can post at. Listings
 * (archives, tags, trips, the timeline itself) are deliberately absent: a
 * response to a filtered view has nobody to notify and nothing to thread under.
 *
 * A private entry takes comments and reactions once unlocked, and receives
 * mentions, but sends none: nobody else could verify a link behind the password.
 */
final class InteractionTarget
{
    /**
     * The model a comment or reaction from this request may be left on, or null
     * when the type is not on the allowlist, the row is missing, or it takes none.
     */
    public static function resolveFor(Request $request, string $type, int $id): ?Model
    {
        $class = self::types()[$type] ?? null;

        if ($class === null) {
            return null;
        }

        $model = $class::query()->find($id);

        return $model !== null && self::takesCommentsAndReactionsFrom($model, $request) ? $model : null;
    }

    /**
     * Whether this request may comment on, react to, or read the conversation of this model.
     *
     * @param  Request|null  $request  Null when nobody is asking, which unlocks nothing.
     */
    public static function takesCommentsAndReactionsFrom(Model $model, ?Request $request): bool
    {
        if (! self::isRespondable($model)) {
            return false;
        }

        return match ($model->status ?? null) {
            EntryStatus::Published, EntryStatus::Unlisted => true,
            EntryStatus::Private => $request !== null && $model->isUnlockedFor($request),
            default => false,
        };
    }

    /**
     * Whether incoming webmentions, and mentions from my own entries, may land on
     * this model. A draft is refused, or a mention could confirm its URL exists.
     */
    public static function takesMentions(Model $model): bool
    {
        return self::isRespondable($model)
            && in_array($model->status ?? null, [EntryStatus::Published, EntryStatus::Unlisted, EntryStatus::Private], strict: true);
    }

    /** Whether this model sends webmentions for its links and records mentions on my own entries. */
    public static function sendsMentions(Model $model): bool
    {
        return self::isRespondable($model)
            && in_array($model->status ?? null, [EntryStatus::Published, EntryStatus::Unlisted], strict: true);
    }

    /** The heading a response target is known by, for sentences about it. */
    public static function titleFor(?Model $target): string
    {
        return match (true) {
            $target instanceof Timelineable => CardPresenter::card($target)->title($target),
            $target instanceof Page => $target->title,
            default => 'an entry that has since gone',
        };
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

        // Pages are not timeline entries, and are the whole reason a guestbook
        // works: /guestbook is a page like any other. A project is standing
        // content on the same terms, and is the likeliest thing here for
        // somebody else to link to or bookmark.
        return $types + ['page' => Page::class];
    }

    /** Whether this is a kind of thing anybody could respond to, whatever its status. */
    private static function isRespondable(Model $model): bool
    {
        return self::keyFor($model) !== null
            && ($model instanceof Page || ($model instanceof Timelineable && $model->shouldAppearOnTimeline()));
    }
}
