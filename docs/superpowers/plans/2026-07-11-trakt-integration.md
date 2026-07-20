# Trakt.tv Integration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Import Taylor's Trakt.tv watch history (films + TV episodes) into the existing `media` timeline type, group episodes under a new `Series` model with browsable series/season/episode pages and watch stats, and keep it current with a scheduled sync.

**Architecture:** A config-driven `Trakt` service client (public-profile, API-key-only) feeds a `trakt:sync` command that upserts `Media` rows (dedup on `source`/`source_id` = Trakt history-event id) and resolves each episode to a `Series` (matched on `trakt_id`, addressed by our own year-disambiguated slug). Posters download into R2 via Spatie Media Library. The timeline collapses same-show-same-day binges into one card; series pages render watch history grouped by season and watch-date with progress/span/runtime stats.

**Tech Stack:** Laravel 13, Inertia v3 + Vue 3, Tailwind v4, Spatie Media Library (R2 disk), Pest 4 (+ browser plugin), Trakt API v2.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-07-11-trakt-integration-design.md`.
- **Identity is always the Trakt id / intrinsic metadata, never the title.** URLs use our own persisted slug derived from `title` (+ `year`, then `-N`). No Trakt id ever appears in a URL.
- Canonical `media.type` values are exactly `film`, `episode`, `book`. No `tv`, `tv_episode`, or `show` anywhere after this PR.
- Canonical `meta` (episode): `{ season, episode, episode_title, show_title, runtime, ids }`. (film): `{ year, runtime, ids }`.
- `source = 'trakt'`, `source_id = (string) history event id`. `unique(source, source_id)` dedupes.
- No em dashes in any UI or generated string. Data lists use commas; the site title uses a pipe.
- Images must be cached in R2. Never hotlink `walter-r2.trakt.tv`.
- `occurred_at` is stored as local wall-clock + a `timezone` string (never UTC). Trakt `watched_at` (UTC) converts to `Europe/London` wall-clock, `timezone = 'Europe/London'`.
- PHP: explicit return types; constructor property promotion; PHPDoc over inline comments; curly braces always. Run `vendor/bin/pint --dirty --format agent` before finishing each task.
- Vue/JS: small reusable components; `<Icon name="..." />` from the icon registry; standard Tailwind scale (no arbitrary bracket values); focus-visible rings mirror hover; comment non-obvious logic.
- Tests: use `php artisan make:test --pest`. Browser tests assert rendered DOM elements, not text that also lives in the Inertia props JSON. Run `php artisan test --compact --filter=...`.
- Config values via `config()`, never `env()` outside config files.
- The pre-existing `tests/Feature/ImportEventsTest.php` failure is unrelated; a green run is "N passed, 1 failed (ImportEventsTest)".

## File Structure

**Create:**
- `app/Services/Trakt.php` — API client.
- `app/Models/Series.php` — TV show aggregate.
- `database/factories/SeriesFactory.php`.
- `database/migrations/<ts>_create_series_table.php`.
- `database/migrations/<ts>_add_series_id_to_media_table.php`.
- `database/migrations/<ts>_canonicalise_media_types.php` — data migration.
- `app/Jobs/FetchTraktPoster.php` — queued poster download.
- `app/Console/Commands/Sync/TraktSync.php` — the sync command.
- `app/Http/Controllers/SeriesController.php` — index/show/season/episode.
- `resources/js/Pages/Media/SeriesIndex.vue`, `SeriesShow.vue`, `SeriesSeason.vue`, `SeriesEpisode.vue`.
- `resources/js/Components/Ui/PosterCard.vue`, `EpisodeRow.vue`, `SeriesStats.vue`.
- Tests: `tests/Feature/TraktServiceTest.php`, `tests/Feature/TraktSyncTest.php`, `tests/Feature/SeriesModelTest.php`, `tests/Feature/SeriesPagesTest.php`, `tests/Feature/TimelineCollapseTest.php`, `tests/Feature/FetchTraktPosterTest.php`, `tests/Browser/SeriesPageTest.php`.

**Modify:**
- `config/services.php` — add `trakt` block. `.env.example` — add `TRAKT_*`.
- `app/Models/Media.php` — `card()` match, `series()` relation, `getPlatformUrlAttribute()`.
- `database/factories/MediaFactory.php` — canonical `type`/`meta`.
- `app/Timeline/TypeRegistry.php` — `media()` taxonomy map.
- `app/Http/Controllers/TimelineController.php:311` — film/tv counts.
- `app/Actions/BuildTimelineFeed.php` — day collapse.
- `routes/web.php` — series routes before the archive loop.
- `routes/console.php` (or `bootstrap/app.php` schedule) — schedule `trakt:sync`.

---

### Task 1: Trakt service client + config

**Files:**
- Create: `app/Services/Trakt.php`
- Modify: `config/services.php`, `.env.example`
- Test: `tests/Feature/TraktServiceTest.php`

**Interfaces:**
- Produces: `App\Services\Trakt` with
  - `historyPage(string $type, int $page, int $limit = 100, ?string $startAt = null): ?array` — `$type` in `movies|episodes`; GET `/users/{username}/history/{type}?extended=full&page=&limit=&start_at=`; returns decoded array or `null`.
  - `show(int|string $traktId): ?array` — GET `/shows/{id}?extended=full`; used for `aired_episodes`/`seasons` and poster fallback.
  - `movie(int|string $traktId): ?array` — GET `/movies/{id}?extended=full`; poster fallback.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Services\Trakt;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.trakt.client_id', 'test-client-id');
    config()->set('services.trakt.username', 'taylor');
});

it('requests a history page with the required headers and params', function () {
    Http::fake([
        'api.trakt.tv/*' => Http::response([
            ['id' => 1, 'watched_at' => '2024-01-01T20:00:00.000Z', 'action' => 'watch', 'type' => 'movie',
             'movie' => ['title' => 'Dune', 'year' => 2021, 'ids' => ['trakt' => 9, 'slug' => 'dune-2021', 'tmdb' => 438631]]],
        ], 200),
    ]);

    $result = app(Trakt::class)->historyPage('movies', 1, 100, '2024-01-01T00:00:00Z');

    expect($result)->toHaveCount(1)
        ->and($result[0]['movie']['title'])->toBe('Dune');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'api.trakt.tv/users/taylor/history/movies')
            && $request['extended'] === 'full'
            && $request['page'] == 1
            && $request['start_at'] === '2024-01-01T00:00:00Z'
            && $request->header('trakt-api-version')[0] === '2'
            && $request->header('trakt-api-key')[0] === 'test-client-id';
    });
});

it('returns null when the request fails', function () {
    Http::fake(['api.trakt.tv/*' => Http::response('nope', 500)]);

    expect(app(Trakt::class)->historyPage('episodes', 1))->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=TraktServiceTest`
