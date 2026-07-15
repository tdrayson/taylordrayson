# Sushi-backed airline/airport lookups — design

**Date:** 2026-07-15
**Branch:** `feat/sushi-lookup-tables` (off `master`)
**Status:** Approved, pending implementation plan

## Context

`airlines` (5,842 rows) and `airports` (9,070 rows) are static reference data living in the app database alongside personal timeline data. They are pure lookups: a recon of the codebase found **zero writes** to either model in application code. `EnrichFlights` writes to `Flight`, not to the lookups; `FetchAirlineLogos` only reads and writes logo files to disk. No seeders touch them.

Moving them to [Sushi](https://github.com/calebporzio/sushi) (Eloquent's array driver) keeps them as ordinary Eloquent models, backed by a per-model cached SQLite database built from CSV, while removing ~15k rows of static data from the app database.

Two findings shaped this design:

1. **One `whereHas` exists**, in advanced search. Eager loading (`with(['airline', 'origin', 'destination'])` in `FlightController`) is fine, because it runs as separate `whereIn` queries that work across connections. But `SearchCompiler::applyCondition()` filters flights by airline *name* through `whereHas($field['relation'], ...)`, a correlated subquery that cannot cross a connection boundary. See "Cross-connection search" below.
2. **Six `exists:` validation rules** in the flight form requests are connection-bound and would break. The abandoned `feat/flat-file-storage` branch hit this same wall and solved it with an `App\Rules\ExistsOnModel` rule, which we lift verbatim.

## Decisions

| Decision | Choice |
| --- | --- |
| Canonical data location | `database/lookups/{airlines,airports}.csv`, moved out of `data/` |
| Why not `data/` | `data/` holds regenerated mirrors of personal timeline data; these two files become canonical and must never be overwritten by an export |
| Validation | Replace `exists:` with `App\Rules\ExistsOnModel` (model-bound, connection-agnostic) |
| Test data | `getRows()` returns `[]` under `app()->environment('testing')`, with an explicit `$schema` |
| Caching | On in dev/prod, **off under test** — a cached empty row set would poison the shared cache file |
| Test churn | **None.** All five test files keep their inline `Airport::create` / `Airline::create` and factories |
| Prior art | Lift from the closed `feat/flat-file-storage` branch, which already built this. Diverge deliberately on three points (below) |
| Timestamps | `$timestamps = false` (CSVs carry none; nothing reads them) |
| Import/export | `airline` / `airport` entries removed from `ImportCsv` and `ExportCsv` |

## Architecture

### 1. Canonical CSVs — `database/lookups/`

`data/airlines.csv` and `data/airports.csv` move to `database/lookups/`. They stop being generated output and become the source of truth, git-tracked and diffable. `data/` keeps its existing meaning: regenerated mirrors of personal timeline data.

Existing headers are kept as-is:

```
airlines: iata_code,icao_code,name,country
airports: iata_code,icao_code,name,city,country,latitude,longitude
```

### 2. Shared CSV reader — `app/Support/LookupCsv.php`

A small single-purpose helper that reads a CSV into an array of associative rows, used by both models' `getRows()`. Exists so the parsing logic is written once and testable in isolation.

### 3. The models — `Airline`, `Airport`

Each gains `use Sushi`, an explicit `$schema`, `$timestamps = false`, and three methods:

- `getRows()` — returns `[]` when `app()->environment('testing')`, otherwise reads the canonical CSV via `LookupCsv`. (`phpunit.xml` sets `APP_ENV=testing`, so this is reliable.)
- `sushiShouldCache()` — `! app()->environment('testing')`. Caching **must** be off under test, see below.
- `sushiCacheReferencePath()` — points at the CSV, so editing it busts the cache. Without this, Sushi compares against the *model file's* mtime and would serve stale data after a CSV edit.

**Why caching must be disabled under test.** The cache file is per-model, at a fixed path shared by every environment on the machine. If tests cached their empty row set, the file would be written with zero rows; a later dev request would compare the CSV's mtime, find it unchanged, judge the cache fresh, and serve an empty lookup table. Tests would silently poison the dev environment, and the failure would look like missing data rather than a stale cache. With caching off under test, Sushi falls back to in-memory and never touches the shared cache file.

`$schema` is required rather than optional here: Sushi infers columns from the first row, so the empty-in-tests case has nothing to infer from. The package documents `$schema` for exactly this scenario.

The existing `Attribute` accessors for `icon_url` / `logo_url` are untouched — they key off `iata_code` and are ordinary Eloquent.

### 4. Cross-connection search — `SearchCompiler`

**Discovered during implementation, after this spec was first approved.** An earlier draft claimed no `whereHas` existed on these relations. That was wrong. `SearchSchema` line 80 declares the airline search field as `'relation' => 'airline'`, and `SearchCompiler::applyCondition()` turns any field with a `relation` key into a `whereHas`. The relation name is a runtime value, not a literal, which is why a text search for `whereHas('airline'` found nothing. The lesson: trace how config is consumed, do not grep for literals.

Impact is contained to one field. `airline` is the only `relation` in the entire schema; `origin` and `destination` filter on the `origin_iata` / `destination_iata` columns directly, so `Airport` is unaffected. Symptom today is a silent zero-result search; once the tables are dropped it becomes "no such table: airlines".

**Resolution: two queries instead of one nested one.** When a relation's model sits on a different connection from its parent, resolve the matching keys on the related model's own connection, then constrain the parent by its foreign key:

```php
$codes = Airline::where('name', 'like', '%easyJet%')->pluck('icao_code'); // sushi connection
$query->whereIn('airline_icao', $codes);                                  // default connection
```

Rejected alternatives:

- **A plain `whereIn` subquery.** Fails identically. The issue is not correlation, it is that a single SQL statement reaches only the tables in the connection it runs on.
- **SQLite `ATTACH DATABASE`.** Would allow one real cross-database query, but permanently couples the app to SQLite and requires managing the attach lifecycle around Sushi's cache rebuilds. Too much fragility to save one round trip.

The extra round trip is acceptable: advanced search is low-traffic personal-use, and it hits a small cached table. `pluck` on a name match returns a handful of codes; the pathological worst case is all 5,842, well inside SQLite's variable limit.

The branch is generic rather than airline-specific, so any future Sushi-backed relation is handled.

### 5. Validation — `app/Rules/ExistsOnModel.php`

Lifted verbatim from `origin/feat/flat-file-storage`. Six rules change across `StoreFlightRequest` and `UpdateFlightRequest`:

```php
// before — queries the default connection for a table that will no longer exist
'airline_icao' => ['required', 'string', 'exists:airlines,icao_code'],

// after — queries through the model, so it follows the model's connection
'airline_icao' => ['required', 'string', new ExistsOnModel(Airline::class, 'icao_code')],
```

### 6. Dropping the tables

A migration drops `airlines` and `airports`. Note `2026_03_18_010000_create_app_tables.php` creates them and is left alone; the new migration is the forward path, and its `down()` recreates both to match that original definition.

### 7. Import/export

`airline` and `airport` are removed from the model maps in `ImportCsv` (which called `$modelClass::create()`) and `ExportCsv`. Once the CSV *is* the table, round-tripping it through the database is redundant and would risk overwriting canonical data.

## Data flow

```
first query
   ├─ cache exists and CSV mtime unchanged? → serve from cached SQLite
   └─ stale or missing? → getRows() → LookupCsv → rebuild cache → serve

Flight::with('airline')
   └─ separate whereIn on the Sushi connection (no join — verified none exist)
```

## Error handling

- Missing or malformed CSV throws from `getRows()`. It fails loudly rather than silently serving an empty lookup table.
- If Sushi cannot write its cache file it falls back to an in-memory SQLite database: slower, still correct. Relevant to the xCloud VPS deploy.
- Cache path is confirmed at install time and must resolve to gitignored storage, not next to the model files.

## Testing

The test environment returns `[]` rows, so every existing test keeps its inline fixtures and its intent:

- `EnrichFlightsTest` keeps `CCC` at `10,10` and `DDD` at `20,20`. This matters: the real `CCC`/`DDD` are Cuba and the Maldives, and the test asserts a great-circle distance of 900–1020 miles from those controlled coordinates. Real data would put them ~9,000 miles apart.
- `AdvancedSearchTest` keeps `easyJet` (the real `EZY` is `easyJet UK`, so `eq 'easyJet'` would fail against real data).
- `ArchiveTest`, `FlightStoryTest` keep their fabricated names (`Heathrow`, `Gatwick`).
- `FlightApiTest` keeps its factories — they still work, because Sushi's table is a real SQLite table.

Additions:

- **No `beforeEach` truncate.** An earlier draft of this spec mandated one, justified by "duplicate codes violate the unique indexes". That reasoning was wrong: Sushi's `$schema` declares column *types* only and creates no indexes. With caching off under test, Sushi uses an in-memory SQLite database that is rebuilt when the app is, i.e. per test, so rows do not leak. The closed `feat/flat-file-storage` branch shipped this same design with no truncate and a passing suite. Implementation verifies this by running the suite; the truncate is added only if leakage actually appears.
- A unit test for `LookupCsv`.
- A test that reads the real `database/lookups/*.csv` **through `LookupCsv` directly, not via the models**, asserting row counts and the presence of required columns. The models' `[]` shortcut hides the real path from every other test, so this is the only thing guarding it.

## Out of scope

- Removing `HasFactory` from the models. The factories still work.
- Touching the logo accessors or `FetchAirlineLogos`.
- Any change to `data/` beyond removing the two moved files.

## Prior art — `feat/flat-file-storage` (PR #17, closed)

The closed branch already implemented this exact migration. Leftover cache files (`storage/framework/cache/sushi-app-models-{airline,airport}.sqlite`) are what revealed it. It independently reached the same conclusions, including `sushiShouldCache()` returning `! app()->environment('testing')`, which is strong validation of the caching decision above.

Implementation lifts from that branch (`git show origin/feat/flat-file-storage:<path>`) rather than writing from scratch:

| Component | Action |
| --- | --- |
| `composer.json` | `calebporzio/sushi: ^2.5` — the version it used |
| `app/Rules/ExistsOnModel.php` | lift verbatim |
| `app/Models/{Airline,Airport}.php` | lift the Sushi additions, adjusting the CSV path |
| `Store/UpdateFlightRequest` | lift the rule swaps verbatim |
| `tests/Feature/Content/SushiLookupTest.php` | lift, adjusting paths |

Three deliberate divergences:

1. **CSV location.** It used `base_path('data/...')`; we use `database_path('lookups/...')`, per the Decisions table.
2. **Missing-file behaviour.** Its `CsvLookupRows::from()` returns `[]` when the file is absent or unreadable. Ours throws. Silently returning `[]` is the exact failure this spec guards against: with caching on, a missing CSV in production would cache an empty lookup table and surface as inexplicable missing data rather than an error.
3. **Migration `down()`.** Its `down()` is intentionally empty; ours recreates both tables to match `2026_03_18_010000_create_app_tables.php`, keeping the migration reversible.

Naming: our helper is `App\Support\LookupCsv`, not `CsvLookupRows`.

## Notes

- Verified no code reads `airline->id`, `airport->id`, or their timestamps. `FlightResource` exposes only `icao` / `iata` / `name`.
- `$timestamps = false` is safe: Sushi only adds timestamp columns when a model uses them, and nothing reads them here.
- Sushi's `$schema` declares column types only; the unique indexes that existed on the real tables (`airports_iata_code_unique`, `airlines_icao_code_unique`) do not carry over. Nothing depends on them, as all lookups go through `where(...)->first()`.
- The one piece of magic is `getRows()` behaving differently under test. It is the price of keeping five test files unchanged and preserving their controlled fixtures.
- `feat/flat-file-storage` (PR #17, closed) remains on origin as the source for `ExistsOnModel`.
