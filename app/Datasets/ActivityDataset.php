<?php

namespace App\Datasets;

use App\Enums\ActivityDiscipline;
use App\Enums\DatasetKind;
use App\Enums\TimelineType;
use App\Models\Activity;
use App\Presenters\Cards\ActivityCard;
use App\Timeline\Taxonomies;

/**
 * Physical activities and workouts, from Strava.
 */
final class ActivityDataset extends BaseDataset
{
    public function type(): TimelineType
    {
        return TimelineType::Activity;
    }

    public function model(): string
    {
        return Activity::class;
    }

    public function kind(): DatasetKind
    {
        return DatasetKind::Health;
    }

    public function icon(): string
    {
        return 'WorkoutRunIcon';
    }

    public function label(): string
    {
        return 'Activity';
    }

    public function plural(): string
    {
        return 'Activities';
    }

    public function slug(): string
    {
        return 'activities';
    }

    public function keywords(): string
    {
        return 'workout exercise sport';
    }

    public function card(): ActivityCard
    {
        return new ActivityCard;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function searchFields(): array
    {
        return [
            'name' => ['label' => 'Name', 'dataType' => 'text', 'column' => 'name', 'category' => 'Activity'],
            'kind' => ['label' => 'Type', 'dataType' => 'enum', 'column' => 'type', 'category' => 'Activity', 'enum' => ActivityDiscipline::class],
            'distance' => ['label' => 'Distance', 'dataType' => 'number', 'column' => 'distance', 'category' => 'Metrics', 'measure' => 'distance', 'store' => 'm'],
            'duration' => ['label' => 'Duration', 'dataType' => 'duration', 'column' => 'duration', 'category' => 'Metrics'],
            'calories' => ['label' => 'Calories', 'dataType' => 'number', 'column' => 'calories', 'category' => 'Metrics', 'suffix' => 'kcal'],
            'avg_hr' => ['label' => 'Avg heart rate', 'dataType' => 'number', 'column' => 'average_heart_rate', 'category' => 'Metrics', 'suffix' => 'bpm'],
            'max_hr' => ['label' => 'Max heart rate', 'dataType' => 'number', 'column' => 'max_heart_rate', 'category' => 'Metrics', 'suffix' => 'bpm'],
            'photos' => ['label' => 'Photos', 'dataType' => 'media', 'column' => null, 'category' => 'Media', 'suffix' => 'photos'],
        ];
    }

    /**
     * @return list<string>
     */
    public function textColumns(): array
    {
        return ['name'];
    }

    public function taxonomy(): callable
    {
        return Taxonomies::column('type', 'Type', fn (string $label): string => "{$label} activities");
    }

    public function stats(): bool
    {
        return true;
    }

    public function synced(): bool
    {
        return true;
    }
}
