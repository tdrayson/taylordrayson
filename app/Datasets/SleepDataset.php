<?php

namespace App\Datasets;

use App\Enums\DatasetKind;
use App\Enums\Source;
use App\Enums\SpanAnchor;
use App\Enums\TimelineType;
use App\Models\Sleep;
use App\Presenters\Cards\SleepCard;

/**
 * Nightly sleep sessions, from Oura or Apple Watch.
 */
final class SleepDataset extends BaseDataset
{
    public function type(): TimelineType
    {
        return TimelineType::Sleep;
    }

    public function model(): string
    {
        return Sleep::class;
    }

    public function kind(): DatasetKind
    {
        return DatasetKind::Health;
    }

    public function icon(): string
    {
        return 'Moon02Icon';
    }

    public function label(): string
    {
        return 'Sleep';
    }

    public function plural(): string
    {
        return 'Sleep';
    }

    public function slug(): string
    {
        return 'sleep';
    }

    public function keywords(): string
    {
        return 'rest bed nap';
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function countNouns(): array
    {
        return ['night', 'nights'];
    }

    public function card(): SleepCard
    {
        return new SleepCard;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function searchFields(): array
    {
        return [
            'duration' => ['label' => 'Duration', 'dataType' => 'duration', 'column' => 'duration', 'category' => 'Sleep'],
            'awake' => ['label' => 'Awake', 'dataType' => 'duration', 'column' => 'awake', 'category' => 'Stages'],
            'rem' => ['label' => 'REM', 'dataType' => 'duration', 'column' => 'rem', 'category' => 'Stages'],
            'core' => ['label' => 'Core', 'dataType' => 'duration', 'column' => 'core', 'category' => 'Stages'],
            'deep' => ['label' => 'Deep', 'dataType' => 'duration', 'column' => 'deep', 'category' => 'Stages'],
            'source' => ['label' => 'Source', 'dataType' => 'enum', 'column' => 'source', 'category' => 'Sleep', 'enum' => Source::class],
        ];
    }

    public function spanAnchor(): SpanAnchor
    {
        return SpanAnchor::End;
    }
}
