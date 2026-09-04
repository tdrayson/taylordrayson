<?php

use App\Actions\GenerateLocationMap;
use App\Models\Checkin;
use App\Models\Event;
use App\Presenters\CardPresenter;
use Illuminate\Support\Facades\Storage;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function () {
    Storage::fake('public');
});

it('generates and attaches a static pin map for an event with coordinates', function () {
    config()->set('services.mapbox.token', 'test-token');
    Saloon::fake(['api.mapbox.com/*' => MockResponse::make(mapPng(), 200)]);

    $event = Event::factory()->create(['latitude' => 51.5129, 'longitude' => -0.1201]);

    $media = (new GenerateLocationMap)($event);

    expect($media)->not->toBeNull()
        ->and($event->fresh()->getFirstMedia('map'))->not->toBeNull();

    Saloon::assertSent(fn ($request, $response) => str_contains($response->getPendingRequest()->getUrl(), 'pin-')
        && str_contains($response->getPendingRequest()->getUrl(), '-0.1201,51.5129'));
});

it('generates both light and dark static pin maps and exposes both URLs on the card', function () {
    config()->set('services.mapbox.token', 'test-token');
    Saloon::fake([
        'api.mapbox.com/styles/v1/mapbox/light-v11/*' => MockResponse::make(mapPng(220), 200),
        'api.mapbox.com/styles/v1/mapbox/dark-v11/*' => MockResponse::make(mapPng(40), 200),
    ]);

    $event = Event::factory()->create(['latitude' => 51.5129, 'longitude' => -0.1201]);

    $media = (new GenerateLocationMap)($event);
    $event = $event->fresh();

    expect($media)->not->toBeNull()
        ->and($event->getFirstMedia('map'))->not->toBeNull()
        ->and($event->getFirstMedia('map_dark'))->not->toBeNull();

    $card = CardPresenter::for($event);

    expect($card->meta->map)->not->toBeNull()
        ->and($card->meta->mapDark)->not->toBeNull()
        ->and($card->meta->map)->not->toBe($card->meta->mapDark);

    Saloon::assertSent(fn ($request, $response) => str_contains($response->getPendingRequest()->getUrl(), 'mapbox/light-v11'));
    Saloon::assertSent(fn ($request, $response) => str_contains($response->getPendingRequest()->getUrl(), 'mapbox/dark-v11'));
});

it('returns null when the model has no coordinates', function () {
    config()->set('services.mapbox.token', 'test-token');
    $event = Event::factory()->create(['latitude' => null, 'longitude' => null]);

    expect((new GenerateLocationMap)($event))->toBeNull();
});

it('stores light and dark pins using the given marker colour', function () {
    config(['services.mapbox.token' => 'test-token']);
    Saloon::fake(['api.mapbox.com*' => MockResponse::make(mapPng(), 200)]);

    $checkin = Checkin::factory()->create(['latitude' => 51.5, 'longitude' => -0.1]);

    app(GenerateLocationMap::class)($checkin, 'ff8800');

    expect($checkin->getFirstMediaUrl('map'))->not->toBe('');
    expect($checkin->getFirstMediaUrl('map_dark'))->not->toBe('');

    Saloon::assertSent(fn ($request, $response) => str_contains($response->getPendingRequest()->getUrl(), 'pin-l+ff8800')
        && str_contains($response->getPendingRequest()->getUrl(), 'light-v11'));
    Saloon::assertSent(fn ($request, $response) => str_contains($response->getPendingRequest()->getUrl(), 'dark-v11'));
});

it('returns null when the checkin has no coordinates', function () {
    config(['services.mapbox.token' => 'test-token']);
    $checkin = Checkin::factory()->create(['latitude' => null, 'longitude' => null]);

    expect(app(GenerateLocationMap::class)($checkin, 'ff8800'))->toBeNull();
});
