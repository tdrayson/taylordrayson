<?php

use App\Datasets\Datasets;
use App\Models\Book;
use App\Models\Film;
use App\Models\TimelineEntry;
use App\Models\TvEpisode;
use App\Models\TvShow;
use App\Presenters\CardPresenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rolls back the tv rename (000008) so the tables are named 'episodes'/'series'
 * again, matching the shape migration 000005 was written against. Only needed
 * by the tests below that replay 000005 directly; every other test in the
 * suite runs against the full, current migration chain.
 */
function revertTvRename(): void
{
    $migration = require database_path('migrations/2026_09_13_000008_rename_episodes_and_series_to_tv.php');
    $migration->down();
}

it('gives films, episodes and books their own dataset key on the timeline', function () {
    $film = Film::factory()->create();
    $episode = TvEpisode::factory()->for(TvShow::factory())->create();
    $book = Book::factory()->create();

    expect(TimelineEntry::query()->pluck('dataset')->sort()->values()->all())->toBe(['book', 'film', 'tv-episode'])
        ->and(CardPresenter::for($film)->type->value)->toBe('film')
        ->and(CardPresenter::for($episode)->type->value)->toBe('tv-episode')
        ->and(CardPresenter::for($book)->type->value)->toBe('book');
});

it('treats media as an unknown type on feeds', function () {
    expect(Datasets::for('media'))->toBeNull();

    $this->get('/feed?types=media')->assertOk();
});

it('lists a tv show episodes through the episode model', function () {
    $tvShow = TvShow::factory()->create();
    TvEpisode::factory()->for($tvShow)->count(2)->create();

    expect($tvShow->episodes)->toHaveCount(2)->each->toBeInstanceOf(TvEpisode::class);
});

it('serves the tv episode archive at /tv-episodes', function () {
    $this->get('/tv-episodes')->assertOk();
});

it('refuses to roll back the media split once an id collides across films, episodes and books', function () {
    revertTvRename();

    $migration = require database_path('migrations/2026_09_13_000005_split_media_into_films_episodes_and_books.php');

    DB::table('films')->insert([
        'id' => 1,
        'occurred_at' => '2024-01-01 20:00:00',
        'title' => 'Dune',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('episodes')->insert([
        'id' => 1,
        'occurred_at' => '2024-02-01 20:00:00',
        'title' => 'Good News About Hell',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => $migration->down())->toThrow(RuntimeException::class);

    expect(Schema::hasTable('media'))->toBeFalse()
        ->and(DB::table('films')->where('id', 1)->exists())->toBeTrue()
        ->and(DB::table('episodes')->where('id', 1)->exists())->toBeTrue();
});

it('refuses to split media while a morph reference points at a missing id', function () {
    revertTvRename();

    $migration = require database_path('migrations/2026_09_13_000005_split_media_into_films_episodes_and_books.php');

    $migration->down();

    DB::table('timeline_entries')->insert([
        'dataset' => 'media',
        'entry_id' => 999999,
        'url_slug' => 'missing',
        'occurred_at' => '2024-01-01 20:00:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => $migration->up())->toThrow(RuntimeException::class);

    expect(Schema::hasTable('films'))->toBeFalse();
});

it('splits a legacy media table into films, episodes and books via the migration, preserving ids', function () {
    revertTvRename();

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
