# Trakt Review-Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Address the agreed review follow-ups on PR #16 (Trakt integration): fail-closed API handling, self-healing incremental window, cheaper index query, enrichment retry-when-bare + a `media:enrich` command, HTTP hardening, bounded per-run work, midnight-safe timestamp nudging, and failure-path tests.

**Architecture:** The Trakt sync pipeline is `app/Console/Commands/Sync/TraktSync.php` calling the `App\Services\Trakt` client, creating `Media`/`Series` rows and dispatching the `EnrichMedia` queued job (which calls `App\Services\Tmdb`). Series pages are `App\Http\Controllers\SeriesController`. Fixes touch these files plus a new `media:enrich` command and a new `TraktException`.

**Tech Stack:** Laravel 13, PHP 8.4, Pest 4, Spatie Media Library, Inertia/Vue.

## Global Constraints

- PHP 8.4: explicit return types on every method/closure where the codebase does; curly braces on all control structures; constructor property promotion; PHPDoc array-shape annotations matching the existing style. Prefer PHPDoc over inline comments; only comment genuinely non-obvious logic.
- Watch times are stored as **Europe/London wall-clock** in `occurred_at` with `timezone` set separately; Trakt `watched_at` is UTC. Never store UTC in `occurred_at`.
- Dedup key is `Media.source_id` (the Trakt history-event id). Re-runs must stay idempotent and must never delete/reconcile on a failed or partial fetch (fail-closed).
- Run `vendor/bin/pint --dirty --format agent` before committing each task. No attribution in commit messages. Leave `data/podcasts.csv` untouched/unstaged.
- Tests: `php artisan test --compact` with a `--filter` for the touched files. Use `Http::fake()` + `Bus::fake()` per the existing `TraktSyncTest`/`TraktServiceTest` conventions. Queue runs `sync` in tests.
- `MAX_CATCHUP_DAYS = 90`. `media:reenrich` naming is wrong — the command is `media:enrich`.

---

### Task 5: Re-enrich when bare + `media:enrich` command (review item 4)

**Files:**
- Modify: `app/Console/Commands/Sync/TraktSync.php` (`createEpisode`)
- Create: `app/Console/Commands/Media/EnrichMediaCommand.php` (signature `media:enrich {--force}`)
- Test: `tests/Feature/TraktSyncTest.php`, new `tests/Feature/MediaEnrichCommandTest.php`