Expected: FAIL (class `App\Services\Trakt` not found).

- [ ] **Step 3: Add config + implement the client**

In `config/services.php`, add:
```php
'trakt' => [
    'client_id' => env('TRAKT_CLIENT_ID'),
    'username' => env('TRAKT_USERNAME'),
],
```

In `.env.example`, add:
```
TRAKT_CLIENT_ID=
TRAKT_USERNAME=
```

Create `app/Services/Trakt.php`:
```php
<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Client for the Trakt API (api.trakt.tv), read-only against a public profile.
 *
 * Authenticates with the client_id alone (the `trakt-api-key` header), so it
 * needs no OAuth token. Every public method maps to a single endpoint and
 * returns the decoded JSON array, or null when the request fails.
 */
class Trakt
{
    private const BASE = 'https://api.trakt.tv';

    /**
     * Fetch one page of the user's watch history for a given media type.
     *
     * @param  string  $type  Either "movies" or "episodes".
     * @param  string|null  $startAt  ISO 8601 lower bound (for incremental syncs).
     * @return array<int, array<string, mixed>>|null
     */
    public function historyPage(string $type, int $page, int $limit = 100, ?string $startAt = null): ?array
    {
        return $this->get("/users/".config('services.trakt.username')."/history/{$type}", array_filter([
            'extended' => 'full',
            'page' => $page,
            'limit' => $limit,
            'start_at' => $startAt,
        ], fn ($value): bool => $value !== null));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function show(int|string $traktId): ?array
    {
        return $this->get("/shows/{$traktId}", ['extended' => 'full']);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function movie(int|string $traktId): ?array
    {
        return $this->get("/movies/{$traktId}", ['extended' => 'full']);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<mixed>|null
     */
    private function get(string $path, array $params = []): ?array
    {
        $response = $this->request($path, $params);

        return $response->failed() ? null : $response->json();
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function request(string $path, array $params): Response
    {
        return Http::withHeaders([
            'trakt-api-version' => '2',
            'trakt-api-key' => config('services.trakt.client_id'),
            'Content-Type' => 'application/json',
        ])->get(self::BASE.$path, $params);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=TraktServiceTest`
Expected: PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Trakt.php config/services.php .env.example tests/Feature/TraktServiceTest.php
git commit -m "feat: Trakt API service client"
```

---

### Task 2: `series` table, `media.series_id`, and the `Series` model

**Files:**
- Create: `database/migrations/<ts>_create_series_table.php`, `database/migrations/<ts>_add_series_id_to_media_table.php`, `app/Models/Series.php`, `database/factories/SeriesFactory.php`
- Modify: `app/Models/Media.php` (add `series()` relation)
- Test: `tests/Feature/SeriesModelTest.php`

**Interfaces:**
- Produces:
  - `Series` model: fillable `trakt_id, slug, title, year, overview, meta`; `casts` `meta => array, year => integer`; `use HasAttachments, HasFactory;`; `episodes(): HasMany` (→ `Media` on `series_id`, ordered `occurred_at`). `registerMediaCollections` inherited from `HasAttachments` (poster → `cover`).
  - `Series::slugFor(string $title, ?int $year, callable $exists): string` — static slug generator: base `Str::slug($title)`; if `$exists(base)` append `-{year}`; if still taken append `-2`, `-3`, … Returns a slug not currently taken.
  - `Media::series(): BelongsTo`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Media;
use App\Models\Series;

it('links episodes to a series', function () {
    $series = Series::factory()->create(['title' => 'The Good Doctor']);
    $episode = Media::factory()->create(['type' => 'episode', 'series_id' => $series->id]);

    expect($series->episodes)->toHaveCount(1)
        ->and($episode->series->is($series))->toBeTrue();
});

it('generates a base slug, then disambiguates by year, then by suffix', function () {
    $taken = [];
    $exists = fn (string $slug): bool => in_array($slug, $taken, true);

    $a = Series::slugFor('The Office', 2005, $exists);
    $taken[] = $a;
    $b = Series::slugFor('The Office', 2001, $exists);
    $taken[] = $b;
    $c = Series::slugFor('The Office', 2001, $exists); // same name AND year

    expect($a)->toBe('the-office')
        ->and($b)->toBe('the-office-2001')
        ->and($c)->toBe('the-office-2001-2');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=SeriesModelTest`
Expected: FAIL (no `series` table / `Series` model).

- [ ] **Step 3: Create migrations, model, factory, relation**

`database/migrations/<ts>_create_series_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trakt_id')->unique();
            $table->string('slug')->unique();
            $table->string('title');
            $table->integer('year')->nullable();
            $table->text('overview')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('series');
    }
};
```

`database/migrations/<ts>_add_series_id_to_media_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->foreignId('series_id')->nullable()->after('id')
                ->constrained('series')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropConstrainedForeignId('series_id');
        });
    }
};
```

