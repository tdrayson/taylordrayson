<?php

use App\Actions\GenerateLocationMap;
use App\Models\Event;
use Illuminate\Support\Facades\Http;

it('generates and attaches a static pin map for an event with coordinates', function () {
    config()->set('services.mapbox.token', 'test-token');
    Http::fake(['api.mapbox.com/*' => Http::response('PNGBYTES', 200)]);

    $event = Event::factory()->create(['latitude' => 51.5129, 'longitude' => -0.1201]);

    $media = (new GenerateLocationMap)($event);

    expect($media)->not->toBeNull()
        ->and($event->fresh()->getFirstMedia('map'))->not->toBeNull();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'pin-')
        && str_contains($request->url(), '-0.1201,51.5129'));
});

it('returns null when the model has no coordinates', function () {
    config()->set('services.mapbox.token', 'test-token');
    $event = Event::factory()->create(['latitude' => null, 'longitude' => null]);

    expect((new GenerateLocationMap)($event))->toBeNull();
});
