# Year & Month Archives Design

**Date:** 2026-07-05
**Status:** Approved direction (Approach A, built C-first)
**References:** Aaron Parecki year/month archives (transport bars + 12 mini calendars; month = calendar with per-day type icons, photos, check-ins, posts), cleverdevil.io year-in-review (type-count tiles, travel map, full entry list). Screenshots in session.

## Goal

Replace the hardcoded placeholder Year page and extend the half-real Month page so both become data-driven "review + browse" hubs: stats and visual summary up top, the full (paginated) timeline underneath.

## Non-goals

- Per-type stats dashboards (`/activities` analytics etc.) - separate project, own brainstorm with Mobbin research.
- Timeline type-filter select (cleverdevil's Curated/Everything menu) - parked; server groundwork exists via the feeds `types=` filtering.
- Caching/materialised aggregates - compute live; optimise only if measurably slow.
- Superlative copywriting per type beyond the defined pool.

## Shared principles

- **Every section is data-driven and self-hiding.** No section renders a zero state; a sparse year simply has fewer sections.
- **The GitHub-style heatmap is the year's visual identity** (same `Heatmap` component family as /now), not Parecki's 12 mini calendars.
- **The timeline tail is everything, paginated** - no curation. Stats always describe the whole period regardless of tail page.
- Existing conventions apply: no arbitrary Tailwind values, self-hiding StatGrid entries, `FutureNote` for future periods, OgMeta already exists for both routes.

## Year page (`/{year}`)

Section stack, top to bottom:

### 1. Header
`ViewHeader` with plain title (no eyebrow), prev/next year links (existing), subtitle "N entries" for the year.

### 2. Numbers (Phase 1)
Real `StatGrid`, built from one spine group-by plus targeted per-model aggregates. Candidate stats, each self-hiding at zero:

| Stat | Source |
|---|---|
| Entries | spine count for year |
| Activities (+ total distance km) | Activity count + sum(distance) |
| Avg sleep | Sleep avg(duration) |
| Days of food (+ total kcal) | Calorie spine-days + sum(calories) |
| Films / shows | Media count (type film/show) |
| Flights (+ miles) | Flight count + sum(distance) |
| Articles + notes | published Article count + Note count |
| Places | Checkin count |

### 3. The year, GitHub-style (Phase 1)
Full-year contribution heatmap: 52-ish week columns, cells coloured by entries-per-day (log-ish bucket scale so food/sleep-only days don't flatten the range).

- **Cell click → day page.** Hover shows `D Mon · N entries` (title attr is fine).
- **Month labels along the top are links to `/{year}/{month}`** - this is the year→month navigation, solving the "can't jump to a month" gap while keeping the GitHub visual.
- Implementation: extend/reuse the existing `Stats/Heatmap.vue` (used on /now) with a full-year mode + clickable month labels; keep one component, no fork.
- Data: one query - spine counts grouped by date.

### 4. Superlatives (Phase 2)
Adaptive highlight cards from a candidate pool; each has a **minimum-data gate** so sparse years shrink gracefully. Show at most 6; order by the table below.

| Card | Value | Gate |
|---|---|---|
| Longest activity | max duration (any type, label = its type) | ≥10 activities in year |
| Most active month | month with max activity count | ≥20 activities |
| Furthest flight | max distance route "LHR → JFK" | ≥3 flights |
| Best sleep month | month with max avg duration | ≥60 nights |
| Biggest food day | max daily kcal sum | ≥100 food days |
| Most-logged day | day with max entry count | always eligible |
| Top place | most-visited checkin | ≥10 check-ins |
| Busiest writing month | month with max articles+notes | ≥6 written |

Card component: icon + label + value + sub (date/context), reusing the highlights row visual already sketched in the placeholder.

### 5. Travel (Phase 2)
- `FlightsMap` (existing, `bleed` full-width-inset) fed only that year's routes. Hidden when the year has no flights.
- Top places `BarList` (top 5 checkin venues by visits). Hidden when no check-ins.
- Section heading "Travel" renders if either child renders.

### 6. Timeline tail (Phase 1)
- Everything from the year, day-grouped (`DateGroup`/`TimelineFeed`), **chronological ascending** (matches Day-page reading order; the home feed stays newest-first).
- Paginated via `?page=` on the spine query (~100 entries/page before day-grouping), standard `Pagination` component.
- Served as an **Inertia deferred prop** with a skeleton, so the stats/heatmap paint immediately.
- Page metadata (numbers, heatmap) always covers the full year, never just the visible page.

## Month page (`/{year}/{month}`)

Already real: entry count, `CalendarMonth` (per-day type icons + sleep/kcal), `monthStats` row. Changes:

### 1. Photos strip (Phase 1)
Row of that month's photos (reuse the gallery query scoped to the month), capped at ~12 thumbnails, lightboxed, with a "View all photos" link to /photos when over the cap. Hidden when empty.

### 2. Timeline tail (Phase 1)
Same as the year tail: everything, day-grouped, chronological ascending, paginated, deferred. A month is typically 30-150 entries so most months are one page.

### 3. Stat row stays as-is
`monthStats` already self-hides per stat; no change beyond any shared helper extraction.

## Backend shape

- Stays in `TimelineController` (`year()`, `month()`) with private helpers; extract a shared `periodStats(Carbon $start, Carbon $end)` so year and month stat rows use one code path (month's existing `monthStats` becomes this).
- Year aggregates use targeted SQL (group-bys and sums), **not** loading all ~4-5k spine rows with relations. The tail is the only hydrated query and it's paginated.
- Heatmap data: `select date(occurred_at), count(*) ... group by 1` on the spine for the year.
- Superlatives (Phase 2): one query per candidate, all cheap max/group-by aggregates; each returns null below its gate.

## Testing (Pest)

- Year payload: numbers reflect seeded models; heatmap has an entry for a seeded day; tail paginates (page 2 differs); future year renders FutureNote; empty year shows no stat sections but still renders.
- Month payload: photos strip lists a seeded photo; tail present; existing month tests keep passing.
- Phase 2: superlative gates (below-gate year omits card; above-gate includes with correct value); travel section hidden without flights.

## Phasing

- **Phase 1 (the C slice):** real year numbers, full-year heatmap with month-label links, paginated deferred tails on year + month, month photos strip. Ships as one plan.
- **Phase 2:** superlatives + travel section. Additive sections, separate plan, no rework of Phase 1.
