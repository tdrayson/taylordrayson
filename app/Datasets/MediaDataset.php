<?php

namespace App\Datasets;

use App\Enums\DatasetKind;
use App\Enums\TimelineType;
use App\Models\Media;
use App\Presenters\Cards\MediaCard;
use App\Timeline\Taxonomies;

/**
 * Films, TV and books logged as watched or read.
 */
final class MediaDataset extends BaseDataset
{
    public function type(): TimelineType
    {
        return TimelineType::Media;
    }

    public function model(): string
    {
        return Media::class;
    }

    public function kind(): DatasetKind
    {
        return DatasetKind::Watching;
    }

    public function icon(): string
    {
        return 'Film01Icon';
    }

    public function label(): string
    {
        return 'Media';
    }

    public function plural(): string
    {
        return 'Media';
    }

    public function slug(): string
    {
        return 'media';
    }

    public function keywords(): string
    {
        return 'watch movie film tv show book reading';
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function countNouns(): array
    {
        return ['logged', 'logged'];
    }

    public function card(): MediaCard
    {
        return new MediaCard;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function searchFields(): array
    {
        return [
            'title' => ['label' => 'Title', 'dataType' => 'text', 'column' => 'title', 'category' => 'Media'],
            'kind' => ['label' => 'Type', 'dataType' => 'enum', 'column' => 'type', 'category' => 'Media'],
            'rating' => ['label' => 'Rating', 'dataType' => 'number', 'column' => 'rating', 'category' => 'Media'],
            // An episode's own title names the episode, so the show it belongs
            // to is the only way to search a series as a whole.
            'show' => ['label' => 'Show', 'dataType' => 'text', 'relation' => 'series', 'column' => 'title', 'category' => 'Media'],
        ];
    }

    /**
     * @return list<string>
     */
    public function textColumns(): array
    {
        return ['title'];
    }

    public function taxonomy(): callable
    {
        return Taxonomies::media();
    }
}
