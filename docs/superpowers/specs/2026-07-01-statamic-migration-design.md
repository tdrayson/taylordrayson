# Statamic Migration Design

**Status:** Draft for review
**Date:** 2026-07-01
**Branch:** `feature/statamic-migration`

## Goal

Adopt Statamic 6 as the site's CMS and admin layer, giving a real Control
Panel and git-tracked flat-file content, without regressing the app's
data-heavy lifelog features or its Inertia + Vue front-end.

## Motivation

- The bespoke/Filament control panel was built, disliked, and removed. Statamic's
  Control Panel is a mature, well-crafted admin we don't have to build.
- Genuine content (pages, articles, notes) should live as flat files in git.
- Statamic blueprints/fieldtypes give clean content modelling.

## Feasibility summary

- **Compatibility (gate: PASS).** App is Laravel 13.0.0 / PHP 8.4. Statamic 6
  supports Laravel 13 (from v6.5.0); Runway supports Laravel 13. Pin Runway's
  exact Statamic-6-compatible version at install time.
- **Data shape.** The app is overwhelmingly time-series lifelog + reference data,
  not content:

  | Bucket | Rows |
  |---|---|
  | airlines / airports (reference) | ~14,900 |
  | calories | 25,390 |
  | timeline_entries | 6,100 |
  | sleep | 1,717 |
  | activities | 1,464 |
  | podcasts / fuel / flights / attachments | ~580 |
  | content (articles / notes / pages) | 1 / 0 / 0 |

- **Why not the Eloquent driver.** Statamic's eloquent-driver is all-or-nothing
  for entries; it cannot mix flat-file content collections with database-backed
  ones, and it would flatten the relational lifelog models (Flight → Airline /
  Airport FKs, accessors, the data-story SQL aggregations) into generic JSON
  `entries` rows. Rejected.
- **Why Runway.** Runway surfaces existing Eloquent models in the Statamic CP
  **as-is** (own tables, columns, relationships). This yields both worlds in one
  CP: flat-file content collections **and** untouched Eloquent data.

## Architecture

Statamic 6 runs **headless** inside the existing Laravel app. It owns the CP and
content storage; it does **not** own the front-end.

```
Vue SPA (unchanged)
   ↑ Inertia props
Laravel controllers
   ↑ query
   ├── Statamic content (flat-file collections)  → pages, articles, notes
   └── Eloquent models (unchanged)               → flights, activities, calories, sleep, fuel, podcasts,
                                                     checkins, events, appearances, projects, timeline, airlines, airports
Statamic Control Panel at /cp
   ├── flat-file collections (native entries)
   └── Runway resources (the Eloquent models above)
```

### Content: flat-file Statamic collections

Only the authored written content becomes flat-file collections (Markdown/YAML
under `content/collections/`), one blueprint each:

- `pages` — slug-routed CMS pages
- `articles` — long-form; body in **Bard**
- `notes` — short-form; body in **Bard**

`articles` and `notes` are migrated **out of their Eloquent tables** into
flat-file collections (they are authored content, not logged data). `pages` is
new/empty. Everything else with a dedicated model stays Eloquent (see Runway
below).

Rich bodies use **Bard** (Statamic's block editor). The single existing
Editor.js article is converted to Bard as a one-time migration; `notes`/`pages`
are empty. The existing Editor.js support (`app/Support/EditorJs.php`,
`Ui/BlockContent.vue`, `Ui/EditorList.vue`) is retired for content and replaced
by rendering Bard.

### Data: Runway resources over existing Eloquent models

Runway resource + blueprint per model; models and tables unchanged.

- **Editable in CP:** `Flight`, `Activity`, `Calorie`, `Sleep`, `Fuel`,
  `Podcast`, `Checkin`, `Event`, `Appearance`, `Project`.
- **Read-only reference:** `Airline`, `Airport` (large lookups; surfaced for
  browse/search, not routine editing).
- **Not a resource:** `TimelineEntry` is a derived index, not hand-edited (see
  Timeline integration below).
- Relationships (e.g. Flight → Airline / Airport) map to Runway `belongs_to`
  fields so they are editable/searchable in the CP.
- Importers (Strava, Health Auto Export, Rovi, PocketCasts, CSV commands) are
  **unchanged** — they keep writing directly to the Eloquent models.

### Timeline integration

The unified timeline currently draws from Eloquent (via the `timeline_entries`
index, 6,100 rows). Because `articles` and `notes` move to Statamic flat files,
the timeline must merge Statamic-sourced content with the Eloquent-sourced data:

- Either the `timeline_entries` index is rebuilt to include Statamic entries
  (an entry-saved hook writes/updates the index row), or the timeline feed query
  merges Statamic `articles`/`notes` with the Eloquent types at read time.
- Preferred: keep the `timeline_entries` index as the single ordered source and
  sync article/note rows into it on Statamic entry save/delete, so the existing
  feed/pagination is untouched.

### Front-end (unchanged)

Inertia + Vue SPA and all custom components stay. Controllers:

- Content pages query Statamic (`Entry::query()` / facades) and pass entries as
  Inertia props exactly like today's model data.
- Bard content is augmented to HTML (or a structured array) server-side and
  rendered in Vue, replacing the Editor.js renderer.
- Data pages continue to read Eloquent models. The three data-story builders are
  untouched.

Statamic's own front-end routing and Runway front-end routing are **disabled**;
the app's routes/controllers remain authoritative. Verify no Statamic catch-all
route shadows existing routes.

### Auth

Statamic CP uses **flat-file users** (Statamic default), independent of the
app's `users` table. The public front-end remains unauthenticated as now.

## Testing

- Feature tests: content collections render through their controllers into
  Inertia props; a Runway-managed model still reads/writes via Eloquent; data
  stories still compute (existing story tests must stay green).
- CP smoke: `/cp` loads, login works, a content entry and a Runway resource are
  editable.
- Full `php artisan test` green; existing suite must not regress.

## Risks & mitigations

- **Runway version pinning** — confirm the Statamic-6/Laravel-13-compatible
  Runway release during install; if only a pre-release qualifies, note it.
- **Bard rendering in Vue** — Bard→HTML augmentation must render correctly in the
  Inertia flow; validate with the migrated article early.
- **Route precedence** — ensure Statamic/Runway front-end routing is off so it
  can't shadow app routes.
- **Reference data size** — airlines/airports as read-only Runway resources to
  avoid heavy CP editing over ~15k rows.
- **Two user systems** — acceptable by choice (flat-file CP users); documented so
  it isn't mistaken for a bug.
- **Timeline index drift** — article/note rows in `timeline_entries` must stay in
  sync with Statamic entries via save/delete hooks; otherwise the feed shows
  stale or missing posts.
- **Content migration correctness** — the one Editor.js article must convert to
  Bard without losing structure; verify the rendered output matches before
  retiring the Editor.js renderer.

## Non-goals

- No rewrite of the front-end to Antlers/Blade.
- No move of lifelog/reference data into Statamic entries or the eloquent-driver.
- No change to importers or the data-story builders.

## Rollback

All work is isolated on `feature/statamic-migration`. Statamic is additive;
reverting the branch removes it cleanly (flat-file content lives in git; Eloquent
data is untouched throughout).
