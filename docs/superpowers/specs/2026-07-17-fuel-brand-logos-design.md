# Fuel Brand Logos Design

**Goal:** Show each fuel entry's garage brand logo (BP, Shell, Esso, ...) on its timeline card and detail page, sourced from logo.dev, downloaded and stored locally, mirroring the existing airline-logo pattern. Fall back to the generic fuel icon when a brand or logo is unavailable.

**Status:** Approved for planning.

## Context

Fuel rows already carry a canonicalised `brand` string (`BP`, `Shell`, `Esso`), resolved on receipt import via PetrolFinder. Today the timeline shows a generic fuel-pump icon for every fuel entry, so a BP stop and a Shell stop are visually identical. Airline entries already solve the equivalent problem: `FetchAirlineLogos` downloads logos from LogoStream into `public/logos/airlines/{variant}/{iata}.png`, and `Airline` exposes `icon_url` / `logo_url` accessors that resolve to the file path or null. This feature applies the same pattern to fuel brands.

Current data: 3 distinct brands (BP x38, Shell x3, Esso x2); 81 rows have no brand. Realistically the brand set stays under ~15 over the site's lifetime.

### Why logo.dev (not BrandFetch / favicons)

BrandFetch has the best coverage but its Logo Link terms forbid downloading/self-hosting (hotlink only), which conflicts with the requirement to store images locally. Keyless favicon sources (Google, DuckDuckGo, icon.horse, Simple Icons) either miss BP entirely or return a low-res / monogram placeholder for it. logo.dev is the one service tested that (a) permits fetching by domain with a token, (b) returns genuine full-colour logos for BP, Shell, Esso and Tesco at 512px, and (c) whose model permits caching the fetched asset locally. Verified with `fallback=404`: every target brand returned a real asset, not a fabricated monogram.

## Architecture

Five small units, each mirroring an existing sibling:

1. **`App\Services\LogoDev`** (new) - HTTP client for logo.dev, analogous to `LogoStream`.
2. **`PetrolFinder::brandDomain()`** (new method) - resolve a brand name to its web domain.
3. **`fuel:brand-logos` command** (new) - backfill/refresh logos, analogous to `airlines:logos`.
4. **`ImportFuelReceipts::apply()`** (modified) - call the command after applying, so new brands self-populate.
5. **`Fuel::logo_url` accessor + `card()` payload + frontend** (modified) - render the logo on card and detail page.

### 1. LogoDev service client

`app/Services/LogoDev.php`

```php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class LogoDev
{
    private const BASE = 'https://img.logo.dev';

    /**
     * Fetch a brand logo PNG for a web domain as raw image bytes.
     *
     * fallback=404 makes logo.dev return a 404 (not a fabricated monogram)
     * when it has no real logo, so a miss is reported as unavailable rather
     * than saved.
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

Token is read from `config('services.logodev.token')` (already wired: `.env` `LOGODEV_TOKEN`, `config/services.php` `services.logodev.token`).

### 2. Brand to domain resolution

PetrolFinder's `brands()` already returns a logo URL per brand of the form `https://cdn.brandfetch.io/{domain}?c=...`. We reuse only the **domain** (public data); the embedded id is discarded. Add:

```php
/**
 * The web domain for a brand name (e.g. "BP" -> "bp.com"), parsed from the
 * brands endpoint's logo URL. Null when the brand is unlisted or has no URL.
 */
public function brandDomain(string $brand): ?string
{
    $logo = $this->brands()[mb_strtoupper($brand)]['logo'] ?? null;
    if ($logo === null) {
        return null;
    }
    $path = parse_url($logo, PHP_URL_PATH);   // "/bp.com"
    $domain = trim((string) $path, '/');

    return $domain !== '' ? $domain : null;
}
```

### 3. Backfill command

`app/Console/Commands/Fetch/FetchFuelBrandLogos.php`, signature `fuel:brand-logos {brand?* : Specific brand names; defaults to every brand on a fuel row} {--force : Re-download logos that already exist}`. Mirrors `FetchAirlineLogos`:

- Fail early if `config('services.logodev.token')` is unset.
- Target brands: the command arguments, else `Fuel::query()->whereNotNull('brand')->distinct()->pluck('brand')`.
- For each brand: `slug = Str::slug($brand)`; `path = public_path("logos/brands/{$slug}.png")`.
  - Skip if `! --force && File::exists($path)`.
  - `domain = PetrolFinder::brandDomain($brand)`; null domain -> report unavailable, continue.
  - `LogoDev::logo($domain)`; on `saved`, `File::ensureDirectoryExists` + `File::put`; count saved / unavailable / error.
- Print a summary line; return `FAILURE` only if a request errored (not merely unavailable).

One image per brand (the logo.dev `img` endpoint returns a single square mark). No icon/logo variant split - unlike airlines, logo.dev exposes one asset per domain, used at both sizes.

