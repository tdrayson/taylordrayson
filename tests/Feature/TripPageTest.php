<?php

use App\Models\Checkin;
use App\Models\Trip;

use function Pest\Laravel\get;

/**
 * The trip window is compared as instants, not wall-clock. A trip starting
 * 08:00 in London begins at 07:00Z, so a Tokyo check-in stamped 15:00 local
 * that same date (06:00Z) happened before the trip started, and one stamped
 * 17:00 local (08:00Z) happened after. Comparing the stored wall-clock numbers
 * would wrongly include both.
 */
it('matches entries by instant rather than wall-clock time', function () {
    $trip = Trip::factory()->create([
        'title' => 'Tokyo 2026',
        'slug' => 'tokyo-2026',
        'starts_at' => '2026-08-05 08:00:00',
        'ends_at' => '2026-08-12 22:00:00',
        'timezone' => 'Europe/London',
    ]);

    Checkin::factory()->create([
        'venue_name' => 'Too Early',
        'occurred_at' => '2026-08-05 15:00:00',
        'timezone' => 'Asia/Tokyo',
    ]);

    Checkin::factory()->create([
        'venue_name' => 'Just Landed',
        'occurred_at' => '2026-08-05 17:00:00',
        'timezone' => 'Asia/Tokyo',
    ]);

    get($trip->url())->assertOk()->assertInertia(fn ($page) => $page
        ->component('Trip')
        ->where('groups', function ($groups) {
            $titles = collect($groups)->flatMap(fn ($group) => collect($group['items'])->pluck('title'))->all();

            return in_array('Just Landed', $titles, true) && ! in_array('Too Early', $titles, true);
        })
    );
});

it('gathers every type inside the window and excludes entries outside it', function () {
    $trip = Trip::factory()->create([
        'title' => 'Las Vegas 2026',
        'slug' => 'las-vegas-2026',
        'starts_at' => '2026-03-01 06:00:00',
        'ends_at' => '2026-03-08 23:00:00',
        'timezone' => 'Europe/London',
    ]);

    Checkin::factory()->create([
        'venue_name' => 'Inside The Window',
        'occurred_at' => '2026-03-04 12:00:00',
        'timezone' => 'America/Los_Angeles',
    ]);

    Checkin::factory()->create([
        'venue_name' => 'Long Before',
        'occurred_at' => '2026-01-04 12:00:00',
        'timezone' => 'Europe/London',
    ]);

    Checkin::factory()->create([
        'venue_name' => 'Long After',
        'occurred_at' => '2026-06-04 12:00:00',
        'timezone' => 'Europe/London',
    ]);

    get($trip->url())->assertOk()->assertInertia(fn ($page) => $page
        ->component('Trip')
        ->where('title', 'Las Vegas 2026')
        ->where('groups', function ($groups) {
            $titles = collect($groups)->flatMap(fn ($group) => collect($group['items'])->pluck('title'))->all();

            return $titles === ['Inside The Window'];
        })
    );
});

it('orders trip entries chronologically', function () {
    $trip = Trip::factory()->create([
        'slug' => 'ordering',
        'starts_at' => '2026-04-01 00:00:00',
        'ends_at' => '2026-04-05 23:59:00',
        'timezone' => 'Europe/London',
    ]);

    Checkin::factory()->create(['venue_name' => 'Third', 'occurred_at' => '2026-04-04 09:00:00', 'timezone' => 'Europe/London']);
    Checkin::factory()->create(['venue_name' => 'First', 'occurred_at' => '2026-04-01 09:00:00', 'timezone' => 'Europe/London']);
    Checkin::factory()->create(['venue_name' => 'Second', 'occurred_at' => '2026-04-02 09:00:00', 'timezone' => 'Europe/London']);

    get($trip->url())->assertOk()->assertInertia(fn ($page) => $page
        ->where('groups', function ($groups) {
            $titles = collect($groups)->flatMap(fn ($group) => collect($group['items'])->pluck('title'))->all();

            return $titles === ['First', 'Second', 'Third'];
        })
    );
});

it('lists trips newest first on the index', function () {
    Trip::factory()->create(['title' => 'Older', 'slug' => 'older', 'starts_at' => '2024-01-01 00:00:00', 'ends_at' => '2024-01-05 00:00:00']);
    Trip::factory()->create(['title' => 'Newer', 'slug' => 'newer', 'starts_at' => '2026-01-01 00:00:00', 'ends_at' => '2026-01-08 00:00:00']);

    get('/trips')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Trips')
        ->where('trips.0.title', 'Newer')
        ->where('trips.0.days', 8)
        ->where('trips.1.title', 'Older')
        ->where('trips.1.days', 5)
    );
});

it('returns 404 for an unknown trip slug', function () {
    get('/trips/no-such-trip')->assertNotFound();
});

it('links an entry back to the trip its instant falls inside', function () {
    Trip::factory()->create([
        'title' => 'Amsterdam 2026',
        'slug' => 'amsterdam-2026',
        'starts_at' => '2026-05-01 07:00:00',
        'ends_at' => '2026-05-06 21:00:00',
        'timezone' => 'Europe/London',
    ]);

    $inside = Checkin::factory()->create([
        'venue_name' => 'Rijksmuseum',
        'occurred_at' => '2026-05-03 11:00:00',
        'timezone' => 'Europe/Amsterdam',
    ]);

    $outside = Checkin::factory()->create([
        'venue_name' => 'Home Again',
        'occurred_at' => '2026-05-20 11:00:00',
        'timezone' => 'Europe/London',
    ]);

    get($inside->url())->assertOk()->assertInertia(fn ($page) => $page
        ->where('trip.title', 'Amsterdam 2026')
        ->where('trip.url', '/trips/amsterdam-2026')
    );

    get($outside->url())->assertOk()->assertInertia(fn ($page) => $page->where('trip', null));
});