**Approach:**
- Bare helper in `TraktSync`: `private function seriesIsBare(Series $series): bool { return ! $series->hasMedia('cover') || empty($series->meta['tmdb']); }`.
- In `createEpisode`, dispatch `EnrichMedia` when `$wasNew || $this->seriesIsBare($series)`, deduped per run via `private array $enrichDispatched = [];` keyed by `$series->id` (so a batch of many episodes of one bare show dispatches once).
- Films: `createFilm` already dispatches on create; a bare existing film is handled by the `media:enrich` command (sync's dedup skips existing films).
- New command `media:enrich`:
  - `#[Signature('media:enrich {--force : Re-enrich everything, not just bare items}')]`, `#[Description('Re-dispatch TMDB enrichment for media missing artwork/metadata')]`.
  - Series: iterate `Series::query()->whereHas('episodes')->get()`; for each where `--force` or bare, dispatch `EnrichMedia::dispatch($series, 'tv', $series->meta['ids']['tmdb'] ?? null, null)`.
  - Films: iterate `Media::query()->where('source','trakt')->where('type','film')->get()`; for each where `--force` or `! $media->hasMedia('cover') || empty($media->meta['tmdb'])`, dispatch `EnrichMedia::dispatch($media, 'movie', $media->meta['ids']['tmdb'] ?? null, null)`.
  - Report counts via `$this->info`. Return `self::SUCCESS`.
- `bare` for the command mirrors `seriesIsBare` (no `cover` media OR no `meta.tmdb`).

**Steps:**
- [ ] Write failing tests: (a) `TraktSyncTest` — an existing series with NO cover/`meta.tmdb` that gets a new episode this run re-dispatches `EnrichMedia` (Bus::assertDispatched), even though `$wasNew` is false; and it dispatches once for a batch of 3 episodes of that show. (b) `MediaEnrichCommandTest` — seed a bare series + a bare film and an already-enriched series (has `meta.tmdb` + a fake `cover` media); `media:enrich` dispatches only for the two bare subjects; `media:enrich --force` dispatches for all three.
- [ ] Run them, confirm they fail.
- [ ] Implement `seriesIsBare`, the dedup dispatch in `createEpisode`, and the `media:enrich` command.
- [ ] Run filtered suite green (`--filter='TraktSync|MediaEnrich'`).
- [ ] Pint, commit.

---

### Task 6: Cheaper `/media/tv` index query (review item 3)

**Files:**
- Modify: `app/Http/Controllers/SeriesController.php` (`index`)
- Modify (if needed): `app/Models/Series.php`
- Test: `tests/Feature/SeriesPagesTest.php`

**Approach:**
- `index()` must stop eager-loading full `episodes`/`media` collections (`->with(['episodes','media'])`). Replace with:
  - `->withMax('episodes', 'occurred_at')` → gives `episodes_max_occurred_at` for the sort (`sortByDesc` on that attribute, or order in SQL).
  - For `progress()`, avoid loading all episodes. `progress` = watched distinct (season,episode) / `meta.aired_episodes`. Distinct season+episode count isn't a plain `withCount`. Prefer a single aggregate query keyed by `series_id` over the shown series (e.g. `Media::query()->whereIn('series_id', $ids)->get(['series_id','meta'])` mapped in PHP to distinct counts, OR a grouped raw count) — no N+1, no full hydrate of every episode row's relations. Keep the distinct-episode semantics identical to `Series::watchedEpisodeCount()`.
  - Keep the poster via `getFirstMediaUrl('cover','card')` (media-library resolves without hydrating `episodes`).
- Keep the rendered output shape identical (`slug,title,year,poster,progress`), ordered most-recently-watched first. This is a refactor: the existing `SeriesPagesTest` index assertions must still pass unchanged.

**Steps:**
- [ ] Confirm/extend `SeriesPagesTest` covers: index ordered by last-watched desc, and `progress` value for a series with known aired/watched counts. If missing, add a focused assertion asserting current correct behaviour, then refactor keeping it green.
- [ ] Refactor `index()` to remove `->with(['episodes','media'])` and use `withMax` + an aggregate progress query.
- [ ] Run `SeriesPagesTest` + `SeriesStatsTest` green.
- [ ] Pint, commit.

---

## Completed tasks (for context; do NOT re-implement)
- Task 1 (done, `9c3ef199`): Fail-closed API handling + HTTP hardening — `Trakt::historyPage`/`ratingsPage` throw `TraktException`; `show`/`movie` stay null-tolerant; `handle()` returns FAILURE; timeouts + 429/connection retry on `Trakt` and `Tmdb`.
- Task 2 (done, `4d5d8d18`): Self-healing window — `resolveStartAt()` = `min(now-days, max(newestSyncedWatch_utc, now-90d))`, `MAX_CATCHUP_DAYS=90`.
- Task 3 (done, `cbd5789b`): Midnight-safe nudge — clamp group base so `base+(count-1)s` stays within the local day.
- Task 4 (done, `d3706e6b`): Bound normalize to series synced this run via `affectedSeriesIds`.

## Post-tasks (controller-run, not a subagent task)
- Update the PR #16 description: `media:reenrich` → `media:enrich`, and remove the OMDB/`meta.ratings` paragraph (not built on this branch). Tick the done-checklist items.
- Final whole-branch review over the review range, then finishing-a-development-branch.
