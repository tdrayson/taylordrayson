<?php

use App\Enums\MediaType;

it('has exactly the three stored values', function () {
    expect(array_column(MediaType::cases(), 'value'))
        ->toBe(['film', 'tv_episode', 'book']);
});

it('labels each case for display', function () {
    expect(MediaType::Film->label())->toBe('Film')
        ->and(MediaType::TvEpisode->label())->toBe('TV episode')
        ->and(MediaType::Book->label())->toBe('Book');
});

it('resolves from the stored backed value', function () {
    expect(MediaType::from('tv_episode'))->toBe(MediaType::TvEpisode);
});
