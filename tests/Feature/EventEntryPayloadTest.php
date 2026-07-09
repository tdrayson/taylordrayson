<?php

use App\Models\Event;

it('exposes photos, location and a google maps url on the event entry', function () {
    $event = Event::factory()->create([
        'name' => 'Test Gig',
        'occurred_at' => '2022-06-02 19:00:00',
        'ends_at' => null,
        'venue_name' => 'Some Venue',
        'city' => 'London',
        'country' => 'United Kingdom',
        'latitude' => 51.5,
        'longitude' => -0.12,
        'meta' => ['address' => 'Some Venue, 1 Test St, London, UK'],
    ]);

    $this->get($event->url())
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('entry.photos')
            ->where('entry.location.lat', 51.5)
            ->where('entry.location.mapsUrl', 'https://www.google.com/maps/search/?api=1&query='.urlencode('Some Venue, 1 Test St, London, UK'))
            ->where('entry.range', null));
});

it('omits location when the event has no coordinates', function () {
    $event = Event::factory()->create(['latitude' => null, 'longitude' => null]);

    $this->get($event->url())
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('entry.location', null));
});

it('exposes a range badge payload for multi-day events', function () {
    $event = Event::factory()->create([
        'occurred_at' => '2022-06-02 09:00:00',
        'ends_at' => '2022-06-04 18:00:00',
    ]);

    $this->get($event->url())
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('entry.range.days', 3)
            ->where('entry.range.label', '2-4 Jun 2022'));
});
