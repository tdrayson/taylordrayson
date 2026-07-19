# Fuel garage/location support

**Date:** 2026-07-17
**Branch:** `feat/fuel-station-lookup`

## Problem

Fuel logs record the purchase (litres, cost, odometer, timestamp) but not *where* the
fuel was bought. Separately, there is a folder of ~45 fuel-receipt photos, each carrying
GPS coordinates and a capture timestamp in EXIF. We want to:

1. Attach garage/location data (name, brand, address, coordinates) to fuel logs.
2. Backfill the existing logs from the receipt photos as a one-off.
3. Keep the data in `data/fuel.csv` as a self-contained backup.
4. Build a **reusable** garage-lookup integration that a future data-entry frontend can
   call ("type an address / use my location, pick the closest garage").

## Decisions (from brainstorming)

- **Flat storage, not a relation.** Store the garage directly on each fuel row, mirroring
  how `Checkin` stores `venue_name`/`address`/`city`/`latitude`/`longitude` flat. The
  recently-added `FuelStation` table + relation + `ResolveFuelStation` action are removed.
  The `fuel_stations` table is empty (0 rows, 0 linked fuel rows), so nothing is lost.
- **Keyless API surface.** The lookup uses PetrolFinder's keyless `GET /api/search`
  endpoint (no `/v1/`, so no Bearer key and no `.env` setup). It supports both `q`
  (postcode / place name) and `lat`/`lng` search, covering both the receipt backfill and
  the future address-search frontend.
- **Auto-match then editable review.** The backfill auto-matches receipts to fuel logs by
  timestamp and auto-picks the nearest station, but writes an **editable review CSV** for
  correction before anything is written to the database (two-pass: dry run, then `--apply`).
- **Data only, no images.** Receipts are used purely as a GPS/timestamp source; the image
  files are not attached to fuel entries (the media stack is mid-migration to R2).

## Field set

Stored flat on the `fuel` table (all nullable):

| Column | Source | Notes |
|---|---|---|
| `station_name` | API `name` | The specific garage, e.g. "Mitcham Road SF Connect". Parallels `Checkin.venue_name`. |
| `brand` | API `brand` | The company, e.g. "BP", "ASDA". |
| `address` | API `address` | Street address. |
| `postcode` | API `postcode` | UK postcode. |
| `city` | API `city` | |
| `county` | (none) | API does not return county; stays null from this source. Column kept for Checkin-consistency. |
| `country` | default | API does not return country; defaults to "United Kingdom". |
| `latitude` | API `latitude` | `decimal(10, 7)`. |
| `longitude` | API `longitude` | `decimal(10, 7)`. |

## Components

### 1. `PetrolFinder` service client (durable integration)

`app/Services/PetrolFinder.php`, following the `TimeApi`/`Strava` pattern. Keyless,
`BASE = 'https://www.petrolfinder.uk'`, wraps `GET /api/search` via `Http::get`.

Public methods:

- `search(?string $query, ?float $latitude, ?float $longitude, float $radius = 10): array`
  Low-level. Sends `q` when `$query` is set, otherwise `lat`/`lng`. Returns an array of
  `FuelStationResult`. Returns `[]` on a non-successful response.
- `nearest(float $latitude, float $longitude, float $radius = 5): ?FuelStationResult`
  Convenience for the backfill: the closest station by distance (results sorted ascending
  by `distance` defensively), or null when none are returned.
- `searchByAddress(string $query, float $radius = 10): array`
  Convenience for the future frontend: postcode / place-name search.

`app/Services/PetrolFinder/FuelStationResult.php` (or a suitable namespace) is an immutable
`readonly` value object: `station_name, brand, address, postcode, city, latitude,
longitude, distance`. A private normaliser maps a raw API `stations[]` element to it.

No `config/services.php` entry is required (keyless).

### 2. Fuel model → flat garage fields

New migration `..._move_fuel_station_to_flat_columns.php`:

- `up()`: add the nine nullable columns above to `fuel`; drop the `fuel_station_id` column;
  drop the `fuel_stations` table.
- `down()`: drop the flat columns; recreate `fuel_stations` (matching
  `2026_06_24_104327_create_fuel_stations_table.php` plus the brand/address columns from
  `2026_07_04_214847`); re-add `fuel_station_id`. Row data is not restored (empty table),
  mirroring the airline/airport drop precedent.

Code changes:

- `app/Models/Fuel.php`: update `#[Fillable]` (remove `fuel_station_id`, add the nine flat
  fields; keep `occurred_at, vehicle_id, litres, cost, fuel_card_cost, price_per_litre,
  odometer`). Remove the `fuelStation()` relation. Update `card()` so `title`/`titleLabel`
  read `station_name` (falling back to "Fuel"); subtitle unchanged.
