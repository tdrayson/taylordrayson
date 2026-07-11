# Trakt.tv Integration — Design Spec

**Date:** 2026-07-11
**Status:** Draft for review
**Delivery:** Single branch + single PR (`feat/trakt-integration`), plan phased internally.

## Goal

Import Taylor's Trakt.tv watch history (films + TV episodes) into the existing `media` timeline type, group episodes under their parent show via a new `Series` model with a browsable series page, and keep it current with a scheduled sync. Backfill everything once, then stay up to date going forward.

## Locked decisions

- **Series library + pages** — episodes roll up into `Series` records, each with its own page.
- **API-key-only auth** — read Taylor's *public* profile with just the `client_id` (`trakt-api-key` header). No OAuth, no token refresh.
- **Posters downloaded to R2** — via the existing Spatie Media Library `cover` collection. No hotlinking (Trakt forbids it). No TMDB key needed — Trakt's own `extended=full` images are used.
- **Watch history only** — no ratings import in this pass. The `media.rating` column stays nullable/unused.
- **Timeline collapse** — same-show, same-day episode watches collapse into one timeline card, which links off the main timeline to the series page anchored at that watch-date. Films and single episodes render individually.
- **`/media/tv`** becomes a poster grid of shows. `/media/films`, `/media/books`, and `/media` are unchanged.

## Global constraints

- No em dashes anywhere (UI or generated text). Use commas / pipes per the separators convention.
- **Identity is always the Trakt id, never the title.** Same-name titles (The Office US 2005 vs The Office UK 2001) are distinct Trakt entities with distinct `ids.trakt` / slugs, so they become distinct rows automatically. Title is display-only everywhere.
- Images must be cached in our own storage (R2). Never hotlink `walter-r2.trakt.tv`.
- Vue: small reusable components; icon-registry `<Icon name="...">`; standard Tailwind scale (no arbitrary bracket values); focus-visible rings mirror hover; comment non-obvious JS.
- PHP: explicit return types; constructor property promotion; PHPDoc over inline comments; run `vendor/bin/pint --dirty` before finishing.
- Browser tests assert rendered DOM elements, not text that also lives in the Inertia props JSON.
- occurred_at is stored as local wall-clock + a `timezone` string (per the timezone model), not UTC.

## The Trakt API (reference)

- Base: `https://api.trakt.tv`. Headers on every request: `trakt-api-version: 2`, `trakt-api-key: {client_id}`. No `Authorization` header (public profile read).
- **`GET /users/{username}/history/{movies|episodes}`** — one endpoint serves both backfill and ongoing.
  - Params: `page`, `limit` (default 100, max 1000 for history; page via the `X-Pagination-Page-Count` response header), `start_at` / `end_at` (ISO 8601, for windowed/incremental fetches), `extended=full` (includes the `images` object with poster/fanart WebP URLs).
  - Each item: `{ id (history event id, globally unique per watch), watched_at (UTC ISO), action, type }` plus a nested object:
    - movie: `{ title, year, ids: { trakt, slug, imdb, tmdb }, images? }`
    - episode: `{ season, number, title, ids }` **and** a sibling `show: { title, year, ids: { trakt, slug, tvdb, imdb, tmdb }, images? }` on every episode row.
- Images: with `extended=full`, `images.poster` is an array of host-relative WebP paths (prepend `https://`). Fallback if absent: `GET /shows/{trakt_id}?extended=full` or `GET /movies/{trakt_id}?extended=full`.
- No watch-history RSS exists. The sync command is the only mechanism.

## Data model

### New: `Series` model + `series` table

A TV show. Not a timeline entry itself (episodes are); a grouping/aggregate with its own page.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| trakt_id | unsigned big int, **unique** | Internal sync-matching key only. Never in a URL, never rendered as identity. |
| slug | string, **unique** | **Our own** persisted slug (see Slug strategy). Service-independent. |
| title | string | Display only. |
| year | integer, nullable | Used for slug disambiguation and display. |
| overview | text, nullable | |
| meta | json, nullable | `{ ids: { tmdb, imdb, tvdb }, network, status, first_aired, aired_episodes, seasons }` |
| timestamps | | |

#### Slug strategy (service-independent identity)

URLs must never depend on Trakt. The slug is generated **once at import from intrinsic metadata** and persisted:

1. `slug = Str::slug(title)`.
2. On collision with an existing series, disambiguate with the **year** (intrinsic, not a Trakt id): e.g. `the-office-2001` vs `the-office-2005`.
3. On the rare same-name *and* same-year collision, append our own incrementing suffix (`-2`), assigned by us and persisted.

