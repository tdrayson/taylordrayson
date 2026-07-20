<?php

use App\Models\Podcast;
use App\Presenters\CardPresenter;

it('uses the square podcast artwork (not the wide video still) for the player thumbnail', function () {
    $podcast = Podcast::factory()->create([
        'thumbnail' => 'https://example.test/square-podcast.jpg',
        'cover_image' => 'https://example.test/wide-video.jpg',
    ]);

    expect(CardPresenter::for($podcast)->meta->media->thumbnail)
        ->toBe('https://example.test/square-podcast.jpg');
});

it('falls back to the video still when there is no square thumbnail', function () {
    $podcast = Podcast::factory()->create([
        'thumbnail' => null,
        'cover_image' => 'https://example.test/wide-video.jpg',
    ]);

    expect(CardPresenter::for($podcast)->meta->media->thumbnail)
        ->toBe('https://example.test/wide-video.jpg');
});
