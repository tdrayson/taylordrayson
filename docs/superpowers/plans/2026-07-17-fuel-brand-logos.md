# Fuel Brand Logos Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show each fuel entry's garage brand logo (BP, Shell, Esso) on its timeline card and detail page, fetched from logo.dev, stored locally, mirroring the airline-logo pattern, with a fuel-icon fallback.

**Architecture:** A `LogoDev` HTTP client fetches a brand PNG by web domain; `PetrolFinder::brandDomain()` resolves a brand name to that domain; a `fuel:brand-logos` command downloads and stores one PNG per brand under `public/logos/brands/{slug}.png`; the receipt import triggers it so new brands self-populate; `Fuel::logo_url` resolves the stored file and the card/detail views render it on a white chip.

**Tech Stack:** Laravel 13, PHP 8.4, Pest 4, Inertia v3 + Vue 3, Tailwind v4. Existing siblings to mirror: `App\Services\LogoStream`, `App\Console\Commands\Fetch\FetchAirlineLogos`, `App\Models\Airline`.

## Global Constraints

- Token is read only via `config('services.logodev.token')` (already wired), never `env()` outside config.
- logo.dev request params are fixed: `token`, `size` (default 256), `retina=true`, `format=png`, `fallback=404`. `fallback=404` is mandatory so a missing brand returns 404 rather than a fabricated monogram.
- Store one PNG per brand at `public_path("logos/brands/{slug}.png")` where `slug = Str::slug($brand)`. No Media Library, no icon/logo variant split.
- Generated `public/logos/brands/*.png` are committed to git (like `public/logos/airlines/*.png`).
- PHP: curly braces always; explicit return types; constructor property promotion; PHPDoc over inline comments. Run `vendor/bin/pint --dirty --format agent` before every commit.
- Vue/JS: comment functions, computeds, and non-obvious logic. Never use arbitrary Tailwind bracket values; use the standard scale. `alt=""` on decorative logos (brand name is already in the title).
- Tests are feature tests (Pest); models built via factories. Run `php artisan test --compact --filter=...`.
- Never `git add -A`/`git add .`; stage explicit paths only.

---

### Task 1: LogoDev service client

**Files:**
- Create: `app/Services/LogoDev.php`
- Test: `tests/Feature/LogoDevTest.php`

**Interfaces:**
- Consumes: `config('services.logodev.token')` (already present).
- Produces: `LogoDev::logo(string $domain, int $size = 256): array{status: 'saved'|'unavailable'|'error', body: string|null}`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/LogoDevTest.php`:

```php
<?php

use App\Services\LogoDev;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.logodev.token' => 'test-token']);
});

it('returns saved with body on an image response', function () {
    Http::fake(['*img.logo.dev*' => Http::response('PNG-BYTES', 200, ['Content-Type' => 'image/png'])]);

    $result = (new LogoDev)->logo('bp.com');

    expect($result['status'])->toBe('saved');
    expect($result['body'])->toBe('PNG-BYTES');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'img.logo.dev/bp.com')
            && $request['token'] === 'test-token'
            && $request['fallback'] === '404';
    });
});

it('returns unavailable on a 404 (no logo for the domain)', function () {
    Http::fake(['*img.logo.dev*' => Http::response('', 404)]);

    expect((new LogoDev)->logo('nope.example')['status'])->toBe('unavailable');
});