`trakt_id` is a plain column used only to match episodes to their show during sync. If Trakt is ever swapped for TMDB or hand-entry, every slug and URL stays identical; only the sync's matching key changes.

#### Progress

- Store `meta.aired_episodes` (Trakt show summary, `extended=full`) and `meta.seasons`, and **recompute them on every sync** so a new season grows the denominator.
- `progress = distinctEpisodesWatched / aired_episodes`, clamped to 100%. Distinct watched is derived from the `media` rows (unique season+episode), so rewatches never inflate it.
- `Series::watchedEpisodeCount(): int` and `Series::progress(): ?int` helpers. Shown on the series page (progress bar, e.g. "100%, 7 seasons") and on the poster cards in the grid. Not shown on the timeline card (kept simple).

#### Watch span + total time (derived, no new storage)

Both come from data already stored (`occurred_at` per row, `meta.runtime`), so no extra API calls or columns:

- `Series::firstWatchedAt()` / `Series::lastWatchedAt()` and `Series::watchSpan(): string` — human elapsed span from first to last watch, e.g. "watched over 8 months (Jan – Sep 2024)", or "binged in 3 days". Span uses min/max `occurred_at` across all rows.
- `Series::totalRuntimeMinutes(): int` — sum of `meta.runtime` across **all** watch instances (a rewatch adds to time-spent, which is truthful for "time to watch"). Rendered as hours, e.g. "≈ 18 hours".
- Both appear in the series-page stat row alongside episodes-watched and progress. The season page shows the same two stats scoped to that season.

- `use HasAttachments, HasFactory;` — poster stored in the `cover` collection (singleFile, auto `card` WebP conversion), landing on the `r2` disk.
- Relationship: `episodes(): HasMany` → `Media` on `series_id`, ordered by `occurred_at`.
- Does **not** use `HasTimelineEntry`.
- Slug used for the route-model-bound series page.

### Extend: `media` table

- Add nullable `series_id` (FK → `series.id`, `nullOnDelete`). Set for episodes; null for films/books.
- `Media` gains `series(): BelongsTo` and (via existing `HasAttachments`) its own `cover` poster for films.
- `source = 'trakt'`, `source_id = {history event id}` (string). `unique(source, source_id)` dedupes re-syncs and captures rewatches as distinct rows truthfully.

### Canonical `type` + `meta` reconciliation

Today `type` is spelled 4 ways across the code. Canonicalise to **`film` / `episode` / `book`** and fix every site in this PR:

| Site | Change |
|---|---|
| `app/Models/Media.php` `card()` match (`Media.php:57-63`) | match arm `'tv'` → `'episode'` (already reads `meta.season` / `meta.episode`). |
| `database/factories/MediaFactory.php:18-40` | emit `'episode'` (not `tv_episode`); write `meta.season` / `meta.episode` / `meta.show_title` (not `season_number` / `episode_number`). |
| `app/Timeline/TypeRegistry.php:138` | map `'tv' => ['episode']`, `'films' => ['film']`, `'books' => ['book']`. |
| `app/Http/Controllers/TimelineController.php:311` | films count `whereIn('type', ['film'])`; add a tv count `whereIn('type', ['episode'])`. Drop the stray `'show'`. |
| Data migration | rewrite any existing rows: `tv`/`tv_episode`/`show` → `episode`, and rename legacy `meta.season_number`/`episode_number` → `season`/`episode`. |

Canonical `meta` shapes:

- film: `{ year, runtime, ids: { trakt, slug, tmdb, imdb }, genres? }`
- episode: `{ season, episode, episode_title, show_title, runtime, ids: { trakt, tmdb, imdb } }` (`show_title` denormalised so the card and the timeline collapse need no join)
- book: `{ author, isbn }` (unchanged)

## Components

### 1. `app/Services/Trakt.php` (the base API)

Mirrors `app/Services/Strava.php`'s shape, simpler (no OAuth):

