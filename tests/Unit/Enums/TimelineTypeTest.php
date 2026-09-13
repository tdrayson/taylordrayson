<?php

use App\Enums\TimelineType;

it('has exactly the fifteen stored values', function () {
    expect(array_column(TimelineType::cases(), 'value'))->toBe([
        'activity',
        'sleep',
        'food',
        'film',
        'tv-episode',
        'book',
        'event',
        'appearance',
        'this-week-with',
        'flight',
        'place',
        'fuel',
        'project',
        'article',
        'note',
    ]);
});

it('resolves each case from its stored backed value', function (string $value, TimelineType $case) {
    expect(TimelineType::from($value))->toBe($case);
})->with([
    ['activity', TimelineType::Activity],
    ['sleep', TimelineType::Sleep],
    ['food', TimelineType::Food],
    ['film', TimelineType::Film],
    ['tv-episode', TimelineType::TvEpisode],
    ['book', TimelineType::Book],
    ['event', TimelineType::Event],
    ['appearance', TimelineType::Appearance],
    ['this-week-with', TimelineType::ThisWeekWith],
    ['flight', TimelineType::Flight],
    ['place', TimelineType::Place],
    ['fuel', TimelineType::Fuel],
    ['project', TimelineType::Project],
    ['article', TimelineType::Article],
    ['note', TimelineType::Note],
]);
