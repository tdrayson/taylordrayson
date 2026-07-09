# Conventions Cleanup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Apply five user-decided standardisations: `source`/`source_id` sync keys everywhere, a real publish gate for articles, drop `projects.started_at`, relational tags shared by articles/notes/projects, and fuel-station resolution (name + brand + address) at log time.

**Architecture:** Continues the API-first branch (`feature/api-first`). Schema changes ship as reversible migrations run against the real dev SQLite DB. New domain logic lives in actions/support classes, not controllers. `meta` JSON columns are a KEEPER by explicit user decision (per-type extension point) — do not remove or "clean up" meta.

**Tech Stack:** Laravel 13, PHP 8.4, Pest 4, Inertia v3/Vue 3.

## Global Constraints

- The dev database contains real data (1,464 activities with Strava sync keys). Migrations must preserve values; verify with read queries after migrating.
- Naming: the sync identity pair is `source` + `source_id` on every synced type. The word is "media", never "media-log".
- Publication state is a positive `published` boolean; unpublished articles must be invisible to guests on EVERY public surface (timeline, month/day pages, archives, entry pages, feeds, search) and visible to the authenticated user.
- Timezone, units, auth conventions from docs/superpowers/plans/2026-07-04-api-v1-foundation.md Global Constraints still bind.
- Explicit return types; PHPDoc over inline comments; Form Requests for validation; `vendor/bin/pint --dirty --format agent` before every commit; conventional commits, no attribution footer.
- Do NOT delete tests; update only what a rename/contract change requires.
- NEVER use `git add -A` (unrelated untracked plan doc in repo); stage files by path.

---

### Task 1: Rename platform_type/platform_id to source/source_id

**Files:**
- Create: `database/migrations/<timestamp>_rename_platform_columns_to_source.php`
- Modify: `app/Models/Activity.php`, `app/Models/Checkin.php`, `app/Models/Media.php` (fillables, any accessors)
- Modify: `app/Http/Controllers/EntryController.php:156` (`$model->platform_type ?? $model->source` collapses to `$model->source ?? null`)
- Modify: commands `app/Console/Commands/Sync/{StravaSync,StravaPhotos,StravaPolylines,BackfillStravaDescriptions,BackfillStravaTimezones}.php`, `app/Console/Commands/Import/{FoursquareImport,ImportActivityDescriptions}.php`
- Modify: tests `BackfillStravaDescriptionsTest, BackfillStravaTimezonesTest, EntryViewTest, FoursquareImportTest, HealthHeartRateTest, ImportActivityDescriptionsTest, StravaPhotosTest, StravaSyncTest`
- Test: extend `tests/Feature/StravaSyncTest.php` expectations to the new names (no new file needed)

**Interfaces:**
- Produces: `activities.source`/`activities.source_id`, `checkins.source`/`checkins.source_id`, `media.source`/`media.source_id` (unique pair index preserved). Calories already conform; sleep keeps its `source` provenance column unchanged.

