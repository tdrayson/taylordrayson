# Fuel garage/location support Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Attach garage/location data to fuel logs, backfill it from GPS-tagged receipt photos via a reusable PetrolFinder API client, and keep it as a self-contained CSV backup.

**Architecture:** Store the garage flat on each `fuel` row (mirroring `Checkin`), dropping the empty `FuelStation` table/relation. A keyless `PetrolFinder` service client wraps `GET /api/search` for both coordinate and address lookups. A `import:fuel-receipts` command reads receipt EXIF, matches each to the nearest fuel log by timestamp, resolves the nearest station, and writes an editable review CSV that a second `--apply` pass writes to the database.

**Tech Stack:** PHP 8.4, Laravel 13, Pest 4, SQLite (dev), PHP `exif` extension.

## Global Constraints

- PHP 8.4: explicit return types on every method; constructor property promotion; curly braces on all control structures.
- PHPDoc blocks over inline comments; add array-shape PHPDoc where useful.
- Immutable value objects use `readonly` classes.
- Never call `env()` outside config; use `Model::query()`, never `DB::`.
- After modifying any PHP file, run `vendor/bin/pint --dirty --format agent` before committing.
- Tests use Pest (`it()`/`expect()`); run with `php artisan test --compact --filter=<name>`.
- Commit messages: conventional commits (`feat:`, `refactor:`, `test:`), no attribution footer.
- Branch: `feat/fuel-station-lookup` (already created off master).

---

### Task 1: PetrolFinder service client + FuelStationResult DTO

**Files:**
- Create: `app/Services/PetrolFinder/FuelStationResult.php`
- Create: `app/Services/PetrolFinder.php`
- Test: `tests/Feature/PetrolFinderTest.php`

**Interfaces:**
- Produces: `App\Services\PetrolFinder\FuelStationResult` (readonly) with public props
  `string $stationName, ?string $brand, ?string $address, ?string $postcode, ?string $city, ?float $latitude, ?float $longitude, ?float $distance` and `static fromApi(array $station): self`.
- Produces: `App\Services\PetrolFinder` with
  `search(?string $query = null, ?float $latitude = null, ?float $longitude = null, float $radius = 10): array` (returns `FuelStationResult[]`),
  `nearest(float $latitude, float $longitude, float $radius = 5): ?FuelStationResult`,
  `searchByAddress(string $query, float $radius = 10): array`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PetrolFinderTest.php`:

```php
<?php

use App\Services\PetrolFinder;
use App\Services\PetrolFinder\FuelStationResult;
use Illuminate\Support\Facades\Http;

it('searches by coordinates and maps stations to results', function () {
    Http::fake(['*/api/search*' => Http::response(['stations' => [[
        'name' => 'ASDA WALLINGTON SUPERSTORE',
        'brand' => 'ASDA',
        'address' => 'MARLOW WAY, CROYDON',
        'postcode' => 'CR0 4XS',
        'city' => 'CROYDON',
        'latitude' => 51.3767648,
        'longitude' => -0.1313429,
        'distance' => 0.4,
    ]]])]);

    $results = app(PetrolFinder::class)->search(latitude: 51.3731, longitude: -0.1318);

    expect($results)->toHaveCount(1);
    expect($results[0])->toBeInstanceOf(FuelStationResult::class);
    expect($results[0]->stationName)->toBe('ASDA WALLINGTON SUPERSTORE');
    expect($results[0]->brand)->toBe('ASDA');
    expect($results[0]->postcode)->toBe('CR0 4XS');
    expect($results[0]->distance)->toBe(0.4);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'lat=51.3731')
        && str_contains($request->url(), 'lng=-0.1318'));
});

it('sends q for address searches', function () {
    Http::fake(['*/api/search*' => Http::response(['stations' => []])]);

    app(PetrolFinder::class)->searchByAddress('SW1A 1AA');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'q=SW1A'));
});

