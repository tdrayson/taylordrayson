# Merge Events into Checkins — Design Spec

> Date: 2026-07-07. Follows the events single-view + multi-day work. Supersedes the standalone `event` type.

## Goal

Retire the `event` type and fold everything it holds into `checkin`, so the site has **one** place-and-time presence type. An "event" is not an IndieWeb h-event and not an RSVP; it is an attendance log, which is checkin-shaped. Merging removes the duplication/gap problem (a checkin at a venue and an event at the same venue being two entries) and fills the multi-year gap when Swarm went unused.

## Decisions (locked)

1. **Checkins only.** Retire the `event` type *and* the "places" naming. The type is `checkin`; its archive lives at `/checkins` (was `/places`); nav label "Checkins".
2. **Pure checkin.** The **venue** is the card/title; the **occasion** ("Birmingham City vs Blackpool", "WordCamp Europe 2024") lives in `description`. No separate occasion/title column.
3. **Accent colour: purple.** Repoint `--color-checkin` to the current event purple `hsl(270 55% 52%)`; retire `--color-event` (the teal `hsl(170 55% 40%)` fought the activity accent).
4. **Notable "events" = a curated list, later.** The ~86 bigger occasions stay special via a hand-curated list that *references* checkins (a future Lists/`featured` feature), not a separate type. Out of scope for this merge.
5. **Strip Swarm gamification.** Drop `Score`/coins/points messages. Keep `Description`, `Checkin URL` (→ `source_id`), `Photo`, venue, address, lat-lon, and `Mayor`.

## Current State (verified)

- `checkins` table: `occurred_at, venue_name, category, address, city, county, country, latitude, longitude, description, is_mayor, source, source_id, timezone` + timestamps. `Checkin` model has `platform_url` accessor (`swarm` + `source_id` → `swarmapp.com/checkin/{id}`), `card()` (title = venue_name, subtitle = category + city, accent `checkin`, icon `map-pin`). **0 checkins, 87 events.**
- `Event` fields: `occurred_at, ends_at, all_day, type, name, organiser, venue_name, city, country, latitude, longitude, url, description, timezone, meta` (json: `address`, `seat`, `place_id`) + media collections `cover`/`photos`/`map`.
- `TimelineEntry` mirror already carries `ends_at` and the generic `scopeCoveringDate` / `scopeCoveringAnniversary` (built for the event multi-day work) — reused as-is for checkins.
- `TypeRegistry`: `event` = `column('type')` taxonomy at `/events`; `checkin` = `column('category')` taxonomy but wrongly slugged/labelled `places`/`Places`.
- `EntryController` builds the rich event detail payload (photos, `location{lat,lng,address,mapsUrl}`, `range`). `EventDetail.vue` renders map + gallery + chip + multi-day badge + Google link. `CheckinDetail.vue` is a plain `DetailList` table + mayor pill + note.
- `GenerateLocationMap` + `events:maps` produce static maps (now purple `pin-l`). `LocationMap.vue` is already generic (built reusable for checkins).
- `foursquare:import {--limit=0}` fetches checkins from the Swarm/Foursquare API via the `Foursquare` service. `ImportCsv` is insert-only (not idempotent).
- WP export `Checkins-Export-2026-July-07-1230.csv`: 763 rows (partial, stale, WP map images). Account has ~2,381 — the API is the real source.

## Schema Changes

