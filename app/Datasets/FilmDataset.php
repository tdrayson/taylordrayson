<?php

namespace App\Datasets;

use App\Enums\DatasetKind;
use App\Enums\TimelineType;
use App\Models\Film;
use App\Presenters\Cards\FilmCard;

/**
 * Films logged as watched, synced from Trakt.
 */
final class FilmDataset extends BaseDataset
{
    public function type(): TimelineType
    {
        return TimelineType::Film;
    }

    public function model(): string
    {
        return Film::class;
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
        return 'Film';
    }

    public function plural(): string
    {
        return 'Films';
    }

    public function slug(): string
    {
        return 'films';
    }

    public function keywords(): string
    {
        return 'watch movie film';
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function countNouns(): array
    {
        return ['watched', 'watched'];
    }

    public function card(): FilmCard
    {
        return new FilmCard;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function searchFields(): array
    {
        return [
            'title' => ['label' => 'Title', 'dataType' => 'text', 'column' => 'title', 'category' => 'Film'],
            'rating' => ['label' => 'Rating', 'dataType' => 'number', 'column' => 'rating', 'category' => 'Film'],
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
