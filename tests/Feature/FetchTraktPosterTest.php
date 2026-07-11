<?php

use App\Jobs\FetchTraktPoster;
use App\Models\Series;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('downloads a poster into the cover collection', function () {
    Storage::fake(config('media-library.disk_name'));
    Http::fake(['*' => Http::response(file_get_contents(base_path('tests/Fixtures/pixel.webp')), 200)]);

    $series = Series::factory()->create();
    (new FetchTraktPoster($series, 'https://walter-r2.trakt.tv/posters/x.jpg.webp'))->handle();

    expect($series->fresh()->getFirstMedia('cover'))->not->toBeNull();
});

it('throws on a failed download so the queue retries', function () {
    Storage::fake(config('media-library.disk_name'));
    Http::fake(['*' => Http::response('', 500)]);

    $series = Series::factory()->create();

    expect(fn () => (new FetchTraktPoster($series, 'https://walter-r2.trakt.tv/posters/x.jpg.webp'))->handle())
        ->toThrow(RuntimeException::class);

    expect($series->fresh()->getFirstMedia('cover'))->toBeNull();
});
