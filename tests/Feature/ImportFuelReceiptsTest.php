<?php

use App\Actions\Fuel\ExtractReceiptLocation;
use App\Actions\Fuel\ReceiptLocation;
use App\Models\Fuel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->folder = storage_path('app/test-receipts');
    File::ensureDirectoryExists($this->folder);
    File::put($this->folder.'/IMG_1.jpeg', 'dummy');
    $this->review = storage_path('app/fuel/test-review.csv');
    File::delete($this->review);
});

afterEach(function () {
    File::deleteDirectory(storage_path('app/test-receipts'));
    File::delete(storage_path('app/fuel/test-review.csv'));
    File::delete(storage_path('app/test-fuel.csv'));
});

it('writes a review csv matching a receipt to the nearest fuel entry and station', function () {
    $this->app->instance(ExtractReceiptLocation::class, new class extends ExtractReceiptLocation
    {
        public function __invoke(string $path): ?ReceiptLocation
        {
            return new ReceiptLocation($path, CarbonImmutable::parse('2026-05-07 20:56:00'), 51.373122, -0.131797);
        }
    });

    Http::fake([
        '*/api/brands*' => Http::response(['brands' => [
            ['brand' => 'Asda', 'logo' => 'https://cdn.brandfetch.io/asda.com'],
        ]]),
        '*/api/search*' => Http::response(['stations' => [[
            'name' => 'ASDA WALLINGTON', 'brand' => 'ASDA', 'address' => 'MARLOW WAY',
            'postcode' => 'CR0 4XS', 'city' => 'CROYDON',
            'latitude' => 51.3767, 'longitude' => -0.1313, 'distance' => 0.4,
        ]]]),
    ]);

    $fuel = Fuel::factory()->create(['occurred_at' => '2026-05-07 20:52:23', 'station_name' => null]);

    $this->artisan('import:fuel-receipts', ['folder' => $this->folder, '--review' => $this->review])
        ->assertSuccessful();

    expect(File::exists($this->review))->toBeTrue();
    $contents = File::get($this->review);
    expect($contents)->toContain('Asda Wallington');
    expect($contents)->toContain(','.$fuel->id.',');
    expect($contents)->toContain('51.3767');
    // dry run writes nothing to the database
    expect($fuel->fresh()->station_name)->toBeNull();
});

it('applies a reviewed csv onto fuel rows and regenerates the backup csv', function () {
    $fuel = Fuel::factory()->create(['station_name' => null]);
    File::ensureDirectoryExists(dirname($this->review));
    $header = 'receipt_file,receipt_time,fuel_id,fuel_occurred_at,delta_minutes,receipt_lat,receipt_lng,station_name,brand,address,postcode,city,station_lat,station_lng,distance_km,alt1_name,alt2_name,flag';
    $row = "IMG_1.jpeg,2026-05-07 20:56:00,{$fuel->id},2026-05-07 20:52:23,4,51.37,-0.13,ASDA WALLINGTON,ASDA,MARLOW WAY,CR0 4XS,CROYDON,51.3767,-0.1313,0.4,,,ok";
    File::put($this->review, $header."\n".$row."\n");

    $export = storage_path('app/test-fuel.csv');

    $this->artisan('import:fuel-receipts', [
        'folder' => $this->folder,
        '--apply' => true,
        '--review' => $this->review,
        '--export' => $export,
    ])->assertSuccessful();

    $fuel->refresh();
    expect($fuel->station_name)->toBe('ASDA WALLINGTON');
    expect($fuel->brand)->toBe('ASDA');
    expect($fuel->country)->toBe('United Kingdom');
    expect((float) $fuel->latitude)->toBe(51.3767);
    expect((float) $fuel->longitude)->toBe(-0.1313);
    expect(File::get($export))->toContain('ASDA WALLINGTON');
});
