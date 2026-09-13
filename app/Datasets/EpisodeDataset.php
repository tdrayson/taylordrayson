<?php

namespace App\Datasets;

use App\Enums\DatasetKind;
use App\Enums\TimelineType;
use App\Models\Episode;
use App\Presenters\Cards\EpisodeCard;

/**
 * TV episodes logged as watched, synced from Trakt. Archived by show at /tv
 * rather than by episode, so this dataset has no archive index of its own.
 */
final class EpisodeDataset extends BaseDataset
{
    public function type(): TimelineType
    {
        return TimelineType::Episode;
    }

    public function model(): string
    {
        return Episode::class;
    }

    public function kind(): DatasetKind
    {
        return DatasetKind::Watching;
    }

    public function icon(): string
    {
        return 'TvMinimalPlayIcon';
    }

    public function label(): string
    {
        return 'Episode';
    }

    public function plural(): string
    {
        return 'TV';
    }

    public function slug(): string
    {
        return 'tv';
    }

    /**
     * The base default would derive "tv" from the plural label, producing
     * "1384 tvs" in archive previews and OG; every row is an episode.
     */
    public function noun(): string
    {
        return 'episode';
    }

    public function keywords(): string
    {
        return 'watch tv show episode series media';
    }

    public function card(): EpisodeCard
    {
        return new EpisodeCard;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function searchFields(): array
    {
        return [
            'title' => ['label' => 'Title', 'dataType' => 'text', 'column' => 'title', 'category' => 'Episode'],
            'rating' => ['label' => 'Rating', 'dataType' => 'number', 'column' => 'rating', 'category' => 'Episode'],
            // An episode's own title names the episode, so the show it belongs
            // to is the only way to search a series as a whole.
            'show' => ['label' => 'Show', 'dataType' => 'text', 'relation' => 'series', 'column' => 'title', 'category' => 'Episode'],
        ];
    }

    /**
     * @return list<string>
     */
    public function textColumns(): array
    {
        return ['title'];
    }

    /** No archive of its own: the series index at /tv serves this dataset. */
    public function archive(): bool
    {
        return false;
    }
}
