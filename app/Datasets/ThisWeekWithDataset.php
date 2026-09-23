<?php

namespace App\Datasets;

use App\Enums\DatasetKind;
use App\Enums\TimelineType;
use App\Models\ThisWeekWith;
use App\Presenters\Cards\ThisWeekWithCard;
use App\Timeline\Taxonomies;

/**
 * Episodes of This Week With, the personal podcast.
 */
final class ThisWeekWithDataset extends BaseDataset
{
    public function type(): TimelineType
    {
        return TimelineType::ThisWeekWith;
    }

    public function model(): string
    {
        return ThisWeekWith::class;
    }

    public function kind(): DatasetKind
    {
        return DatasetKind::Speaking;
    }

    public function icon(): string
    {
        return 'PodcastIcon';
    }

    public function label(): string
    {
        return 'This Week With';
    }

    public function plural(): string
    {
        return 'This Week With';
    }

    public function slug(): string
    {
        return 'this-week-with';
    }

    public function keywords(): string
    {
        return 'podcast tww episode';
    }

    public function noun(): string
    {
        return 'episode';
    }

    public function card(): ThisWeekWithCard
    {
        return new ThisWeekWithCard;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function searchFields(): array
    {
        return [
            'topic' => ['label' => 'Topic', 'dataType' => 'text', 'column' => 'topic', 'category' => 'Episode'],
            'season' => ['label' => 'Season', 'dataType' => 'number', 'column' => 'season_number', 'category' => 'Episode'],
            'episode' => ['label' => 'Episode', 'dataType' => 'number', 'column' => 'episode_number', 'category' => 'Episode'],
            'duration' => ['label' => 'Duration', 'dataType' => 'duration', 'column' => 'duration', 'category' => 'Episode'],
            'notes' => ['label' => 'Show notes', 'dataType' => 'text', 'column' => 'show_notes', 'category' => 'Episode'],
            'transcript' => ['label' => 'Transcript', 'dataType' => 'text', 'column' => 'transcript', 'category' => 'Episode'],
        ];
    }

    /**
     * @return list<string>
     */
    public function textColumns(): array
    {
        return ['topic', 'show_notes'];
    }

    public function taxonomy(): callable
    {
        return Taxonomies::thisWeekWithSeason();
    }

    public function synced(): bool
    {
        return true;
    }

    public function syncCommand(): ?string
    {
        return 'this-week-with:sync';
    }
}
