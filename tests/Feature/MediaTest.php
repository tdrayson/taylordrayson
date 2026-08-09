<?php

use App\Enums\MediaType;
use App\Enums\TimelineType;
use App\Models\Media;
use App\Presenters\CardPresenter;

it('casts a stored type string to the MediaType enum, but serialises the raw value', function (MediaType $type) {
    $media = Media::factory()->create(['type' => $type]);

    expect($media->type)->toBeInstanceOf(MediaType::class)
        ->and($media->type)->toBe($type)
        ->and($media->type->value)->toBe($type->value)
        ->and($media->toArray()['type'])->toBe($type->value);
})->with(MediaType::cases());

it('renders a card for every media type', function (MediaType $type) {
    // Empty meta on purpose: the factory gives an episode a show_title, which
    // would (correctly) lead the card instead of the row's own title. How each
    // type is titled is pinned in MediaTypeTest; this is only about every type
    // producing a card at all.
    $media = Media::factory()->create(['type' => $type, 'title' => 'Test Title', 'meta' => []]);

    $card = CardPresenter::for($media);

    expect($card->type)->toBe(TimelineType::Media)
        ->and($card->title)->toBe('Test Title');
})->with(MediaType::cases());

it('builds the tv episode detail from meta using the enum arm, not the phantom "tv" value', function () {
    $episode = Media::factory()->create([
        'type' => MediaType::TvEpisode,
        'meta' => ['season' => 2, 'episode' => 5],
    ]);

    expect(CardPresenter::for($episode)->subtitle)->toContain('S02E05');
});
