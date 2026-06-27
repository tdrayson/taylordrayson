<?php

use App\Support\OgPhrases;

it('returns null for an unknown key', function () {
    expect(OgPhrases::pick('nope.not.here'))->toBeNull();
});

it('fills placeholders in the chosen phrase', function () {
    config(['og-phrases.demo' => ['I ate :kcal kcal']]);

    expect(OgPhrases::pick('demo', ['kcal' => '2,140']))->toBe('I ate 2,140 kcal');
});

it('is deterministic for a given seed', function () {
    config(['og-phrases.demo' => ['a', 'b', 'c', 'd', 'e']]);

    $pick = OgPhrases::pick('demo', [], 'seed-123');

    expect(OgPhrases::pick('demo', [], 'seed-123'))->toBe($pick);
});

it('varies across different seeds', function () {
    config(['og-phrases.demo' => ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h']]);

    $picks = collect(range(1, 16))
        ->map(fn (int $index): ?string => OgPhrases::pick('demo', [], "seed-{$index}"))
        ->unique();

    expect($picks->count())->toBeGreaterThan(1);
});
