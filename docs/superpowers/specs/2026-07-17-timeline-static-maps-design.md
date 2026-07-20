# Timeline static maps: pre-generate and store everywhere

**Date:** 2026-07-17
**Branch:** `feat/timeline-static-maps` (off `feat/fuel-station-lookup`, which adds fuel coordinates)

## Problem

Located timeline entries render their map thumbnails inconsistently:

- **Events** pre-generate light+dark pin maps, store them in media, and serve stored
  URLs via `card()`. This is the desired pattern.
- **Activities** have a stored light-only map (`GenerateStaticMap`, run during Strava
  sync) but `FeedItem` ignores it and **live-renders** from the polyline via
  `lib/staticMap.js`.
- **Flights** are **live-rendered** from the great-circle arc in `FeedItem`; nothing stored.
- **Fuel and checkins** have coordinates but no map at all.

We want two things:

1. **Every** located type (event, activity, flight, fuel, checkin) shows a
   pre-generated, stored static map (light + dark) on the timeline. No Mapbox rendering
   at view time.
2. Fuel and checkins additionally get the interactive single-entry map (`LocationMap`,
   as events already have), and `FuelDetail` surfaces the new garage fields
   (station name, brand, address, postcode).

## Decisions (from brainstorming)

- **Scope:** fuel, checkins, activities, and flights, in one migration (events already done).
- **Stored, not live:** the timeline thumbnail is always a stored image served through
  `card()['map']`/`['mapDark']`. `FeedItem` stops calling `staticRouteMap`/`staticArcMap`.
- **Single-entry maps stay interactive:** the single-entry page keeps the interactive
  MapLibre `LocationMap` (fuel/checkin) and `EntryMap` (activity route). "No realtime
  render" applies to the timeline static thumbnails, not the interactive detail maps.
- **Pin colour per type:** the stored pin uses the type's accent hex from
  `TypeColors::hex($token)` (fuel/checkin/event), not a hardcoded colour.

## Current building blocks (reused)

- `App\Support\StaticMap` — server-side Mapbox Static Images URL builder with
  `marker()` (pin), `route()` (polyline), and `arc()` (flight) — all three shapes exist.
- `App\Actions\GenerateLocationMap` — fetches + stores light (`map`) and dark
  (`map_dark`) **pin** images for any `Model&HasMedia` with lat/lng. Marker colour is
  currently hardcoded to the event purple.
- `App\Actions\GenerateStaticMap` — stores a light-only **route** map for an activity;
  no dark variant, uses an inline URL rather than `StaticMap::route()`.
- `App\Console\Commands\Fetch\FetchEventMaps` — idempotent backfill for events
  (`events:maps {--force}`).
- `App\Support\TypeColors::hex($token)` — per-type accent hex from `app.css`.
- Frontend: `Components/Maps/LocationMap.vue` (interactive pin, used by `EventDetail`),
  `RouteThumb.vue` (SVG route fallback, no tiles), `FeedItem.vue` (already renders
  `props.map`/`props.mapDark` for events).
- `EntryController` builds `location {lat,lng,address,mapsUrl}` — but only for `Event`.

## Design

### 1. Unified stored generation (light + dark, per shape)

Each located type stores a light and a dark PNG in the `map` / `map_dark` media
collections. Three shape-specific generators, one orchestrator:

- **Pin** (event, fuel, checkin): `GenerateLocationMap`, extended to accept a marker
  colour, called with `TypeColors::hex('fuel'|'checkin'|'event')`. Signature becomes
  `__invoke(Model&HasMedia $model, ?string $markerColor = null): ?Media` (defaults to the
  existing event purple when null, so `FetchEventMaps` is unaffected).
- **Route** (activity): `GenerateStaticMap`, extended to store **both** light and dark
  using `StaticMap::route()` for both styles (dark passes the dark style), matching the
  event light/dark convention. Keeps its existing "skip if a `map` already exists unless
  forced" behaviour.
- **Arc** (flight): new `GenerateFlightMap` action, mirroring `GenerateLocationMap`'s
  fetch/store loop but using `StaticMap::arc()` from the flight's origin/destination
  airport coordinates, storing light + dark.

`StaticMap` already produces all three shapes; `route()` and `arc()` take a style so a
dark variant is a second call. Where a generator currently hardcodes a style/colour,
switch it to the shared `StaticMap` helpers so the stored image matches the look the
timeline used when it rendered live.

### 2. `card()` emits stored map/mapDark

Each model's `card()` returns stored URLs (mirroring `Event::card()`):

```php
'map' => $this->getFirstMediaUrl('map') ?: null,
'mapDark' => $this->getFirstMediaUrl('map_dark') ?: null,
```

- **Activity::card()** keeps `polyline` (for the `RouteThumb` SVG fallback) but adds
  `map`/`mapDark`; `FeedItem` prefers the stored image.
