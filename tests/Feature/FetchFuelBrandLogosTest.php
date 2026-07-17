<?php

use App\Models\Fuel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.logodev.token' => 'test-token']);
});

afterEach(function () {
    foreach (['bp', 'shell'] as $slug) {
        File::delete(public_path("logos/brands/{$slug}.png"));
    }
});

function fakeBrandLogoHttp(): void
{
    Http::fake([
        '*petrolfinder.uk/api/brands*' => Http::response([
            'brands' => [
                ['brand' => 'BP', 'logo' => 'https://cdn.brandfetch.io/bp.com?c=abc'],
            ],
        ], 200),
        '*img.logo.dev*' => Http::response('PNG-BYTES', 200, ['Content-Type' => 'image/png']),
    ]);
}

it('downloads and stores a logo for a fuel brand', function () {
    fakeBrandLogoHttp();
    Fuel::factory()->create(['brand' => 'BP']);

    $this->artisan('fuel:brand-logos')->assertExitCode(0);

    expect(File::exists(public_path('logos/brands/bp.png')))->toBeTrue();
    expect(File::get(public_path('logos/brands/bp.png')))->toBe('PNG-BYTES');
});

it('skips a brand whose logo already exists without --force', function () {
    fakeBrandLogoHttp();
    Fuel::factory()->create(['brand' => 'BP']);
    File::ensureDirectoryExists(public_path('logos/brands'));
    File::put(public_path('logos/brands/bp.png'), 'existing');

    $this->artisan('fuel:brand-logos')->assertExitCode(0);

    Http::assertNothingSent();
    expect(File::get(public_path('logos/brands/bp.png')))->toBe('existing');
});

it('writes no file when logo.dev has no logo for the brand', function () {
    Http::fake([
        '*petrolfinder.uk/api/brands*' => Http::response([
            'brands' => [['brand' => 'BP', 'logo' => 'https://cdn.brandfetch.io/bp.com?c=abc']],
        ], 200),
        '*img.logo.dev*' => Http::response('', 404),
    ]);
    Fuel::factory()->create(['brand' => 'BP']);

    $this->artisan('fuel:brand-logos')->assertExitCode(0);

    expect(File::exists(public_path('logos/brands/bp.png')))->toBeFalse();
});

it('errors when the logo.dev token is not set', function () {
    config(['services.logodev.token' => null]);
    Fuel::factory()->create(['brand' => 'BP']);

    $this->artisan('fuel:brand-logos')->assertExitCode(1);
});
