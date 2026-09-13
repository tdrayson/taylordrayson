<?php

use App\Datasets\Datasets;
use App\Models\Book;
use App\Models\Episode;
use App\Models\Film;
use App\Models\Series;
use App\Models\TimelineEntry;
use App\Presenters\CardPresenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

it('gives films, episodes and books their own dataset key on the timeline', function () {
    $film = Film::factory()->create();
    $episode = Episode::factory()->for(Series::factory())->create();
    $book = Book::factory()->create();

    expect(TimelineEntry::query()->pluck('dataset')->sort()->values()->all())->toBe(['book', 'episode', 'film'])
        ->and(CardPresenter::for($film)->type->value)->toBe('film')
        ->and(CardPresenter::for($episode)->type->value)->toBe('episode')
        ->and(CardPresenter::for($book)->type->value)->toBe('book');
});

it('still accepts media as all three on feeds', function () {
    expect(array_map(fn ($dataset) => $dataset->type()->value, Datasets::resolve('media')))->toBe(['film', 'episode', 'book'])
        ->and(Datasets::resolveOne('media'))->toBeNull();

    $this->get('/feed?types=media')->assertOk();
});

it('lists a series episodes through the episode model', function () {
    $series = Series::factory()->create();
    Episode::factory()->for($series)->count(2)->create();

    expect($series->episodes)->toHaveCount(2)->each->toBeInstanceOf(Episode::class);
});

it('registers no archive route for the episode dataset', function () {
    expect(fn () => route('archive.tv'))->toThrow(RouteNotFoundException::class);
});

it('splits a legacy media table into films, episodes and books via the migration, preserving ids', function () {
    $migration = require database_path('migrations/2026_09_13_000005_split_media_into_films_episodes_and_books.php');

    $migration->down();

    $filmId = DB::table('media')->insertGetId([
        'occurred_at' => '2024-01-01 20:00:00',
        'type' => 'film',
        'title' => 'Dune',
        'source' => 'trakt',
        'source_id' => 'f1',
        'meta' => json_encode(['year' => 2021]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $seriesId = DB::table('series')->insertGetId([
        'trakt_id' => 700,
        'slug' => 'severance',
        'title' => 'Severance',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $episodeId = DB::table('media')->insertGetId([
        'series_id' => $seriesId,
        'occurred_at' => '2024-02-01 20:00:00',
        'type' => 'episode',
        'title' => 'Good News About Hell',
        'source' => 'trakt',
        'source_id' => 'e1',
        'meta' => json_encode(['season' => 1, 'episode' => 1]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $bookId = DB::table('media')->insertGetId([
        'occurred_at' => '2024-03-01 20:00:00',
        'type' => 'book',
        'title' => 'Piranesi',
        'meta' => json_encode(['author' => 'Susanna Clarke']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('timeline_entries')->insert([
        'dataset' => 'media',
        'entry_id' => $filmId,
        'url_slug' => 'dune',
        'occurred_at' => '2024-01-01 20:00:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('attachments')->insert([
        'model_type' => 'media',
        'model_id' => $filmId,
        'collection_name' => 'cover',
        'name' => 'poster',
        'file_name' => 'poster.webp',
        'mime_type' => 'image/webp',
        'disk' => 'public',
        'conversions_disk' => 'public',
        'size' => 1,
        'manipulations' => '[]',
        'custom_properties' => '[]',
        'generated_conversions' => '[]',
        'responsive_images' => '[]',
        'order_column' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration->up();

    expect(DB::table('films')->where('id', $filmId)->value('title'))->toBe('Dune')
        ->and(DB::table('episodes')->where('id', $episodeId)->value('title'))->toBe('Good News About Hell')
        ->and(DB::table('episodes')->where('id', $episodeId)->value('series_id'))->toBe($seriesId)
        ->and(DB::table('books')->where('id', $bookId)->value('title'))->toBe('Piranesi')
        ->and(DB::table('timeline_entries')->where('entry_id', $filmId)->value('dataset'))->toBe('film')
        ->and(DB::table('attachments')->where('model_id', $filmId)->value('model_type'))->toBe('film')
        ->and(Schema::hasTable('media'))->toBeFalse();
});