it('returns the closest station from nearest()', function () {
    Http::fake(['*/api/search*' => Http::response(['stations' => [
        ['name' => 'FAR', 'distance' => 2.5, 'latitude' => 1, 'longitude' => 1],
        ['name' => 'NEAR', 'distance' => 0.3, 'latitude' => 2, 'longitude' => 2],
    ]])]);

    $result = app(PetrolFinder::class)->nearest(51.3731, -0.1318);

    expect($result->stationName)->toBe('NEAR');
});

it('returns an empty array when the api fails', function () {
    Http::fake(['*/api/search*' => Http::response('boom', 500)]);

    expect(app(PetrolFinder::class)->search(latitude: 1, longitude: 1))->toBe([]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=PetrolFinder`
Expected: FAIL (class `App\Services\PetrolFinder` not found).

- [ ] **Step 3: Create the FuelStationResult DTO**

Create `app/Services/PetrolFinder/FuelStationResult.php`:

```php
<?php

namespace App\Services\PetrolFinder;

/**
 * An immutable fuel-station lookup result from the PetrolFinder API.
 */
readonly class FuelStationResult
{
    public function __construct(
        public string $stationName,
        public ?string $brand,
        public ?string $address,
        public ?string $postcode,
        public ?string $city,
        public ?float $latitude,
        public ?float $longitude,
        public ?float $distance,
    ) {}

    /**
     * @param  array<string, mixed>  $station
     */
    public static function fromApi(array $station): self
    {
        return new self(
            stationName: (string) ($station['name'] ?? ''),
            brand: $station['brand'] ?? null,
            address: $station['address'] ?? null,
            postcode: $station['postcode'] ?? null,
            city: $station['city'] ?? null,
            latitude: isset($station['latitude']) ? (float) $station['latitude'] : null,
            longitude: isset($station['longitude']) ? (float) $station['longitude'] : null,
            distance: isset($station['distance']) ? (float) $station['distance'] : null,
        );
    }
}
```

- [ ] **Step 4: Create the PetrolFinder client**

Create `app/Services/PetrolFinder.php`:

```php
<?php

namespace App\Services;

use App\Services\PetrolFinder\FuelStationResult;
use Illuminate\Support\Facades\Http;

/**
 * Client for the PetrolFinder.uk fuel-station lookup service.
 *
 * Uses the keyless public search endpoint (no /v1/ prefix, so no API key is
 * required). Supports both address/postcode (`q`) and coordinate lookups.
 */
class PetrolFinder
{
    private const BASE = 'https://www.petrolfinder.uk';

    /**
     * Search stations by postcode/place name (`$query`) or by coordinates.
     *
     * @return array<int, FuelStationResult>
     */
    public function search(?string $query = null, ?float $latitude = null, ?float $longitude = null, float $radius = 10): array
    {
        $parameters = ['radius' => $radius];

        if ($query !== null) {
            $parameters['q'] = $query;
        } else {
            $parameters['lat'] = $latitude;
            $parameters['lng'] = $longitude;
        }

        $response = Http::get(self::BASE.'/api/search', $parameters);

        if (! $response->successful()) {
            return [];
        }

        return array_map(
            fn (array $station): FuelStationResult => FuelStationResult::fromApi($station),
            $response->json('stations', []),
        );
    }

    /**
     * The single closest station to a coordinate, or null when none are found.
     */
    public function nearest(float $latitude, float $longitude, float $radius = 5): ?FuelStationResult
    {
        $stations = $this->search(latitude: $latitude, longitude: $longitude, radius: $radius);

        usort(
            $stations,
            fn (FuelStationResult $a, FuelStationResult $b): int => ($a->distance ?? INF) <=> ($b->distance ?? INF),
        );

        return $stations[0] ?? null;
    }

    /**
     * Search stations by postcode or place name.
     *
     * @return array<int, FuelStationResult>
     */
    public function searchByAddress(string $query, float $radius = 10): array
    {
        return $this->search(query: $query, radius: $radius);
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact --filter=PetrolFinder`
Expected: PASS (4 tests).

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/PetrolFinder.php app/Services/PetrolFinder/FuelStationResult.php tests/Feature/PetrolFinderTest.php
git commit -m "feat: add PetrolFinder fuel-station lookup client"
```

---

### Task 2: Flat garage columns on fuel, remove FuelStation

**Files:**
- Create: `database/migrations/<timestamp>_move_fuel_station_to_flat_columns.php` (via `make:migration`)
- Modify: `app/Models/Fuel.php`
- Modify: `database/factories/FuelFactory.php`
- Delete: `app/Models/FuelStation.php`
- Delete: `app/Actions/Fuel/ResolveFuelStation.php`
- Delete: `database/factories/FuelStationFactory.php`
- Delete: `tests/Feature/FuelStationTest.php`
- Delete: `tests/Feature/ResolveFuelStationTest.php`
- Test: `tests/Feature/FuelCardTest.php`

**Interfaces:**
- Produces: `App\Models\Fuel` with fillable `occurred_at, vehicle_id, station_name, brand, address, postcode, city, county, country, latitude, longitude, litres, cost, fuel_card_cost, price_per_litre, odometer` and no `fuelStation()` relation. `card()['title']` is `station_name` or `'Fuel'`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/FuelCardTest.php`:

```php
<?php

use App\Models\Fuel;

it('uses the flat station_name as the card title', function () {
    $fuel = Fuel::factory()->create([
        'station_name' => 'ASDA Wallington',
        'litres' => 32.13,
        'cost' => 41.13,
        'price_per_litre' => 1.28,
    ]);

    $card = $fuel->card();

    expect($card['title'])->toBe('ASDA Wallington');
    expect($card['titleLabel'])->toContain('ASDA Wallington');
    expect($card['subtitle'])->toContain('£41.13');
});

it('falls back to Fuel when no station is set', function () {
    $fuel = Fuel::factory()->create(['station_name' => null]);

    expect($fuel->card()['title'])->toBe('Fuel');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=FuelCard`
Expected: FAIL (`station_name` is not fillable / column missing).

- [ ] **Step 3: Generate and write the migration**

Run: `php artisan make:migration move_fuel_station_to_flat_columns --no-interaction`

Replace the generated file's contents with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel', function (Blueprint $table): void {
            $table->string('station_name')->nullable()->after('vehicle_id');
            $table->string('brand')->nullable()->after('station_name');
            $table->string('address')->nullable()->after('brand');
            $table->string('postcode')->nullable()->after('address');
            $table->string('city')->nullable()->after('postcode');
            $table->string('county')->nullable()->after('city');
            $table->string('country')->nullable()->after('county');
            $table->decimal('latitude', 10, 7)->nullable()->after('country');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });

        Schema::table('fuel', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('fuel_station_id');
        });

        Schema::dropIfExists('fuel_stations');
    }

    public function down(): void
    {
        Schema::create('fuel_stations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('brand')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();

            $table->index(['name', 'city', 'country']);
        });

        Schema::table('fuel', function (Blueprint $table): void {
            $table->foreignId('fuel_station_id')
                ->nullable()
                ->after('odometer')
                ->constrained('fuel_stations')
                ->nullOnDelete();

            $table->dropColumn([
                'station_name', 'brand', 'address', 'postcode',
                'city', 'county', 'country', 'latitude', 'longitude',
            ]);
        });
    }
};
```

- [ ] **Step 4: Update the Fuel model**

In `app/Models/Fuel.php`, replace the `#[Fillable([...])]` attribute with:

```php
#[Fillable([
    'occurred_at',
    'vehicle_id',
    'station_name',
    'brand',
    'address',
    'postcode',
    'city',
    'county',
    'country',
    'latitude',
    'longitude',
    'litres',
    'cost',
    'fuel_card_cost',
    'price_per_litre',
    'odometer',
])]
```

Remove the `fuelStation()` method and its `use Illuminate\Database\Eloquent\Relations\BelongsTo;` and `use App\Models\FuelStation;` imports (FuelStation import is not present; only remove BelongsTo). Replace the `card()` method with:

```php
public function card(): array
{
    $parts = array_filter([
        sprintf('%sL, £%.2f', $this->litres, $this->cost),
        $this->price_per_litre ? sprintf('£%s / L', number_format($this->price_per_litre, 3)) : null,
    ]);

    return [
        'type' => 'fuel',
        'icon' => 'fuel',
        'title' => $this->station_name ?? 'Fuel',
        'titleLabel' => 'Fuel stop'.($this->station_name ? ', '.$this->station_name : ''),
        'subtitle' => implode(', ', $parts),
        'occurred_at' => $this->occurred_at,
        'accent' => 'fuel',
        'meta' => [],
    ];
}
```

- [ ] **Step 5: Update the FuelFactory**

In `database/factories/FuelFactory.php`, replace `'fuel_station_id' => null,` in the returned array with:

```php
            'station_name' => fake()->company().' Service Station',
            'brand' => fake()->randomElement(['BP', 'Shell', 'Esso', 'ASDA', 'Tesco']),
            'address' => fake()->streetAddress(),
            'postcode' => fake()->postcode(),
            'city' => fake()->city(),
            'county' => null,
            'country' => 'United Kingdom',
            'latitude' => fake()->latitude(51, 52),
            'longitude' => fake()->longitude(-1, 0),
```

- [ ] **Step 6: Delete the FuelStation code and tests**

```bash
git rm app/Models/FuelStation.php app/Actions/Fuel/ResolveFuelStation.php database/factories/FuelStationFactory.php tests/Feature/FuelStationTest.php tests/Feature/ResolveFuelStationTest.php
```

- [ ] **Step 7: Migrate and run tests**

Run:
```bash
php artisan migrate --no-interaction
php artisan test --compact --filter=FuelCard
```
Expected: migration runs cleanly; both FuelCard tests PASS.

- [ ] **Step 8: Verify no dangling FuelStation references**

Run: `grep -rn "FuelStation\|fuelStation\|fuel_station" app database tests`
Expected: no matches (empty output).

- [ ] **Step 9: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "refactor: store fuel garage flat on the fuel row, drop FuelStation"
```

---

### Task 3: ExtractReceiptLocation action

**Files:**
- Create: `app/Actions/Fuel/ReceiptLocation.php`
- Create: `app/Actions/Fuel/ExtractReceiptLocation.php`
- Test: `tests/Feature/ExtractReceiptLocationTest.php`

**Interfaces:**
- Produces: `App\Actions\Fuel\ReceiptLocation` (readonly) with
  `string $path, Carbon\CarbonImmutable $capturedAt, float $latitude, float $longitude`.
- Produces: `App\Actions\Fuel\ExtractReceiptLocation` with
  `__invoke(string $path): ?ReceiptLocation` and
  `static coordinate(?array $parts, ?string $ref): ?float`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ExtractReceiptLocationTest.php`:

```php
<?php

use App\Actions\Fuel\ExtractReceiptLocation;

it('converts EXIF GPS rationals to a decimal coordinate', function () {
    $latitude = ExtractReceiptLocation::coordinate(['51/1', '22/1', '2322/100'], 'N');

    expect(round($latitude, 5))->toBe(51.37312);
});

it('negates southern and western coordinates', function () {
    $longitude = ExtractReceiptLocation::coordinate(['0/1', '7/1', '546/100'], 'W');

    expect($longitude)->toBeLessThan(0.0);
});

it('returns null for malformed coordinate parts', function () {
    expect(ExtractReceiptLocation::coordinate(null, 'N'))->toBeNull();
    expect(ExtractReceiptLocation::coordinate(['51/1'], 'N'))->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ExtractReceiptLocation`
Expected: FAIL (class not found).

- [ ] **Step 3: Create the ReceiptLocation DTO**

Create `app/Actions/Fuel/ReceiptLocation.php`:

```php
<?php

namespace App\Actions\Fuel;

use Carbon\CarbonImmutable;

/**
 * The GPS coordinate and capture time extracted from a receipt photo.
 */
readonly class ReceiptLocation
{
    public function __construct(
        public string $path,
        public CarbonImmutable $capturedAt,
        public float $latitude,
        public float $longitude,
    ) {}
}
```

- [ ] **Step 4: Create the ExtractReceiptLocation action**

Create `app/Actions/Fuel/ExtractReceiptLocation.php`:

```php
<?php

namespace App\Actions\Fuel;

use Carbon\CarbonImmutable;

/**
 * Reads GPS coordinates and the capture timestamp from a photo's EXIF data.
 */
class ExtractReceiptLocation
{
    /**
     * Returns the receipt's location, or null when GPS or a timestamp is absent.
     */
    public function __invoke(string $path): ?ReceiptLocation
    {
        $exif = @exif_read_data($path);

        if ($exif === false) {
            return null;
        }

        $latitude = self::coordinate($exif['GPSLatitude'] ?? null, $exif['GPSLatitudeRef'] ?? null);
        $longitude = self::coordinate($exif['GPSLongitude'] ?? null, $exif['GPSLongitudeRef'] ?? null);
        $timestamp = $exif['DateTimeOriginal'] ?? $exif['DateTime'] ?? null;

        if ($latitude === null || $longitude === null || $timestamp === null) {
            return null;
        }

        return new ReceiptLocation(
            path: $path,
            capturedAt: CarbonImmutable::createFromFormat('Y:m:d H:i:s', $timestamp),
            latitude: $latitude,
            longitude: $longitude,
        );
    }

    /**
     * Convert EXIF degrees/minutes/seconds rationals to a signed decimal degree.
     *
     * @param  array<int, string>|null  $parts
     */
    public static function coordinate(?array $parts, ?string $ref): ?float
    {
        if ($parts === null || count($parts) < 3) {
            return null;
        }

        $decimal = self::rational($parts[0])
            + self::rational($parts[1]) / 60
            + self::rational($parts[2]) / 3600;

        return in_array($ref, ['S', 'W'], true) ? -$decimal : $decimal;
    }

    /**
     * Evaluate a single "numerator/denominator" EXIF rational as a float.
     */
    private static function rational(string $value): float
    {
        [$numerator, $denominator] = array_pad(explode('/', $value), 2, '1');

        return (float) $denominator === 0.0 ? 0.0 : (float) $numerator / (float) $denominator;
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact --filter=ExtractReceiptLocation`
Expected: PASS (3 tests).

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/Fuel/ReceiptLocation.php app/Actions/Fuel/ExtractReceiptLocation.php tests/Feature/ExtractReceiptLocationTest.php
git commit -m "feat: add receipt EXIF location extractor"
```

---

### Task 4: import:fuel-receipts command

**Files:**
- Create: `app/Console/Commands/Import/ImportFuelReceipts.php`
- Test: `tests/Feature/ImportFuelReceiptsTest.php`

**Interfaces:**
- Consumes: `App\Services\PetrolFinder`, `App\Actions\Fuel\ExtractReceiptLocation`,
  `App\Actions\Fuel\ReceiptLocation`, `App\Models\Fuel`.
- Signature: `import:fuel-receipts {folder} {--apply} {--window=1440} {--review=} {--export=data/fuel.csv}`.
- Review CSV header: `receipt_file,receipt_time,fuel_id,fuel_occurred_at,delta_minutes,receipt_lat,receipt_lng,station_name,brand,address,postcode,city,distance_km,alt1_name,alt2_name,flag`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ImportFuelReceiptsTest.php`:

```php
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

    Http::fake(['*/api/search*' => Http::response(['stations' => [[
        'name' => 'ASDA WALLINGTON', 'brand' => 'ASDA', 'address' => 'MARLOW WAY',
        'postcode' => 'CR0 4XS', 'city' => 'CROYDON',
        'latitude' => 51.3767, 'longitude' => -0.1313, 'distance' => 0.4,
    ]]])]);

    $fuel = Fuel::factory()->create(['occurred_at' => '2026-05-07 20:52:23', 'station_name' => null]);

    $this->artisan('import:fuel-receipts', ['folder' => $this->folder, '--review' => $this->review])
        ->assertSuccessful();

    expect(File::exists($this->review))->toBeTrue();
    $contents = File::get($this->review);
    expect($contents)->toContain('ASDA WALLINGTON');
    expect($contents)->toContain(','.$fuel->id.',');
    // dry run writes nothing to the database
    expect($fuel->fresh()->station_name)->toBeNull();
});

it('applies a reviewed csv onto fuel rows and regenerates the backup csv', function () {
    $fuel = Fuel::factory()->create(['station_name' => null]);
    File::ensureDirectoryExists(dirname($this->review));
    $header = 'receipt_file,receipt_time,fuel_id,fuel_occurred_at,delta_minutes,receipt_lat,receipt_lng,station_name,brand,address,postcode,city,distance_km,alt1_name,alt2_name,flag';
    $row = "IMG_1.jpeg,2026-05-07 20:56:00,{$fuel->id},2026-05-07 20:52:23,4,51.37,-0.13,ASDA WALLINGTON,ASDA,MARLOW WAY,CR0 4XS,CROYDON,0.4,,,ok";
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
    expect(File::get($export))->toContain('ASDA WALLINGTON');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ImportFuelReceipts`
Expected: FAIL (command `import:fuel-receipts` not defined).

- [ ] **Step 3: Create the command**

Create `app/Console/Commands/Import/ImportFuelReceipts.php`:

```php
<?php

namespace App\Console\Commands\Import;

use App\Actions\Fuel\ExtractReceiptLocation;
use App\Models\Fuel;
use App\Services\PetrolFinder;
use App\Services\PetrolFinder\FuelStationResult;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('import:fuel-receipts {folder : Folder of GPS-tagged receipt photos} {--apply : Write the reviewed CSV to the database} {--window=1440 : Max match window in minutes} {--review= : Review CSV path (default storage/app/fuel/receipt-review.csv)} {--export=data/fuel.csv : Path to regenerate the fuel CSV backup on apply}')]
#[Description('Backfill fuel logs with garage/location data from receipt photos')]
class ImportFuelReceipts extends Command
{
    private const REVIEW_HEADER = [
        'receipt_file', 'receipt_time', 'fuel_id', 'fuel_occurred_at', 'delta_minutes',
        'receipt_lat', 'receipt_lng', 'station_name', 'brand', 'address', 'postcode',
        'city', 'distance_km', 'alt1_name', 'alt2_name', 'flag',
    ];

    public function handle(PetrolFinder $petrolFinder, ExtractReceiptLocation $extract): int
    {
        $reviewPath = $this->option('review') ?: storage_path('app/fuel/receipt-review.csv');

        return $this->option('apply')
            ? $this->apply($reviewPath)
            : $this->dryRun($petrolFinder, $extract, $reviewPath);
    }

    private function dryRun(PetrolFinder $petrolFinder, ExtractReceiptLocation $extract, string $reviewPath): int
    {
        $folder = $this->argument('folder');

        if (! File::isDirectory($folder)) {
            $this->error("Folder not found: {$folder}");

            return self::FAILURE;
        }

        $window = (int) $this->option('window');
        $entries = Fuel::query()->get(['id', 'occurred_at']);

        /** @var array<string, array<int, FuelStationResult>> $stationCache */
        $stationCache = [];
        $rows = [];
        $matched = 0;
        $skipped = 0;

        foreach ($this->imageFiles($folder) as $path) {
            $location = $extract($path);

            if ($location === null) {
                $skipped++;
                $this->warn('Skipped (no GPS/time): '.basename($path));

                continue;
            }

            $best = null;
            $bestDelta = null;
            foreach ($entries as $entry) {
                $delta = (int) abs($entry->occurred_at->diffInMinutes($location->capturedAt));
                if ($delta <= $window && ($bestDelta === null || $delta < $bestDelta)) {
                    $best = $entry;
                    $bestDelta = $delta;
                }
            }

            $cacheKey = round($location->latitude, 4).','.round($location->longitude, 4);
            $stations = $stationCache[$cacheKey] ??= $petrolFinder->search(
                latitude: $location->latitude,
                longitude: $location->longitude,
                radius: 5,
            );
            usort($stations, fn (FuelStationResult $a, FuelStationResult $b): int => ($a->distance ?? INF) <=> ($b->distance ?? INF));

            $station = $stations[0] ?? null;
            $flag = match (true) {
                $best === null => 'unmatched',
                $station === null => 'no-station',
                $bestDelta > 60 => 'check-delta',
                default => 'ok',
            };

            if ($best !== null && $station !== null) {
                $matched++;
            }

            $rows[] = [
                basename($path),
                $location->capturedAt->format('Y-m-d H:i:s'),
                $best?->id ?? '',
                $best?->occurred_at?->format('Y-m-d H:i:s') ?? '',
                $bestDelta ?? '',
                $location->latitude,
                $location->longitude,
                $station?->stationName ?? '',
                $station?->brand ?? '',
                $station?->address ?? '',
                $station?->postcode ?? '',
                $station?->city ?? '',
                $station?->distance ?? '',
                $stations[1]->stationName ?? '',
                $stations[2]->stationName ?? '',
                $flag,
            ];
        }

        $this->writeCsv($reviewPath, $rows);

        $this->info("Wrote {$reviewPath}: {$matched} matched, {$skipped} skipped, ".count($rows).' rows total.');
        $this->line('Review and edit the CSV, then re-run with --apply.');

        return self::SUCCESS;
    }

    private function apply(string $reviewPath): int
    {
        if (! File::exists($reviewPath)) {
            $this->error("Review file not found: {$reviewPath}");

            return self::FAILURE;
        }

        $handle = fopen($reviewPath, 'r');
        $header = fgetcsv($handle);
        $updated = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($header)) {
                continue;
            }
            $data = array_combine($header, $row);

            if ($data['fuel_id'] === '' || $data['station_name'] === '') {
                continue;
            }

            $fuel = Fuel::query()->find($data['fuel_id']);
            if ($fuel === null) {
                continue;
            }

            $fuel->update([
                'station_name' => $data['station_name'],
                'brand' => $data['brand'] ?: null,
                'address' => $data['address'] ?: null,
                'postcode' => $data['postcode'] ?: null,
                'city' => $data['city'] ?: null,
                'country' => 'United Kingdom',
                'latitude' => $data['receipt_lat'] ?: null,
                'longitude' => $data['receipt_lng'] ?: null,
            ]);
            $updated++;
        }

        fclose($handle);

        $this->call('export:csv', ['file' => $this->option('export'), 'type' => 'fuel']);
        $this->info("Applied {$updated} rows and regenerated {$this->option('export')}.");

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function imageFiles(string $folder): array
    {
        return collect(File::files($folder))
            ->filter(fn ($file): bool => in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'heic'], true))
            ->map(fn ($file): string => $file->getPathname())
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function writeCsv(string $path, array $rows): void
    {
        File::ensureDirectoryExists(dirname($path));
        $handle = fopen($path, 'w');
        fputcsv($handle, self::REVIEW_HEADER, ',', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '\\');
        }
        fclose($handle);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=ImportFuelReceipts`
Expected: PASS (2 tests).

- [ ] **Step 5: Run the full fuel-related suite**

Run: `php artisan test --compact --filter="Fuel|PetrolFinder|Receipt"`
Expected: all PASS.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/Import/ImportFuelReceipts.php tests/Feature/ImportFuelReceiptsTest.php
git commit -m "feat: add import:fuel-receipts backfill command"
```

---

## Post-implementation: run the real backfill (manual, not a test)

After all tasks pass, run against the real receipts folder:

```bash
php artisan import:fuel-receipts "/Users/taylordrayson/Temp/Receipts"
# review storage/app/fuel/receipt-review.csv, correct any 'check-delta'/'no-station'/'unmatched' rows
php artisan import:fuel-receipts "/Users/taylordrayson/Temp/Receipts" --apply
```

Then verify `data/fuel.csv` has the garage columns populated and commit the regenerated CSV separately.
