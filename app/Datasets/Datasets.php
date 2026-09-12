<?php

namespace App\Datasets;

use App\Enums\TimelineType;
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
            new CalorieDataset,
            new MediaDataset,
            new EventDataset,
            new AppearanceDataset,
            new PodcastDataset,
            new FlightDataset,
            new CheckinDataset,
            new FuelDataset,
            new ProjectDataset,
            new ArticleDataset,
            new NoteDataset,
        ] as $dataset) {
            $all[$dataset->type()->value] = $dataset;
        }

        return self::$all = $all;
    }

    public static function for(TimelineType|string $type): ?Dataset
    {
        return self::all()[$type instanceof TimelineType ? $type->value : $type] ?? null;
    }

    /**
     * @param  Model|class-string<Model>  $model
     */
    public static function forModel(Model|string $model): ?Dataset
    {
        $class = is_string($model) ? $model : $model::class;

        foreach (self::all() as $dataset) {
            if ($dataset->model() === $class) {
                return $dataset;
            }
        }

        return null;
    }
}