`app/Models/Series.php`:
```php
<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;

#[Fillable(['trakt_id', 'slug', 'title', 'year', 'overview', 'meta'])]
class Series extends Model implements HasMedia
{
    use HasAttachments, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'year' => 'integer',
        ];
    }

    public function episodes(): HasMany
    {
        return $this->hasMany(Media::class)->orderBy('occurred_at');
    }

    /**
     * Build a persisted, service-independent slug from intrinsic metadata:
     * base title slug, disambiguated by year, then by an incrementing suffix.
     *
     * @param  callable(string): bool  $exists  Returns true if the slug is taken.
     */
    public static function slugFor(string $title, ?int $year, callable $exists): string
    {
        $base = Str::slug($title);

        if (! $exists($base)) {
            return $base;
        }

        $withYear = $year ? "{$base}-{$year}" : $base;

        if ($year && ! $exists($withYear)) {
            return $withYear;
        }

        $candidate = $withYear;
        $suffix = 2;
        while ($exists($candidate)) {
            $candidate = "{$withYear}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }
}
```

`database/factories/SeriesFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Series;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Series>
 */
class SeriesFactory extends Factory
{
    protected $model = Series::class;

    public function definition(): array
    {
        $title = fake()->unique()->words(fake()->numberBetween(1, 3), true);

        return [
            'trakt_id' => fake()->unique()->numberBetween(1, 999999),
            'slug' => Str::slug($title),
            'title' => Str::title($title),
            'year' => fake()->numberBetween(1990, 2026),
            'overview' => fake()->sentence(),
            'meta' => ['aired_episodes' => fake()->numberBetween(10, 120), 'seasons' => fake()->numberBetween(1, 8)],
        ];
    }
}
```

In `app/Models/Media.php`, add the relation (and `use Illuminate\Database\Eloquent\Relations\BelongsTo;`):
```php
public function series(): BelongsTo
{
    return $this->belongsTo(Series::class);
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=SeriesModelTest`
Expected: PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Series.php app/Models/Media.php database/migrations database/factories/SeriesFactory.php tests/Feature/SeriesModelTest.php
git commit -m "feat: Series model, series table, media.series_id"
```

---

### Task 3: Canonicalise `media.type` + `meta`, fix all reference sites

**Files:**
- Modify: `app/Models/Media.php` (`card()` match, `getPlatformUrlAttribute()`), `database/factories/MediaFactory.php`, `app/Timeline/TypeRegistry.php`, `app/Http/Controllers/TimelineController.php`
- Create: `database/migrations/<ts>_canonicalise_media_types.php`
- Test: `tests/Feature/MediaTypeTest.php`

**Interfaces:**
- Consumes: nothing from later tasks.
- Produces: canonical `type` in `{film, episode, book}`; episode `meta` keyed `season`/`episode`/`show_title`/`episode_title`/`runtime`/`ids`; `getPlatformUrlAttribute()` builds a real Trakt content URL from `meta.ids.slug` (film) or the show slug + S/E (episode), not the history id.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Media;
use App\Timeline\TypeRegistry;

it('renders an episode card from canonical meta', function () {
    $media = Media::factory()->make([
        'type' => 'episode',
        'title' => 'Pilot',
        'rating' => null,
        'meta' => ['season' => 1, 'episode' => 3, 'show_title' => 'Severance'],
    ]);

    expect($media->card()['subtitle'])->toContain('S01E03');
});

it('builds a trakt content url for a film from meta ids, not the history id', function () {
    $media = Media::factory()->make([
        'type' => 'film',
        'source' => 'trakt',
        'source_id' => '99999',
        'meta' => ['ids' => ['slug' => 'dune-2021']],
    ]);

    expect($media->platform_url)->toBe('https://trakt.tv/movies/dune-2021');
});

it('maps the tv taxonomy to the canonical episode type only', function () {
    $definition = TypeRegistry::all()['media'];
    $taxonomy = $definition['taxonomy'];
    $query = App\Models\Media::query();
    $taxonomy['filter']($query, 'tv');

    expect($query->toSql())->toContain('type'); // filters on ['episode']
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=MediaTypeTest`
Expected: FAIL (card uses `'tv'`, platform_url uses history id).

- [ ] **Step 3: Update `card()`, `getPlatformUrlAttribute()`, factory, TypeRegistry, TimelineController**

`app/Models/Media.php` — `card()` match arm and platform URL:
```php
$detail = match ($this->type) {
    'film' => $this->meta['year'] ?? null,
    'episode' => isset($this->meta['season'], $this->meta['episode'])
        ? sprintf('S%02dE%02d', $this->meta['season'], $this->meta['episode'])
        : null,
    'book' => $this->meta['author'] ?? null,
    default => null,
};
```
```php
public function getPlatformUrlAttribute(): ?string
{
    if ($this->source !== 'trakt') {
        return null;
    }

    $slug = $this->meta['ids']['slug'] ?? null;

    if ($this->type === 'film' && $slug) {
        return "https://trakt.tv/movies/{$slug}";
    }

    $showSlug = $this->meta['show_slug'] ?? null;
    if ($this->type === 'episode' && $showSlug && isset($this->meta['season'], $this->meta['episode'])) {
        return "https://trakt.tv/shows/{$showSlug}/seasons/{$this->meta['season']}/episodes/{$this->meta['episode']}";
    }

    return null;
}
```

