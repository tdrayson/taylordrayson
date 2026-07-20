<?php

use App\Models\Podcast;
use App\Presenters\CardPresenter;

it('fronts the timeline card with the wide video still and gives the audio player the square art', function () {
    $podcast = Podcast::factory()->create([
        'thumbnail' => 'https://example.test/square-podcast.jpg',
        'cover_image' => 'https://example.test/wide-video.jpg',
    ]);

    $media = CardPresenter::for($podcast)->meta->media;

    expect($media->thumbnail)->toBe('https://example.test/wide-video.jpg')
        ->and($media->audioCover)->toBe('https://example.test/square-podcast.jpg');
});

it('falls back across both fields when only one image exists', function () {
    $podcast = Podcast::factory()->create([
        'thumbnail' => null,
        'cover_image' => 'https://example.test/wide-video.jpg',
    ]);

    $media = CardPresenter::for($podcast)->meta->media;

    expect($media->thumbnail)->toBe('https://example.test/wide-video.jpg')
        ->and($media->audioCover)->toBe('https://example.test/wide-video.jpg');
});
