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

it('generates both light and dark static pin maps and exposes both URLs on the card', function () {
    config()->set('services.mapbox.token', 'test-token');
    Http::fake([
        'api.mapbox.com/styles/v1/mapbox/light-v11/*' => Http::response('LIGHT-PNG', 200),
        'api.mapbox.com/styles/v1/mapbox/dark-v11/*' => Http::response('DARK-PNG', 200),
    ]);

    $event = Event::factory()->create(['latitude' => 51.5129, 'longitude' => -0.1201]);

    $media = (new GenerateLocationMap)($event);
    $event = $event->fresh();

    expect($media)->not->toBeNull()
        ->and($event->getFirstMedia('map'))->not->toBeNull()
        ->and($event->getFirstMedia('map_dark'))->not->toBeNull();

    $card = $event->card();

    expect($card['meta']['map'])->not->toBeNull()
        ->and($card['meta']['mapDark'])->not->toBeNull()
        ->and($card['meta']['map'])->not->toBe($card['meta']['mapDark']);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'mapbox/light-v11'));
    Http::assertSent(fn ($request) => str_contains($request->url(), 'mapbox/dark-v11'));
});

it('returns null when the model has no coordinates', function () {
    config()->set('services.mapbox.token', 'test-token');
    $event = Event::factory()->create(['latitude' => null, 'longitude' => null]);

    expect((new GenerateLocationMap)($event))->toBeNull();
});