`database/factories/MediaFactory.php` — emit canonical `episode` type and `season`/`episode` keys:
```php
$type = fake()->randomElement(['film', 'episode', 'book']);

$meta = match ($type) {
    'film' => [
        'year' => fake()->numberBetween(1990, 2026),
        'runtime' => fake()->numberBetween(80, 200),
        'ids' => ['slug' => fake()->slug()],
    ],
    'episode' => [
        'show_title' => fake()->words(fake()->numberBetween(2, 4), true),
        'season' => fake()->numberBetween(1, 8),
        'episode' => fake()->numberBetween(1, 24),
        'episode_title' => fake()->words(fake()->numberBetween(2, 4), true),
        'runtime' => fake()->numberBetween(25, 65),
    ],
    'book' => [
        'author' => fake()->name(),
        'isbn' => fake()->isbn13(),
    ],
};
```

`app/Timeline/TypeRegistry.php` — `media()` map:
```php
$map = ['films' => ['film'], 'tv' => ['episode'], 'books' => ['book']];
```

`app/Http/Controllers/TimelineController.php:311` — canonical film count (and confirm any tv count uses `['episode']`):
```php
$films = $between(Media::query())->whereIn('type', ['film'])->count();
```

- [ ] **Step 4: Create the data migration**

`database/migrations/<ts>_canonicalise_media_types.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('media')->whereIn('type', ['tv', 'tv_episode', 'show'])->update(['type' => 'episode']);

        DB::table('media')->where('type', 'episode')->get()->each(function ($row) {
            $meta = json_decode($row->meta ?? '[]', true) ?: [];
            if (isset($meta['season_number'])) {
                $meta['season'] = $meta['season_number'];
                unset($meta['season_number']);
            }
            if (isset($meta['episode_number'])) {
                $meta['episode'] = $meta['episode_number'];
                unset($meta['episode_number']);
            }
            DB::table('media')->where('id', $row->id)->update(['meta' => json_encode($meta)]);
        });
    }

    public function down(): void
    {
        // Irreversible normalisation; no-op.
    }
};
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact --filter=MediaTypeTest` then `php artisan migrate --pretend` to sanity-check the migration parses.
Expected: PASS.

- [ ] **Step 6: Full suite guard + Pint + commit**

Run: `php artisan test --compact` (expect only the known `ImportEventsTest` failure).
```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Media.php database/factories/MediaFactory.php app/Timeline/TypeRegistry.php app/Http/Controllers/TimelineController.php database/migrations tests/Feature/MediaTypeTest.php
git commit -m "refactor: canonicalise media type to film/episode/book"
```

---

### Task 4: Series stats — progress, span, total runtime

**Files:**
- Modify: `app/Models/Series.php`
- Test: `tests/Feature/SeriesStatsTest.php`

**Interfaces:**
- Consumes: `Series` + `Media` from Task 2/3.
- Produces on `Series`:
  - `watchedEpisodeCount(): int` — distinct `season`+`episode` across `episodes`.
  - `progress(): ?int` — `round(watched / meta.aired_episodes * 100)` clamped 0–100; `null` if `aired_episodes` unknown.
  - `firstWatchedAt(): ?CarbonInterface`, `lastWatchedAt(): ?CarbonInterface`.
  - `watchSpan(): ?string` — human span, e.g. "over 8 months" or "in 3 days"; `null` if no episodes.
  - `totalRuntimeMinutes(): int` — sum of `meta.runtime` across all episode rows (rewatches included).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Media;
use App\Models\Series;

it('computes distinct-episode progress, clamped and rewatch-proof', function () {
    $series = Series::factory()->create(['meta' => ['aired_episodes' => 10]]);
    // Watch episode 1 twice, episode 2 once => 2 distinct of 10 => 20%.
    Media::factory()->count(2)->create(['series_id' => $series->id, 'type' => 'episode', 'meta' => ['season' => 1, 'episode' => 1, 'runtime' => 50]]);
    Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'meta' => ['season' => 1, 'episode' => 2, 'runtime' => 50]]);

    expect($series->watchedEpisodeCount())->toBe(2)
        ->and($series->progress())->toBe(20)
        ->and($series->totalRuntimeMinutes())->toBe(150); // 3 watches x 50
});

it('summarises the watch span from first to last watch', function () {
    $series = Series::factory()->create();
    Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'occurred_at' => '2024-01-01 20:00:00', 'meta' => ['season' => 1, 'episode' => 1]]);
    Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'occurred_at' => '2024-09-01 20:00:00', 'meta' => ['season' => 1, 'episode' => 2]]);

    expect($series->watchSpan())->toContain('months');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=SeriesStatsTest`
Expected: FAIL (methods missing).

- [ ] **Step 3: Implement the helpers on `Series`**

```php
public function watchedEpisodeCount(): int
{
    return $this->episodes
        ->map(fn (Media $m): string => ($m->meta['season'] ?? '?').'x'.($m->meta['episode'] ?? '?'))
        ->unique()
        ->count();
}

public function progress(): ?int
{
    $aired = $this->meta['aired_episodes'] ?? null;
    if (! $aired) {
        return null;
    }

    return (int) min(100, round($this->watchedEpisodeCount() / $aired * 100));
}

public function firstWatchedAt(): ?\Carbon\CarbonInterface
{
    return $this->episodes->min('occurred_at');
}

public function lastWatchedAt(): ?\Carbon\CarbonInterface
{
    return $this->episodes->max('occurred_at');
}

public function watchSpan(): ?string
{
    $first = $this->firstWatchedAt();
    $last = $this->lastWatchedAt();
    if (! $first || ! $last) {
        return null;
    }

    return $first->equalTo($last)
        ? 'in one day'
        : $first->diffForHumans($last, ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE, 'parts' => 1]);
}

