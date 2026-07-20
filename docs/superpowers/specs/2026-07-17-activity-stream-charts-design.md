# Activity stream charts: elevation, speed & heart rate synced to the route

**Date:** 2026-07-17
**Branch:** `feat/activity-stream-charts` (off master)

## Problem

Activity pages show a route map but none of the rich per-point data Strava records.
We want three stacked profile charts (heart rate, elevation, speed) under the route map,
with a single vertical cursor synced across all three, and a dot that moves along the
route as you scrub — bidirectionally (hover a chart moves the map dot; scrub the route
moves the cursor), on mouse or touch.

## Decisions (from brainstorming)

- **One aligned source.** Fetch `time, distance, latlng, altitude, velocity_smooth,
  heartrate` from Strava's streams endpoint in a single request. They come back
  index-aligned, so all series share one time base and the cursor lines up exactly.
- **Strava is the HR source when it has streams**, with the existing Apple Health import
  (`ImportHealthHeartRate`) kept as a **fallback** for activities with no Strava HR.
- **Storage mirrors the existing `heart_rate` column**: downsampled, time-keyed JSON
  columns on `activities`. New columns `altitude`, `speed`, `track`. CSV inclusion is
  automatic and its size is irrelevant (see [[csvs-are-temporary]]).
- **Three separate stacked charts** (HR, elevation, speed), not combined; **shared
  vertical hover line**; **bidirectional** scrub; hover/pointer driven (no clicks);
  touch-friendly.
