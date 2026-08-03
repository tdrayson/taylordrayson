<?php

namespace App\Fields;

use App\Data\FieldData;
use App\Enums\MediaType;
use App\Models\Appearance;
use App\Models\Article;
use App\Models\Event;
use App\Models\Flight;
use App\Models\Fuel;
use App\Models\Media;
use App\Models\Note;
use App\Models\Page;
use App\Models\Project;
use App\Presenters\CardPresenter;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Resolves a model to the fields the authoring UI offers for it.
 *
 * The single entry point for building an edit form, mirroring
 * {@see CardPresenter::for()}: one declaration per type, in code, driving the
 * form, the properties panel and the "+ Add field" menu alike. Adding a field
 * is an edit and a deploy, deliberately — this is not a runtime field builder.
 *
 * Types absent here are not hand-authored: activities, sleep, calories,
 * check-ins, podcasts and flights all arrive from a sync, and films and
 * episodes come from Trakt. Books are the one Media type entered by hand.
 */
final class FieldRegistry
{
    /**
     * @return list<FieldData>
     */
    public static function for(Model $model): array
    {
        return match (true) {
            $model instanceof Note => NoteFields::fields(),
            $model instanceof Page => PageFields::fields(),
            $model instanceof Article => ArticleFields::fields(),
            $model instanceof Project => ProjectFields::fields(),
            $model instanceof Event => EventFields::fields(),
            $model instanceof Flight => FlightFields::fields(),
            $model instanceof Fuel => FuelFields::fields(),
            $model instanceof Appearance => AppearanceFields::fields(),
            $model instanceof Media && self::isBook($model) => BookFields::fields(),
            default => throw new LogicException('No fields registered for '.$model::class),
        };
    }

    /**
     * Whether a model can be authored at all, so callers can offer editing
     * without catching an exception to find out.
     */
    public static function has(Model $model): bool
    {
        return $model instanceof Note
            || $model instanceof Page
            || $model instanceof Article
            || $model instanceof Project
            || $model instanceof Event
            || $model instanceof Flight
            || $model instanceof Fuel
            || $model instanceof Appearance
            || ($model instanceof Media && self::isBook($model));
    }

    /**
     * Films and episodes come from Trakt and are never edited by hand; a book
     * has no Trakt equivalent, so it is the one Media type with a form.
     *
     * A Media row with no type yet is treated as a book, since that is the only
     * kind the authoring UI can create.
     */
    private static function isBook(Media $media): bool
    {
        $type = $media->getAttribute('type');

        return $type === null
            || $type === MediaType::Book
            || $type === MediaType::Book->value;
    }
}
