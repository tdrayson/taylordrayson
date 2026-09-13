<?php

namespace App\Datasets;

use App\Enums\TimelineType;
use App\Models\Page;
use App\Models\Trip;
use App\Models\TvShow;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * The ordered list of every dataset, and lookups into it.
 */
final class Datasets
{
    /** @var array<string, Dataset>|null */
    private static ?array $all = null;

    /**
     * @return array<string, Dataset> Keyed by type value, in TimelineType order.
     */
    public static function all(): array
    {
        if (self::$all !== null) {
            return self::$all;
        }

        $all = [];

        foreach ([
            new ActivityDataset,
            new SleepDataset,
            new FoodDataset,
            new FilmDataset,
            new TvEpisodeDataset,
            new BookDataset,
            new EventDataset,
            new AppearanceDataset,
            new ThisWeekWithDataset,
            new FlightDataset,
            new PlaceDataset,
            new FuelDataset,
            new ProjectDataset,
            new ArticleDataset,
            new NoteDataset,
        ] as $dataset) {
            $all[$dataset->type()->value] = $dataset;
        }

        return self::$all = $all;
    }

    /**
     * Every morphable model keyed by the alias stored in morph columns.
     *
     * @return array<string, class-string<Model>>
     */
    public static function morphMap(): array
    {
        return [
            ...array_map(fn (Dataset $dataset): string => $dataset->model(), self::all()),
            'page' => Page::class,
            'tv-show' => TvShow::class,
            'user' => User::class,
            'trip' => Trip::class,
        ];
    }

    public static function for(TimelineType|string $type): ?Dataset
    {
        return self::all()[$type instanceof TimelineType ? $type->value : $type] ?? null;
    }

    /**
     * Matches subclasses too, so a test double or specialised model still
     * resolves to its parent's dataset.
     *
     * @param  Model|class-string<Model>  $model
     */
    public static function forModel(Model|string $model): ?Dataset
    {
        $class = is_string($model) ? $model : $model::class;

        foreach (self::all() as $dataset) {
            if (is_a($class, $dataset->model(), true)) {
                return $dataset;
            }
        }

        return null;
    }
}
