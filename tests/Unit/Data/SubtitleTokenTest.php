<?php

use App\Data\SubtitleToken;

it('serialises a dist token with only t, m, p', function () {
    expect(SubtitleToken::dist(5000, 1)->toArray())->toBe(['t' => 'dist', 'm' => 5000, 'p' => 1]);
});

it('serialises a wt token with only t, kg, p', function () {
    expect(SubtitleToken::wt(60.5, 0)->toArray())->toBe(['t' => 'wt', 'kg' => 60.5, 'p' => 0]);
});

it('serialises a text token with only t, v', function () {
    expect(SubtitleToken::text('1 exercise')->toArray())->toBe(['t' => 'text', 'v' => '1 exercise']);
});

it('serialises a text token with a null value', function () {
    expect(SubtitleToken::text(null)->toArray())->toBe(['t' => 'text', 'v' => null]);
});

it('jsonSerialize matches toArray', function () {
    $token = SubtitleToken::dist(100, 0);

    expect($token->jsonSerialize())->toBe($token->toArray());
});