- Delete `app/Models/FuelStation.php`, `app/Actions/Fuel/ResolveFuelStation.php`,
  `database/factories/FuelStationFactory.php`.
- Update `database/factories/FuelFactory.php` to fake the flat garage fields.
- Delete `tests/Feature/FuelStationTest.php` and `tests/Feature/ResolveFuelStationTest.php`
  (approved as part of this design).

No changes to `ExportCsv`/`ImportCsv`: both are fillable-driven, so `data/fuel.csv`
automatically gains the garage columns and loses `fuel_station_id`. `fuel.csv` is
regenerated by the backfill's `--apply` step.

### 3. `import:fuel-receipts` command (one-off backfill)

`app/Console/Commands/Import/ImportFuelReceipts.php`.
Signature: `import:fuel-receipts {folder} {--apply} {--window=1440} {--review=}`
(`--window` is the max match window in minutes; `--review` overrides the default review
CSV path `storage/app/fuel/receipt-review.csv`).

Supporting action `app/Actions/Fuel/ExtractReceiptLocation.php`: given an image path,
reads `exif_read_data`, converts GPS rationals to decimal (respecting N/S/E/W refs), parses
`DateTimeOriginal` to a `Carbon`, and returns an immutable DTO `(path, capturedAt,
latitude, longitude)` or null when GPS/timestamp is missing. The rational→decimal
conversion is a pure static method so it can be unit-tested without a fixture image.

**Dry run (default):**
1. Scan `folder` for image files; extract location + time. Files without GPS/time are
   skipped and listed in the summary.
2. For each receipt, find the `fuel` entry with the closest `occurred_at` within `--window`;
   compute the delta in minutes.
3. Resolve the nearest station via `PetrolFinder::nearest()`, caching by rounded coordinate
   to dedupe repeat calls for the same forecourt.
4. Write the review CSV: `receipt_file, receipt_time, fuel_id, fuel_occurred_at,
   delta_minutes, receipt_lat, receipt_lng, station_name, brand, address, postcode, city,
   distance_km, alt1_name, alt2_name, flag`. Nothing is written to the database.
5. Print a summary (matched / flagged / unmatched / skipped counts).

**`--apply`:**
1. Read the (possibly edited) review CSV.
2. For each row with a `fuel_id` and a station, update that fuel row's flat garage fields
   (country defaults to "United Kingdom").
3. Regenerate `data/fuel.csv` (invoke `export:csv fuel`).
4. Report the updated count.

Idempotent: re-running `--apply` overwrites the same fields; safe to repeat. The receipts
folder lives outside the repo and is passed as an argument, never committed.

## Data flow

```
receipt.jpeg
  -> ExtractReceiptLocation (lat, lng, capturedAt)
  -> match to Fuel by closest occurred_at within window
  -> PetrolFinder::nearest(lat, lng) -> FuelStationResult
  -> review CSV row  --(human edit)-->  --apply
  -> Fuel row flat fields + regenerated data/fuel.csv
```

## Error handling

- Receipt with no GPS or no timestamp: skipped, listed in summary.
- No fuel entry within `--window`: review row with empty `fuel_id`, `flag = unmatched`.
- API failure or zero stations: review row with empty station fields, `flag = no-station`.
- `--apply` skips any row lacking a `fuel_id` or a `station_name`.

## Testing

- `PetrolFinder`: `Http::fake` with a captured `/api/search` fixture. Assert `q` vs
  `lat`/`lng` parameter selection, field mapping into `FuelStationResult`, distance sort in
  `nearest()`, and `[]` on failure.
- `ExtractReceiptLocation`: unit-test the GPS rational→decimal conversion as a pure function
  (including S/W negatives). Avoids committing a real receipt (with real coordinates) to git.
- `ImportFuelReceipts`: bind a fake `PetrolFinder` returning canned stations and a fake/temp
  extractor; seed fuel entries; run dry run and assert the review CSV contents; run `--apply`
  and assert the fuel rows are updated and `data/fuel.csv` is regenerated.
- `Fuel::card()` with flat fields populated and empty.
- Update `FuelFactory`; remove the two `FuelStation` tests.

## Out of scope

- Attaching receipt images to fuel entries (deferred until the R2 media migration settles).
- The data-entry frontend / control panel that will consume `PetrolFinder::searchByAddress`
  (this spec only delivers the reusable client it will call).
- Populating `county` (not available from the API).