- **Independent feature**, off master; unrelated to the fuel (#50, merged) / maps (#51)
  branches, though it touches `EntryMap.vue` (as does the concurrent
  `feat/activity-photo-map-markers` — reconcile at merge).

## Data model

New nullable JSON columns on `activities`, all downsampled and time-keyed to match
`heart_rate` (which stores `[{time, bpm}]` absolute wall-clock timestamps):

| Column | Shape | Source | Units |
|---|---|---|---|
| `heart_rate` (exists) | `[{time, bpm}]` | Strava `heartrate` (preferred) / Apple Health (fallback) | bpm |
| `altitude` (new) | `[{time, value}]` | Strava `altitude` | metres |
| `speed` (new) | `[{time, value}]` | Strava `velocity_smooth` | m/s (display converts) |
| `track` (new) | `[{time, lat, lng}]` | Strava `latlng` | degrees |

- **Time base:** Strava streams are seconds-from-start; store as absolute timestamps
  (`activity start + offset`) so every series (including the Apple-Health HR) shares an
  x-axis and can be co-indexed.
- **Downsampling:** reuse/extract the `downsample()` helper already in
  `ImportHealthHeartRate` (same cap), and downsample all four series by the **same
  sampled indices** so they stay aligned point-for-point.
- **x-axis = elapsed time** (derived from the timestamps), which keeps all series and the
  `track`-driven map dot on one shared index without needing a stored distance series.

## Components

### 1. Strava streams fetch + storage

- `App\Services\Strava::activityStreams()` already exists; call it with keys
  `['time','distance','latlng','altitude','velocity_smooth','heartrate']`.
- `StravaSync`: after fetching the detailed activity, fetch its streams, downsample the
  aligned series, and populate `altitude`, `speed`, `track`, and `heart_rate` (from Strava
  when the `heartrate` stream is present). Only stores a series when Strava returns it, so
  non-GPS / sensor-less activities simply get null columns.
- **HR precedence:** Strava-sourced HR wins for stream activities. `ImportHealthHeartRate`
  must not clobber a Strava-sourced HR series — it fills `heart_rate` only where still
  null (or is ordered to run before the streams backfill). The plan pins the exact guard.

### 2. Backfill command

`strava:streams {--force}` — fetches and stores streams for every activity with a route
(the ~741 with a `meta.polyline`). Idempotent: skips activities that already have an
`altitude` series unless `--force`. **Rate-limit aware:** Strava allows ~100 requests /
15 min and ~1000 / day, so the command paces itself and is resumable across runs (already
-done activities are skipped), logging how many remain.

### 3. Migration + model

- Migration adds `altitude`, `speed`, `track` json nullable columns to `activities`.
- `Activity`: add the three to `#[Fillable]` and cast them `array` (like `heart_rate`).
- Export/import are fillable-driven, so `data/activities.csv` gains the columns with no
  command change.

### 4. Entry payload

- The activity single-entry page loads the profile series (`heart_rate`, `altitude`,
  `speed`, `track`) as a **deferred Inertia prop** with an animated skeleton (per the
  deferred-prop convention), so the initial entry render stays light and the charts
  hydrate after. `EntryController` provides it for activity entries that have any series.

### 5. Frontend: charts + synced cursor

- **`ActivityProfile.vue`** — renders up to three stacked Chart.js charts (HR line,
  elevation area, speed line), each only when its series exists. Uses the already-installed
  `chart.js`. Colours from the type/metric tokens; theme-aware.
- **Shared cursor** — a small composable (e.g. `useActivityCursor`) holds a reactive
  cursor index (or timestamp) and null-when-inactive. Each chart:
  - on pointer move, resolves the nearest data index and sets the cursor;
  - draws a vertical line at the cursor (a lightweight Chart.js plugin/overlay);
  - clears the cursor on pointer leave.
- **`EntryMap.vue`** gains a route-scrub dot: it accepts the `track` + the shared cursor,
  renders a dot at `track[cursor]`, and on pointer move over the route resolves the nearest
  track point and sets the cursor (reverse direction). Pointer events unify mouse + touch.
- All interaction is hover/pointer, no clicks; charts and dot hide their cursor on leave.

## Data flow

```
Strava streams (time,distance,latlng,altitude,velocity_smooth,heartrate)  [aligned]
  -> downsample by shared indices -> absolute-time series
  -> activities.{heart_rate,altitude,speed,track}  (+ data/activities.csv)
  -> EntryController deferred prop -> ActivityProfile (3 charts) + EntryMap dot
  -> shared cursor composable <-> both directions (pointer/touch)
```

## Error handling

- No streams for an activity (manual entry, no sensor, non-GPS): columns stay null; the
  relevant chart(s) and the map dot simply don't render.
- Strava rate limit / failure during backfill: pace and skip; the command is resumable.
- Apple Health import guarded so it never overwrites a Strava-sourced HR series.

## Testing

- **Strava service:** `activityStreams` requests the exact key set (`Http::fake`,
  assert the `keys=` param).
- **StravaSync:** from a faked streams response, assert `altitude`/`speed`/`track` are
  stored, downsampled, aligned (same length), absolute-timed, and that HR comes from
  Strava when present.
- **downsample helper:** unit-test the shared cap/spacing (and that identical indices are
  used across series).
- **strava:streams:** idempotent skip, `--force` regeneration, pacing (mock/inspect), and
  that it targets route-bearing activities.
- **Migration/model:** columns added and cast to array.
- **EntryController:** the deferred profile prop is present for an activity with series.
- **Frontend (Pest browser):** assert the rendered chart canvases and the map dot element
  appear (rendered DOM, not props JSON, per the browser-test rule); a basic cursor-sync
  smoke check where feasible.

## Risks

- **Rate-limited backfill** of ~741 activities (multi-run, paced).
- **Downsampling fidelity** — keep four series index-aligned; prefer a method that
  preserves elevation peaks rather than naive stride sampling.
- **Dual-source HR coexistence** (Strava vs Apple Health) — precedence + non-clobber.
- **Cursor-sync performance** on mobile with Chart.js + MapLibre (pointer throttling).
- **`EntryMap.vue` overlap** with the concurrent `feat/activity-photo-map-markers` branch.

## Out of scope

- Power (`watts`), cadence, temperature, grade streams (available; not requested now).
- Splits / laps / best-efforts.
- Distance-based x-axis (time is used; distance could be added later with a stored series).