public function totalRuntimeMinutes(): int
{
    return (int) $this->episodes->sum(fn (Media $m): int => (int) ($m->meta['runtime'] ?? 0));
}
```
Note: `min`/`max` on an Eloquent collection return the `Carbon` values because `occurred_at` is cast to datetime. If `episodes` is not loaded, these trigger a query; controllers should eager-load `episodes` before rendering.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=SeriesStatsTest`
Expected: PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Series.php tests/Feature/SeriesStatsTest.php
git commit -m "feat: Series progress, watch span, and total runtime stats"
```

---

### Task 5: `FetchTraktPoster` queued job

**Files:**
- Create: `app/Jobs/FetchTraktPoster.php`
- Test: `tests/Feature/FetchTraktPosterTest.php`

**Interfaces:**
- Produces: `FetchTraktPoster` (`implements ShouldQueue`), constructor `(Model $subject, string $posterUrl)` where `$subject` is a `Series` or a film `Media` (both use `HasAttachments`). Downloads the poster and stores it in the `cover` collection (clear-then-store for idempotency).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Jobs\FetchTraktPoster;
use App\Models\Series;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('downloads a poster into the cover collection', function () {
    Storage::fake(config('media-library.disk_name'));
    Http::fake(['*' => Http::response(file_get_contents(base_path('tests/Fixtures/pixel.webp')), 200)]);

    $series = Series::factory()->create();
    (new FetchTraktPoster($series, 'https://walter-r2.trakt.tv/posters/x.jpg.webp'))->handle();

    expect($series->fresh()->getFirstMedia('cover'))->not->toBeNull();
});
```
(Create `tests/Fixtures/pixel.webp` — any tiny valid image file — as part of this task.)

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=FetchTraktPosterTest`
Expected: FAIL (job missing).

- [ ] **Step 3: Implement the job**

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;

/**
 * Downloads a Trakt/TMDB poster and caches it in the subject's `cover`
 * collection on R2. Trakt forbids hotlinking, so the image must live in our
 * own storage. Clears the collection first so a re-sync stays idempotent.
 */
class FetchTraktPoster implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private Model&HasMedia $subject, private string $posterUrl) {}

    public function handle(): void
    {
        $url = Str::startsWith($this->posterUrl, 'http') ? $this->posterUrl : 'https://'.$this->posterUrl;
        $response = Http::get($url);

        if ($response->failed()) {
            return;
        }

        $this->subject->clearMediaCollection('cover');
        $this->subject->addMediaFromString($response->body())
            ->usingFileName(Str::uuid().'.webp')
            ->toMediaCollection('cover');
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=FetchTraktPosterTest`
Expected: PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Jobs/FetchTraktPoster.php tests/Feature/FetchTraktPosterTest.php tests/Fixtures/pixel.webp
git commit -m "feat: queued Trakt poster download to R2"
```

---

### Task 6: `trakt:sync` command + schedule

**Files:**
- Create: `app/Console/Commands/Sync/TraktSync.php`
- Modify: `routes/console.php` (schedule)
- Test: `tests/Feature/TraktSyncTest.php`

**Interfaces:**
- Consumes: `Trakt` (Task 1), `Series`/`Media` (Task 2/3), `FetchTraktPoster` (Task 5).
- Produces: `trakt:sync {--days=7} {--full}`. Pages both `movies` and `episodes` history; dedups incoming history ids against existing `Media.source_id` for `source='trakt'`; creates film/episode rows with canonical `type`/`meta`; resolves each episode's `Series` by `trakt_id` (generating the persisted slug on first create and refreshing `meta.aired_episodes`/`meta.seasons` every run from `Trakt::show`); converts `watched_at` (UTC) to `Europe/London` wall-clock; dispatches `FetchTraktPoster` for new shows/films.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Jobs\FetchTraktPoster;
use App\Models\Media;
use App\Models\Series;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.trakt.client_id', 'k');
    config()->set('services.trakt.username', 'taylor');
    Bus::fake();
});

function fakeTraktHistory(array $movies, array $episodes): void
{
    Http::fake(function ($request) use ($movies, $episodes) {
        return match (true) {
            str_contains($request->url(), '/history/movies') => Http::response($request['page'] == 1 ? $movies : [], 200),
            str_contains($request->url(), '/history/episodes') => Http::response($request['page'] == 1 ? $episodes : [], 200),
            str_contains($request->url(), '/shows/') => Http::response(['aired_episodes' => 20, 'ids' => ['slug' => 'severance'], 'title' => 'Severance'], 200),
            default => Http::response([], 200),
        };
    });
}

it('imports films and episodes, groups same-name shows by distinct trakt id, and dedupes on re-run', function () {
    fakeTraktHistory(
        movies: [[
            'id' => 501, 'watched_at' => '2024-01-01T20:00:00.000Z', 'action' => 'watch', 'type' => 'movie',
            'movie' => ['title' => 'Dune', 'year' => 2021, 'runtime' => 155, 'ids' => ['trakt' => 9, 'slug' => 'dune-2021', 'tmdb' => 438631]],
        ]],
        episodes: [
            ['id' => 601, 'watched_at' => '2024-02-01T20:00:00.000Z', 'action' => 'watch', 'type' => 'episode',
             'episode' => ['season' => 1, 'number' => 1, 'title' => 'Good News', 'runtime' => 50, 'ids' => ['trakt' => 111]],
             'show' => ['title' => 'The Office', 'year' => 2005, 'ids' => ['trakt' => 700, 'slug' => 'the-office-us', 'tmdb' => 2316]]],
            ['id' => 602, 'watched_at' => '2024-02-01T21:00:00.000Z', 'action' => 'watch', 'type' => 'episode',
             'episode' => ['season' => 1, 'number' => 1, 'title' => 'Downsize', 'runtime' => 30, 'ids' => ['trakt' => 222]],
             'show' => ['title' => 'The Office', 'year' => 2001, 'ids' => ['trakt' => 800, 'slug' => 'the-office', 'tmdb' => 2996]]],
        ],
    );

    $this->artisan('trakt:sync', ['--full' => true])->assertSuccessful();

    expect(Media::where('type', 'film')->count())->toBe(1)
        ->and(Media::where('type', 'episode')->count())->toBe(2)
        ->and(Series::count())->toBe(2) // two distinct shows despite identical title
        ->and(Series::pluck('slug')->sort()->values()->all())->toEqual(['the-office', 'the-office-2005']);

    // Re-run creates nothing new.
    $this->artisan('trakt:sync', ['--full' => true])->assertSuccessful();
    expect(Media::count())->toBe(3);

    Bus::assertDispatched(FetchTraktPoster::class); // posters queued for new shows/film
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=TraktSyncTest`
Expected: FAIL (command missing).

