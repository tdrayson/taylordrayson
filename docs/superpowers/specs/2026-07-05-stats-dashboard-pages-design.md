# Stats dashboard pages (per data type)

**Date:** 2026-07-05
**Status:** Approved, ready for implementation plan

## Summary

A new, fourth kind of page alongside archives (browse lists), data stories (editorial
deep-dives), and the Day/Month/Year pages: an auto-generated **stats dashboard per data
type**, at `/stats/{type}`. This spec covers the reusable dashboard layout kit and a
fully-realised **activities** dashboard built with placeholder data, so we can judge the
look and feel before wiring real queries. Other types are out of scope here; the kit is
designed so they drop in later.

## Goals

- Establish a reusable dashboard layout (approach A: overview stat grid + a grid of chart cards).
- Ship a complete, placeholder-data **activities** dashboard to evaluate the design.
- Keep the prop contract realistic so going live later is a data-source swap, not a re-layout.

## Non-goals

- Real data/queries (placeholder only for now).
- A functional period control (cosmetic in this prototype).
- Dashboards for any type other than activities.
- Replacing or changing the existing archive, story, or Day/Month/Year pages.

## Routing

- `GET /stats/{type}` -> `StatsController@show`, route name `stats.show`. `{type}` is the
  archive slug (e.g. `activities`), validated against `App\Timeline\TypeRegistry`. Unknown
  slugs, or types without a stats dashboard yet, return 404.
- **Two-way support:** inside the existing `TypeRegistry` archive-route loop in
  `routes/web.php`, register `"{$definition['slug']}/stats"` as a **301 redirect** to
  `/stats/{slug}`. It MUST be registered *before* that type's taxonomy route
  (`{base}/{value}`), otherwise `/activities/stats` matches `/activities/{value}` and
  "stats" is treated as a taxonomy value. Canonical URL is `/stats/{slug}`.

## Controller

`App\Http\Controllers\StatsController@show(string $type): Response`

- Resolve the type from `TypeRegistry`; `abort(404)` if unknown or unbuilt.
- Return `Inertia::render('Stats', [...])` with **placeholder** props whose shapes match
  what a real implementation would return:
  - `type` (string, the slug)
  - `og` (via a new `OgMeta::stats($type, $label, $accentToken)`)
  - `overview`: array of `{ value, label, tone? }` for `StatCards`
  - `charts`: named chart payloads, each `{ title, meta?, type, data, options }` consumed by `ChartCard`
  - `records`: array of `{ value, label }` for the records `StatCards`
- Placeholder values live in a private method (e.g. `activitiesPlaceholder()`), clearly
  commented as placeholder, so it is obvious where real queries slot in.

## Page: `resources/js/Pages/Stats.vue`

- Props: `type`, `og`, `overview`, `charts`, `records`.
- Structure, top to bottom:
  1. `AppHead` + `ViewHeader` (title e.g. "Activity stats", accent-themed eyebrow).
  2. Period control: `Week | Month | Year | All`, client-only state defaulting to "Year".
     Cosmetic for this prototype (no data refetch); documented as such in a comment.
  3. `StatCards` overview row (the `overview` prop).
  4. A responsive dashboard grid of `ChartCard`s: 1 column on mobile, 2 columns md+.
  5. A "Records" section rendered with `StatCards` (the `records` prop).
- Themed to the activity accent colour, consistent with how stories theme per type.

## Component kit

- **New** `resources/js/Components/Stats/ChartCard.vue`: a titled card (title + optional
  meta line) wrapping the existing `Components/Ui/Chart.vue`. Props: `title`, `meta?`,
  plus the chart props (`type`, `data`, `options`, `height?`) passed through. This is the
  workhorse every future type reuses.
- **Reused** `Components/Ui/StatCards.vue` for the overview and records rows.
- **Reused** `Components/Ui/Chart.vue` and `resources/js/lib/chart.js`
  (`PALETTE`, `baseOptions`, `tooltip`) for chart rendering and theming.

## Activities dashboard content (placeholder)

- **Overview cards:** Activities, Walked (mi), Ran (mi), Cycled (mi), Avg pace, Longest run.
- **Chart cards:**
  - Distance over time — bar
  - By discipline — doughnut (walk / run / ride)
  - Day-of-week pattern — bar
- **Records:** a `StatCards` of bests (e.g. longest run, biggest week, fastest pace).

## Testing

Feature test (`tests/Feature/StatsPageTest.php`):

- `GET /stats/activities` returns 200 and renders the `Stats` component with the expected
  placeholder props present (`overview`, `charts`, `records`).
- `GET /activities/stats` returns a 301 redirect to `/stats/activities`.
- `GET /stats/{unknown}` returns 404.

## Reuse and conventions

- Follows existing patterns: `TypeRegistry` for type resolution, `OgMeta` for page meta,
  Inertia render, small focused components, per-type accent theming, `mi` units in `<abbr>`.
- No changes to existing pages or dependencies.
