<?php

use App\Data\CardData;
use App\Data\CardMeta;
use App\Enums\TimelineType;

it('omits summary from the card payload when there is none', function () {
    $card = new CardData(TimelineType::Note, 'Title', null, 'Sub', null, null, null, CardMeta::empty());

    expect($card->toArray())->not->toHaveKey('summary');
});

it('emits summary after the subtitle when set', function () {
    $card = new CardData(TimelineType::Note, 'Title', null, 'Sub', null, null, null, CardMeta::empty(), summary: 'My words');

    expect(array_keys($card->toArray()))->toContain('summary')
        ->and($card->toArray()['summary'])->toBe('My words');
});
