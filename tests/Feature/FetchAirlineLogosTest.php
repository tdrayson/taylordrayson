<?php

use App\Models\Airline;
use Illuminate\Support\Facades\File;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function () {
    config(['services.logostream.key' => 'test-key']);
});

afterEach(function () {
    foreach (['icon', 'logo'] as $type) {
        foreach (['XX', 'YY'] as $code) {
            File::delete(public_path("logos/airlines/{$type}/{$code}.png"));
        }
    }
});

it('downloads real logos and skips airlines with no logo available', function () {
    // A callable mock receives the PendingRequest, so one wildcard entry can
    // answer differently per URL.
    Saloon::fake(['*' => function ($pendingRequest) {
        if (str_contains($pendingRequest->getUrl(), '/iata/XX')) {
            return MockResponse::make('FAKE-PNG-BYTES', 200, ['Content-Type' => 'image/png', 'x-asset' => 'XXX_logo']);
        }

        // LogoStream serves an SVG placeholder with x-asset "-" when it has no logo.
        return MockResponse::make('<svg/>', 200, ['Content-Type' => 'image/svg+xml', 'x-asset' => '-']);
    }]);

    $this->artisan('airlines:logos', ['iata' => ['XX', 'YY']])->assertExitCode(0);

    expect(File::exists(public_path('logos/airlines/icon/XX.png')))->toBeTrue();
    expect(File::exists(public_path('logos/airlines/logo/XX.png')))->toBeTrue();
    expect(File::exists(public_path('logos/airlines/icon/YY.png')))->toBeFalse();
    expect(File::exists(public_path('logos/airlines/logo/YY.png')))->toBeFalse();
});

it('does not re-download logos that already exist without --force', function () {
    File::ensureDirectoryExists(public_path('logos/airlines/icon'));
    File::ensureDirectoryExists(public_path('logos/airlines/logo'));
    File::put(public_path('logos/airlines/icon/XX.png'), 'existing');
    File::put(public_path('logos/airlines/logo/XX.png'), 'existing');

    Saloon::fake(['' => MockResponse::make('', 200)]);

    $this->artisan('airlines:logos', ['iata' => ['XX']])->assertExitCode(0);

    Saloon::assertNothingSent();
    expect(File::get(public_path('logos/airlines/logo/XX.png')))->toBe('existing');
});

it('resolves icon and logo urls by IATA code, null when absent', function () {
    File::ensureDirectoryExists(public_path('logos/airlines/logo'));
    File::put(public_path('logos/airlines/logo/XX.png'), 'x');

    $airline = new Airline(['iata_code' => 'XX']);

    expect($airline->logo_url)->toBe('/logos/airlines/logo/XX.png');
    expect($airline->icon_url)->toBeNull();
});
