# Sushi Lookup Tables Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move `airlines` (5,842 rows) and `airports` (9,070 rows) out of the app database onto Sushi-backed Eloquent models reading canonical CSVs from `database/lookups/`.

**Architecture:** Sushi builds a cached SQLite database per model from CSV, so `Airline` and `Airport` stay ordinary Eloquent models. Caching is on in dev/prod and off under test, where `getRows()` returns `[]` so existing tests keep their own inline fixtures. The six connection-bound `exists:` validation rules become a model-bound `ExistsOnModel` rule.

**Tech Stack:** PHP 8.4, Laravel 13, Pest 4, `calebporzio/sushi` ^2.5, SQLite.

**Spec:** `docs/superpowers/specs/2026-07-15-sushi-lookup-tables-design.md`

## Global Constraints

- **Branch:** `feat/sushi-lookup-tables`, already created off `master`. Do not create another.
- **Do not modify these five test files.** They must pass unchanged. This is the primary success signal:
  `tests/Feature/Api/FlightApiTest.php`, `tests/Feature/ArchiveTest.php`, `tests/Feature/EnrichFlightsTest.php`, `tests/Feature/AdvancedSearchTest.php`, `tests/Feature/FlightStoryTest.php`
- **Prior art:** the closed `feat/flat-file-storage` branch already built this. Read it with `git show origin/feat/flat-file-storage:<path>`. Lift from it, but honour the three divergences in the spec (CSV path, throw-on-missing-file, recreating `down()`).
- **Run Pint** after any PHP change: `vendor/bin/pint --dirty --format agent`
- **Test command:** `php artisan test --compact` (add `--filter=X` to narrow)
- **PHP style:** explicit return types on every method; PHPDoc blocks over inline comments; curly braces always.
- **No em dashes** in any code comment, commit message, or doc.

---

### Task 1: Install Sushi and move the CSVs

**Files:**
- Modify: `composer.json` (require block)
- Move: `data/airlines.csv` → `database/lookups/airlines.csv`
- Move: `data/airports.csv` → `database/lookups/airports.csv`

**Interfaces:**
- Consumes: nothing.
- Produces: `database/lookups/airlines.csv` (headers `iata_code,icao_code,name,country`) and `database/lookups/airports.csv` (headers `iata_code,icao_code,name,city,country,latitude,longitude`). Every later task depends on these exact paths.

- [ ] **Step 1: Install Sushi**

```bash
composer require calebporzio/sushi:^2.5
```

Expected: composer.json gains `"calebporzio/sushi": "^2.5"`, lock updates, no errors.

- [ ] **Step 2: Move the CSVs with git so history follows**

```bash
mkdir -p database/lookups
git mv data/airlines.csv database/lookups/airlines.csv
git mv data/airports.csv database/lookups/airports.csv
```

- [ ] **Step 3: Verify the moved files are intact**

```bash
head -1 database/lookups/airlines.csv
head -1 database/lookups/airports.csv
wc -l database/lookups/airlines.csv database/lookups/airports.csv
```

Expected exactly:
```
iata_code,icao_code,name,country
iata_code,icao_code,name,city,country,latitude,longitude
    5843 database/lookups/airlines.csv
    9071 database/lookups/airports.csv
```
(Row counts are 5,842 and 9,070 plus one header line each.)

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock database/lookups/
git commit -m "feat: install sushi and move airline/airport csvs to database/lookups"
```

---

### Task 2: `LookupCsv` support class

**Files:**
- Create: `app/Support/LookupCsv.php`
- Test: `tests/Unit/LookupCsvTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `App\Support\LookupCsv::from(string $absolutePath): array`, returning a `list<array<string, string|null>>`, one entry per data row, keyed by header. Empty string cells become `null`. **Throws `RuntimeException`** when the file is missing or unreadable. Tasks 3 and 4 call this.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/LookupCsvTest.php`:

```php
<?php

use App\Support\LookupCsv;