- **Flight::card()** adds `map`/`mapDark`; keeps `route` (used by the `FlightRoute` text
  component).
- **Fuel::card()** / **Checkin::card()** add `map`/`mapDark`, shown only when the entry
  has no photos (mirroring `Event::card()`).

### 3. `FeedItem` renders stored images only

- Remove `routeImageUrl` / `routeImageDarkUrl` computeds that call `staticRouteMap` /
  `staticArcMap`, and the `staticMap.js` import.
- Render `props.map` / `props.mapDark` directly (the existing `<img>` banner slots and
  the map-plus-cover layout stay).
- Keep `RouteThumb` (SVG from polyline/arc points) as the fallback when a stored image is
  absent, so an un-backfilled activity/flight still shows something.
- If `lib/staticMap.js` ends up unreferenced, delete it (confirm no other importer first).

### 4. Single-entry maps for fuel/checkin

- **EntryController:** generalise the `location` block (currently `if ($model instanceof
  Event)`) so any located model (event, fuel, checkin) gets
  `location {lat, lng, address, mapsUrl}`. For fuel the address is the station name +
  address; `mapsUrl` is a Google Maps search by that address (or coordinates).
- **FuelDetail.vue:** add `LocationMap` (`color="var(--color-fuel)"`, label = station
  name) from `entry.location`, and surface `station_name`, `brand`, `address`,
  `postcode`, `city` in the detail list alongside the existing stats.
- **CheckinDetail.vue:** add `LocationMap` (`color="var(--color-checkin)"`) from
  `entry.location`.

### 5. Media collections

Fuel and Checkin already implement `HasMedia` via `HasAttachments`. Storing to the `map`
/ `map_dark` collections works the same way events do (`addMediaFromString(...)
->toMediaCollection('map')`, read back with `getFirstMediaUrl('map')`), via the
`attachments` media table. No conversions are needed (the `@2x` retina size is baked into
the Mapbox URL).

### 6. Backfill command

A single idempotent command generates stored maps for all located rows of the migrated
types:

`maps:generate {type : activity|flight|fuel|checkin} {--force}`

It selects the model, filters to rows with the required coordinates/polyline, skips rows
that already have a `map` (unless `--force`), and calls the matching generator with the
type's colour. `events:maps` stays as-is. The command is run per type to backfill:
fuel (43 rows locally), activities and flights (as present in the dev DB), and checkins
(0 in the dev DB, so prod-only in practice).

## Data flow

```
model (lat/lng or polyline or arc endpoints)
  -> generator (StaticMap::marker|route|arc, light + dark)
  -> Http::get(Mapbox) -> addMediaFromString -> media 'map' / 'map_dark'
  -> card()['map'] / ['mapDark']  -> FeedItem <img> (dark: swap)     [timeline]
  -> EntryController location {lat,lng,...} -> LocationMap (interactive) [single entry]
```

## Error handling

- No coordinates / no polyline: generator returns null, the row is skipped and reported.
- Mapbox failure / missing token: generator skips that row (matching the existing
  `GenerateLocationMap` behaviour); the timeline falls back to `RouteThumb` (routes/arcs)
  or shows no map (pins).
- Backfill is idempotent: existing `map` media are skipped unless `--force`.

## Testing

- **Generators** (`GenerateLocationMap` colour param, `GenerateStaticMap` light+dark,
  `GenerateFlightMap`): `Http::fake` Mapbox, assert both `map` and `map_dark` media are
  attached and that the requested style/colour appears in the sent URL.
- **card()**: fuel/checkin/activity/flight `card()` returns `map`/`mapDark` from fake
  media, null when absent; fuel/checkin suppress the map when photos exist.
- **EntryController**: the `location` object is built for fuel and checkin, with
  `address`/`mapsUrl`.
- **FeedItem**: renders `props.map`/`props.mapDark` and no longer emits a Mapbox URL when
  they are absent (prop/DOM assertion).
- **FuelDetail / CheckinDetail** (Pest browser): assert the rendered map container and the
  garage fields appear in the DOM (not just the Inertia props JSON).
- **maps:generate**: seeds located rows, fakes Mapbox, asserts media created and
  idempotent re-run skips.

## Risks

- **Visual parity** for activities/flights: the stored route/arc must match what the
  timeline rendered live. Align generation on the shared `StaticMap` helpers and
  screenshot-compare a sample before/after.
- **Mapbox usage / storage:** the one-off backfill makes N light+dark calls and stores
  2N images; ongoing generation happens once per new entry at ingest/sync time.
- **0 checkins in the dev DB:** the checkin path is covered by tests/factories and wired
  end-to-end, but can only be exercised against real data in production.

## Out of scope

- Moving stored images to Cloudflare R2 (the media stack's separate migration; this
  feature stores via the existing media pipeline and inherits R2 when that lands).
- Changing the interactive single-entry maps' tile source or `EntryMap` (activity route).
- Any new map styling beyond matching the current look.