- [ ] **Step 3: Implement the command**

Mirror `StravaSync` structure: `#[Signature('trakt:sync {--days=7 : Days back to fetch} {--full : Backfill entire history}')]`, `#[Description('Sync Trakt watch history to the media timeline')]`, inject `Trakt` into `handle()`. Page each type via a `while (true)` loop until an empty batch. For each item not already in `Media` (`source='trakt'`), create the row; for episodes resolve/refresh the `Series`. Key logic:

```php
public function handle(Trakt $trakt): int
{
    $startAt = $this->option('full') ? null : now()->subDays((int) $this->option('days'))->toIso8601ZuluString();

    $existing = Media::query()->where('source', 'trakt')->pluck('source_id')->flip();

    $this->importMovies($trakt, $startAt, $existing);
    $this->importEpisodes($trakt, $startAt, $existing);

    return self::SUCCESS;
}
```
- Paging helper `fetchAllPages(Trakt $trakt, string $type, ?string $startAt): array` mirrors `StravaSync::fetchActivities`.
- `occurred_at`: `Carbon::parse($watchedAt, 'UTC')->setTimezone('Europe/London')->format('Y-m-d H:i:s')`, with `timezone = 'Europe/London'`. (Media has no `timezone` column today — if `occurredAtForDisplay()`/`timezone()` on the model expect one, store the tz in `meta['timezone']` and confirm the timeline reads it; otherwise store `occurred_at` in local wall-clock and leave display as-is. The implementer must check `HasTimelineEntry`/`Timelineable` for how `timezone()` resolves for Media and follow it. Do NOT invent a column.)
- Film row:
```php
Media::create([
    'occurred_at' => $occurredAt,
    'type' => 'film',
    'title' => $movie['title'],
    'source' => 'trakt',
    'source_id' => (string) $item['id'],
    'meta' => [
        'year' => $movie['year'] ?? null,
        'runtime' => $movie['runtime'] ?? null,
        'ids' => $movie['ids'] ?? [],
    ],
]);
// dispatch FetchTraktPoster with the movie poster (item.movie.images.poster[0] or Trakt::movie fallback)
```
- Episode row: resolve series first:
```php
$series = Series::firstOrNew(['trakt_id' => $show['ids']['trakt']]);
if (! $series->exists) {
    $series->fill([
        'slug' => Series::slugFor($show['title'], $show['year'] ?? null,
            fn (string $slug): bool => Series::where('slug', $slug)->exists()),
        'title' => $show['title'],
        'year' => $show['year'] ?? null,
        'overview' => $show['overview'] ?? null,
    ]);
}
// refresh aired_episodes/seasons every run from the show summary (extended=full already on history, else Trakt::show)
$series->meta = array_merge($series->meta ?? [], [
    'ids' => $show['ids'] ?? [],
    'aired_episodes' => $show['aired_episodes'] ?? ($series->meta['aired_episodes'] ?? null),
]);
$wasNew = ! $series->exists;
$series->save();
if ($wasNew) { /* dispatch FetchTraktPoster for the show poster */ }

Media::create([
    'occurred_at' => $occurredAt,
    'type' => 'episode',
    'title' => $episode['title'] ?? "Episode {$episode['number']}",
    'series_id' => $series->id,
    'source' => 'trakt',
    'source_id' => (string) $item['id'],
    'meta' => [
        'season' => $episode['season'],
        'episode' => $episode['number'],
        'episode_title' => $episode['title'] ?? null,
        'show_title' => $show['title'],
        'show_slug' => $show['ids']['slug'] ?? null,
        'runtime' => $episode['runtime'] ?? null,
        'ids' => $episode['ids'] ?? [],
    ],
]);
```
Poster URL extraction: prefer `item['movie']['images']['poster'][0]` / `item['show']['images']['poster'][0]` (from `extended=full`); if absent, call `Trakt::movie`/`Trakt::show` once and read its `images.poster[0]`. Only dispatch when a URL is found.

Schedule in `routes/console.php`:
```php
use Illuminate\Support\Facades\Schedule;
Schedule::command('trakt:sync')->daily();
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=TraktSyncTest`
Expected: PASS.

- [ ] **Step 5: Full suite guard + Pint + commit**

Run: `php artisan test --compact` (only `ImportEventsTest` should fail).
```bash
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/Sync/TraktSync.php routes/console.php tests/Feature/TraktSyncTest.php
git commit -m "feat: trakt:sync command with backfill and scheduled incremental sync"
```

---

### Task 7: Timeline binge collapse

**Files:**
- Modify: `app/Actions/BuildTimelineFeed.php`
- Test: `tests/Feature/TimelineCollapseTest.php`

**Interfaces:**
- Consumes: `Media` episodes with `series_id` (Task 2/3).
- Produces: within `groupByDay`, multiple same-`series_id` episode entries on one day collapse into a single synthesized card; single episodes, films, and non-media entries are unchanged. Synthesized card shape mirrors `cardItem()` keys plus `count`, with `url` pointing at `/media/tv/{slug}#watch-{date}`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Actions\BuildTimelineFeed;
use App\Models\Media;
use App\Models\Series;

