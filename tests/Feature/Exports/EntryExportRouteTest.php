<?php

use App\Models\Flight;
use App\Models\Note;

it('serves an entry as json at its own url plus an extension', function () {
    $flight = krkToLgw();

    $this->get($flight->url().'.json')
        ->assertOk()
        ->assertHeader('content-type', 'application/json')
        ->assertJsonPath('type', 'flight');
});

it('lists the other formats as a trail', function () {
    $flight = krkToLgw();

    $response = $this->get($flight->url().'.json')->json();

    expect($response['formats'])->toHaveKeys(['yaml', 'md', 'mf2', 'sql', 'ics', 'geojson'])
        ->and($response['formats'])->not->toHaveKey('json')
        ->and($response['formats']['yaml'])->toStartWith('http');
});

it('404s an extension the entry does not support', function () {
    $note = Note::factory()->create(['occurred_at' => '2026-06-08 10:00:00']);

    $this->get($note->url().'.geojson')->assertNotFound();
    $this->get($note->url().'.ics')->assertNotFound();
});

it('404s an unknown slug and an unpublished entry', function () {
    $this->get('/2026/06/08/nothing-here.json')->assertNotFound();

    $draft = Flight::factory()->create(['occurred_at' => '2026-06-08 22:15:00', 'status' => 'draft']);

    $this->get($draft->url().'.json')->assertNotFound();
});

it('does not let an extension fall through to the entry page', function () {
    krkToLgw();

    $this->get('/2026/06/08/krk-lgw.json')->assertHeader('content-type', 'application/json');
});