- `private const BASE = 'https://api.trakt.tv';`
- Credentials from `config('services.trakt.client_id')` and `config('services.trakt.username')`.
- `private function get(string $path, array $params = []): ?Response` — `Http::withHeaders(['trakt-api-version' => '2', 'trakt-api-key' => $clientId])->get(...)`, returns `null` on failure. Returns the full `Response` so callers can read `X-Pagination-Page-Count`.
- `public function historyPage(string $type, int $page, int $limit = 100, ?string $startAt = null): ?array` — `type` ∈ `movies|episodes`; hits `/users/{username}/history/{type}?extended=full&page=&limit=&start_at=`; returns the decoded array (or `null`).
- Config block added to `config/services.php`:
  ```php
  'trakt' => [
      'client_id' => env('TRAKT_CLIENT_ID'),
      'username'  => env('TRAKT_USERNAME'),
  ],
  ```
  Add `TRAKT_CLIENT_ID` / `TRAKT_USERNAME` to `.env.example`.

### 2. `app/Jobs/FetchTraktPoster.php` (queued)

- `implements ShouldQueue`. Constructor: the target model (`Series` or `Media` film) + the poster URL.
- `Http::get($url)` → `->addMediaFromString($response->body())->usingFileName("{$identifier}.webp")->toMediaCollection('cover')` (clear-then-store for idempotency, mirroring `DownloadAppearanceThumbnails.php:73-77`).
- Dispatched from the sync command only for **new** shows and **new** film rows (episodes inherit the series poster; no per-episode images).

### 3. `app/Console/Commands/Sync/TraktSync.php` (backfill + ongoing)

- `#[Signature('trakt:sync {--days=7 : Days back to fetch} {--full : Backfill entire history}')]`, `#[Description('Sync Trakt watch history to the media timeline')]`.
- Injects `Trakt` into `handle()`.
- Window: `--full` ignores `start_at` (walk every page); otherwise `start_at = now()->subDays(--days)`. Idempotent via `source_id` dedup, so a daily `--days=7` run with overlap never duplicates (same idiom as `StravaSync`).
- For each `type` in `['movies', 'episodes']`: page with `while (true)` until an empty batch (mirror `StravaSync::fetchActivities`). Dedup incoming against existing `Media::where('source','trakt')->pluck('source_id')`.
- Per new episode: resolve the `Series` by `trakt_id` (`firstOrNew`); on first create, generate the persisted `slug` via the Slug strategy and dispatch `FetchTraktPoster`. On **every** run, refresh `meta.aired_episodes` / `meta.seasons` from the show summary (`extended=full`) so progress denominators stay current. Then create the `Media` episode row with `series_id`, canonical `meta`, `occurred_at` = `watched_at` converted from UTC to Taylor's home tz wall-clock with `timezone` set accordingly.
- Per new film: create `Media` film row; dispatch `FetchTraktPoster` for its poster.
- Scheduled daily in `routes/console.php` (or the app schedule) via `Schedule::command('trakt:sync')->daily()`.

### 4. Timeline collapse — `app/Actions/BuildTimelineFeed.php`

- Insert a pre-pass in `groupByDay()` at the per-day `$group->map(...cardItem)` step (`BuildTimelineFeed.php:28`). Within each day's `Collection<TimelineEntry>`:
  - Partition `Media` entries whose `timelineable->type === 'episode'` by `series_id`.
  - A partition with **>1** episode → one **synthesized** item: `{ iconKey: 'media', accent: 'media', title: show_title, meta: "N episodes, S{season}E{a}–E{b}" (or multi-season summary), url: "/media/tv/{slug}#watch-{date}", media: series poster if available }`.
  - Partitions with a single episode, all films, and all non-media entries → normal `cardItem()`.
- The synthesized item mirrors `cardItem()`'s key shape (plus an optional `count`) so `Timeline.vue` renders it with no template changes.
- Note: `TimelineController::day()` (`:366`) calls `cardItem()` directly, bypassing `groupByDay`, so **day pages naturally show every episode un-collapsed** — the expanded view comes for free there, complementing the anchored series page.

### 5. Series pages — `app/Http/Controllers/SeriesController.php` + Vue

All series routes live under `/media/tv/...` (consistent with `/media/films`, `/media/books`). Every level is a real inline page — no redirects — so each URL is permanent and shareable. Season and episode numbers are intrinsic, so those segments are service-independent too. Registered in `routes/web.php` **before** the generic archive loop so they take precedence:

- `GET /media/tv` → `SeriesController@index` — poster grid of shows. Overrides the generic `/media/{value}` taxonomy page for `tv` only.
- `GET /media/tv/{series:slug}` → `SeriesController@show` — the whole show: episodes grouped by season, then by watch-date within each.
- `GET /media/tv/{series:slug}/season-{season}` → `SeriesController@season` — just that season's watched episodes.
- `GET /media/tv/{series:slug}/season-{season}/episode-{episode}` → `SeriesController@episode` — that episode, listing **every watch instance** (each a distinct `media` row via the history-event id), ordered by `occurred_at`, with dates and any rating.
- `/media/films`, `/media/books`, `/media` keep the generic `ArchiveController`.

