<?php

use App\Models\Fuel;
use Illuminate\Support\Facades\File;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

/**
 * Texaco is a real brand, so it resolves to a logo domain, and texaco.png is
 * not one of the logos already on disk, so these tests cannot clobber one.
 */
beforeEach(function () {
    config(['services.logodev.token' => 'test-token']);
});

afterEach(function () {
    foreach (['texaco'] as $slug) {
        File::delete(public_path("logos/brands/{$slug}.png"));
    }
});

it('downloads and stores a logo for a fuel brand', function () {
    Saloon::fake(['img.logo.dev*' => MockResponse::make('PNG-BYTES', 200, ['Content-Type' => 'image/png'])]);
    Fuel::factory()->create(['brand' => 'Texaco']);

    $this->artisan('fuel:brand-logos')->assertExitCode(0);

    expect(File::exists(public_path('logos/brands/texaco.png')))->toBeTrue();
    expect(File::get(public_path('logos/brands/texaco.png')))->toBe('PNG-BYTES');
    Saloon::assertSent(fn ($request, $response) => str_contains($response->getPendingRequest()->getUrl(), 'texaco.com'));
});

it('skips a brand whose logo already exists without --force', function () {
    Saloon::fake(['img.logo.dev*' => MockResponse::make('PNG-BYTES', 200, ['Content-Type' => 'image/png'])]);
    Fuel::factory()->create(['brand' => 'Texaco']);
    File::ensureDirectoryExists(public_path('logos/brands'));
    File::put(public_path('logos/brands/texaco.png'), 'existing');

    $this->artisan('fuel:brand-logos')->assertExitCode(0);

    Saloon::assertNothingSent();
    expect(File::get(public_path('logos/brands/texaco.png')))->toBe('existing');
});

it('writes no file when logo.dev has no logo for the brand', function () {
    Saloon::fake(['img.logo.dev*' => MockResponse::make('', 404)]);
    Fuel::factory()->create(['brand' => 'Texaco']);

    $this->artisan('fuel:brand-logos')->assertExitCode(0);

    expect(File::exists(public_path('logos/brands/texaco.png')))->toBeFalse();
});

it('does not call logo.dev for a brand with no known domain', function () {
    Saloon::fake(['img.logo.dev*' => MockResponse::make('PNG-BYTES', 200, ['Content-Type' => 'image/png'])]);
    Fuel::factory()->create(['brand' => 'Indie Fuels']);

    $this->artisan('fuel:brand-logos')->assertExitCode(0);

    Saloon::assertNothingSent();
});

it('errors when the logo.dev token is not set', function () {
    config(['services.logodev.token' => null]);
    Fuel::factory()->create(['brand' => 'Texaco']);

    $this->artisan('fuel:brand-logos')->assertExitCode(1);
});
