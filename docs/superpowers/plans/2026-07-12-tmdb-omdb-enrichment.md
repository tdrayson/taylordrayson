# TMDB + OMDB Enrichment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Enrich the Trakt-synced media with TMDB (season/episode structure + high-res poster/backdrop/logo images) and OMDB (cross-platform ratings, certification, awards), and surface it on the series/film pages. Folds into `feat/trakt-integration`.

**Architecture:** Two config-driven service clients (`Tmdb`, `Omdb`) keyed off the `tmdb`/`imdb` ids already stored in `meta.ids`. A queued `EnrichMedia` job (dispatched from `trakt:sync` for new series/films, replacing the poster-only `FetchTraktPoster`) writes TMDB structure + OMDB ratings into `meta` and downloads poster/backdrop/logo into Spatie collections. Trakt remains the poster fallback. The controllers pass the new art/ratings/season data to the existing Vue pages.

**Tech Stack:** Laravel 13, Inertia v3 + Vue 3, Spatie Media Library (R2), Pest 4, TMDB API v3, OMDB API.

## Global Constraints

- Extends spec `docs/superpowers/specs/2026-07-11-trakt-integration-design.md` and its plan.
- Identity/URLs unchanged: still keyed on our own slug + Trakt id. TMDB/OMDB are enrichment only; never authoritative for identity.
- Images cached in R2 (never hotlink). TMDB image base `https://image.tmdb.org/t/p/`; sizes: poster `w780`, backdrop `w1280`, logo `w500`.
- **No episode stills** (one per episode = too many). Title-level images only.
- Config via `config()`, never `env()` outside config files. Keys already in `.env` (`TMDB_API_KEY`, `OMDB_API_KEY`).
- Graceful degradation: a title missing a tmdb id, imdb id, or a given image type must not error; enrichment is best-effort and a failure never breaks the sync.
- No em dashes; no `·`/`—` separators (commas for lists). PHP: explicit return types, PHPDoc, constructor promotion, `vendor/bin/pint --dirty` before finishing. Vue: small components, standard Tailwind scale, focus-visible mirrors hover, `<Icon :icon="Object">` (this branch's convention).
- Browser tests assert rendered DOM, not prop JSON. Known pre-existing failure: `ImportEventsTest`.

## Data shapes written by enrichment

**Series `meta`** gains:
- `tmdb`: `{ id, status, network, genres: [..], tagline, vote }`
- `seasons`: int (count, overwrites the Trakt-derived value)
- `season_list`: `[{ number, name, episode_count, air_date }]`
- `ratings`: `{ imdb, imdb_votes, rotten_tomatoes, metacritic, certification, awards }` (any subset; nulls omitted)

**Film `Media.meta`** gains:
- `tmdb`: `{ status, genres, tagline, vote }`
- `ratings`: `{ imdb, imdb_votes, rotten_tomatoes, metacritic, certification, awards, box_office }`

**Image collections** (Spatie, on both `Series` and film `Media`): `cover` (poster, upgraded to TMDB w780), new `backdrop` (w1280, singleFile), new `logo` (w500 PNG, singleFile).

## File structure

**Create:** `app/Services/Tmdb.php`, `app/Services/Omdb.php`, `app/Jobs/EnrichMedia.php`; tests `tests/Feature/TmdbServiceTest.php`, `OmdbServiceTest.php`, `EnrichMediaTest.php`.
**Modify:** `config/services.php` (+`.env.example`), `app/Models/Concerns/HasAttachments.php` (add `backdrop`/`logo` collections), `app/Console/Commands/Sync/TraktSync.php` (dispatch `EnrichMedia` instead of `FetchTraktPoster`), `app/Http/Controllers/SeriesController.php` (pass backdrop/logo/ratings/season_list), the Series Vue pages + `MediaDetail.vue` (surface art + ratings + seasons).
**Delete:** `app/Jobs/FetchTraktPoster.php` + `tests/Feature/FetchTraktPosterTest.php` (superseded by `EnrichMedia`, which owns all image downloads incl. the Trakt fallback).

---

### Task 1: `Tmdb` service client

**Files:** Create `app/Services/Tmdb.php`; modify `config/services.php`, `.env.example`; test `tests/Feature/TmdbServiceTest.php`.

**Interfaces (produces):**
- `tv(int $id): ?array` — GET `/3/tv/{id}` (returns `number_of_seasons`, `seasons[]`, `poster_path`, `backdrop_path`, `status`, `networks`, `genres`, `tagline`, `vote_average`).
- `movie(int $id): ?array` — GET `/3/movie/{id}`.
- `images(string $kind, int $id): ?array` — GET `/3/{kind}/{id}/images` (`kind` in `tv|movie`), for `logos[]`.
- `imageUrl(?string $path, string $size): ?string` — `null` when `$path` null, else `config('services.tmdb.image_base').$size.$path`.
- All read `config('services.tmdb.key')`; auth via `?api_key=` query param; return `null` on failure.

- [ ] **Step 1: failing test** — `tests/Feature/TmdbServiceTest.php`: fake `api.themoviedb.org/*`; assert `tv(71712)` returns the decoded body and that the request carried `api_key`; assert `imageUrl('/abc.jpg','w780')` builds `https://image.tmdb.org/t/p/w780/abc.jpg` and `imageUrl(null,'w780')` is null; assert `null` on a 500.
- [ ] **Step 2:** run `php artisan test --compact --filter=TmdbServiceTest` (fails).
- [ ] **Step 3:** add config:
  ```php
  'tmdb' => [
      'key' => env('TMDB_API_KEY'),
      'image_base' => env('TMDB_IMAGE_BASE', 'https://image.tmdb.org/t/p/'),
  ],
  ```
  add `TMDB_API_KEY=` and `TMDB_IMAGE_BASE=https://image.tmdb.org/t/p/` to `.env.example`; implement `app/Services/Tmdb.php` mirroring the `Trakt` service shape (private `BASE = 'https://api.themoviedb.org/3'`, a private `get()` helper injecting `api_key`, `?array` returns).
- [ ] **Step 4:** run the test (passes).
- [ ] **Step 5:** `vendor/bin/pint --dirty --format agent`; commit `feat: TMDB service client`.

---

### Task 2: `Omdb` service client

**Files:** Create `app/Services/Omdb.php`; modify `config/services.php`, `.env.example`; test `tests/Feature/OmdbServiceTest.php`.

**Interfaces (produces):**
- `byImdb(string $imdbId): ?array` — GET `https://www.omdbapi.com/?apikey=..&i={imdbId}`; returns the decoded body, or `null` when the request fails OR the payload has `Response === 'False'` (OMDB signals "not found" with a 200 + `Response:False`).

- [ ] **Step 1: failing test** — fake `omdbapi.com/*`: one case returns a full body (`Ratings`, `Rated`, `Awards`, `imdbRating`) and asserts `byImdb('tt6470478')` returns it with the `apikey`/`i` params sent; one case returns `{"Response":"False","Error":"..."}` and asserts `null`; one 500 asserts `null`.
- [ ] **Step 2:** run `php artisan test --compact --filter=OmdbServiceTest` (fails).
- [ ] **Step 3:** add config `'omdb' => ['key' => env('OMDB_API_KEY')]`; `.env.example` `OMDB_API_KEY=`; implement `app/Services/Omdb.php` (private `get()`, the `Response==='False'` → null guard).
- [ ] **Step 4:** run (passes).
- [ ] **Step 5:** pint; commit `feat: OMDB service client`.

---

### Task 3: `backdrop` + `logo` media collections

**Files:** Modify `app/Models/Concerns/HasAttachments.php`; test `tests/Feature/SeriesModelTest.php` (add a collection assertion) or a small new test.

**Interface:** `Series` and film `Media` can store single-file `backdrop` and `logo` images (in addition to `cover`), each with a webp `card`-style conversion where sensible.

- [ ] **Step 1: failing test** — assert `Series::factory()->create()->addMediaFromString($bytes,...)->toMediaCollection('backdrop')` then `getFirstMedia('backdrop')` is not null (use the existing `tests/Fixtures/pixel.webp`). Same for `logo`.
- [ ] **Step 2:** run (fails — collections not registered).
- [ ] **Step 3:** in `registerMediaCollections()` add `$this->addMediaCollection('backdrop')->singleFile();` and `$this->addMediaCollection('logo')->singleFile();`. (Shared trait; other models simply won't use them.)
- [ ] **Step 4:** run (passes).
- [ ] **Step 5:** pint; commit `feat: backdrop and logo media collections`.

---

### Task 4: `EnrichMedia` job + sync rewiring (replaces `FetchTraktPoster`)

**Files:** Create `app/Jobs/EnrichMedia.php`; modify `app/Console/Commands/Sync/TraktSync.php`; delete `app/Jobs/FetchTraktPoster.php` + `tests/Feature/FetchTraktPosterTest.php`; test `tests/Feature/EnrichMediaTest.php`; update `tests/Feature/TraktSyncTest.php` (assert `EnrichMedia` dispatched, not `FetchTraktPoster`).

**Interfaces:**
- `EnrichMedia` (`implements ShouldQueue`, `$tries = 3`, `backoff()`): constructor `(Model&HasMedia $subject, string $kind, ?int $tmdbId, ?string $imdbId, ?string $fallbackPosterUrl)` where `$kind` in `tv|movie`.
- `handle(Tmdb $tmdb, Omdb $omdb)`:
  1. If `$tmdbId`: fetch detail (`tv`/`movie`) + `images()`. Merge into `meta`: `tmdb` block; for tv also `seasons` (count) + `season_list`. Download poster (`poster_path` @ w780) → `cover`; backdrop (`backdrop_path` @ w1280) → `backdrop`; best logo (`images.logos[0].file_path` @ w500, prefer an `en`/null language entry) → `logo`. Each download best-effort (skip on null path / failed fetch).
  2. If no TMDB poster was stored and `$fallbackPosterUrl` given: download it → `cover` (the old Trakt path).
  3. If `$imdbId`: `omdb->byImdb()`; map into `meta.ratings` (`imdb`=imdbRating, `imdb_votes`, `rotten_tomatoes`/`metacritic` parsed out of `Ratings[]`, `certification`=Rated, `awards`=Awards, and for movies `box_office`=BoxOffice). Omit null/`'N/A'` values.
  4. `array_filter` nulls before merging so we never store `'N/A'`/null noise. Save the model.
- A private image-download helper mirrors the old `FetchTraktPoster` (`Http::get` → `clearMediaCollection` → `addMediaFromString(...webp)`), throwing on a failed fetch so the queue retries.

**Sync change:** in `createFilm`/`createEpisode`, replace the `FetchTraktPoster::dispatch(...)` with `EnrichMedia::dispatch($subject, $kind, $tmdbId, $imdbId, $traktPosterUrl)`, where `$tmdbId`/`$imdbId` come from the movie/show `ids`, and `$traktPosterUrl` is the existing Trakt fallback URL. Dispatch for new series (once) and each new film.

- [ ] **Step 1: failing test** — `EnrichMediaTest`: `Http::fake` TMDB detail (with `poster_path`, `backdrop_path`, `number_of_seasons`, `seasons[]`), TMDB `/images` (a `logos[]`), the image binaries (`tests/Fixtures/pixel.webp` bytes), and OMDB (`Ratings[]` with RT + Metacritic, `Rated`, `Awards`). `Storage::fake` the media disk. Run `EnrichMedia` for a `Series` (`kind:'tv'`, real tmdb/imdb). Assert: `meta['seasons']` set, `meta['season_list']` non-empty, `meta['ratings']['rotten_tomatoes']` + `certification` set, and `getFirstMedia('cover')`/`('backdrop')`/`('logo')` all present. Add a case: no tmdb poster + a `fallbackPosterUrl` → `cover` still populated from the fallback. Add a case: OMDB `Response:False` → no ratings, no error.
- [ ] **Step 2:** run `--filter=EnrichMediaTest` (fails).
- [ ] **Step 3:** implement `EnrichMedia`; delete `FetchTraktPoster` + its test; rewire `TraktSync`; update `TraktSyncTest` to `Bus::assertDispatched(EnrichMedia::class, ...)` (keep the counts/dedup assertions — a film + 2 new shows = 3 dispatches).
- [ ] **Step 4:** run `--filter='EnrichMediaTest|TraktSyncTest'` (pass), then full suite (only `ImportEventsTest` fails).
- [ ] **Step 5:** pint; commit `feat: EnrichMedia job (TMDB+OMDB) replacing FetchTraktPoster`.

---

### Task 5: surface enrichment on the series + film pages

**Files:** Modify `app/Http/Controllers/SeriesController.php`, `resources/js/Pages/Media/SeriesIndex.vue` + `SeriesShow.vue` + `SeriesSeason.vue`, `resources/js/Pages/MediaDetail.vue` (film detail), new `resources/js/Components/Ui/RatingBadges.vue`; test `tests/Browser/SeriesPageTest.php` (extend).

**Interfaces:** controllers add to their payloads: `backdrop` + `logo` URLs (`getFirstMediaUrl('backdrop'/'logo')`, null-safe), `ratings` (from meta), and for `SeriesShow` a `seasonList` (from `meta.season_list`, each `{number, name, episodeCount, airDate}`). `SeriesIndex` uses the upgraded higher-res poster automatically (same `cover` collection).

- [ ] **Step 1: failing browser test** — extend `SeriesPageTest`: seed a Series with a `backdrop`/`logo` attachment (via `addMediaFromString` + `tests/Fixtures/pixel.webp`) and `meta.ratings`/`meta.season_list`; assert `[data-testid="series-backdrop"]`, `[data-testid="rating-badge"]`, and a season-list element render on `/media/tv/{slug}`.
- [ ] **Step 2:** run `--filter=SeriesPageTest` (fails).
- [ ] **Step 3:** build it:
  - `RatingBadges.vue` — renders IMDb / Rotten Tomatoes / Metacritic / certification pills from a `ratings` object, each `data-testid="rating-badge"`, omitting absent sources. Commas, no `·`.
  - `SeriesShow.vue` — a backdrop hero (`data-testid="series-backdrop"`, background image when present, graceful gradient when absent), the `logo` image overlaid (falls back to the title text when absent), `RatingBadges`, and the season overview from `seasonList` (season name, episode count, air year) above the watched-episode groups.
  - `MediaDetail.vue` (films) — backdrop + `RatingBadges` where a film entry is shown.
  - `SeriesIndex.vue` — no markup change needed beyond confirming the sharper poster renders.
  - Icons via `<Icon :icon="Object">`; focus-visible on any new links; no arbitrary Tailwind brackets.
- [ ] **Step 4:** `npm run build`; run `--filter=SeriesPageTest` (pass); full suite (only `ImportEventsTest` fails).
- [ ] **Step 5:** pint; commit `feat: surface backdrop, logo, ratings, and season overview on media pages`.

---

## Final review

After Task 5, run the whole-branch review over `origin/master...HEAD` (now Trakt + enrichment), fix Critical/Important, then hand back for Taylor's visual pass + push/PR decision.
