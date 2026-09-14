<?php

use App\Actions\BuildTimelineFeed;
use App\Data\CardData;
use App\Data\CardMeta;
use App\Enums\TimelineType;
use App\Models\Activity;
use App\Models\Appearance;
use App\Models\Event;
use App\Models\Place;
use App\Models\ThisWeekWith;
use App\Presenters\CardPresenter;

it('omits summary from the card payload when there is none', function () {
    $card = new CardData(TimelineType::Note, 'Title', null, 'Sub', null, null, null, CardMeta::empty());

    expect($card->toArray())->not->toHaveKey('summary');
});

it('emits summary after the subtitle when set', function () {
    $card = new CardData(TimelineType::Note, 'Title', null, 'Sub', null, null, null, CardMeta::empty(), summary: 'My words');

    expect(array_keys($card->toArray()))->toContain('summary')
        ->and($card->toArray()['summary'])->toBe('My words');
});

it('carries each dataset\'s own prose as the summary', function (Closure $make, string $expected) {
    expect(CardPresenter::for($make())->summary)->toBe($expected);
})->with([
    'activity' => [fn () => Activity::factory()->create(['description' => 'Watched the eclipse']), 'Watched the eclipse'],
    'event' => [fn () => Event::factory()->create(['description' => 'Met the cast']), 'Met the cast'],
    'appearance' => [fn () => Appearance::factory()->create(['description' => 'Talking blocks']), 'Talking blocks'],
    'place' => [fn () => Place::factory()->create(['description' => 'Great coffee here']), 'Great coffee here'],
    'this week with' => [fn () => ThisWeekWith::factory()->create(['topic' => 'Shipping']), 'Shipping'],
]);

it('has no summary when the prose is blank', function () {
    $activity = Activity::factory()->create(['description' => "  \n "]);

    expect(CardPresenter::for($activity)->summary)->toBeNull();
});

it('keeps a place note out of the subtitle', function () {
    $place = Place::factory()->create(['description' => 'Great coffee here']);

    expect(CardPresenter::for($place)->subtitle)->toBeNull();
});

it('passes the summary to the timeline feed item', function () {
    $activity = Activity::factory()->create(['description' => 'Got lost, made it back.']);

    $item = app(BuildTimelineFeed::class)->cardItem($activity->timelineEntry);

    expect($item['summary'])->toBe('Got lost, made it back.');
});
