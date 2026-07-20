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

it('serialises a podcast media block with no srcset key at all', function () {
    $media = MediaData::withoutSrcset(
        id: 42,
        title: 'Season 1, Episode 1',
        audioUrl: '/audio.mp3',
        videoUrl: null,
        thumbnail: '/thumb.jpg',
        duration: 1200,
        url: '/2026/01/01/tww-s1-e1',
    );

    expect($media->toArray())->toBe([
        'id' => 42,
        'title' => 'Season 1, Episode 1',
        'audioUrl' => '/audio.mp3',
        'videoUrl' => null,
        'thumbnail' => '/thumb.jpg',
        'duration' => 1200,
        'url' => '/2026/01/01/tww-s1-e1',
    ])->not->toHaveKey('srcset');
});
