# Activity photo map markers

**Date:** 2026-07-17
**Status:** Approved, ready for planning

## Summary

Place Strava activity photos on the activity detail route map as circular photo
markers, positioned where each photo was taken. Coordinates are derived by
interpolating each photo's capture time against the activity's GPS stream, and
stored on the photo as Media Library custom properties.

## Motivation

Inspiration came from another personal site (Google Maps based) showing photos
pinned along a walk route. Our stack differs: `EntryMap.vue` is MapLibre with a
decoded polyline, and there is no Google Maps in the codebase. The idea carries
over; the implementation does not.

Today `ActivityMedia.vue` renders the route map, then a separate square-thumbnail
grid below it, each thumbnail opening a `Lightbox` by index.

## Findings that shaped the design

These were verified against the live API and the local database, not assumed.

**Strava photos carry no location.** The `/activities/{id}/photos` payload
contains `unique_id`, `athlete_id`, `activity_id`, `activity_name`, `caption`,
`type`, `source`, `status`, `uploaded_at`, `created_at`, `created_at_local`,
`urls`, `sizes`, `default_photo`, `cursor`, `is_strava_strength`. There is no
coordinate field.

**EXIF is stripped.** A stored Strava photo
(`255FA561-8F66-4E8F-9B47-D3BE5F3443C8.jpg`) exposes only `FileName`,
`FileDateTime`, `FileSize`, `FileType`, `MimeType`, `SectionsFound`, `COMPUTED`.
No GPS block. Coordinates cannot be read off the image.

**The streams endpoint works and is sufficient.** `GET /activities/{id}/streams`
with `keys=time,latlng&key_by_type=true` returned 200 with `time`, `latlng` and
`distance`. The sample walk had 2054 points, `time` as elapsed seconds from
start (`[0,1,2,3,4...]`), `latlng` as `[[51.339762,-0.092839], ...]`.

**Timestamps need no timezone maths.** The activity summary carries `start_date`
in UTC (`"2026-06-29T20:45:37Z"`) and photo `created_at` is UTC. The offset is a
straight subtraction. We never touch our local wall-clock `occurred_at` or its
per-entry timezone. `StravaPhotos::resolveTargets()` already pages these
summaries to read `total_photo_count`, so `start_date` costs nothing extra.

**Photo volume is small.** 103 activities have photos, 134 photos in total, max 4
on any single activity, 81 activities have exactly one. Clustering and
spiderfying are therefore out of scope.

**Custom properties are available and unused.** `spatie/laravel-medialibrary`
11.23.1; the `attachments` table already has the `custom_properties` JSON column
(`2026_06_24_211106_create_attachments_table.php:26`). Nothing in the app uses
custom properties yet, so this sets the convention.

## Decisions

| Decision | Choice | Why |
|---|---|---|
| Grid vs markers | Keep both; markers are additive | Unplaceable photos always have somewhere to live |
| Marker click | Open the existing Lightbox | One interaction model shared with the grid |
| Scope | Activity detail map only | `RouteThumb`/`RouteHeatmap` are too small for photo bubbles |
| Coordinates | Interpolate at sync, store on photo | Deterministic; nothing at render time; map stays dumb |
| Backfill | Separate `strava:photo-locations` command | Re-derivable without re-downloading images |

Rejected: storing the full stream per activity (~5MB across 103 activities in
SQLite, for a feature needing two floats per photo; revisit if per-point features
like splits are wanted); fetching streams at render time (puts a rate-limited
external call in the page request path).

Accepted cost of interpolating at sync: it is lossy. Changing the interpolation
logic means re-fetching streams. The dedicated backfill command makes that cheap.

## Backend architecture

Flow: `StravaPhotos` (orchestration, rate limiting) -> `Strava` (HTTP) ->
`LocatePhotoOnRoute` (pure maths) -> `SyncStravaPhotos` (persistence).

**`App\Services\Strava`** gains one method beside `activityPhotos()`:

```php
public function activityStreams(int|string $id, array $keys = ['time', 'latlng']): ?array
```

**`App\Actions\LocatePhotoOnRoute`** is pure: no Strava, no HTTP, no Eloquent.

```php
public function __invoke(
    CarbonImmutable $capturedAt,
    CarbonImmutable $activityStart,
    array $timeStream,
    array $latlngStream,
): ?array
```

Computes the offset in seconds, returns `null` if it falls outside the stream's
bounds, binary-searches `$timeStream` for the nearest index, returns
`$latlngStream[$index]` as `[$lat, $lng]`. When an offset sits exactly between
two samples, the earlier point wins, so the result is deterministic.

**Coordinate order is a known trap.** Strava's `latlng` stream and this action
both use `[lat, lng]`. MapLibre's `setLngLat()` and the decoded polyline in
`EntryMap` both use `[lng, lat]`. Storage keys are named (`latitude`,
`longitude`) so the ambiguity cannot survive into the database, and the flip
happens once, at the `setLngLat()` call site. A swapped pair puts UK markers in
the Atlantic off Africa, which is at least obvious on sight.

**`StravaPhotos::resolveTargets()`** returns activity-plus-`start_date` pairs
rather than a bare `Collection<Activity>`; it is the only place holding the
summary.

**`StravaPhotos::fetchPhotos()`** fetches streams once per activity beside the
existing `activityPhotos()` call. This doubles requests per activity, so the
existing 95-per-15-minute limiter trips once during a full 103-activity backfill
and pauses. It already handles that.

**`SyncStravaPhotos`** takes the stream and start, calls `LocatePhotoOnRoute` per
photo, and adds to the existing chain:

```php
->withCustomProperties(['latitude' => $lat, 'longitude' => $lng])
```

omitting the properties entirely when interpolation returns `null`.

