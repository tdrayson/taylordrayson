<?php

use App\Models\Series;

it('redirects every old media url to its new home', function (string $from, string $to) {
    $this->get($from)->assertMovedPermanently()->assertRedirect($to);
})->with([
    ['/media', '/films'],
    ['/media/films', '/films'],
    ['/media/books', '/books'],
    ['/media/tv', '/tv'],
]);

it('redirects an old show url to the tv show page', function () {
    $series = Series::factory()->create(['slug' => 'severance']);

    $this->get('/media/tv/severance')->assertMovedPermanently()->assertRedirect('/tv/severance');
    $this->get('/tv/severance')->assertOk();
    expect($series->url())->toBe('/tv/severance');
});

it('serves the tv index and the film and book archives', function () {
    $this->get('/tv')->assertOk();
    $this->get('/films')->assertOk();
    $this->get('/books')->assertOk();
});