Controller notes:
- `index()` — `Series` having episodes, each with its `cover` poster and computed `progress()`, ordered by latest episode `occurred_at`. Renders `Media/SeriesIndex`.
- `show(Series $series)` — series + stats (episodes watched, seasons, progress, watch span, total time watched) + episode watches grouped by season then watch-date (each date group gets an `id="watch-{date}"` anchor, the collapsed-timeline-card link target). Renders `Media/SeriesShow`.
- `season(Series $series, int $season)` — episodes of that season only. Renders `Media/SeriesSeason`.
- `episode(Series $series, int $season, int $episode)` — all `media` rows matching `series_id` + `meta.season` + `meta.episode`. Renders `Media/SeriesEpisode`.

Vue (small components per conventions):
- `resources/js/Pages/Media/SeriesIndex.vue` — responsive poster grid of `PosterCard`s (2:3 portrait), newest-watched first, each with a progress indicator.
- `resources/js/Pages/Media/SeriesShow.vue` — hero (poster, title, year, overview, progress bar, Trakt link via `ExternalLink.vue`), stat row, then episodes grouped by season and watch-date with anchors.
- `resources/js/Pages/Media/SeriesSeason.vue` and `resources/js/Pages/Media/SeriesEpisode.vue` — the drill-down views; reuse the shared episode-row and stat components.
- `resources/js/Components/Ui/PosterCard.vue` — one show poster + title/meta + progress; links to the series page. Reused by the index and the timeline synthesized card.

## Testing

- **Trakt service** (`tests/Feature`) — `Http::fake` the two history endpoints; assert request headers (`trakt-api-version`, `trakt-api-key`), URL, params, and that `historyPage` returns the decoded array / `null` on failure.
- **TraktSync command** — fake a payload containing: a film, a same-day multi-episode binge, two same-name shows (The Office US vs UK, distinct `ids.trakt`). Assert: two distinct `Series` rows with distinct slugs disambiguated by year (proves title never collides and no Trakt id leaks into the slug), correct `Media` rows with canonical `type`/`meta` and `series_id`, re-run creates nothing new (dedup) but refreshes `meta.aired_episodes`, `FetchTraktPoster` dispatched once per new show/film (`Bus::fake`).
- **Progress** — `Series::progress()` returns distinct-watched / aired clamped to 100; a rewatch does not inflate it; raising `aired_episodes` (new season) lowers it.
- **Watch span + total time** — `watchSpan()` reflects first→last `occurred_at`; `totalRuntimeMinutes()` sums `meta.runtime` across all instances (a rewatch increases it).
- **Series routes** — `show` / `season` / `episode` render the right filtered rows; the episode route lists multiple watch instances of the same episode.
- **BuildTimelineFeed collapse** — a binge day yields one synthesized item with the right count and series-page URL; a single-episode day and a film render as normal cards.
- **Type cleanup** — `TimelineController` film/tv stat counts use canonical types and count correctly.
- **Series pages** — feature tests assert the Inertia component + props for index/show; **browser tests assert rendered DOM** (poster `<img>` elements in the grid; episode rows grouped by date on the show page), per the DOM-assertion convention.

## Out of scope (future passes)

- Ratings, watchlist, collection (would need OAuth).
- **Podcasts folding into `media`** — podcasts stay their own type/source (PocketCasts) for now; different pipeline and cadence. Revisit only if the archive feels fragmented.
- Books via other sources (the `book` type stays as-is).
- Per-episode still images (episodes inherit the show poster).
- A dedicated per-day "watch session" page (the anchored series page + existing day page cover this).

## Phasing (within the single PR)

1. Config + `Trakt` service (+ tests).
2. `Series` model + `series` migration + `media.series_id` migration + type/meta canonicalisation + data migration + fix `card()` / factory / `TypeRegistry` / `TimelineController` (+ tests).
3. `FetchTraktPoster` job (+ test).
4. `TraktSync` command + schedule (+ tests).
5. Timeline collapse in `BuildTimelineFeed` (+ test).
6. `SeriesController` (index/show/season/episode) + routes + `SeriesIndex` / `SeriesShow` / `SeriesSeason` / `SeriesEpisode` / `PosterCard` Vue + `progress()` display (+ feature + browser tests).
