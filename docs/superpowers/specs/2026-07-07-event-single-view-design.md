# Event Single-View & Multi-Day Display — Design Spec

> Date: 2026-07-07. Follows the events data-type restructure (events + photos now imported).

## Goal

Make imported events display richly on the site:

1. A **location map** — a static thumbnail (card) and an interactive marker map (entry view), from the event's stored `lat`/`lng`.
2. A **cover photo + gallery** when photos exist; the static map as the visual when they do not.
3. An **address → Google Maps** link.
4. Correct **multi-day** behaviour: a multi-day event appears on every day it covers (day pages + on-this-day) while counting **once** in stats.

The guiding principle: **events mirror activities**, which already have a static map thumbnail, an interactive entry-view map, a photo cover, and a "+N" gallery. This is mostly reuse; the route is swapped for a location pin.

## Current State (verified)

- 88 events imported into `events`; 52 photos attached (`cover` / `photos` collections) with `card` WebP conversions generated.
- Events carry `latitude`/`longitude` (geocoded), `meta.address`, `ends_at`, `all_day`.
- Timeline is `occurred_at`-driven via the `timeline_entries` mirror (one row per model, keyed `timelineable_type` + `timelineable_id`, populated by `TimelineEntryObserver::saved`). `ends_at` exists on `events` but is used nowhere for display/query.
- Counts are `count(*)` of `timeline_entries` per type (`MoreController`).
- Maps: interactive is MapLibre GL + OpenFreeMap positron (`resources/js/lib/maplibre.js`, `EntryMap.vue` for polylines, `FlightsMap.vue` for markers). Static maps use the Mapbox Static Images API (`App\Actions\GenerateStaticMap`, path overlay) stored in the `map` media collection; `FeedItem.vue` already renders a static-map banner + photo cover + "+N" badge.
- `EntryController::detailData` passes `photos` only for `Activity`/`Note`. `EventDetail.vue` renders detail rows + description + url; no photos or map.
- Mapbox token is configured (`services.mapbox.token`).

## Design

### Unit 1 — `timeline_entries.ends_at` (migration + observer)

- Migration: add nullable `timestamp('ends_at')` to `timeline_entries` after `occurred_at`.
- `TimelineEntryObserver::saved`: when the model has an `ends_at` attribute (Event), mirror it onto the entry; null for all other types (they are single-day).
- **Interface:** each timeline row can now carry an end date for range queries. No behaviour change for single-day types.

### Unit 2 — Range-aware day queries

- **Day view** (`/YYYY/MM/DD`, `TimelineController`): an entry matches day `D` when
  `DATE(occurred_at) <= D <= DATE(COALESCE(ends_at, occurred_at))`.
- **On-this-day**: include an entry when today's `(month, day)` falls within `[occurred_at … ends_at]`. Implement the common same-year/within-range case; document that a range spanning a month boundary matches each covered month-day.
- **Unchanged:** the main reverse-chron feed (`/`) and month view — a multi-day event appears **once**, on its start date.
- **Counts/stats unchanged:** they count `timeline_entries` rows; still one per event.

### Unit 3 — `GenerateStaticMap` point support + backfill

- Generalise `GenerateStaticMap` to render a **marker** for a model exposing `lat`/`lng` without a polyline, using a Mapbox static `pin-s+<color>(lng,lat)` overlay at a fixed sensible zoom (e.g. 14), `/auto` disabled in favour of `lng,lat,zoom` centring. Existing polyline path for activities is retained.
- Store the PNG in the model's `map` collection (`singleFile`).
- New Artisan command (e.g. `events:maps`) backfills static maps for every event with coordinates. Idempotent (skips when a `map` already exists unless `--force`).
- **Decision:** generate for all events (cheap, within Mapbox free tier); the card uses photo-first and falls back to this static map when there is no cover photo.

### Unit 4 — `LocationMap.vue` (generic, reusable)

