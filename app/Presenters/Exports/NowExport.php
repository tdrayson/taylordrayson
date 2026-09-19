<?php

namespace App\Presenters\Exports;

use App\Data\ExportData;
use App\Data\ExportField;
use App\Data\ExportLink;
use App\Queries\CurrentlyReading;
use App\Queries\LastNightSleep;
use App\Queries\LatestEpisode;
use App\Queries\PhotoStream;
use App\Support\Units;

/**
 * The /now dashboard as an export: the same widgets NowController reads,
 * shaped as labelled fields. No aspects: nothing here is a moment or a place.
 */
final class NowExport
{
    public function present(): ExportData
    {
        $sleep = app(LastNightSleep::class)();
        $book = app(CurrentlyReading::class)->book();
        $episode = app(LatestEpisode::class)();
        $photos = count(app(PhotoStream::class)(6));

        return new ExportData(
            type: 'now',
            url: url('/now'),
            title: 'Now',
            summary: null,
            occurred: null,
            fields: array_values(array_filter([
                $sleep === null ? null : ExportField::make('sleep', 'Last night', Units::humanDuration($sleep->duration), $sleep->duration),
                $book === null ? null : ExportField::make('reading', 'Reading', $book->title.($book->meta->author ? " by {$book->meta->author}" : ''), ['title' => $book->title, 'author' => (string) $book->meta->author]),
                $episode === null ? null : ExportField::make('episode', 'Latest episode', "S{$episode->season_number}E{$episode->episode_number}, {$episode->topic}", ['season' => $episode->season_number, 'episode' => $episode->episode_number]),
                ExportField::make('photos', 'Recent photos', "{$photos} photos", $photos),
            ])),
            links: array_values(array_filter([
                $sleep === null ? null : ExportLink::make('sleep', 'Sleep', 'Sleep', '/sleep'),
                $book === null ? null : ExportLink::make('reading', 'Reading', $book->title, $book->url()),
                $episode === null ? null : ExportLink::make('episode', 'Episode', $episode->title, $episode->url()),
            ])),
        );
    }
}
