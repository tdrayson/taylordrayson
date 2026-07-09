# Events Data Type Restructure — Design

**Date:** 2026-07-06
**Status:** Draft for review

## Context & goal

The `event` timeline type exists end-to-end (model, table, factory, seeder, `TypeRegistry`, timeline card, `EventDetail.vue`) but its schema predates a real, enriched dataset of 90 attended events (concerts, theatre, musicals, magic, comedy, circus, immersive, sport, festivals, conventions, conferences, exhibitions, dance). That dataset — built from calendar extraction + web research + Google geocoding, then hand-curated — has fields the current table cannot hold: an end/all-day (multi-day conventions & festivals span days), a richer category set, the "who puts it on" company, who I went with, a personal note, a ticket/seat detail, an official link, and canonical geocoded location.

**Goal:** evolve the existing `event` type to hold this structure, and import the real dataset as the seed data. This is an *evolution of an existing type*, not a new one.

## Scope

**In scope**
- Migration to evolve the `events` table.
- `Event` model updates (fillable, casts, `card()` unchanged in shape).
- Expanded category taxonomy (data-driven; minimal registry impact).
- An importer command that loads the curated CSV into the table.
- Switch event seeding to the real import; keep the factory for tests.
- **Minimal** `EventDetail.vue` / factory / seeder updates so nothing breaks with the renamed/removed columns.
- Tests for the importer and the model/timeline card.

**Non-goals (explicit follow-ups)**
- Full `EventDetail.vue` visual redesign (map render, multi-day range styling, company/seat/link presentation). This spec only keeps the detail view working against the new columns.
- `/api/v1/events` endpoint + MCP tool (would follow the Flight reference pattern).
- Per-category colours — `event` stays a single timeline colour; category is a sub-type/taxonomy, exactly like activity sub-types.

## Data model changes

Current `events` columns: `occurred_at, type, name, venue_name, address, city, country, latitude, longitude, ticket_price, notes, timezone`.

Migration (`database/migrations/`):

| Change | Column | Notes |
|---|---|---|
| **add** | `ends_at` (timestamp, nullable, after `occurred_at`) | end of multi-day / timed events |
| **add** | `all_day` (boolean, default false) | drives all-day display |
| **add** | `company` (string, nullable) | act / troupe / producer / club that puts it on — first-class so it can be labelled and (later) filtered/grouped |
| **add** | `url` (string, nullable) | official / ticket / more-info link |
| **add** | `meta` (json, nullable) | loose, display-only secondary details: `seat`, `place_id`, `formatted_address`, with room to grow (booking ref, row, rating, setlist…). Matches the existing `meta` JSON pattern on `Flight`, `Activity`, `Media`. |
| **rename** | `notes` → `description` (text, nullable) | **standardises** with `appearances`, `checkins`, `projects` which all use `description`; events was the outlier |
| **drop** | `address` | superseded by `meta.formatted_address` |
| **drop** | `ticket_price` | no content, not relevant |

`latitude`/`longitude` **stay real (decimal) columns** — they're typed and used for maps/queries; only the geocoding *extras* (`place_id`, `formatted_address`) live in `meta`. `timezone` already exists (added in `2026_07_04_202516_add_timezone_to_timeline_tables.php`). `occurred_at` stays the **start**, stored as local wall-clock (existing convention); `ends_at` is the end. All-day events store `occurred_at` at local 00:00 with `all_day = true`.

**Why `meta` for `seat`/geo-extras, real columns for the rest** — mirrors how `Flight` already works: first-class attributes that are displayed prominently, sorted, or filtered are real columns (`company`, `url`, `venue_name`, `city`, `latitude`…); loose secondary bits read out via `data_get($this->meta, 'seat')` live in `meta`. This keeps the schema honest without a migration every time a small quirky field is added.

**Model** (`app/Models/Event.php`): update `#[Fillable]` to the new set (`occurred_at, ends_at, all_day, type, name, company, venue_name, city, country, latitude, longitude, url, description, timezone, meta`); cast `ends_at => datetime`, `all_day => boolean`, `meta => 'array'`. `card()` shape is unchanged (subtitle stays `venue_name, city`); a richer card is out of scope here.

