<?php

use App\Models\Fuel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.logodev.token' => 'test-token']);
});

afterEach(function () {
    foreach (['testco'] as $slug) {
        File::delete(public_path("logos/brands/{$slug}.png"));
    }
});

function fakeBrandLogoHttp(): void
{
    Http::fake([
        '*petrolfinder.uk/api/brands*' => Http::response([
            'brands' => [
                ['brand' => 'Testco', 'logo' => 'https://cdn.brandfetch.io/testco.example?c=abc'],
            ],
        ], 200),
        '*img.logo.dev*' => Http::response('PNG-BYTES', 200, ['Content-Type' => 'image/png']),
    ]);
}

it('downloads and stores a logo for a fuel brand', function () {
    fakeBrandLogoHttp();
    Fuel::factory()->create(['brand' => 'Testco']);

    $this->artisan('fuel:brand-logos')->assertExitCode(0);

    expect(File::exists(public_path('logos/brands/testco.png')))->toBeTrue();
    expect(File::get(public_path('logos/brands/testco.png')))->toBe('PNG-BYTES');
});

it('skips a brand whose logo already exists without --force', function () {
    fakeBrandLogoHttp();
    Fuel::factory()->create(['brand' => 'Testco']);
    File::ensureDirectoryExists(public_path('logos/brands'));
    File::put(public_path('logos/brands/testco.png'), 'existing');

    $this->artisan('fuel:brand-logos')->assertExitCode(0);

    Http::assertNothingSent();
    expect(File::get(public_path('logos/brands/testco.png')))->toBe('existing');
});

it('writes no file when logo.dev has no logo for the brand', function () {
    Http::fake([
        '*petrolfinder.uk/api/brands*' => Http::response([
            'brands' => [['brand' => 'Testco', 'logo' => 'https://cdn.brandfetch.io/testco.example?c=abc']],
        ], 200),
        '*img.logo.dev*' => Http::response('', 404),
    ]);
    Fuel::factory()->create(['brand' => 'Testco']);

    $this->artisan('fuel:brand-logos')->assertExitCode(0);

    expect(File::exists(public_path('logos/brands/testco.png')))->toBeFalse();
});

it('errors when the logo.dev token is not set', function () {
    config(['services.logodev.token' => null]);
    Fuel::factory()->create(['brand' => 'Testco']);

    $this->artisan('fuel:brand-logos')->assertExitCode(1);
});