- [ ] **Step 1: Migration.** `php artisan make:migration rename_platform_columns_to_source --no-interaction`, then for each of `activities`, `checkins`, `media`: drop the existing unique index on `[platform_type, platform_id]` (find its actual name via `SELECT name FROM sqlite_master WHERE type='index'`), `renameColumn('platform_type', 'source')`, `renameColumn('platform_id', 'source_id')`, recreate `$table->unique(['source', 'source_id'])`. Reversible `down()`. Run `php artisan migrate --no-interaction`, then sanity-check: `SELECT source, COUNT(*) FROM activities GROUP BY source` should show `strava` with ~1464 rows.
- [ ] **Step 2: Code sweep.** `grep -rn "platform_type\|platform_id" app/ tests/` and rename every reference (updateOrCreate keys, where clauses, fillables, test seeds/assertions). Values stay the same ('strava', 'swarm'). Any `platform_url` accessor keeps its name (it's a display concept, not the sync key) — only the two key columns rename.
- [ ] **Step 3: Gate.** `grep -rn "platform_type\|platform_id" app/ resources/js tests/` returns nothing (only old migrations under database/migrations may reference them). Full suite green.
- [ ] **Step 4: Commit** `refactor: standardise sync identity columns to source/source_id`.

---

### Task 2: Article publish gate on every public surface

**Files:**
- Modify: `app/Models/Concerns/Timelineable.php` (add contract method), `app/Models/Concerns/HasTimelineEntry.php` (default true)
- Modify: `app/Models/Article.php` (override), `app/Observers/TimelineEntryObserver.php`
- Modify: `app/Http/Controllers/EntryController.php` (guest 404 for unpublished article entry pages)
- Modify: `app/Search/` (article queries exclude unpublished for guests — find the seam where the article model query is built and add the conditional filter)
- Test: `tests/Feature/ArticlePublishGateTest.php` (new)

**Interfaces:**
- Produces: `Timelineable::shouldAppearOnTimeline(): bool` (trait default `true`; `Article` returns `(bool) ($this->attributes['published'] ?? false)`). `TimelineEntryObserver::saved()` deletes any existing timeline entry and skips creation when `shouldAppearOnTimeline()` is false — so unpublished articles have NO `timeline_entries` row, which removes them from timeline, month/day, archives, and feeds in one mechanism (they all read the spine). Publishing an article (saving with published=true) creates the entry.

- [ ] **Step 1: Failing tests.** Create `tests/Feature/ArticlePublishGateTest.php`:

```php
<?php

use App\Models\Article;
use App\Models\User;

it('creates no timeline entry for an unpublished article', function () {
    $article = Article::factory()->create(['published' => false]);

    expect($article->timelineEntry)->toBeNull();
});

it('creates the timeline entry when the article is published', function () {
    $article = Article::factory()->create(['published' => false]);
    $article->update(['published' => true]);

    expect($article->fresh()->timelineEntry)->not->toBeNull();
});

it('removes the timeline entry when an article is unpublished', function () {
    $article = Article::factory()->create(['published' => true]);
    $article->update(['published' => false]);

    expect($article->fresh()->timelineEntry)->toBeNull();
});

it('404s an unpublished article entry page for guests but shows it when authenticated', function () {
    $article = Article::factory()->create(['published' => false]);
    $url = $article->url();

    $this->get($url)->assertNotFound();
    $this->actingAs(User::factory()->create())->get($url)->assertSuccessful();
});

it('shows a published article entry page to guests', function () {
    $article = Article::factory()->create(['published' => true]);

    $this->get($article->url())->assertSuccessful();
});
```

- [ ] **Step 2: Contract + observer.** Add `public function shouldAppearOnTimeline(): bool;` to the `Timelineable` interface with a trait default returning `true` in `HasTimelineEntry`. Article overrides it reading the raw attribute. In `TimelineEntryObserver::saved()`, before the upsert: `if (! $model->shouldAppearOnTimeline()) { $model->timelineEntry()->delete(); return; }`.
- [ ] **Step 3: Entry page + search.** In `EntryController`, where the article model is resolved for its entry page, 404 for guests when `! $article->published` (mirror `PageController`'s pattern: `! $model->published && ! Auth::check()`). In the search layer, find where Article results are queried (`app/Search/`) and exclude unpublished articles for guests. Read the compiler/schema to find the correct seam; keep it conditional on `Auth::check()`.
- [ ] **Step 4: Verify + commit.** Suite green (existing article tests may seed `published => false` randomly via `fake()->boolean(90)` — pin `published => true` in any test that asserts an article is publicly visible). Commit `feat: unpublished articles are invisible to guests on all public surfaces`.

---

### Task 3: Drop projects.started_at

**Files:**
- Create: `database/migrations/<timestamp>_drop_started_at_from_projects.php` (`dropColumn('started_at')`; down re-adds nullable datetime)
- Modify: `app/Models/Project.php` (fillable + cast), `database/factories/ProjectFactory.php`

- [ ] **Step 1:** Migration + remove the two model references + factory line. `occurred_at` is the single project date.
- [ ] **Step 2:** `grep -rn "started_at" app/ resources/js tests/ database/factories` returns nothing; migrate; suite green; commit `refactor: drop redundant projects.started_at (occurred_at is the project date)`.

---

### Task 4: Unified relational tags (articles, notes, projects)

**Files:**
- Create: `database/migrations/<timestamp>_create_tags_tables.php`, `app/Models/Tag.php`, `app/Models/Concerns/HasTags.php`
- Create: `database/migrations/<timestamp>_drop_json_tags_columns.php` (articles + projects)
- Modify: `app/Models/Article.php`, `app/Models/Note.php`, `app/Models/Project.php` (use HasTags; remove `tags` from fillable/casts)
- Modify: `app/Timeline/TypeRegistry.php` (`tags()` taxonomy switches from JSON scan to relations; register the taxonomy on the `note` type too)
- Modify: `app/Http/Requests/Api/V1/StoreNoteRequest.php`, `UpdateNoteRequest.php` (accept `tags` array), `app/Actions/Notes/CreateNote.php`, `UpdateNote.php` (sync tags), `app/Http/Resources/V1/NoteResource.php` (return tag names)
- Modify: `database/factories/ArticleFactory.php`, `ProjectFactory.php` (stop setting a `tags` column; where tests need tags, attach via the trait)
- Modify: `app/Search/` if it references the JSON tags column (check `SearchSchema`/`SearchPresets`)
- Test: `tests/Feature/TagsTest.php` (new) + additions to `tests/Feature/Api/NoteApiTest.php`

**Interfaces:**
- Produces: `tags` table (`id`, `name` string, `slug` string unique, timestamps); `taggables` morph pivot (`tag_id` FK, `taggable_type`, `taggable_id`, unique triple). `Tag` model (fillable name/slug). Trait `HasTags`:

```php
<?php

namespace App\Models\Concerns;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

trait HasTags
{
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    /**
     * Sync by human-readable names, creating tags on first use.
     *
     * @param  list<string>  $names
     */
    public function syncTagNames(array $names): void
    {
        $ids = collect($names)
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->unique(fn (string $name): string => Str::slug($name))
            ->map(fn (string $name): int => Tag::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            )->id);

        $this->tags()->sync($ids->all());
    }

    /** @return list<string> */
    public function tagNames(): array
    {
        return $this->tags->pluck('name')->all();
    }
}
```

- `TypeRegistry::tags()` reimplemented over relations, same returned array shape (base/param/label/title/filter/labelFor/values):
  - `filter`: `fn (Builder $query, string $value) => $query->whereHas('tags', fn ($t) => $t->where('slug', $value))`
  - `values`: tags that are attached to at least one record of `$model`, `['value' => slug, 'label' => name]`, sorted by name
  - `labelFor`: look the slug up in the same set, fall back to `Str::headline($value)`
- Note API: `tags` optional array of strings (`['sometimes', 'array']`, `'tags.*' => ['string', 'max:50']`); `CreateNote`/`UpdateNote` call `syncTagNames` when the key is present; `NoteResource` adds `'tags' => $this->tagNames()`.

- [ ] **Step 1: Failing tests** (TagsTest: trait sync/dedupe-by-slug/firstOrCreate reuse across models; NoteApiTest: create note with tags returns them, same tag name reused across notes yields one Tag row). Write them, watch them fail.
- [ ] **Step 2: Tables, model, trait** as specified. Migrate.
- [ ] **Step 3: Wire models + API + TypeRegistry**; the tags-taxonomy archive routes for articles/projects must keep working (existing archive tests cover them — they will fail until the TypeRegistry rewrite lands, then pass; update seeds that set a `tags` column to attach via `syncTagNames` instead).
- [ ] **Step 4: Drop JSON columns** (articles.tags, projects.tags) in the second migration only after the suite is green on the relational path.
- [ ] **Step 5: Gate.** `grep -rn "whereJsonContains" app/` returns nothing; `grep -rn "'tags'" app/Models` shows no fillable/cast leftovers; full suite green; `npm run build` if any Vue changed. Commit `feat: unified relational tags for articles, notes, and projects`.

---

### Task 5: Fuel stations - brand, address, resolve-by-name

**Files:**
- Create: `database/migrations/<timestamp>_add_brand_and_address_to_fuel_stations.php`
- Create: `app/Actions/Fuel/ResolveFuelStation.php`
- Modify: `app/Models/FuelStation.php` (fillable), `database/factories/FuelStationFactory.php`
- Test: `tests/Feature/ResolveFuelStationTest.php` (new)

**Interfaces:**
- Produces: `fuel_stations.brand` (nullable string, e.g. "BP", "Shell") and `fuel_stations.address` (nullable string) after `name`. `ResolveFuelStation::__invoke(array $attributes): FuelStation` — the seam the Plan 4 fuel API vertical will call when a fuel log passes a station by name:

```php
<?php

namespace App\Actions\Fuel;

use App\Models\FuelStation;

class ResolveFuelStation
{
    /**
     * Find or create a station by name so fuel logs can reference stations
     * without pre-registering them. Matching is case-insensitive on name;
     * any newly supplied detail (brand, address, city, country, lat/lng)
     * fills gaps on the existing record but never overwrites a stored value.
     *
     * @param  array{name: string, brand?: ?string, address?: ?string, city?: ?string, country?: ?string, latitude?: ?float, longitude?: ?float}  $attributes
     */
    public function __invoke(array $attributes): FuelStation
    {
        $station = FuelStation::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($attributes['name']))])
            ->first();

        if ($station === null) {
            return FuelStation::create($attributes);
        }

        $station->fill(
            collect($attributes)
                ->except('name')
                ->filter(fn ($value, string $key): bool => $value !== null && $station->getAttribute($key) === null)
                ->all()
        )->save();

        return $station->refresh();
    }
}
```

(Note: the closure references `$station`; adjust to `use ($station)` or arrow-fn capture as needed when implementing.)

- [ ] **Step 1: Failing tests** covering: creates a new station with brand+address; matches an existing station case-insensitively and reuses it; fills a missing brand on the existing record without overwriting a stored address; never overwrites non-null stored values.
- [ ] **Step 2: Migration + fillable + factory** (`brand` => random of BP/Shell/Esso/Texaco/Tesco, `address` => fake()->streetAddress()).
- [ ] **Step 3: Implement action**, tests pass, full suite, commit `feat: fuel stations gain brand/address and resolve-by-name action`.

---

## Self-Review Notes

- User decisions encoded: source/source_id naming (Task 1), article gate (Task 2), started_at drop (Task 3), relational tags incl. projects for one system (Task 4), fuel station name+brand+address resolution (Task 5). `meta` JSON explicitly retained; nothing here touches it.
- Cross-task type consistency: `shouldAppearOnTimeline(): bool` only consumed by `TimelineEntryObserver`; `HasTags::syncTagNames(array): void` consumed by note actions + factories/tests; `ResolveFuelStation` has no consumer yet by design (Plan 4 fuel vertical wires it).
- Sequencing: tasks are independent except Task 4 Step 4 (drop JSON columns) must follow its own Step 3.