it('collapses a same-show same-day binge into one card', function () {
    $series = Series::factory()->create(['slug' => 'severance', 'title' => 'Severance']);
    collect([1, 2, 3])->each(fn ($n) => Media::factory()->create([
        'series_id' => $series->id, 'type' => 'episode',
        'occurred_at' => "2024-03-01 2{$n}:00:00",
        'meta' => ['season' => 1, 'episode' => $n, 'show_title' => 'Severance'],
    ]));

    $feed = app(BuildTimelineFeed::class);
    $day = $feed->groupByDay(App\Models\TimelineEntry::query()->with('timelineable')->get())[0];

    $severance = collect($day['items'])->firstWhere('title', 'Severance');
    expect($severance['count'])->toBe(3)
        ->and($severance['url'])->toBe('/media/tv/severance#watch-2024-03-01');
});

it('leaves a single episode and a film as their own cards', function () {
    $series = Series::factory()->create();
    Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'occurred_at' => '2024-04-01 20:00:00', 'meta' => ['season' => 1, 'episode' => 1]]);
    Media::factory()->create(['type' => 'film', 'occurred_at' => '2024-04-01 22:00:00', 'meta' => ['year' => 2021]]);

    $day = app(BuildTimelineFeed::class)->groupByDay(App\Models\TimelineEntry::query()->with('timelineable')->get())[0];
    expect(collect($day['items'])->pluck('count')->filter())->toBeEmpty();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=TimelineCollapseTest`
Expected: FAIL (no collapse; three separate cards).

- [ ] **Step 3: Implement the collapse in `groupByDay`**

Before mapping the per-day `$group` to `cardItem()`, partition out episode entries by `series_id`; fold groups of >1 into one synthesized item, keep the rest as `cardItem()`. Add a private helper:
```php
private function collapseEpisodes(Collection $group, string $date): array
{
    [$episodes, $rest] = $group->partition(fn (TimelineEntry $e): bool =>
        $e->timelineable instanceof Media && $e->timelineable->type === 'episode' && $e->timelineable->series_id);

    $items = $rest->map(fn (TimelineEntry $e): array => $this->cardItem($e))->all();

    foreach ($episodes->groupBy(fn (TimelineEntry $e) => $e->timelineable->series_id) as $seriesEntries) {
        if ($seriesEntries->count() === 1) {
            $items[] = $this->cardItem($seriesEntries->first());

            continue;
        }
        $items[] = $this->synthesiseSeriesCard($seriesEntries, $date);
    }

    return collect($items)->sortByDesc('datetime')->values()->all();
}

private function synthesiseSeriesCard(Collection $entries, string $date): array
{
    $first = $entries->first()->timelineable;
    $series = $first->series;
    $count = $entries->count();
    $local = LocalTime::for($first->occurredAtForDisplay(), $first->timezone());

    return [
        'iconKey' => 'media',
        'accent' => 'media',
        'title' => $series?->title ?? $first->meta['show_title'] ?? $first->title,
        'meta' => "{$count} episodes",
        'count' => $count,
        'time' => $local['time'],
        'datetime' => $local['iso'],
        'label' => $local['label'],
        'offset' => $local['offset'],
        'url' => $series ? "/media/tv/{$series->slug}#watch-{$date}" : null,
    ];
}
```
Then in `groupByDay`, replace the `'items' => $group->map(...cardItem...)` line with `'items' => $this->collapseEpisodes($group, $group->first()->occurred_at->format('Y-m-d'))`. Import `App\Models\Media` and `Illuminate\Support\Collection` if not already present. Ensure `series` is available without an N+1: eager-load `timelineable.series` where the feed query is built, or accept the lazy load given the small per-day count (note which you chose).

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=TimelineCollapseTest`
Expected: PASS.

- [ ] **Step 5: Full suite guard + Pint + commit**

Run: `php artisan test --compact` (timeline feed is widely used; confirm nothing else broke).
```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/BuildTimelineFeed.php tests/Feature/TimelineCollapseTest.php
git commit -m "feat: collapse same-show same-day episodes into one timeline card"
```

---

### Task 8: `SeriesController` + routes (index / show / season / episode)

**Files:**
- Create: `app/Http/Controllers/SeriesController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/SeriesPagesTest.php`

**Interfaces:**
- Consumes: `Series`/`Media` + stats (Task 2/3/4).
- Produces four routes under `/media/tv`, registered **before** the `TypeRegistry` archive loop:
  - `GET /media/tv` → `index` → `Inertia::render('Media/SeriesIndex', ['series' => [...]])`.
  - `GET /media/tv/{series:slug}` → `show`.
  - `GET /media/tv/{series:slug}/season-{season}` → `season` (constrain `season` to digits).
  - `GET /media/tv/{series:slug}/season-{season}/episode-{episode}` → `episode`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Media;
use App\Models\Series;
use Inertia\Testing\AssertableInertia as Assert;

it('lists shows on the tv index', function () {
    $series = Series::factory()->create(['title' => 'Severance', 'slug' => 'severance']);
    Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'meta' => ['season' => 1, 'episode' => 1]]);

    $this->get('/media/tv')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Media/SeriesIndex')->has('series', 1));
});

it('shows a series with its episodes and stats', function () {
    $series = Series::factory()->create(['slug' => 'severance', 'meta' => ['aired_episodes' => 9]]);
    Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'meta' => ['season' => 1, 'episode' => 1]]);

    $this->get('/media/tv/severance')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Media/SeriesShow')->where('series.slug', 'severance')->has('stats'));
});

it('filters to a season and to a single episode across all its watches', function () {
    $series = Series::factory()->create(['slug' => 'the-good-doctor']);
    Media::factory()->count(2)->create(['series_id' => $series->id, 'type' => 'episode', 'meta' => ['season' => 6, 'episode' => 8]]); // watched twice
    Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'meta' => ['season' => 7, 'episode' => 1]]);

    $this->get('/media/tv/the-good-doctor/season-6')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Media/SeriesSeason'));
    $this->get('/media/tv/the-good-doctor/season-6/episode-8')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Media/SeriesEpisode')->has('watches', 2));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=SeriesPagesTest`