Add to `checkins` (one migration):
- `ends_at` (nullable timestamp, after `occurred_at`) — multi-day span.
- `url` (nullable string) — reference link (the event's own site, e.g. wordcamp.org). Distinct from `platform_url` (the Swarm permalink).

Everything else already exists. No `meta` column (seat folds into `description` — see mapping). `all_day` is not carried over.

`Checkin` model gains: `ends_at`/`url` in `#[Fillable]` + casts (`ends_at` → datetime); a `dateRange()` method (moved verbatim from `Event`, with the `label`/`long`/`days` shapes); `card()` enriched to emit `range` + photo-or-static-map `meta.map` like the event card does now.

## Event → Checkin Field Mapping (migration)

| Event | → Checkin |
|---|---|
| `occurred_at`, `ends_at` | same |
| `type` | `category` = `Str::headline(type)` (Convention, Show, Sport, Conference, …) |
| `venue_name`, `city`, `country`, `latitude`, `longitude`, `timezone` | same (`county` → null) |
| `meta.address` | `address` |
| `name` | lead line of `description` (skipped when `name === venue_name`) |
| `description` (note) | appended after the name |
| `organiser` (when `!== name`) | appended to `description` |
| `meta.seat` | appended to `description` as `Seat: X` |
| `url` | `url` |
| — | `source = 'manual'`, `source_id = null` (no Swarm permalink) |
| media `cover`/`photos`/`map` | re-attached to the checkin's collections |

Example: Shrek → venue_name "Theatre Royal Drury Lane", description "Shrek the Musical. Just met Amanda Holden at Stage Door." Card title = the venue; the curated list (later) is what re-elevates the occasion.

`timeline_entries` rows re-point from `App\Models\Event` to `App\Models\Checkin` (same `occurred_at`/`ends_at`), so counts and day/on-this-day range queries carry over unchanged.

## Type Retirement

- **Routes:** `/places*` → `/checkins*`; `/events*` removed (301 → `/checkins`).
- **TypeRegistry:** delete `event`; change `checkin` slug/label `places`/`Places` → `checkins`/`Checkins`; taxonomy stays `column('category')`.
- **Nav (`/more`) + MoreController:** drop the `events` and `places` rows, add one `checkins` row with its live count.
- **Colour:** `--color-checkin` → purple; delete `--color-event`; repoint any `event` accent usages.
- **Components:** delete `EventDetail.vue` (its map/gallery/chip/multi-day/Google-link logic moves into `CheckinDetail.vue`); `FeedItem.vue` event branch → checkin; the type chip links to `/checkins/{category-slug}`.
- **Actions/commands:** `events:maps` → `checkins:maps`; `GenerateLocationMap` unchanged (already generic).
- **Model/table:** delete `Event` model + `events` table (after data migration verifies).
- **Payload:** `EntryController` builds the rich `location`/`photos`/`range` block for `Checkin` instead of `Event`.

## CheckinDetail.vue (absorbs the event view)

Category chip (linkable to `/checkins/{category}`), then multi-day range line (full-text, from `dateRange().long`, single-day → none), `LocationMap` (venue pin, purple), venue/city caption + "View on Google Maps", photo cover + gallery (`ActivityMedia` + `Lightbox`), the `description` note, the Mayor pill when set, and "View on Swarm" (`platform_url`) + "More info" (`url`) links when present.

## Swarm Import + Dedupe

1. **Fetch** all checkins via `foursquare:import` (API, `--limit=0`). Persist: venue, address (city/county), lat-lon, `occurred_at`, `description`, `is_mayor`, `source='swarm'`, `source_id`, plus one photo when present. Drop `Score`/coins.
2. **Backfill** from the WP CSV only for checkins the API no longer returns (match on Swarm id).
3. **Dedupe** the 87 migrated event-checkins (`source='manual'`) against the Swarm set: a match is **same calendar day + same venue** (name match or coordinate proximity < ~150 m). On a match, the **rich event wins** — keep its row, graft the Swarm `source_id` (→ `platform_url`) and any photo it lacks; do **not** create a second row.
4. **Review, don't guess.** Uncertain matches are logged to a review list, not auto-merged. Log any silent caps.

## Testing

- **Feature:** migrated event → checkin exposes photos + `location` (with `mapsUrl`) + `range`; a single-day checkin has no range; counts unchanged after migration.
- **Feature:** multi-day checkin (ex-WordCamp) still appears on each covered day at `/YYYY/MM/DD` and counts once.
- **Unit:** event→checkin field mapping (name/note/seat/organiser folding; `name === venue_name` skip); `dateRange()` `label`/`long`.
- **Feature:** dedupe keeps the rich checkin and grafts the Swarm URL/photo on a same-day/same-venue collision; leaves non-matches untouched.
- **Feature:** `/checkins` and `/checkins/{category}` resolve; `/events` 301s to `/checkins`.

## Phases (for the plan)

0. **Commit the current event work** as a clean checkpoint (restore point before the type is dismantled).
1. Schema + `Checkin` model (`ends_at`, `url`, `dateRange`, enriched `card`).
2. UI/routing: `CheckinDetail` absorbs the rich view; `FeedItem`; colour→purple; TypeRegistry/nav/routes rename `places`→`checkins`, remove `event`.
3. Data migration: 87 events → checkins (fields + media + `timeline_entries` re-point + regenerate static maps purple); delete `Event`/`EventDetail`/`events:maps`.
4. Swarm import + dedupe pipeline.

## Out of Scope

- Curated "notable events" list/Highlights over checkins (future).
- A reusable `Place`/venue entity (checkins keep denormalised place fields, as events did).
- Swarm gamification (coins, scores, streaks).

## Risks / Decisions

- **Pure-checkin title.** WordCamp/conventions headline as their venue, not the occasion. Accepted for simplicity; the curated list restores prominence later.
- **Lossy fold.** `organiser`/`seat` collapse into `description` prose rather than structured columns. Reversible if we later want them back.
- **Fuzzy dedupe.** Venue-name variants + coordinate drift mean same-day/same-venue matching needs a manual review gate, not blind merging.
- **Insert-only `ImportCsv`.** The migration is a bespoke command (Event→Checkin), not a CSV re-import, to avoid duplicates and preserve media/timeline links.
