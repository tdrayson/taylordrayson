<?php

use App\Models\TvShow;

it('redirects every old media url to its new home', function (string $from, string $to) {
    $this->get($from)->assertMovedPermanently()->assertRedirect($to);
})->with([
    ['/media', '/films'],
    ['/media/films', '/films'],
    ['/media/books', '/books'],
    ['/media/tv', '/tv-shows'],
]);

it('redirects an old show url to the tv show page', function () {
    $tvShow = TvShow::factory()->create(['slug' => 'severance']);

    $this->get('/media/tv/severance')->assertMovedPermanently()->assertRedirect('/tv-shows/severance');
    $this->get('/tv-shows/severance')->assertOk();
    expect($tvShow->url())->toBe('/tv-shows/severance');
});

it('serves the tv index and the film and book archives', function () {
    $this->get('/tv-shows')->assertOk();
    $this->get('/films')->assertOk();
    $this->get('/books')->assertOk();
});
