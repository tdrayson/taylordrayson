# Timezone Handling: Local Wall-Clock + Per-Entry Timezone

**Status:** Design / spec (chosen approach: Option A). Touches stored data (Strava activities only) and time display app-wide. Review before implementation.

## Goal

Show every entry at the local time it actually happened, with its UTC offset (e.g. `Wed 1 Jul 2026, 9:30am +01:00`), identically for every viewer, instead of the current behaviour where the displayed time shifts to whoever is looking at the page.

## The problem

`occurred_at` is stored as a bare wall-clock string with no timezone. The app serializes it as `...+00:00` and the Vue formatters do `new Date()` + local getters, so the time renders in the **viewer's** browser timezone. Same entry, different time per viewer.

## Chosen model (Option A): local wall-clock + timezone

`occurred_at` stays the **local wall-clock time the entry happened** (naive, no zone), and each entry gains a **timezone** (IANA) describing what zone that wall-clock is in. The true UTC instant is derivable from the pair when needed.

**Why not store UTC everywhere:** `occurred_at` is overloaded in this codebase, it also drives entry permalinks (`/{Y}/{m}/{d}/{slug}`), day-grouping (timeline, calendar, day/month/year), and date-level aggregation (daily calories, sleep). Those are all *local-day* concerns. Converting to UTC would shift near-midnight and abroad entries onto a different day, changing some permalinks and regrouping days, and would break date-level rows (a calorie at local midnight jumps to the previous day). Local wall-clock keeps all of that working untouched. Most data is already local; only Strava activities are stored in UTC and need converting.

### Timezone source per type

Each `Timelineable` exposes `timezone(): ?string`; a resolver falls back to `config('app.home_timezone')` (`Europe/London`) when null.

- **Flight** -> `departure_timezone` (the zone `occurred_at` is the departure in).
- **Activity** -> new `activities.timezone` column (from Strava).
- **All others** (calorie, sleep, event, appearance, article, note, project, fuel, podcast, media, checkin) -> null -> `Europe/London`. Per-type columns can be added later through the same contract.

### Display (computed server-side, rendered verbatim)

Because `occurred_at` is already the local wall-clock, the visible local time is just `occurred_at` formatted directly, no conversion. The timezone is only needed to produce the **offset** and the machine-readable instant. All of this is computed server-side with Carbon (DST-aware via IANA) and passed to the front end as plain strings, so the client never does `new Date()` timezone math:

- `time` , local clock, e.g. `9:30am` (`occurred_at->format('g:ia')`).
- `label` , full local date+time, e.g. `Wed 1 Jul 2026, 9:30am`.
- `offset` , e.g. `+01:00` (`CarbonImmutable::parse($occurred_at, $timezone)->format('P')`).
- `iso` , the true instant for the `<time datetime>` attribute, e.g. `2026-07-01T09:30:00+01:00` (`...->toIso8601String()`).

Surfaces:
- **Single-entry view:** local date+time **with offset** shown inline; `<time :datetime="iso">`.
- **Timeline feed:** visible text is the local `time` (no offset); the `<time>` `title` (hover) is the full `label` **with offset**. `FeedItem.vue` already has `<time :datetime :title>` with `dt-published`, so this is wiring values, not new structure.
- **Detail components** that print times (flight depart/arrive, etc.) use the server-provided local strings + offset, not client `new Date()`.

## Data changes

- **Add `activities.timezone`** (nullable string) migration.
- **Add `config('app.home_timezone')`** = `Europe/London` (env `APP_HOME_TIMEZONE`, config only).
- **Activity backfill (network):** a command pages all Strava summary activities and, per activity (matched by `platform_id`), sets `occurred_at = start_date_local` and `timezone` = the IANA name parsed from Strava's `timezone` field (e.g. `(GMT+00:00) Europe/London` -> `Europe/London`). This converts the ~1,463 existing activities from UTC to local wall-clock and records their zone. Activities with no Strava match (rare) keep their value and default to the home zone.
- **StravaSync going forward:** store `occurred_at = start_date_local` (not `start_date`) and `timezone`; add `timezone` to its CSV headers.
- **No migration for flights or other types** , their `occurred_at` is already local wall-clock and stays as-is.

## Backend payload changes

A small helper (e.g. `App\Support\LocalTime` or a method on the timeline payload) turns `(occurred_at, timezone)` into `{time, label, offset, iso}`. Wired into:
- `EntryController` (single entry) , add `offset` + `iso` (and a full label) to the payload.
- `App\Actions\BuildTimelineFeed` , it already emits `time` and `datetime`; add `offset` and a full `label` for the hover title; `datetime` becomes the offset-aware `iso`.
- Detail-component payloads that show times (flights via `card()` `departed_local`/`arrived_local`, etc.).

## Frontend changes

- `Entry.vue` , render the server `label` + `offset` inline; `<time :datetime="iso">`. Stop using `dateTime(new Date())`.
- `FeedItem.vue` , `title` = server `label` + `offset`; keep visible `time`; `:datetime="iso"`.
- `resources/js/lib/format.js` , the `new Date()`-based date/time helpers stop being used for entry timestamps (kept only for any non-entry, viewer-relative use, if any). Entry times come pre-formatted from the server.

## CSVs

`activities.csv` gains a `timezone` column and its `occurred_at` values change from UTC to local (via the backfill). Re-run `export:all` afterwards and re-verify DB/CSV parity. Other CSVs are unaffected.

## Phasing (the implementation plan will detail each)

1. `config('app.home_timezone')` + a timezone resolver + `Timelineable::timezone()` default (null) + the `LocalTime` formatter helper (`{time,label,offset,iso}`).
2. `activities.timezone` migration + `Activity::timezone()` + `Flight::timezone()` (returns `departure_timezone`).
3. Strava activity backfill command (UTC->local + timezone) and `StravaSync` update (store `start_date_local` + `timezone` + CSV header).
4. Backend payloads: `EntryController`, `BuildTimelineFeed`, and time-bearing detail payloads emit `{time,label,offset,iso}`.
5. Frontend: `Entry.vue` (inline offset), `FeedItem.vue` (hover label+offset), stop client-side `new Date()` time math.
6. Re-export CSVs; verify DB/CSV parity.

## Risks

- The activity backfill is a network operation and mutates `occurred_at` for ~1,463 rows; mitigated by git-tracked CSVs and a re-export afterwards. It is re-runnable (idempotent: matches by `platform_id`).
- Permalinks, day-grouping, and daily totals are deliberately untouched, no URL changes.
- Activities that aren't returned by the Strava list (manually added, deleted on Strava) stay UTC and fall back to the home zone for display; acceptable and rare.

## Out of scope (deferred)

- Per-type timezone columns for manual entries abroad, calories, sleep, podcasts, media, checkins. They default to `Europe/London` now; addable later via the same `timezone()` contract.
- Any move to true-UTC storage (Option B) , explicitly not chosen.
