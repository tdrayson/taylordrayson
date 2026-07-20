<?php

use App\Enums\MediaType;
use App\Models\Media;

it('casts a stored type string to the MediaType enum, but serialises the raw value', function (MediaType $type) {
    $media = Media::factory()->create(['type' => $type]);

    expect($media->type)->toBeInstanceOf(MediaType::class)
        ->and($media->type)->toBe($type)
        ->and($media->type->value)->toBe($type->value)
        ->and($media->toArray()['type'])->toBe($type->value);
})->with(MediaType::cases());

it('renders a card for every media type', function (MediaType $type) {
    $media = Media::factory()->create(['type' => $type, 'title' => 'Test Title']);

    $card = $media->card();

    expect($card->type)->toBe('media')
        ->and($card->title)->toBe('Test Title');
})->with(MediaType::cases());

it('builds the tv episode detail from meta using the enum arm, not the phantom "tv" value', function () {
    $episode = Media::factory()->create([
        'type' => MediaType::TvEpisode,
        'meta' => ['season' => 2, 'episode' => 5],
    ]);

    expect($episode->card()->subtitle)->toContain('S02E05');
});