### 4. Self-populating on import

At the end of `ImportFuelReceipts::apply()`, after the CSV export, call the command with no arguments so any brand newly written to a fuel row downloads its logo (idempotent - existing files are skipped):

```php
$this->call('fuel:brand-logos');
```

A brand that logo.dev lacks simply produces no file and falls back to the fuel icon; the command still succeeds.

### 5. Model accessor, card payload, frontend

**`Fuel` model** - add a `logo_url` accessor mirroring `Airline::logoUrl`:

```php
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

Extend `Fuel::card()` `meta` with `'brandLogo' => $this->logo_url` (null when absent).

**Timeline card** (`resources/js/Components/Timeline/FeedItem.vue`): the fuel-pump icon stays in the leading icon rail (type identity), and the brand logo renders as a small chip near the title, exactly as flights already render `airline.icon` inline (FeedItem line ~225). The chip is a fixed-size white rounded tile containing the `<img>`:

```html
<span class="inline-flex size-5 items-center justify-center overflow-hidden rounded bg-white ring-1 ring-neutral-100">
    <img :src="brandLogo" alt="" class="size-full object-contain p-0.5">
</span>
```

The white tile is deliberate: logo.dev assets have mixed transparency (BP/Esso/Tesco carry a baked white background, Shell/Asda are transparent), so a white chip renders every brand consistently and stays legible in dark mode. `alt=""` because the brand name is already in the card title.

**Detail page** (`resources/js/Components/Entry/FuelDetail.vue`): render the same white-chip logo at a larger size beside the station name, mirroring `FlightDetail`'s airline-logo header. Fuel icon shown when `brandLogo` is null.

## Data Flow

Import (`--apply`) writes `brand` to fuel rows -> `fuel:brand-logos` resolves each brand's domain via PetrolFinder -> `LogoDev` downloads the PNG -> stored at `public/logos/brands/{slug}.png`. At render time `Fuel::logo_url` maps `brand` -> file path (or null) -> `card()`/detail expose it -> Vue renders the white-chip logo or falls back to the fuel icon.

## Error Handling

- **No token:** command errors clearly and exits before any request (matches `airlines:logos`).
- **logo.dev 404 (no logo for brand):** `unavailable`, no file written, accessor returns null, UI falls back to fuel icon.
- **Request/network error:** `error`, counted, command returns `FAILURE`; no partial file written.
- **Unknown brand / no domain:** treated as unavailable, skipped.
- **Brand-less rows (81):** `logo_url` is null, unchanged fuel-icon rendering.

## Testing

- **`LogoDevTest`** (feature, HTTP faked): `saved` on a 200 image response; `unavailable` on 404; `error` on 500 or non-image content-type; asserts the request carries the configured token and `fallback=404`.
- **`PetrolFinderTest`** (extend): `brandDomain()` parses `bp.com` from a faked brands response; returns null for an unlisted brand.
- **`FetchFuelBrandLogosTest`** (feature, `Storage`/`File` + HTTP faked): downloads and writes `public/logos/brands/bp.png` for a fuel row with brand BP; skips an existing file unless `--force`; errors when the token is unset; a 404 brand writes no file and does not fail the command.
- **`FuelCardTest`** (extend): `card()['meta']['brandLogo']` is the path when the file exists (fake the file) and null when the brand is null or the file is absent.
- **Frontend:** a browser/smoke assertion that a fuel entry with a brand logo renders the `<img>` (asserting the DOM element, not prop JSON, per project convention).

## Design Rationale

- **logo.dev over BrandFetch:** the only tested source that both permits local storage and reliably covers BP (88% of branded rows). BrandFetch forbids self-hosting; favicon sources fail on BP.
- **Store locally (not hotlink):** matches the airline-logo pattern and the project's pre-generated-assets stance; the token never reaches the browser; no per-view third-party dependency; offline-safe.
- **Per-brand file keyed by slug (not Media Library):** identical to airline logos, no model or migration needed, trivially deduplicated (one BP file for 38 rows).
- **White chip:** normalises logo.dev's mixed transparency and guarantees dark-mode legibility without per-brand handling.
- **Self-populate via the import command:** no standing manual step; the backfill command remains the single source of fetch logic.

## Notes & Caveats

- Local storage assumes logo.dev's terms permit caching the fetched asset; the service was chosen on that basis.
- The generated `public/logos/brands/*.png` files are **committed to git**, exactly as the existing `public/logos/airlines/*.png` are (29 already tracked). Prod therefore receives brand logos via deploy - the token and `fuel:brand-logos` are only needed locally to generate/refresh them.
- One asset per brand only; no separate icon/wordmark variants (logo.dev exposes a single mark per domain).
- Card placement mirrors flights (type icon in the rail + brand chip by the title); flip to replacing the rail icon if preferred at review.
```