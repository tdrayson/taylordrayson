<?php

namespace App\Fields;

use App\Data\FieldData;
use App\Models\Article;
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
 * {@see CardPresenter::for()}: one declaration per type, in
 * code, driving the form, the properties panel and the "+ Add field" menu
 * alike. Adding a field is an edit and a deploy, deliberately — this is not a
 * runtime field builder.
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
            default => throw new LogicException('No fields registered for '.$model::class),
        };
    }

    /**
     * Whether a type can be authored at all, so callers can offer editing
     * without catching an exception to find out.
     */
    public static function has(Model $model): bool
    {
        return $model instanceof Note
            || $model instanceof Page
            || $model instanceof Article
            || $model instanceof Project;
    }
}
