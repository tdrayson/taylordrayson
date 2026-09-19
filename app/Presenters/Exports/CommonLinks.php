<?php

namespace App\Presenters\Exports;

use App\Data\ExportLink;
use App\Data\TagLink;
use App\Datasets\Datasets;
use App\Enums\Source;
use App\Models\Concerns\Timelineable;
use App\Models\Tag;
use App\Queries\TripForEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The link kinds every export shares, so the seventeen `*Export` classes
 * don't each hand-roll them and drift on the `rel` values `.mf2` depends on
 * (`category` for a tag, `syndication` for a source permalink).
 *
 * A type-specific export composes these after its own links:
 * `[...$ownLinks, ...CommonLinks::for($model)]`.
 */
final class CommonLinks
{
    /**
     * @return list<ExportLink>
     */
    public static function for(Model $model): array
    {
        return array_values(array_filter([
            self::type($model),
            self::day($model),
            self::trip($model),
            ...self::tags($model),
            self::source($model),
        ]));
    }

    /** The type's own archive page. */
    private static function type(Model $model): ?ExportLink
    {
        $dataset = Datasets::forModel($model);

        return $dataset === null ? null : ExportLink::make(
            'type',
            'All '.Str::lower($dataset->plural()),
            $dataset->plural(),
            '/'.$dataset->slug(),
        );
    }

    /** The dated day this entry happened on. */
    private static function day(Model $model): ?ExportLink
    {
        return $model->occurred_at === null ? null : ExportLink::make(
            'day',
            'That day',
            $model->occurred_at->format('j F Y'),
            $model->occurred_at->format('/Y/m/d'),
        );
    }

    /** The trip this entry falls inside, for a model with a real occurred_at/timezone to place. */
    private static function trip(Model $model): ?ExportLink
    {
        if (! $model instanceof Timelineable) {
            return null;
        }

        $trip = app(TripForEntry::class)($model);

        return $trip === null ? null : ExportLink::make('trip', 'Trip', $trip->title, $trip->url());
    }

    /**
     * @return list<ExportLink>
     */
    private static function tags(Model $model): array
    {
        if (! method_exists($model, 'tagNames')) {
            return [];
        }

        $model->loadMissing('tags');

        return $model->tags
            ->map(fn (Tag $tag): ExportLink => ExportLink::make('tag', 'Tagged', $tag->name, TagLink::for($tag)->url, 'category'))
            ->all();
    }

    /** Where this entry's data came from, when it was synced rather than written by hand. */
    private static function source(Model $model): ?ExportLink
    {
        $platform = $model->source ?? null;

        if ($platform === null) {
            return null;
        }

        $label = Source::tryFrom($platform)?->label() ?? Str::headline($platform);

        return ExportLink::maybe('source', 'Source', $label, $model->platform_url, 'syndication');
    }
}