## Category taxonomy

`TypeRegistry` already builds the events taxonomy from **distinct `type` values** (`self::column('type', 'Type', …)`), so new categories require **no registry change** — they appear on `/events/{slug}` automatically once data carries them.

Stored `type` values are **kebab-case** (app convention): `theatre, musical, magic, comedy, concert, circus, immersive, sport, festival, convention, conference, exhibition, dance, other`. The importer lowercases/kebab-cases the CSV `category` into `type`. The factory's sub-type list is widened to this set so seeded/test data is representative.

## Content semantics (encoded by the importer / carried in the CSV)

- **description** — a short *personal* note, never generic/AI prose: companions mapped to relationships (**Clare→mum, Gordon→dad, Grandad→grandad**; everyone else by first name, Taylor omitted), plus occasion ("Christmas present from mum", "My treat"). Sanderstead Dramatic Club pantos → "Helped backstage." (attended but volunteering); dad's ATG plays → "Watched dad perform." Blank when nothing real is known.
- **company** — the act / troupe / producer / club that puts it on. Populated for self-titled acts (Penn & Teller, Blue Man Group), producing orgs (National Theatre), and local clubs (Sanderstead Dramatic Club, ATG, Parlour Players). **Blank** for an individual creator who wasn't in it (writer/director/producer) and for sport (teams already in the `name`).
- **name** — always the actual event name (show/panto title, "Team A vs Team B (Competition)" for fixtures). Authoritative from the curated CSV.
- **seat** — open text, kept as a deliberately fun/quirky detail; stored in `meta.seat`.
- **geo** — `latitude`, `longitude` are real columns; `formatted_address` and `place_id` (from Google Geocoding) live in `meta`; `venue_name` is the friendly name.
- **timezone** — `Europe/London` unless the venue is abroad (per-row IANA zone); times are local wall-clock.

## Source dataset & importer

The curated file (currently `Taylor's Events - events_structured.csv`, 90 rows) lands at **`data/events.csv`** — matching the existing `data/*.csv` seed-source convention (flights, podcasts).

**Shipped approach (reconciled):** rather than a bespoke command, `data/events.csv` is produced already in DB-shaped columns (header exactly matches `Event`'s fillable; `occurred_at`/`ends_at` as `Y-m-d H:i:s`; `all_day` `1`/`0`; `category` kebab-cased into `type`; `seat`/`place_id`/`formatted_address` packed into the `meta` JSON cell), and loaded by the **existing generic `import:csv {file} {type}`** command (already registered for `event`; it maps headers→fillable and `json_decode`s `array`-cast columns). The one-time date/time/`meta` transform is a data-prep script, not runtime code.
- `data/events.csv` is registered in `import:all`'s list (alongside flights/activities/…).
- **Not idempotent** (deliberate, matches every other type): `import:csv` `create()`s rows and is designed to run against a fresh DB (`import:all --fresh` runs `migrate:fresh` first). No bespoke upsert.

## Seeding

**Shipped approach (reconciled):** the real-data path in this app is `import:all` (where flights/activities' real `data/*.csv` load), so the 90 curated events load there via the newly-registered `data/events.csv` — not by rewiring `DatabaseSeeder`. `DatabaseSeeder::seedEvents()` keeps generating synthetic random events for local demo-timeline density (with covers/photos); the widened `EventFactory` also serves tests. Same outcome (real events available), less churn.

## Testing (Pest)

- **Importer feature test**: fixture CSV → asserts row count, field mapping, a multi-day event (`ends_at` set), an all-day event (`all_day = true`), kebab-cased `type`, and idempotency (second run adds nothing).
- **Model/timeline test**: an event renders a timeline card with the expected `type`/`accent`; taxonomy page `/events/{category}` resolves for a new category (e.g. `sport`).
- Confirm nothing referencing the dropped `ticket_price` / renamed `notes` remains (model, `EventDetail.vue`, factory, seeder).

## Follow-ups (separate specs)

1. `EventDetail.vue` richer redesign — multi-day range, company, map, seat tag, ticket link.
2. `/api/v1/events` + MCP tool (Flight-pattern controller / resource / requests / actions).
