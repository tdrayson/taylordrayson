<?php

use App\Enums\MediaType;

it('has exactly the three stored values', function () {
    expect(array_column(MediaType::cases(), 'value'))
        ->toBe(['film', 'episode', 'book']);
});

it('labels each case for display', function () {
    expect(MediaType::Film->label())->toBe('Film')
        ->and(MediaType::TvEpisode->label())->toBe('TV episode')
        ->and(MediaType::Book->label())->toBe('Book');
});

it('resolves from the stored backed value', function () {
    expect(MediaType::from('episode'))->toBe(MediaType::TvEpisode);
});
