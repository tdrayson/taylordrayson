<?php

use App\Models\Episode;

it('raises on a mistyped field instead of quietly reading null', function () {
    // The point of the whole exercise: `meta['show_titel']` used to be null,
    // indistinguishable from a show with no title.
    $episode = Episode::factory()->create(['meta' => ['show_title' => 'Ted Lasso']]);

    expect(fn (): ?string => $episode->meta->showTitel)->toThrow(ErrorException::class);
});