it('returns error on a server failure or non-image response', function () {
    Http::fake(['*img.logo.dev*' => Http::response('<html/>', 200, ['Content-Type' => 'text/html'])]);

    expect((new LogoDev)->logo('bp.com')['status'])->toBe('error');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=LogoDevTest`
Expected: FAIL, `Class "App\Services\LogoDev" not found`.

- [ ] **Step 3: Write minimal implementation**

Create `app/Services/LogoDev.php`:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Client for the logo.dev image API.
 *
 * Fetches a brand logo as raw image bytes, keyed by the brand's web domain.
 * The `fallback=404` parameter makes logo.dev return a 404 (rather than a
 * generated monogram placeholder) when it has no real logo, so a miss is
 * reported as unavailable instead of saved.
 */
class LogoDev
{
    private const BASE = 'https://img.logo.dev';

    /**
     * Fetch a brand logo PNG for a web domain as raw image bytes.
     *
     * @return array{status: 'saved'|'unavailable'|'error', body: string|null}
     */
    public function logo(string $domain, int $size = 256): array
    {
        $response = Http::get(self::BASE.'/'.$domain, [
            'token' => config('services.logodev.token'),
            'size' => $size,
            'retina' => 'true',
            'format' => 'png',
            'fallback' => '404',
        ]);

        if ($response->status() === 404) {
            return ['status' => 'unavailable', 'body' => null];
        }

        if (! $response->successful() || ! str_starts_with((string) $response->header('content-type'), 'image/')) {
            return ['status' => 'error', 'body' => null];
        }

        return ['status' => 'saved', 'body' => $response->body()];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=LogoDevTest`
Expected: PASS (3 passed).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/LogoDev.php tests/Feature/LogoDevTest.php
git commit -m "feat: add LogoDev client for brand logos by domain"
```

---

### Task 2: Resolve a brand name to a domain

**Files:**
- Modify: `app/Services/PetrolFinder.php` (add `brandDomain()` after the `brands()` method, ~line 82)
- Test: `tests/Feature/PetrolFinderTest.php`

**Interfaces:**
- Consumes: existing `PetrolFinder::brands(): array<string, array{name: string, logo: ?string}>` (keyed by uppercased brand name; `logo` is a URL of the form `https://cdn.brandfetch.io/{domain}?c=...`).
- Produces: `PetrolFinder::brandDomain(string $brand): ?string` returning the domain (e.g. `bp.com`) or null.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PetrolFinderTest.php`:

```php
<?php

use App\Services\PetrolFinder;
use Illuminate\Support\Facades\Http;

it('resolves a brand name to its web domain', function () {
    Http::fake(['*petrolfinder.uk/api/brands*' => Http::response([
        'brands' => [
            ['brand' => 'BP', 'logo' => 'https://cdn.brandfetch.io/bp.com?c=abc'],
        ],
    ], 200)]);

    expect((new PetrolFinder)->brandDomain('bp'))->toBe('bp.com');
});

it('returns null for an unlisted brand', function () {
    Http::fake(['*petrolfinder.uk/api/brands*' => Http::response(['brands' => []], 200)]);

    expect((new PetrolFinder)->brandDomain('Unknown Garage'))->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=PetrolFinderTest`
Expected: FAIL, `Call to undefined method App\Services\PetrolFinder::brandDomain()`.

- [ ] **Step 3: Write minimal implementation**

In `app/Services/PetrolFinder.php`, add this method immediately after the `brands()` method (before `toResult()`):

```php
    /**
     * The web domain for a brand name (e.g. "BP" -> "bp.com"), parsed from the
     * brands endpoint's logo URL. Null when the brand is unlisted or the entry
     * has no logo URL.
     */
    public function brandDomain(string $brand): ?string
    {
        $logo = $this->brands()[mb_strtoupper($brand)]['logo'] ?? null;

        if ($logo === null) {
            return null;
        }

        $domain = trim((string) parse_url($logo, PHP_URL_PATH), '/');

        return $domain !== '' ? $domain : null;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=PetrolFinderTest`
Expected: PASS (2 passed).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/PetrolFinder.php tests/Feature/PetrolFinderTest.php
git commit -m "feat: resolve fuel brand name to web domain in PetrolFinder"
```

---

### Task 3: `fuel:brand-logos` download command

**Files:**
- Create: `app/Console/Commands/Fetch/FetchFuelBrandLogos.php`
- Test: `tests/Feature/FetchFuelBrandLogosTest.php`

**Interfaces:**
- Consumes: `LogoDev::logo()` (Task 1), `PetrolFinder::brandDomain()` (Task 2), `Fuel` factory (`brand` column).
- Produces: command `fuel:brand-logos {brand?*} {--force}`; writes `public/logos/brands/{Str::slug(brand)}.png`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/FetchFuelBrandLogosTest.php`:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=FetchFuelBrandLogosTest`
Expected: FAIL, command `fuel:brand-logos` not found (namespace/command missing).

- [ ] **Step 3: Write minimal implementation**

Create `app/Console/Commands/Fetch/FetchFuelBrandLogos.php`:

```php
<?php

namespace App\Console\Commands\Fetch;

use App\Models\Fuel;
use App\Services\LogoDev;
use App\Services\PetrolFinder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

#[Signature('fuel:brand-logos {brand?* : Specific brand names to fetch; defaults to every brand on a fuel row} {--force : Re-download logos that already exist}')]
#[Description('Download fuel-station brand logos from logo.dev, keyed by brand slug')]
class FetchFuelBrandLogos extends Command
{
    public function handle(LogoDev $logoDev, PetrolFinder $petrolFinder): int
    {
        if (! config('services.logodev.token')) {
            $this->components->error('LOGODEV_TOKEN is not set.');

            return self::FAILURE;
        }

        $brands = $this->targetBrands();

        if ($brands->isEmpty()) {
            $this->components->warn('No brands to fetch.');

            return self::SUCCESS;
        }

        $downloaded = 0;
        $skipped = 0;
        $unavailable = 0;
        $failed = 0;

        foreach ($brands as $brand) {
            $path = public_path('logos/brands/'.Str::slug($brand).'.png');

            if (! $this->option('force') && File::exists($path)) {
                $skipped++;

                continue;
            }

            $domain = $petrolFinder->brandDomain($brand);

            if ($domain === null) {
                $this->components->warn("{$brand} - no domain");
                $unavailable++;

                continue;
            }

            $result = $logoDev->logo($domain);

            match ($result['status']) {
                'saved' => [$this->store($path, $result['body']), $this->components->task($brand), $downloaded++],
                'unavailable' => [$this->components->warn("{$brand} - no logo available"), $unavailable++],
                default => [$this->components->error("{$brand} - request failed"), $failed++],
            };
        }

        $this->newLine();
        $this->components->info("Downloaded {$downloaded}, skipped {$skipped}, unavailable {$unavailable}, failed {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Brands to fetch: the command arguments, or every distinct brand that
     * appears on a fuel row.
     *
     * @return Collection<int, string>
     */
    private function targetBrands(): Collection
    {
        $given = collect($this->argument('brand'))
            ->map(fn (string $brand): string => trim($brand))
            ->filter();

        if ($given->isNotEmpty()) {
            return $given->unique()->values();
        }

        return Fuel::query()
            ->whereNotNull('brand')
            ->distinct()
            ->pluck('brand')
            ->values();
    }

    private function store(string $path, ?string $body): void
    {
        if ($body === null) {
            return;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $body);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=FetchFuelBrandLogosTest`
Expected: PASS (4 passed).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/Fetch/FetchFuelBrandLogos.php tests/Feature/FetchFuelBrandLogosTest.php
git commit -m "feat: add fuel:brand-logos download command"
```

---

### Task 4: Self-populate logos on receipt import

**Files:**
- Modify: `app/Console/Commands/Import/ImportFuelReceipts.php` (the `apply()` method, immediately after the `export:csv` call, ~line 172)
- Test: `tests/Feature/ImportFuelReceiptsTest.php` (add one test)

**Interfaces:**
- Consumes: `fuel:brand-logos` command (Task 3).
- Produces: nothing new; `apply()` invokes `fuel:brand-logos` after applying rows.

- [ ] **Step 1: Write the failing test**

Add a new `it(...)` block to `tests/Feature/ImportFuelReceiptsTest.php` (keep existing tests). The file already `use`s `App\Models\Fuel`, `Illuminate\Support\Facades\File`, and `Illuminate\Support\Facades\Http`, and defines `$this->folder` / `$this->review` in `beforeEach`. This test proves the import triggers the logo fetch by asserting the side effect: with logo.dev faked, applying a row with brand `Shell` writes `public/logos/brands/shell.png`.

Add a cleanup line to the existing `afterEach` at the top of the file:

```php
    File::delete(public_path('logos/brands/shell.png'));
```

Then add the test:

```php
it('fetches brand logos after applying the reviewed csv', function () {
    config(['services.logodev.token' => 'test-token']);
    Http::fake([
        '*/api/brands*' => Http::response(['brands' => [
            ['brand' => 'Shell', 'logo' => 'https://cdn.brandfetch.io/shell.com'],
        ]]),
        '*img.logo.dev*' => Http::response('PNG-BYTES', 200, ['Content-Type' => 'image/png']),
    ]);

    $fuel = Fuel::factory()->create(['station_name' => null]);
    $header = 'receipt_file,receipt_time,fuel_id,fuel_occurred_at,delta_minutes,receipt_lat,receipt_lng,station_name,brand,address,postcode,city,station_lat,station_lng,distance_km,alt1_name,alt2_name,flag';
    $row = "IMG_1.jpeg,2026-05-07 20:56:00,{$fuel->id},2026-05-07 20:52:23,4,51.37,-0.13,SHELL COBHAM,Shell,,,,51.37,-0.13,0.4,,,ok";
    File::put($this->review, $header."\n".$row."\n");

    $this->artisan('import:fuel-receipts', [
        'folder' => $this->folder,
        '--apply' => true,
        '--review' => $this->review,
        '--export' => storage_path('app/test-fuel.csv'),
    ])->assertSuccessful();

    expect(File::exists(public_path('logos/brands/shell.png')))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter="fetches brand logos after applying"`
Expected: FAIL - `shell.png` is not written, because `apply()` does not yet call `fuel:brand-logos`.

- [ ] **Step 3: Write minimal implementation**

In `app/Console/Commands/Import/ImportFuelReceipts.php`, inside `apply()`, immediately after:

```php
        $this->call('export:csv', ['file' => $this->option('export'), 'type' => 'fuel']);
```

add:

```php
        $this->call('fuel:brand-logos');
```

so newly-applied brands download their logos. The command is idempotent (existing files are skipped).

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=ImportFuelReceiptsTest`
Expected: PASS (all existing tests plus the new one).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/Import/ImportFuelReceipts.php tests/Feature/ImportFuelReceiptsTest.php
git commit -m "feat: fetch brand logos after fuel receipt import"
```

---

### Task 5: Fuel model accessor + card payload + feed mapping

**Files:**
- Modify: `app/Models/Fuel.php` (add `Str` + `Attribute` imports, `$appends`, `logoUrl()` accessor, `brandLogo` in `card()` meta)
- Modify: `app/Actions/BuildTimelineFeed.php:62` (add `brandLogo` mapping)
- Test: `tests/Feature/FuelCardTest.php` (extend)

**Interfaces:**
- Consumes: `public/logos/brands/{slug}.png` files written by Task 3.
- Produces: `Fuel::$logo_url` (string path or null); `card()['meta']['brandLogo']`; feed item key `brandLogo`.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/FuelCardTest.php` (append; the file already `use`s `App\Models\Fuel`). Add `use Illuminate\Support\Facades\File;` at the top if absent.

```php
afterEach(function () {
    File::delete(public_path('logos/brands/bp.png'));
});

it('exposes the brand logo url on the card when the file exists', function () {
    File::ensureDirectoryExists(public_path('logos/brands'));
    File::put(public_path('logos/brands/bp.png'), 'x');
    $fuel = Fuel::factory()->create(['brand' => 'BP']);

    expect($fuel->card()['meta']['brandLogo'])->toBe('/logos/brands/bp.png');
    expect($fuel->logo_url)->toBe('/logos/brands/bp.png');
});

it('has a null brand logo when the file is absent or brand is null', function () {
    expect(Fuel::factory()->create(['brand' => 'BP'])->card()['meta']['brandLogo'])->toBeNull();
    expect(Fuel::factory()->create(['brand' => null])->logo_url)->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=FuelCardTest`
Expected: FAIL, `Undefined array key "brandLogo"` / `logo_url` null-vs-path mismatch.

- [ ] **Step 3: Write minimal implementation**

In `app/Models/Fuel.php`:

Add imports (after the existing `use Illuminate\Database\Eloquent\Model;` line):

```php
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;
```

Add the `$appends` property and accessor inside the class (e.g. after the `casts()` method):

```php
    /**
     * @var list<string>
     */
    protected $appends = ['logo_url'];

    /**
     * Public path to the stored brand logo, or null when the brand is unset or
     * no logo file has been downloaded.
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->brand) {
                return null;
            }

            $slug = Str::slug($this->brand);

            return file_exists(public_path("logos/brands/{$slug}.png"))
                ? "/logos/brands/{$slug}.png"
                : null;
        });
    }
```

In `card()`, change the `meta` array to include the brand logo. Current:

```php
            'meta' => [
                'map' => $this->getFirstMediaUrl('map') ?: null,
                'mapDark' => $this->getFirstMediaUrl('map_dark') ?: null,
            ],
```

to:

```php
            'meta' => [
                'brandLogo' => $this->logo_url,
                'map' => $this->getFirstMediaUrl('map') ?: null,
                'mapDark' => $this->getFirstMediaUrl('map_dark') ?: null,
            ],
```

In `app/Actions/BuildTimelineFeed.php`, after the `'mapDark' => $card['meta']['mapDark'] ?? null,` line (line 62), add:

```php
            'brandLogo' => $card['meta']['brandLogo'] ?? null,
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=FuelCardTest`
Expected: PASS (all FuelCardTest cases).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Fuel.php app/Actions/BuildTimelineFeed.php tests/Feature/FuelCardTest.php
git commit -m "feat: expose fuel brand logo on model and card payload"
```

---

### Task 6: Render the brand logo on card and detail page

**Files:**
- Modify: `resources/js/Components/Timeline/FeedItem.vue` (add `brandLogo` prop + render chip)
- Modify: `resources/js/Components/Entry/FuelDetail.vue` (add header logo chip; de-duplicate station name)
- Test: `tests/Browser/FuelBrandLogoTest.php`

**Interfaces:**
- Consumes: feed item key `brandLogo` (Task 5); `entry.logo_url` (Task 5 `$appends`).
- Produces: rendered `<img src="/logos/brands/...">` on card and detail.

- [ ] **Step 1: Write the failing test**

Create `tests/Browser/FuelBrandLogoTest.php` (Pest 4 browser test; asserts the rendered DOM element, not prop JSON):

```php
<?php

use App\Models\Fuel;
use Illuminate\Support\Facades\File;

afterEach(function () {
    File::delete(public_path('logos/brands/bp.png'));
});

it('shows the brand logo image on a fuel entry page', function () {
    File::ensureDirectoryExists(public_path('logos/brands'));
    File::put(public_path('logos/brands/bp.png'), 'x');

    $fuel = Fuel::factory()->create([
        'brand' => 'BP',
        'station_name' => 'BP Cobham',
        'latitude' => 51.3,
        'longitude' => -0.1,
    ]);

    $page = visit($fuel->url());

    $page->assertPresent('img[src="/logos/brands/bp.png"]');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=FuelBrandLogoTest`
Expected: FAIL, no `img[src="/logos/brands/bp.png"]` in the DOM.

- [ ] **Step 3: Write minimal implementation**

**FeedItem.vue** - add the prop. After the `mapDark` prop (~line 45), add:

```javascript
    // Stored brand logo for fuel entries (e.g. /logos/brands/bp.png); null when
    // the brand has no downloaded logo. Rendered as a small white chip.
    brandLogo: { type: String, default: null },
```

Add the render block in the template immediately after the closing `</h3>` title tag and before the `<div v-if="airline" ...>` block:

```html
        <div v-if="brandLogo" class="mt-1.5">
            <span class="inline-flex size-6 items-center justify-center overflow-hidden rounded bg-white ring-1 ring-neutral-100">
                <img :src="brandLogo" alt="" class="size-full object-contain p-0.5">
            </span>
        </div>
```

**FuelDetail.vue** - add a header chip and remove the duplicated station name from the map caption.

Add, as the first child inside the root `<div class="space-y-8">` (before the `<div v-if="location" ...>`):

```html
        <div v-if="entry.logo_url || entry.station_name" class="flex items-center gap-3">
            <span v-if="entry.logo_url" class="inline-flex size-12 items-center justify-center overflow-hidden rounded-lg bg-white ring-1 ring-neutral-100">
                <img :src="entry.logo_url" :alt="entry.brand ? `${entry.brand} logo` : ''" class="size-full object-contain p-1.5">
            </span>
            <span v-if="entry.station_name" class="font-display text-section">{{ entry.station_name }}</span>
        </div>
```

Then, to avoid showing the station name twice, change the map caption paragraph. Current:

```html
                <p class="text-meta text-neutral-600">
                    <span v-if="entry.station_name" class="font-medium text-neutral-900">{{ entry.station_name }}</span><span v-if="entry.city">{{ entry.station_name ? ', ' : '' }}{{ entry.city }}</span>
                </p>
```

to (city only; the station name now lives in the header):

```html
                <p v-if="entry.city" class="text-meta text-neutral-600">
                    <span>{{ entry.city }}</span>
                </p>
```

- [ ] **Step 4: Build assets and run the test**

Run: `npm run build`
Then: `php artisan test --compact --filter=FuelBrandLogoTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Components/Timeline/FeedItem.vue resources/js/Components/Entry/FuelDetail.vue tests/Browser/FuelBrandLogoTest.php
git commit -m "feat: render fuel brand logo on card and detail page"
```

---

### Task 7: Generate and commit the real brand logos

**Files:**
- Create (generated, committed): `public/logos/brands/*.png`

This task runs the command against the real logo.dev API to produce the committed assets. It has no automated test (it is a data-generation step); verify by inspecting the files.

- [ ] **Step 1: Run the command**

Run: `php artisan fuel:brand-logos`
Expected: `Downloaded 3, skipped 0, unavailable 0, failed 0.` (BP, Shell, Esso).

- [ ] **Step 2: Verify the files exist and are real PNGs**

Run: `file public/logos/brands/*.png`
Expected: three `PNG image data` lines (`bp.png`, `shell.png`, `esso.png`).

- [ ] **Step 3: Commit the generated logos**

```bash
git add public/logos/brands/bp.png public/logos/brands/shell.png public/logos/brands/esso.png
git commit -m "chore: add BP, Shell, Esso brand logos"
```

---

## Self-Review Notes

- **Spec coverage:** LogoDev client (Task 1), brandDomain (Task 2), command (Task 3), self-populate on import (Task 4), accessor + card payload (Task 5), card + detail rendering (Task 6), real assets (Task 7). All spec sections covered.
- **Type consistency:** `logo(string $domain, int $size = 256): array{status,body}` and `brandDomain(string): ?string` are used identically in Task 3. `logo_url` accessor name matches `$appends` and card meta key `brandLogo`, mapped to feed key `brandLogo`, consumed as the `brandLogo` prop.
- **Task 4 caveat:** the exact `Artisan` assertion helper available in this Pest/Laravel version may differ; the implementer should prefer `Artisan::assertRan('fuel:brand-logos')` if available, else assert the row update + clean exit. This is the one task with a test-mechanism choice; keep the behavior (import triggers the command) as the invariant.
```