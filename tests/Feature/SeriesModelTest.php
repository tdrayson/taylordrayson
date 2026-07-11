<?php

use App\Models\Media;
use App\Models\Series;

it('links episodes to a series', function () {
    $series = Series::factory()->create(['title' => 'The Good Doctor']);
    $episode = Media::factory()->create(['type' => 'episode', 'series_id' => $series->id]);

    expect($series->episodes)->toHaveCount(1)
        ->and($episode->series->is($series))->toBeTrue();
});

it('generates a base slug, then disambiguates by year, then by suffix', function () {
    $taken = [];
    // Regular closure with capture-by-reference: an arrow fn would snapshot
    // $taken at creation time and never see the appends below.
    $exists = function (string $slug) use (&$taken): bool {
        return in_array($slug, $taken, true);
    };

    $a = Series::slugFor('The Office', 2005, $exists);
    $taken[] = $a;
    $b = Series::slugFor('The Office', 2001, $exists);
    $taken[] = $b;
    $c = Series::slugFor('The Office', 2001, $exists); // same name AND year

    expect($a)->toBe('the-office')
        ->and($b)->toBe('the-office-2001')
        ->and($c)->toBe('the-office-2001-2');
});