Expected: FAIL (routes 404).

- [ ] **Step 3: Implement the controller + routes**

`SeriesController` methods eager-load `episodes` (for stats), build view models (episodes grouped by season then `Y-m-d` watch-date with anchors), and pass `stats` (`episodesWatched`, `seasons`, `progress`, `watchSpan`, `totalHours`). `episode()` collects all `Media` rows matching `series_id` + `meta->season` + `meta->episode` ordered by `occurred_at` as `watches`.

In `routes/web.php`, **above** the `foreach (TypeRegistry::all() ...)` loop:
```php
Route::get('/media/tv', [SeriesController::class, 'index'])->name('series.index');
Route::get('/media/tv/{series:slug}', [SeriesController::class, 'show'])->name('series.show');
Route::get('/media/tv/{series:slug}/season-{season}', [SeriesController::class, 'season'])
    ->where('season', '[0-9]+')->name('series.season');
Route::get('/media/tv/{series:slug}/season-{season}/episode-{episode}', [SeriesController::class, 'episode'])
    ->where(['season' => '[0-9]+', 'episode' => '[0-9]+'])->name('series.episode');
```
(The archive loop still registers `/media/{value}`; the explicit `/media/tv` above wins because it is declared first.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=SeriesPagesTest`
Expected: PASS (components will resolve once Task 9 adds the Vue files; if Inertia testing requires the page to exist, create minimal stub `.vue` files here and flesh them out in Task 9, or run Task 9's page creation first. Prefer creating stubs now so this task's tests pass independently.)

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/SeriesController.php routes/web.php tests/Feature/SeriesPagesTest.php resources/js/Pages/Media
git commit -m "feat: series index/show/season/episode routes and controller"
```

---

### Task 9: Vue pages + components + browser test

**Files:**
- Create/flesh out: `resources/js/Pages/Media/SeriesIndex.vue`, `SeriesShow.vue`, `SeriesSeason.vue`, `SeriesEpisode.vue`, `resources/js/Components/Ui/PosterCard.vue`, `EpisodeRow.vue`, `SeriesStats.vue`
- Test: `tests/Browser/SeriesPageTest.php`

**Interfaces:**
- Consumes: props from `SeriesController` (Task 8).

- [ ] **Step 1: Write the failing browser test**

```php
<?php

use App\Models\Media;
use App\Models\Series;

it('renders the series page with episode rows grouped by date', function () {
    $series = Series::factory()->create(['title' => 'Severance', 'slug' => 'severance', 'meta' => ['aired_episodes' => 9, 'seasons' => 1]]);
    Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'title' => 'Good News About Hell', 'occurred_at' => '2024-03-01 20:00:00', 'meta' => ['season' => 1, 'episode' => 1, 'runtime' => 50]]);

    $page = visit('/media/tv/severance');

    $page->assertSee('Severance')
        ->assertPresent('[data-testid="series-stats"]')
        ->assertPresent('[data-testid="episode-row"]');
});

it('renders the tv index as a poster grid', function () {
    $series = Series::factory()->create(['slug' => 'severance']);
    Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'meta' => ['season' => 1, 'episode' => 1]]);

    visit('/media/tv')->assertPresent('[data-testid="poster-card"]');
});
```
(Asserts rendered DOM elements via `data-testid`, per the DOM-assertion convention, not prop text.)

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=SeriesPageTest`
Expected: FAIL (pages are stubs / missing testids).

- [ ] **Step 3: Build the Vue pages and components**

- `PosterCard.vue` — `[data-testid="poster-card"]`, 2:3 portrait poster (`getFirstMediaUrl('cover','card')` passed from the controller, with a graceful placeholder when absent), title, year, and a progress indicator. Links to `/media/tv/{slug}`.
- `SeriesStats.vue` — `[data-testid="series-stats"]`, one row: episodes watched, seasons, progress %, watch span, total hours. Use commas between stats (no separators/`·`).
- `EpisodeRow.vue` — `[data-testid="episode-row"]`, one episode watch: SxEy, title, time. Links to the episode page.
- `SeriesIndex.vue` — responsive poster grid of `PosterCard` (collapse gracefully on small screens; standard Tailwind grid scale).
- `SeriesShow.vue` — hero (poster, title, year, overview, progress bar, Trakt `ExternalLink`), `SeriesStats`, then episodes grouped by season and by watch-date, each date group wrapped in `<section :id="'watch-' + date">` (the collapse anchor), rendering `EpisodeRow`s.
- `SeriesSeason.vue` / `SeriesEpisode.vue` — reuse `SeriesStats` (scoped to the season for `SeriesSeason`) and `EpisodeRow`; `SeriesEpisode` lists every watch instance from `watches`.
- Icons via `<Icon name="..." />`; focus-visible rings mirror hover on all links; comment non-obvious computeds.

- [ ] **Step 4: Build assets + run the browser test**

Run: `npm run build` then `php artisan test --compact --filter=SeriesPageTest`
Expected: PASS.

- [ ] **Step 5: Full suite + Pint + commit**

Run: `php artisan test --compact` (expect only `ImportEventsTest` to fail).
```bash
vendor/bin/pint --dirty --format agent
git add resources/js tests/Browser/SeriesPageTest.php
git commit -m "feat: series index/show/season/episode Vue pages"
```

---

## Final review

After Task 9, dispatch the whole-branch code review (superpowers:requesting-code-review) over `origin/master...HEAD`, fix Critical/Important findings, then finish the branch (push, open PR titled per the spec, do not merge without Taylor's say-so).
