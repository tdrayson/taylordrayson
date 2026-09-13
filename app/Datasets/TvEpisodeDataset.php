<?php

namespace App\Datasets;

use App\Enums\DatasetKind;
use App\Enums\TimelineType;
use App\Models\TvEpisode;
use App\Presenters\Cards\TvEpisodeCard;

/**
 * TV episodes logged as watched, synced from Trakt.
 */
final class TvEpisodeDataset extends BaseDataset
{
    public function type(): TimelineType
    {
        return TimelineType::TvEpisode;
    }

    public function model(): string
    {
        return TvEpisode::class;
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
        return 'TV episode';
    }

    public function plural(): string
    {
        return 'TV episodes';
    }

    public function slug(): string
    {
        return 'tv-episodes';
    }

    /**
     * Entries count as episodes; the base default would derive "tv episode"
     * from the plural label.
     */
    public function noun(): string
    {
        return 'episode';
    }

    public function keywords(): string
    {
        return 'watch tv show episode series media';
    }

    public function card(): TvEpisodeCard
    {
        return new TvEpisodeCard;
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
            // to is the only way to search a show as a whole.
            'show' => ['label' => 'Show', 'dataType' => 'text', 'relation' => 'tvShow', 'column' => 'title', 'category' => 'Episode'],
        ];
    }

    /**
     * @return list<string>
     */
    public function textColumns(): array
    {
        return ['title'];
    }
}
