<?php

use App\Support\Text;

it('returns the value unchanged when within the limit', function () {
    expect(Text::excerpt('Short topic', 160))->toBe('Short topic');
});

it('returns null for null', function () {
    expect(Text::excerpt(null))->toBeNull();
});

it('strips a trailing dash and spaces before the ellipsis', function () {
    $value = 'Pop Convention - Filming Sessions - Recording Good Audio - Upgrading';

    expect(Text::excerpt($value, 40))->toBe('Pop Convention - Filming Sessions…')
        ->and(Text::excerpt($value, 40))->not->toContain('-…');
});

it('cuts on a word boundary and never ends mid-punctuation', function () {
    $value = 'Alpha beta, gamma delta. Epsilon zeta eta theta iota kappa lambda';
    $result = Text::excerpt($value, 30);

    expect($result)->toEndWith('…')
        ->and(rtrim($result, '…'))->toMatch('/\w$/u');
});