it('parses a csv into rows keyed by header', function () {
    $path = tempnam(sys_get_temp_dir(), 'lookup').'.csv';
    file_put_contents($path, "iata_code,name,city\nLHR,\"London Heathrow Airport\",London\nJFK,\"John F Kennedy\",\"New York\"\n");

    $rows = LookupCsv::from($path);

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toBe(['iata_code' => 'LHR', 'name' => 'London Heathrow Airport', 'city' => 'London'])
        ->and($rows[1]['city'])->toBe('New York');

    @unlink($path);
});

it('converts empty cells to null', function () {
    $path = tempnam(sys_get_temp_dir(), 'lookup').'.csv';
    file_put_contents($path, "iata_code,icao_code,name\nAAA,,Anaa\n");

    $rows = LookupCsv::from($path);

    expect($rows[0]['icao_code'])->toBeNull()
        ->and($rows[0]['name'])->toBe('Anaa');

    @unlink($path);
});

it('throws when the file is missing rather than returning an empty set', function () {
    LookupCsv::from('/nonexistent/path/airports.csv');
})->throws(RuntimeException::class, 'Lookup CSV not found');

it('returns an empty list for a header-only file', function () {
    $path = tempnam(sys_get_temp_dir(), 'lookup').'.csv';
    file_put_contents($path, "iata_code,name\n");

    expect(LookupCsv::from($path))->toBe([]);

    @unlink($path);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=LookupCsv`
Expected: FAIL with `Class "App\Support\LookupCsv" not found`.

- [ ] **Step 3: Write the implementation**

Create `app/Support/LookupCsv.php`:

```php
<?php

namespace App\Support;

use RuntimeException;

class LookupCsv
{
    /**
     * Read a lookup CSV into rows keyed by column header.
     *
     * Throws rather than returning an empty set when the file is unreadable:
     * an empty set would be cached by Sushi and surface as missing data
     * instead of an error.
     *
     * @return list<array<string, string|null>>
     */
    public static function from(string $absolutePath): array
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException("Lookup CSV not found: {$absolutePath}");
        }

        $handle = fopen($absolutePath, 'r');

        if ($handle === false) {
            throw new RuntimeException("Lookup CSV could not be opened: {$absolutePath}");
        }

        try {
            $headers = fgetcsv($handle, null, ',', '"', '\\');

            if ($headers === false) {
                throw new RuntimeException("Lookup CSV has no header row: {$absolutePath}");
            }

            $headers = array_map(static fn (?string $header): string => (string) $header, $headers);
            $rows = [];

            while (($values = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
                if ($values === [null]) {
                    continue;
                }

                $row = [];

                foreach ($headers as $index => $header) {
                    $value = $values[$index] ?? null;
                    $row[$header] = ($value === '' || $value === null) ? null : $value;
                }

                $rows[] = $row;
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=LookupCsv`
Expected: PASS, 4 tests.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/LookupCsv.php tests/Unit/LookupCsvTest.php
git commit -m "feat: add LookupCsv reader for canonical lookup csvs"
```

---

### Task 3: Convert `Airline` and `Airport` to Sushi

**Files:**
- Modify: `app/Models/Airline.php`
- Modify: `app/Models/Airport.php`
- Test: `tests/Feature/SushiLookupTest.php` (create)

**Interfaces:**
- Consumes: `App\Support\LookupCsv::from()` from Task 2; the CSVs from Task 1.
- Produces: `Airline` and `Airport` as Sushi models on their own connection. `Airline` columns: `iata_code, icao_code, name, country`. `Airport` columns: `iata_code, icao_code, name, city, country, latitude, longitude`. Both keep `HasFactory`, so `Airline::factory()->create()` still works. Task 4's `ExistsOnModel` queries through these.

**Reference:** `git show origin/feat/flat-file-storage:app/Models/Airline.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/SushiLookupTest.php`:

```php
<?php

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Flight;
use App\Support\LookupCsv;

it('loads the real airline and airport rows from the canonical csvs', function () {
    $airlines = LookupCsv::from(database_path('lookups/airlines.csv'));
    $airports = LookupCsv::from(database_path('lookups/airports.csv'));

    expect($airlines)->toHaveCount(5842)
        ->and($airports)->toHaveCount(9070)
        ->and(collect($airlines)->firstWhere('icao_code', 'BAW')['name'] ?? null)->toBe('British Airways')
        ->and(collect($airports)->firstWhere('iata_code', 'LHR')['city'] ?? null)->toBe('London');
});

it('starts with empty lookup tables under test', function () {
    expect(Airline::count())->toBe(0)
        ->and(Airport::count())->toBe(0);
});

it('resolves flight airline and airport relations across the sushi connection', function () {
    Airline::factory()->create(['icao_code' => 'BAW', 'iata_code' => 'BA', 'name' => 'British Airways']);
    Airport::factory()->create(['iata_code' => 'LHR', 'name' => 'Heathrow', 'city' => 'London', 'country' => 'GB']);
    Airport::factory()->create(['iata_code' => 'JFK', 'name' => 'JFK', 'city' => 'New York', 'country' => 'US']);

    $flight = Flight::factory()->create([
        'airline_icao' => 'BAW',
        'origin_iata' => 'LHR',
        'destination_iata' => 'JFK',
    ]);

    $flight->load(['airline', 'origin', 'destination']);

    expect($flight->airline?->name)->toBe('British Airways')
        ->and($flight->origin?->iata_code)->toBe('LHR')
        ->and($flight->destination?->city)->toBe('New York')
        ->and($flight->origin?->place)->toBe('London, GB');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=SushiLookup`
Expected: FAIL. The first test fails on the CSV path or count; the second fails because `Airline::count()` reads the real DB table.

- [ ] **Step 3: Convert `Airline`**

Replace `app/Models/Airline.php` entirely:

```php
<?php

namespace App\Models;

use App\Support\LookupCsv;
use Database\Factories\AirlineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Sushi\Sushi;

#[Fillable([
    'iata_code',
    'icao_code',
    'name',
    'country',
])]
class Airline extends Model
{
    /** @use HasFactory<AirlineFactory> */
    use HasFactory;

    use Sushi;

    public $timestamps = false;

    /**
     * Declared explicitly because getRows() is empty under test, leaving
     * Sushi nothing to infer the column types from.
     *
     * @var array<string, string>
     */
    protected $schema = [
        'iata_code' => 'string',
        'icao_code' => 'string',
        'name' => 'string',
        'country' => 'string',
    ];

    /**
     * @var list<string>
     */
    protected $appends = ['icon_url', 'logo_url'];

    /**
     * @return list<array<string, string|null>>
     */
    public function getRows(): array
    {
        if (app()->environment('testing')) {
            return [];
        }

        return LookupCsv::from($this->lookupPath());
    }

    /**
     * Caching stays off under test. A cached empty row set would be written to
     * the shared cache file and later served to dev as real data.
     */
    protected function sushiShouldCache(): bool
    {
        return ! app()->environment('testing');
    }

    protected function sushiCacheReferencePath(): string
    {
        return $this->lookupPath();
    }

    private function lookupPath(): string
    {
        return database_path('lookups/airlines.csv');
    }

    /**
     * The square icon mark, resolved by IATA code, or null when not downloaded.
     */
    protected function iconUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->logoPath('icon'));
    }

    /**
     * The full wordmark logo, resolved by IATA code, or null when not downloaded.
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->logoPath('logo'));
    }

    /**
     * Public path to a downloaded logo variant, or null when the file is absent.
     */
    private function logoPath(string $variant): ?string
    {
        $iata = strtoupper((string) $this->iata_code);

        if ($iata === '' || ! file_exists(public_path("logos/airlines/{$variant}/{$iata}.png"))) {
            return null;
        }

        return "/logos/airlines/{$variant}/{$iata}.png";
    }
}
```

- [ ] **Step 4: Convert `Airport`**

Replace `app/Models/Airport.php` entirely:

```php
<?php

namespace App\Models;

use App\Support\LookupCsv;
use Database\Factories\AirportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Sushi\Sushi;

#[Fillable([
    'iata_code',
    'icao_code',
    'name',
    'city',
    'country',
    'latitude',
    'longitude',
])]
class Airport extends Model
{
    /** @use HasFactory<AirportFactory> */
    use HasFactory;

    use Sushi;

    public $timestamps = false;

    /**
     * Declared explicitly because getRows() is empty under test, leaving
     * Sushi nothing to infer the column types from.
     *
     * @var array<string, string>
     */
    protected $schema = [
        'iata_code' => 'string',
        'icao_code' => 'string',
        'name' => 'string',
        'city' => 'string',
        'country' => 'string',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    /**
     * @var list<string>
     */
    protected $appends = ['place'];

    /**
     * @return list<array<string, string|null>>
     */
    public function getRows(): array
    {
        if (app()->environment('testing')) {
            return [];
        }

        return LookupCsv::from($this->lookupPath());
    }

    /**
     * Caching stays off under test. A cached empty row set would be written to
     * the shared cache file and later served to dev as real data.
     */
    protected function sushiShouldCache(): bool
    {
        return ! app()->environment('testing');
    }

    protected function sushiCacheReferencePath(): string
    {
        return $this->lookupPath();
    }

    private function lookupPath(): string
    {
        return database_path('lookups/airports.csv');
    }

    /**
     * Human label combining the city (or airport name) with its country as the
     * short ISO code, e.g. "London, GB".
     */
    protected function place(): Attribute
    {
        return Attribute::get(function (): string {
            $city = $this->city ?: $this->name;
            $country = $this->country ? strtoupper($this->country) : null;

            return collect([$city, $country])->filter()->implode(', ');
        });
    }
}
```

- [ ] **Step 5: Clear the stale Sushi caches from the old branch**

`storage/framework/cache/` still holds `sushi-app-models-airline.sqlite` and `sushi-app-models-airport.sqlite` from the closed branch, built from the old `data/` paths.

```bash
rm -f storage/framework/cache/sushi-app-models-airline.sqlite storage/framework/cache/sushi-app-models-airport.sqlite
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact --filter=SushiLookup`
Expected: PASS, 3 tests.

If "no such column: created_at" appears, `$timestamps = false` is missing from a model.

- [ ] **Step 7: Confirm the cache lands in gitignored storage**

```bash
php artisan tinker --execute "echo App\Models\Airport::count().PHP_EOL;"
ls -la storage/framework/cache/ | grep sushi
git check-ignore -v storage/framework/cache/sushi-app-models-airport.sqlite
```

Expected: count prints `9070`; both sushi `.sqlite` files exist; `git check-ignore` confirms they are ignored by the `*` rule in `storage/framework/cache/.gitignore`. If the count is `0`, `getRows()` is returning `[]` outside testing.

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Airline.php app/Models/Airport.php tests/Feature/SushiLookupTest.php
git commit -m "feat: back airline and airport lookups with sushi"
```

---

### Task 4: `ExistsOnModel` validation rule

**Files:**
- Create: `app/Rules/ExistsOnModel.php`
- Modify: `app/Http/Requests/Api/V1/StoreFlightRequest.php:67-69`
- Modify: `app/Http/Requests/Api/V1/UpdateFlightRequest.php:66-68`
- Test: `tests/Feature/Api/FlightApiTest.php` (run only, do not edit)

**Interfaces:**
- Consumes: `Airline` / `Airport` from Task 3.
- Produces: `App\Rules\ExistsOnModel::__construct(string $modelClass, string $column)` implementing `ValidationRule`. Fails with the standard `validation.exists` message, so existing error-message assertions keep passing.

**Why:** `exists:airlines,icao_code` resolves against the default connection. Once Task 5 drops that table the rule throws "no such table". Querying through the model follows the model's own connection.

**Reference:** `git show origin/feat/flat-file-storage:app/Rules/ExistsOnModel.php`

- [ ] **Step 1: Run the existing test to confirm it currently passes**

Run: `php artisan test --compact --filter=FlightApiTest`
Expected: PASS. This is the baseline; the file must never be edited.

- [ ] **Step 2: Create the rule**

Create `app/Rules/ExistsOnModel.php`:

```php
<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

class ExistsOnModel implements ValidationRule
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public function __construct(
        private string $modelClass,
        private string $column,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->modelClass::query()->where($this->column, $value)->exists()) {
            $fail(__('validation.exists', ['attribute' => $attribute]));
        }
    }
}
```

- [ ] **Step 3: Swap the rules in `StoreFlightRequest`**

Add to the `use` block at the top of `app/Http/Requests/Api/V1/StoreFlightRequest.php`:

```php
use App\Models\Airline;
use App\Models\Airport;
use App\Rules\ExistsOnModel;
```

Replace these three lines:

```php
            'airline_icao' => ['required', 'string', 'exists:airlines,icao_code'],
            'origin_iata' => ['required', 'string', 'size:3', 'exists:airports,iata_code'],
            'destination_iata' => ['required', 'string', 'size:3', 'exists:airports,iata_code'],
```

with:

```php
            'airline_icao' => ['required', 'string', new ExistsOnModel(Airline::class, 'icao_code')],
            'origin_iata' => ['required', 'string', 'size:3', new ExistsOnModel(Airport::class, 'iata_code')],
            'destination_iata' => ['required', 'string', 'size:3', new ExistsOnModel(Airport::class, 'iata_code')],
```

- [ ] **Step 4: Swap the rules in `UpdateFlightRequest`**

Add the same three `use` statements to `app/Http/Requests/Api/V1/UpdateFlightRequest.php`, then replace:

```php
            'airline_icao' => ['sometimes', 'string', 'exists:airlines,icao_code'],
            'origin_iata' => ['sometimes', 'string', 'size:3', 'exists:airports,iata_code'],
            'destination_iata' => ['sometimes', 'string', 'size:3', 'exists:airports,iata_code'],
```

with:

```php
            'airline_icao' => ['sometimes', 'string', new ExistsOnModel(Airline::class, 'icao_code')],
            'origin_iata' => ['sometimes', 'string', 'size:3', new ExistsOnModel(Airport::class, 'iata_code')],
            'destination_iata' => ['sometimes', 'string', 'size:3', new ExistsOnModel(Airport::class, 'iata_code')],
```

- [ ] **Step 5: Verify no `exists:` rules remain on these tables**

```bash
grep -rn "exists:airlines\|exists:airports" app/
```

Expected: no output.

- [ ] **Step 6: Run the flight API tests unchanged**

Run: `php artisan test --compact --filter=FlightApiTest`
Expected: PASS, including "rejects unknown airports and airlines".

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Rules/ExistsOnModel.php app/Http/Requests/Api/V1/StoreFlightRequest.php app/Http/Requests/Api/V1/UpdateFlightRequest.php
git commit -m "feat: validate airline and airport codes through the model"
```

---

### Task 5: Drop the tables and remove the import/export mappings

**Files:**
- Create: `database/migrations/2026_07_15_120000_drop_airlines_and_airports_tables.php`
- Modify: `app/Console/Commands/Import/ImportCsv.php:32-33`
- Modify: `app/Console/Commands/Export/ExportCsv.php:32-33`

**Interfaces:**
- Consumes: Tasks 3 and 4 must be complete. Dropping the tables before the rule swap would break flight validation.
- Produces: no `airlines`/`airports` tables in the app database; neither command references those models.

- [ ] **Step 1: Create the migration**

Create `database/migrations/2026_07_15_120000_drop_airlines_and_airports_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Airlines and airports are static reference data, now served by Sushi
     * from database/lookups/*.csv. The app database keeps personal data only.
     */
    public function up(): void
    {
        Schema::dropIfExists('airlines');
        Schema::dropIfExists('airports');
    }

    /**
     * Recreated to match 2026_03_18_010000_create_app_tables.php. The rows are
     * not restored: the CSVs are the source of truth.
     */
    public function down(): void
    {
        Schema::create('airports', function (Blueprint $table) {
            $table->id();
            $table->string('iata_code')->unique();
            $table->string('icao_code')->nullable();
            $table->string('name');
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });

        Schema::create('airlines', function (Blueprint $table) {
            $table->id();
            $table->string('iata_code')->nullable();
            $table->string('icao_code')->unique();
            $table->string('name');
            $table->string('country')->nullable();
            $table->timestamps();
        });
    }
};
```

- [ ] **Step 2: Remove the airline/airport entries from `ImportCsv`**

In `app/Console/Commands/Import/ImportCsv.php`, delete these two lines from the model map:

```php
        'airline' => Airline::class,
        'airport' => Airport::class,
```

Then remove the now-unused `use App\Models\Airline;` and `use App\Models\Airport;` imports.

- [ ] **Step 3: Remove the airline/airport entries from `ExportCsv`**

In `app/Console/Commands/Export/ExportCsv.php`, delete the same two lines and their now-unused imports.

- [ ] **Step 4: Verify no stale references remain**

```bash
grep -rn "Airline::class\|Airport::class" app/Console/Commands/
```

Expected: no output.

- [ ] **Step 5: Run the migration**

```bash
php artisan migrate
php artisan tinker --execute "
echo 'airlines table: '.(Schema::hasTable('airlines') ? 'EXISTS' : 'GONE').PHP_EOL;
echo 'Airport::count() via sushi: '.App\Models\Airport::count().PHP_EOL;
"
```

Expected: `airlines table: GONE` and `Airport::count() via sushi: 9070`.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/ app/Console/Commands/Import/ImportCsv.php app/Console/Commands/Export/ExportCsv.php
git commit -m "feat: drop airline and airport tables, drop them from csv import/export"
```

---

### Task 6: Full verification

**Files:**
- Modify: `tests/Pest.php` (only if Step 2 proves it necessary)

**Interfaces:**
- Consumes: all prior tasks.
- Produces: a green suite and a verified-working site.

- [ ] **Step 1: Run the full suite**

Run: `php artisan test --compact`
Expected: PASS. The five untouched test files are the success signal.

- [ ] **Step 2: Decide on the truncate, based on evidence**

The spec predicts no `beforeEach` truncate is needed: with caching off, Sushi's in-memory database is rebuilt with the app, i.e. per test. The closed branch shipped without one.

If, and only if, Step 1 shows failures from rows leaking between tests (duplicate airports, unexpected counts), add to `tests/Pest.php` after the `pest()->extend(...)` block:

```php
pest()->beforeEach(function () {
    App\Models\Airline::query()->delete();
    App\Models\Airport::query()->delete();
})->in('Feature', 'Browser');
```

with `use App\Models\Airline;` / `use App\Models\Airport;` at the top. If Step 1 passed, change nothing and record that in the commit.

- [ ] **Step 3: Verify the real site renders**

```bash
php artisan config:clear
curl -sk https://taylordrayson.test/flights -o /dev/null -w "flights page: HTTP %{http_code}\n"
php artisan tinker --execute "
\$f = App\Models\Flight::query()->latest('occurred_at')->first();
\$f->load(['airline','origin','destination']);
echo 'airline: '.(\$f->airline?->name ?? 'NOT FOUND').PHP_EOL;
echo 'origin:  '.(\$f->origin?->name ?? 'NOT FOUND').PHP_EOL;
"
```

Expected: `HTTP 200`, `airline: easyJet UK`, `origin: Kraków John Paul II International Airport`. `NOT FOUND` means the Sushi lookup is not resolving.

- [ ] **Step 4: Verify the cache busts when the CSV changes**

```bash
touch database/lookups/airports.csv
php artisan tinker --execute "echo App\Models\Airport::count().PHP_EOL;"
```

Expected: `9070`. This proves `sushiCacheReferencePath()` rebuilds from the CSV rather than serving a stale cache.

- [ ] **Step 5: Commit any Step 2 change and push**

```bash
git add -A
git commit -m "test: verify sushi lookups across the suite"
git push -u origin feat/sushi-lookup-tables
```

---

## Notes for the implementer

- **`data/` still contains the other CSVs** (`activities.csv`, `flights.csv`, etc.). They stay put and keep being regenerated by `ExportCsv`. Only the two lookup files moved.
- **If a test fails with "no such table: airlines"** after Task 5, something still routes through the default connection. Find it with `grep -rn "exists:airlines\|exists:airports\|join('airlines'\|join('airports'" app/`.
- **`storage/backups/` is untracked** and holds ~35MB SQLite backups. Out of scope here, but worth gitignoring separately.
