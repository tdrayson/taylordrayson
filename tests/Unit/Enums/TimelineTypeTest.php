<?php

use App\Enums\TimelineType;

it('has exactly the thirteen stored values', function () {
    expect(array_column(TimelineType::cases(), 'value'))->toBe([
        'activity',
        'sleep',
        'calorie',
        'media',
        'event',
        'appearance',
        'podcast',
        'flight',
        'checkin',
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
    ['calorie', TimelineType::Calorie],
    ['media', TimelineType::Media],
    ['event', TimelineType::Event],
    ['appearance', TimelineType::Appearance],
    ['podcast', TimelineType::Podcast],
    ['flight', TimelineType::Flight],
    ['checkin', TimelineType::Checkin],
    ['fuel', TimelineType::Fuel],
    ['project', TimelineType::Project],
    ['article', TimelineType::Article],
    ['note', TimelineType::Note],
]);
