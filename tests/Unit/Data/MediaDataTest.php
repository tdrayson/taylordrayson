<?php

use App\Data\MediaData;

it('serialises an appearance media block with the srcset key present', function () {
    $media = MediaData::withSrcset(
        id: 'appearance-1',
        title: 'Title',
        audioUrl: null,
        videoUrl: 'https://youtu.be/x',
        thumbnail: 'thumb.jpg',
        srcset: null,
        duration: 300,
        url: '/2026/01/01/title',
    );

    expect($media->toArray())->toBe([
        'id' => 'appearance-1',
        'title' => 'Title',
        'audioUrl' => null,
        'videoUrl' => 'https://youtu.be/x',
        'thumbnail' => 'thumb.jpg',
        'srcset' => null,
        'duration' => 300,
        'url' => '/2026/01/01/title',
    ]);
});

it('serialises a podcast media block with the square audioCover and no srcset key', function () {
    $media = MediaData::withoutSrcset(
        id: 42,
        title: 'Season 1, Episode 1',
        audioUrl: '/audio.mp3',
        videoUrl: null,
        thumbnail: '/wide.jpg',
        audioCover: '/square.jpg',
        duration: 1200,
        url: '/2026/01/01/tww-s1-e1',
    );

    expect($media->toArray())->toBe([
        'id' => 42,
        'title' => 'Season 1, Episode 1',
        'audioUrl' => '/audio.mp3',
        'videoUrl' => null,
        'thumbnail' => '/wide.jpg',
        'audioCover' => '/square.jpg',
        'duration' => 1200,
        'url' => '/2026/01/01/tww-s1-e1',
    ])->not->toHaveKey('srcset');
});

it('omits the audioCover key when there is no square artwork', function () {
    $media = MediaData::withoutSrcset(
        id: 42,
        title: 'Season 1, Episode 1',
        audioUrl: '/audio.mp3',
        videoUrl: null,
        thumbnail: '/wide.jpg',
        audioCover: null,
        duration: 1200,
        url: '/2026/01/01/tww-s1-e1',
    );

    expect($media->toArray())->not->toHaveKey('audioCover');
});
