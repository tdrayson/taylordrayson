# Client-side formatting settings (Project B) — design

**Date:** 2026-07-11
**Branch:** `feature/settings-panel` (stacked on Project A; Project B commits continue on the same branch / PR #9)
**Status:** Approved (narrowed to distance + weight), pending implementation plan

## Context

**Project B** of the settings effort. Project A shipped the settings modal, the `useSettings` store, and the `defineSetting(key, fallback, allowed?)` factory (localStorage-backed, `pref:`-namespaced, reactive). B consumes that factory to make **distance and weight** display visitor-configurable, updating **live** (no reload).

### Why not time/date (scope decision, 2026-07-11)

Entry timestamps and dates are **server-formatted**, not client-formatted, by deliberate design:
- `FeedItem.vue` renders the timeline card time from a **server prop** (`time: String`), not a client computation.
- `Entry.vue` renders `occurredLabel` + `occurredOffset`, both **server props**.
- The `timezone-model` decision stores `occurred_at` as local wall-clock + per-entry timezone and formats it **server-side** precisely so the client never does `new Date()` tz math.
- `dateLong`/`dateTime` in `lib/format.js` are currently **unused**; real dates are scattered inline partial labels.

A client-side `timeFormat`/`dateFormat` therefore could not reformat the entry timestamps a visitor actually reads — it would only touch the Now-page clock and flight/sleep times, reading as a broken setting. Making time/date genuinely configurable requires server changes (send raw/structured timestamps or both variants while honoring per-entry tz) and contradicts the timezone model's intent, so **time and date are deferred to a separate Project B.2 spec.** Distance and weight have no such coupling — they render from raw numbers on the client — so they ship cleanly now.

Today: `resources/js/lib/distance.js` is miles-only (`metresToMiles`); weight renders as `kg` in `resources/js/Components/Entry/ActivityDetail.vue` (gym-set weights + volume) and distance strings appear in activity/flight components. Defaults in B reproduce today's exact output (`mi`, `kg`), so nothing visibly changes until a visitor opts in.

## Decisions

| Decision | Choice |
| --- | --- |
| Settings | `distanceUnit`, `weightUnit` — via `defineSetting` (time/date deferred to B.2) |
| Reactivity | **Live update, no reload** — a reactive `useFormat()` composable formatters read from |
| Panel UI | A new **Formatting** section in `SettingsModal.vue`, below Appearance |
| Controls | Reusable segmented pill toggle (`SettingToggle.vue`, `role=radiogroup`) — one per setting |
| Storage | Per-browser localStorage via `defineSetting` (`pref:` prefix); no visitor auth |
| Distance default | Miles stays the DEFAULT; km is a visitor opt-in (see updated `display-units-miles` memory) |

### Settings

| Key | Values | Default | Renders as |
| --- | --- | --- | --- |
| `distanceUnit` | `mi` / `km` | `mi` | `12 mi` / `19 km` |
| `weightUnit` | `kg` / `lbs` | `kg` | `80 kg` / `176 lbs` |

Each `defineSetting(key, default, allowedValues)` call whitelists its values; an out-of-range stored value falls back to the default.

## Architecture

### 1. Pure primitives — `lib/distance.js` + a weight helper

Settings-independent conversion primitives, pure and unit-testable:

- `metresToMiles(metres, precision = 0)` — existing.
- `metresToKm(metres, precision = 0)` — **new** in `distance.js` (`metres / 1000`, rounded to `precision`).
- `kgToLbs(kg, precision = 0)` — **new** (`kg * 2.20462`, rounded). Placed alongside the existing `number()` display rounding.

These take a value, return a number — no store coupling.

### 2. Reactive composable — `composables/useFormat.js`

Defines the two settings via `defineSetting` (module-level, shared) and returns reactive-aware formatters plus the raw refs for the panel:

- `distance(metres, precision?)` → reads `distanceUnit.value`; returns a **display string** `"<n> mi"` or `"<n> km"` (uses `metresToMiles`/`metresToKm` + `number()` for grouping).
- `distanceParts(metres, precision?)` → `{ value, unit }` for call sites that render the unit inside an `<abbr>`/StatGrid (so screen-reader expansion via `UNIT_TITLES` keeps working). `km` is already in `UNIT_TITLES`.
- `weight(kg, precision?)` → reads `weightUnit.value`; returns `"<n> kg"` or `"<n> lbs"`.
- Re-exports `distanceUnit`, `weightUnit` refs + their setters for the controls.

Each formatter reads its `settingRef.value` **inside the function body**, so calling it in a Vue template/computed registers the ref as a render dependency — every consumer re-renders the instant the setting changes (the live-update requirement), with no per-component wiring.

### 3. Call-site migration

Migrate the client-side distance/weight display sites to `const { distance, distanceParts, weight } = useFormat()`:

- **Distance:** `ActivityDetail.vue` (`metresToMiles`), `FlightDetail.vue` (`metresToMiles`), and the hardcoded `` `${number(props.route.distance)} mi` `` flight note in `FeedItem.vue` (route distance is in miles already — B.2 caveat: `route.distance` is a mile value, so km display converts from miles via `milesToKm`; the plan pins the exact source unit per call site).
- **Weight:** `ActivityDetail.vue` set weight (`… kg`), volume (`… kg volume`), and per-exercise volume.

The plan enumerates each exact line. Setting-independent helpers (`duration`, `number`, `titleCase`, `flightDurationLabel`) stay as plain `lib/format` imports. Every migrated site is verified by the new live-update browser test.

> Source-unit care: some call sites hold **metres** (activity distance), others hold **miles** (flight `route.distance`). The plan specifies, per site, which conversion the km path uses (`metresToKm` vs a `milesToKm`) so no site double-converts. If a `milesToKm` primitive is needed it is added to `distance.js` in the same pure style.

### 4. Panel controls

- `SettingToggle.vue` — a `role=radiogroup` segmented pill control: a `label` + N option pills, each `role=radio` + `aria-checked` + `aria-label`, with mirrored `focus-visible` rings (mirrors the theme-card a11y). Props: `modelValue`, `options: [{ value, label }]`, `label`, `ariaLabel`. Selecting an option calls the store setter.
- `SettingsModal.vue` — gains a **Formatting** `<section>` under Appearance with two rows: **Distance** (`mi`/`km`) and **Weight** (`kg`/`lbs`).

## Testing

- **Live-update browser test** (`tests/Browser/FormattingSettingsTest.php`): open the modal; switch `distanceUnit` to `km` and assert a rendered distance flips `mi` → `km` **without a reload**; switch `weightUnit` to `lbs` and assert a rendered weight flips `kg` → `lbs`; assert the choices persist in `localStorage['pref:distanceUnit']` / `['pref:weightUnit']`. Assert the RENDERED DOM element, never text that also appears in the Inertia `data-page` props JSON (known false-positive trap).
- The pure primitives (`metresToKm`, `kgToLbs`, any `milesToKm`) are exercised end-to-end through that test's rendered output; there is no standalone JS unit runner in this project. If the plan finds an existing pattern for asserting pure `lib/*` output directly, it may add focused checks too.

## Scope extension (2026-07-11): react everywhere, not just detail pages

Initial implementation migrated only the client-formatted detail pages (ActivityDetail, FlightDetail, FeedItem flight note). But distance/weight are also **pre-composed into strings server-side** on high-visibility surfaces: timeline card subtitles (`Activity::card()`/`Flight::card()`), the Stats page (`StatsController`), and timeline year/month/day aggregates (`TimelineController`). Those did not react to the toggle. Unlike time/date, distance/weight have no per-entry-timezone constraint, so the fix is safe: the server sends **raw values** (metres / kg) — as ordered subtitle **tokens** for fused card strings, or as a `distanceM` field for the split value+unit stat components — and the client composes/formats via `useFormat`. This brings the toggle to every data-driven surface. **Stories stay British/miles** (editorial voice, out of scope), as do sparkline trend series (unit-agnostic shapes), console/CSV/search/API paths.

## Out of scope (B)

- **Time and date formatting — deferred to Project B.2** (needs server-side changes for per-entry-timezone timestamps; see "Why not time/date" above).
- Server-side / per-account preferences (no visitor auth exists).
- The site's own editorial voice (stories/articles stay miles/British regardless of the visitor toggle — the toggle only affects data-driven display components).
- Temperature, currency, number-grouping locale, or any setting beyond the two above.
- Theme (shipped in Project A).

## Notes

- Defaults reproduce current output exactly (`mi`, `kg`), so the migration is invisible until a visitor changes a setting — safe to ship incrementally.
- The `display-units-miles` memory was updated: miles is the default, km is a visitor opt-in, and `metresToKm` is now a legitimate display path.
- Builds directly on Project A's `defineSetting`; this is the first real consumer of that extension point.
- A follow-up `2026-07-…-formatting-time-date` spec (Project B.2) will handle time/date once the server-timestamp approach is designed.
