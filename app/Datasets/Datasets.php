<?php

namespace App\Datasets;

use App\Enums\TimelineType;
use App\Models\Page;
use App\Models\Series;
use App\Models\Trip;
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
            new MediaDataset,
            new EventDataset,
            new AppearanceDataset,
            new PodcastDataset,
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
            'series' => Series::class,
            'user' => User::class,
            'trip' => Trip::class,
        ];
    }

    public static function for(TimelineType|string $type): ?Dataset
    {
        return self::all()[$type instanceof TimelineType ? $type->value : $type] ?? null;
    }

    /**
     * Keys that no longer exist but are still accepted from outside, mapped to what replaced them.
     *
     * @var array<string, list<string>>
     */
    public const ALIASES = [
        'calorie' => ['food'],
        'checkin' => ['place'],
    ];

    /**
     * Every dataset a key names: itself when live, its replacements when an alias.
     *
     * @return list<Dataset>
     */
    public static function resolve(string $key): array
    {
        $key = trim($key);

        if (($dataset = self::for($key)) !== null) {
            return [$dataset];
        }

        return array_values(array_filter(array_map(
            fn (string $target): ?Dataset => self::for($target),
            self::ALIASES[$key] ?? [],
        )));
    }

    /**
     * The single dataset a key names, or null when it names none or several.
     */
    public static function resolveOne(string $key): ?Dataset
    {
        $datasets = self::resolve($key);

        return count($datasets) === 1 ? $datasets[0] : null;
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