It also always records `captured_at` (the photo's `created_at`) as a custom
property, whether or not the photo could be located. Stored media otherwise keeps
no record of capture time, and without it `strava:photo-locations` could not
re-derive a position without re-fetching the photos endpoint, which would defeat
its purpose.

Consequence for the first run: the 134 existing photos predate `captured_at`, so
they must be re-downloaded once via `strava:photos --force` before the backfill
can locate them. Every re-derivation after that is free, which is the point of
the trade-off.

**`strava:photo-locations`** backfills coordinates onto existing media via
`setCustomProperty()` + `save()`, fetching streams but never re-downloading
images. Reuses `LocatePhotoOnRoute`.

It needs `start_date` as well as streams, and must not call `Strava::activity()`
per activity for it (103 extra requests against a 95-per-15-minute limit). It
pages the activity list once, exactly as `resolveTargets()` does, to build a
`source_id => start_date` map, then fetches streams per activity. At 200 per
page that is roughly 1 request per 200 activities, versus one per activity.

Since both commands need the same "page summaries, keep `start_date`, match to
local activities" logic, it is extracted into a shared private helper or a small
action rather than duplicated. Implementation planning should settle which; the
requirement is that it exists in one place.

## Frontend architecture

**`HasAttachments::galleryPhotos()`** gains two keys per photo, `null` when the
photo was never located:

```php
'latitude' => $media->getCustomProperty('latitude'),
'longitude' => $media->getCustomProperty('longitude'),
```

Added to the shared trait rather than `Activity` so one photo shape holds across
all entry types. Notes and events get `null` today and inherit the behaviour free
if their photos are ever located.

**Index preservation is load-bearing.** `Lightbox` opens by index into the
`photos` array and `ActivityMedia` already emits `open(index)` from the grid.
Markers must carry the index in the *original unfiltered* array. Map before
filtering:

```js
photos.map((photo, index) => ({ ...photo, index })).filter(p => p.latitude !== null)
```

Filtering first would silently open the wrong photo, and would look correct
whenever an activity has a single located photo.

**`EntryMap.vue`** takes a `photos` prop (default `[]`, ignored when empty) and
emits `open-photo` with that index. **`ActivityMedia.vue`** passes `:photos`
down and forwards the event into the same `$emit('open', index)` the grid uses,
so both paths reach an identical Lightbox call.

**`Components/Maps/PhotoMarker.vue`** is a circular `<button>` wrapping the
thumbnail, using the existing `card` srcset with `sizes="44px"` so a 44px circle
does not pull a 640px image. A real button, keyboard-focusable, `aria-label`
naming the photo ("View photo 2"), a focus-visible ring mirroring hover, `img`
with `alt=""` since the button carries the label. Standard scale utilities
(`size-11`, `ring-2`), no bracket values, route colour as the ring.

Wiring: render `PhotoMarker` components into a hidden container with refs, then
`new maplibregl.Marker({ element: el }).setLngLat([lng, lat]).addTo(map)` after
load. MapLibre relocates the node into its overlay; Vue keeps owning rendering.

Two consequences fall out. Markers are DOM overlays rather than style layers, so
unlike `addRouteLayer` they survive `setStyle` and need no re-add on theme
toggle. And because coordinates come from the route, every marker sits on the
route, so the existing `fitBounds` frames them with no change.

## Error handling

Governing rule: **a stream problem must never cost us a photo.** Photo download
and coordinate lookup are independent; the photo always wins.

- **Streams request fails** (rate limit, network, outage): warn, store photos
  without coordinates. `fetchPhotos()` currently does `continue` when
  `activityPhotos()` returns null; the stream fetch must not copy that pattern.
- **Activity has no GPS** (indoor, manual entry): the response omits `latlng`
  entirely. A treadmill run returns `time,distance` only. `$streams['latlng']['data'] ?? null`
  must be a real check. No latlng means no markers; photos still render.
- **Photo timestamp outside the activity window**: Instagram-sourced photos
  (`source: 2`) and photos added later. Returns `null`, photo shows in the grid
  as today. Expected traffic, not an error, so no warning noise.
- **Camera clock skew**: a wrong device clock still lands inside the stream and
  places a marker confidently in the wrong spot. Undetectable from the data;
  accepted. The one failure mode that is silently wrong rather than absent.
- **Empty or single-point streams**: guard before the binary search.

## Testing

Unit, `LocatePhotoOnRoute` (highest value; pure, holds all the maths): exact
match returns that point; between two samples returns the nearest; an exact
midpoint returns the earlier point; before start returns `null`; after end
returns `null`; empty stream returns `null`; single-point stream does not break
the binary search. One test asserts the returned pair is `[lat, lng]` order, so
the trap above is pinned by a test rather than a comment.

Feature, `SyncStravaPhotos`: with `Http::fake()`, a located photo lands
`latitude`/`longitude` in `custom_properties`; an out-of-window photo stores the
image with no coordinate keys.

Feature, `StravaPhotos`: the regression that matters most. Streams faked to 500,
assert the photo is still stored without coordinates. Streams without a `latlng`
key (indoor) stores photos fine.

Feature, `strava:photo-locations`: coordinates written to existing media, and
`Http::assertNotSent()` against the CloudFront image host proves no re-download.

Feature, `galleryPhotos()`: exposes `latitude`/`longitude`, `null` when
unlocated.

Index preservation: dedicated test for the sharp edge. An activity with photo 0
unlocated and photo 1 located produces exactly one marker carrying index `1`.

Browser, following `tests/Browser/PhotoGridTest.php`: markers render on the
activity detail map and clicking one opens the Lightbox. Asserts rendered DOM
elements (marker buttons by test id, the Lightbox dialog), never text, because
`latitude`/`longitude` now sit in the Inertia props JSON and a text assertion
would pass whether or not any marker rendered.

## Out of scope

Marker clustering and spiderfying (max 4 photos on any activity). Markers on
`RouteThumb` or `RouteHeatmap`. Map popups as a second viewing mode. Locating
photos for notes and events (the trait change leaves the door open).