- New Vue component: an interactive OpenFreeMap/MapLibre map with a **single marker** at `{ lat, lng }`, using the `lib/maplibre.js` loader + positron style, mirroring `EntryMap`'s load/teardown lifecycle.
- Props: `lat`, `lng`, `label?` (marker/aria), `zoom?` (default ~14), `heightClass?`.
- **Built generically** (no event-specific coupling) so checkins/places can reuse it later. This is an explicit requirement, not an accident of the events work.

### Unit 5 — `EntryController` wiring for events

- Add `Event` to the photos condition: `$data['photos'] = $model->galleryPhotos();`.
- Add for events: `$data['location'] = ['lat' => …, 'lng' => …, 'address' => …, 'mapsUrl' => …]` where `mapsUrl` is a Google Maps search URL built from the full address (see Unit 7). Null when the event has no coordinates.

### Unit 6 — `EventDetail.vue` (map + gallery + address + multi-day)

- **Photos:** cover + gallery via the existing `ActivityMedia` grid + `Lightbox` (consuming `entry.photos` shaped `{ src, srcset, full }`).
- **Location:** render `LocationMap` with the marker when `entry.location` is present.
- **Address:** an `ExternalLink` "View on Google Maps" using `location.mapsUrl`.
- **Multi-day:** show the range and a badge (Unit 8) when `ends_at` is present.
- Layout: cover/gallery first, then location map, then detail rows, description, address link.

### Unit 7 — Google Maps link

- Build `https://www.google.com/maps/search/?api=1&query=<url-encoded address>`.
- Address source: `meta.address`, falling back to `"{venue_name}, {city}, {country}"`.
- Server-side (in `EntryController`) so it is a plain prop; unit-tested for encoding.

### Unit 8 — Multi-day badge + range formatting

- Shared formatter producing e.g. **"2–4 Jun 2022 · 3 days"** from `occurred_at` + `ends_at`.
- Single-day events (no `ends_at`, or same date): no badge, no range.
- Shown on both the entry view (`EventDetail.vue`) and the timeline card (`FeedItem.vue`).

### Unit 9 — Timeline card (`FeedItem.vue`)

- Ensure events feed the card the same shape activities do: photo cover if present, else the static-map banner.
- Add the multi-day badge to the card.

## Data Flow

```
Event (lat/lng, ends_at, photos, meta.address)
  └─ EntryController::detailData → props: { photos[], location{lat,lng,address,mapsUrl}, multiDay range }
        └─ EventDetail.vue → cover + gallery (ActivityMedia/Lightbox)
                            + LocationMap (marker)
                            + "View on Google Maps" (ExternalLink)
                            + multi-day badge
TimelineEntry (+ends_at mirror)
  └─ day / on-this-day queries: range-aware match
  └─ FeedItem.vue card: photo-or-static-map + multi-day badge
Counts (MoreController): count(timeline_entries) — one row per event, unchanged
```

## Testing

- **Feature:** a multi-day event appears on day 2 of its range at `/YYYY/MM/DD`, and is counted **once** in `MoreController` counts.
- **Feature:** on-this-day includes a multi-day event on a mid-range month-day.
- **Feature:** event detail props include `photos` + `location` (with `mapsUrl`); a single-day event exposes no multi-day range.
- **Unit:** Google Maps URL encoding; multi-day range formatter (single-day → empty).
- **Unit/Feature:** `GenerateStaticMap` builds the correct Mapbox marker URL for a point.

## Out of Scope

- Checkins/places adopting `LocationMap` (the component is built reusable, but wiring checkins is a later, separate change).
- Changing main-feed behaviour for multi-day events (they stay a single appearance in the reverse-chron stream).
- Static-map generation for non-event types beyond the existing activity path.

## Risks / Decisions

- **Mapbox static usage** for 88 events — one-off backfill, within free tier.
- **On-this-day multi-month ranges** — rare (no current event spans a month boundary); handle the simple within-range case and document.
- **Static map for all events vs no-photo only** — generate for all as a reliable fallback; the card prefers a photo when present.
- **`ends_at` on `timeline_entries`** duplicates the event's own `ends_at`, but keeps day-range queries on the mirror (no per-type joins) and keeps single-day types null — a deliberate denormalisation for query simplicity.
