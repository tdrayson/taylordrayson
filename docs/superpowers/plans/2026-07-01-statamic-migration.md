# Statamic Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add headless Statamic 6 + Runway so genuine content (pages/articles/notes) lives in flat-file collections editable in the Statamic Control Panel, while every Eloquent lifelog/reference model is surfaced in the same CP via Runway, with the Inertia+Vue front-end and data stories unchanged.

**Architecture:** Statamic 6 runs headless inside the existing Laravel 13 app: it owns the CP (`/cp`) and flat-file content storage, but not the front-end. Runway exposes existing Eloquent models in the CP without converting them to entries. Articles/notes move out of Eloquent into Statamic collections (Bard bodies); the timeline, archives, entry pages, feeds, search and OG are re-plumbed to read that content from Statamic via a single `ContentRepository` seam. Everything else stays Eloquent.

**Tech Stack:** Laravel 13, PHP 8.4, Statamic 6 (>=6.5), Runway (rad-pack, Statamic-6 line), Inertia v3, Vue 3, Tailwind v4, Pest 4.

## Global Constraints

- Laravel 13.0.0 / PHP 8.4; Statamic 6 >= 6.5; pin Runway's Statamic-6-compatible release at install (its latest line was pre-release alongside Statamic 6 alphas) — verify version before committing `composer.json`.
- Do NOT use Statamic's eloquent-driver (it is all-or-nothing for entries and precludes flat-file content collections).
- Statamic and Runway front-end routing MUST be disabled; the app's existing routes stay authoritative (catch-all `/{slug}` page route, `/{year}/...` timeline routes).
- Runway resources leave Eloquent models, tables, relationships, accessors, the three data-story builders, and all importers untouched.
- CP auth uses Statamic flat-file users; the public front-end stays unauthenticated.
- Rich content bodies use Bard; the Editor.js `content` (array cast) is converted to Bard, and the Editor.js support (`app/Support/EditorJs.php`, `resources/js/Components/Ui/BlockContent.vue`, `resources/js/Components/Ui/EditorList.vue`) is retired only after content renders correctly.
- No em dashes in any generated copy. Comment Vue/JS (JSDoc) and keep PHP comments minimal. Run `vendor/bin/pint --dirty --format agent` before finalizing any task that changes PHP. Do not run `npm run build` while the Vite watcher is running.
- `data/*.csv` files are precious: back up before any script touches them.
- Every task ends green: `php artisan test --compact` for the touched files, and the existing story tests (`StoryControllerTest`, `FlightStoryTest`, `FoodStoryTest`, `FuelStoryTest`) must not regress.

---

## File Structure

**New (Statamic/Runway config + content):**
- `config/statamic/*.php` — published Statamic config (edit `cp.php`, `routes`, `users.php`, `api.php`)
- `config/runway.php` — Runway resource definitions
- `content/collections/{pages,articles,notes}.yaml` + `content/collections/{pages,articles,notes}/*.md` — flat-file content
- `resources/blueprints/collections/{pages,articles,notes}/*.yaml` — content blueprints
- `resources/blueprints/vendor/runway/*.yaml` — Runway blueprints per model
- `users/*.yaml` — Statamic flat-file CP users

**New (app seam):**
- `app/Content/ContentEntry.php` — read-model DTO wrapping a Statamic content entry, exposing `card()/slug()/url()/occurredAt()` like a `Timelineable`
- `app/Content/ContentRepository.php` — queries Statamic `articles`/`notes`/`pages`; the single seam every touchpoint uses
- `app/Content/BardRenderer.php` — augments a Bard field to HTML for Inertia props
- `app/Console/Commands/MigrateContentToStatamic.php` — one-off Eloquent→Statamic migration (articles/notes/pages)
- `app/Support/EditorJsToBard.php` — converts Editor.js block JSON to Bard (ProseMirror) structure

**Modified:**
- `app/Http/Controllers/PageController.php`, `EntryController.php`, `TimelineController.php`, `SearchController.php`, `OgImageController.php`
- `app/Actions/BuildTimelineFeed.php`
- `app/Models/TimelineEntry.php` (feed items for content via ContentRepository)
- `app/Timeline/TypeRegistry.php` (article/note types read Statamic)
- `resources/js/Components/Entry/ArticleDetail.vue`, `NoteDetail.vue`, `resources/js/Pages/Page.vue` (render Bard HTML)

**Removed at the end:** `app/Models/Article.php`, `app/Models/Note.php`, `app/Models/Page.php`, their factories, the `articles`/`notes`/`pages` tables (migration), Editor.js support files, article/note rows in `timeline_entries`.

---

## Phase 0 — Statamic + Runway foundation

### Task 1: Install Statamic 6 headless

**Files:**
- Modify: `composer.json` (require `statamic/cms`)
- Create: `config/statamic/*.php` (published), `users/` dir
- Modify: `config/statamic/routes.php`, `config/statamic/cp.php`, `config/statamic/users.php`, `.env`
- Test: `tests/Feature/Statamic/InstallSmokeTest.php`

**Interfaces:**
- Produces: a working `/cp` login; `Statamic::pro()`/facades available; no change to existing front-end routes.

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/Statamic/InstallSmokeTest.php
it('serves the control panel login and leaves the timeline intact', function () {
    $this->get('/cp')->assertRedirect('/cp/auth/login');
    $this->get('/cp/auth/login')->assertOk();
    $this->get('/')->assertOk(); // existing timeline still resolves
});
```

- [ ] **Step 2: Run it — expect failure** (`/cp` 404 before install).

Run: `php artisan test --compact --filter=InstallSmokeTest`
Expected: FAIL (404 on `/cp`).

- [ ] **Step 3: Install Statamic into the existing app**

Follow statamic.dev "Install into an existing Laravel application". Commands:

```bash
composer require statamic/cms
php artisan vendor:publish --tag=statamic-config
php please make:user   # create the first flat-file CP user (email + password)
```

- [ ] **Step 4: Configure headless + flat-file users**

- In `config/statamic/routes.php`, ensure Statamic's front-end routing does not add a catch-all that shadows app routes (leave `routes` empty / do not enable the amp or front-end site routing). Confirm the app's `routes/web.php` still loads last.
- In `config/statamic/users.php`, keep `'repository' => 'file'` (flat-file users; default).
- In `config/statamic/cp.php`, set `'enabled' => true`, `'route' => 'cp'`.
- `.env`: `STATAMIC_PRO_ENABLED` as appropriate (Runway needs Pro? verify — Runway is free but multi-site/other features may need Pro; set `false` unless a feature requires it).

- [ ] **Step 5: Run the test — expect pass; run the full suite for regressions**

Run: `php artisan test --compact --filter=InstallSmokeTest` → PASS
Run: `php artisan test --compact` → existing suite green (no route shadowing).

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: install Statamic 6 headless with flat-file CP users"
```

### Task 2: Install Runway and prove one resource end-to-end

**Files:**
- Modify: `composer.json` (require `statamic-rad-pack/runway`)
- Create: `config/runway.php`, `resources/blueprints/vendor/runway/flight.yaml`
- Test: `tests/Feature/Statamic/RunwayFlightResourceTest.php`

**Interfaces:**
- Produces: a `flight` Runway resource listable/editable in the CP, backed by the existing `App\Models\Flight` (unchanged).

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/Statamic/RunwayFlightResourceTest.php
use App\Models\Flight;

it('exposes flights as a runway resource without touching the model', function () {
    $resource = \StatamicRadPack\Runway\Runway::findResource('flight');
    expect($resource->model())->toBeInstanceOf(Flight::class);
    // The model still reads from its own table.
    expect(Flight::query()->count())->toBe(\DB::table('flights')->count());
});
```

- [ ] **Step 2: Run it — expect failure** (Runway not installed / resource missing).

Run: `php artisan test --compact --filter=RunwayFlightResourceTest`

- [ ] **Step 3: Install Runway (pin the Statamic-6 compatible version)**

```bash
composer require statamic-rad-pack/runway
php artisan vendor:publish --tag=runway-config
```

Verify the installed version supports Statamic 6 + Laravel 13; if only a pre-release qualifies, require it explicitly and note it in the commit body.

- [ ] **Step 4: Configure the `flight` resource**

`config/runway.php` `resources` array:

```php
'resources' => [
    \App\Models\Flight::class => [
        'name' => 'Flights',
        'blueprint' => 'flight',
        'route' => null, // no front-end routing (headless)
    ],
],
```

`resources/blueprints/vendor/runway/flight.yaml` — mirror the flights table columns (occurred_at, flight_number, airline_icao, origin_iata, destination_iata, distance_miles, duration, cabin_class, reason) as fields, plus `belongs_to` fields for airline/origin/destination pointing at the airline/airport resources (added in Task 4). Use plain `text`/`select` fields until then.

- [ ] **Step 5: Run the test — expect pass; smoke the CP listing**

Run: `php artisan test --compact --filter=RunwayFlightResourceTest` → PASS
Manual: `/cp/runway/flight` lists flights.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: expose flights in the CP via Runway"
```

---

## Phase 1 — Runway resources for every model

### Task 3: Runway resources for the editable lifelog models

**Files:**
- Modify: `config/runway.php`
- Create: `resources/blueprints/vendor/runway/{activity,calorie,sleep,fuel,podcast,checkin,event,appearance,project}.yaml`
- Test: `tests/Feature/Statamic/RunwayResourcesTest.php`

**Interfaces:**
- Consumes: Runway config pattern from Task 2.
- Produces: a Runway resource per model; existing tables/relationships unchanged.

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/Statamic/RunwayResourcesTest.php
it('registers a runway resource for every editable lifelog model', function (string $handle, string $model) {
    $resource = \StatamicRadPack\Runway\Runway::findResource($handle);
    expect($resource->model())->toBeInstanceOf($model);
})->with([
    ['activity', App\Models\Activity::class],
    ['calorie', App\Models\Calorie::class],
    ['sleep', App\Models\Sleep::class],
    ['fuel', App\Models\Fuel::class],
    ['podcast', App\Models\Podcast::class],
    ['checkin', App\Models\Checkin::class],
    ['event', App\Models\Event::class],
    ['appearance', App\Models\Appearance::class],
    ['project', App\Models\Project::class],
]);
```

- [ ] **Step 2: Run — expect failure** (resources missing).

- [ ] **Step 3: Add each resource to `config/runway.php` and a blueprint per model**

For each model add a `resources` entry (`name`, `blueprint`, `route => null`) and a blueprint whose fields mirror the model's fillable/casts (dates as `date` fields, JSON `meta` as a `textarea`/`array` read field, enums as `select`). Keep it faithful to the table columns; do not add computed fields.

- [ ] **Step 4: Run — expect pass.**

Run: `php artisan test --compact --filter=RunwayResourcesTest` → PASS

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: Runway resources for all editable lifelog models"
```

### Task 4: Read-only Runway resources for airlines/airports + wire flight relations

**Files:**
- Modify: `config/runway.php`, `resources/blueprints/vendor/runway/flight.yaml`
- Create: `resources/blueprints/vendor/runway/{airline,airport}.yaml`
- Test: `tests/Feature/Statamic/RunwayReferenceTest.php`

**Interfaces:**
- Produces: `airline`/`airport` read-only resources; flight `belongs_to` fields resolve to them.

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/Statamic/RunwayReferenceTest.php
it('registers airlines and airports as read-only reference resources', function (string $handle) {
    $resource = \StatamicRadPack\Runway\Runway::findResource($handle);
    expect($resource->readOnly())->toBeTrue();
})->with(['airline', 'airport']);
```

- [ ] **Step 2: Run — expect failure.**

- [ ] **Step 3: Add read-only resources + relations**

`config/runway.php`: add `airline` and `airport` resources with `'read_only' => true`. Update `flight.yaml` airline/origin/destination fields to `belongs_to` pointing at those resources (Runway `belongs_to` fieldtype, keyed by the model's route key — verify Runway relation config against its docs).

- [ ] **Step 4: Run — expect pass.**

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: read-only airline/airport resources + flight relations in CP"
```

---

## Phase 2 — Pages collection (lowest-risk content)

### Task 5: `pages` collection + PageController reads Statamic

**Files:**
- Create: `content/collections/pages.yaml`, `resources/blueprints/collections/pages/page.yaml`, `app/Content/ContentEntry.php`, `app/Content/ContentRepository.php`, `app/Content/BardRenderer.php`
- Modify: `app/Http/Controllers/PageController.php`
- Test: `tests/Feature/Content/PageFromStatamicTest.php`

**Interfaces:**
- Produces:
  - `ContentRepository::page(string $slug): ?ContentEntry`
  - `ContentEntry` methods: `title(): string`, `excerpt(): ?string`, `bodyHtml(): string`, `isDraft(): bool`, `slug(): string`
  - `BardRenderer::toHtml(mixed $bardValue): string`

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/Content/PageFromStatamicTest.php
use Statamic\Facades\Entry;

it('renders a page from a statamic entry', function () {
    Entry::make()->collection('pages')->slug('colophon')
        ->data(['title' => 'Colophon', 'excerpt' => 'About', 'content' => 'Hello'])
        ->save();

    $this->get('/colophon')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Page')->where('title', 'Colophon'));
});

it('404s a draft page for guests', function () {
    Entry::make()->collection('pages')->slug('secret')
        ->data(['title' => 'Secret', 'published' => false])->save();
    $this->get('/secret')->assertNotFound();
});
```

- [ ] **Step 2: Run — expect failure** (PageController still reads Eloquent `Page`).

- [ ] **Step 3: Create the `pages` collection + blueprint**

`content/collections/pages.yaml`: `title: Pages`, `template: null`, `route: null` (headless — no Statamic front routing). Blueprint fields: `title` (text), `slug` (slug), `excerpt` (textarea), `content` (Bard).

- [ ] **Step 4: Implement ContentEntry, ContentRepository, BardRenderer**

`ContentRepository::page()` queries `Entry::query()->where('collection','pages')->where('slug',$slug)->first()`, wraps in `ContentEntry`. `ContentEntry::bodyHtml()` delegates to `BardRenderer::toHtml($entry->augmentedValue('content'))` (Bard augments to HTML via its `->value()`/augmented string — verify exact call against statamic.dev Bard augmentation). Draft = `! $entry->published()`.

- [ ] **Step 5: Point PageController at ContentRepository**

```php
public function show(string $slug, ContentRepository $content): Response
{
    $page = $content->page($slug);
    if ($page === null || ($page->isDraft() && ! Auth::check())) {
        throw new NotFoundHttpException;
    }
    return Inertia::render('Page', [
        'title' => $page->title(),
        'excerpt' => $page->excerpt(),
        'bodyHtml' => $page->bodyHtml(),
        'og' => ['title' => $page->title(), 'description' => $page->excerpt()],
    ]);
}
```

- [ ] **Step 6: Run — expect pass.**

Run: `php artisan test --compact --filter=PageFromStatamicTest` → PASS

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: pages served from Statamic via ContentRepository"
```

### Task 6: Render Bard HTML in Page.vue

**Files:**
- Modify: `resources/js/Pages/Page.vue`
- Create: `resources/js/Components/Ui/ProseBody.vue`
- Test: `tests/Browser/PageContentTest.php` (Pest browser smoke)

**Interfaces:**
- Consumes: `bodyHtml` prop from Task 5.
- Produces: `ProseBody` — renders sanitized HTML string in a prose container.

- [ ] **Step 1: Write the failing browser test**

```php
<?php // tests/Browser/PageContentTest.php
use Statamic\Facades\Entry;
it('shows page body content without JS errors', function () {
    Entry::make()->collection('pages')->slug('colophon')
        ->data(['title' => 'Colophon', 'content' => '<p>Built with care</p>'])->save();
    visit('/colophon')->assertSee('Built with care')->assertNoJavaScriptErrors();
});
```

- [ ] **Step 2: Run — expect failure** (Page.vue still expects Editor.js `content`).

- [ ] **Step 3: Create `ProseBody.vue`**

```vue
<script setup>
// Renders server-rendered (Bard) HTML inside the prose type scale.
defineProps({ html: { type: String, default: '' } });
</script>
<template>
  <div class="prose-body" v-html="html" />
</template>
```

- [ ] **Step 4: Use it in Page.vue** — replace the Editor.js `BlockContent` usage with `<ProseBody :html="bodyHtml" />`.

- [ ] **Step 5: Run — expect pass.**

Run: `php artisan test --compact tests/Browser/PageContentTest.php` → PASS

- [ ] **Step 6: Commit** (`npm run build` NOT run — watcher handles it)

```bash
git add -A && git commit -m "feat: render Bard page bodies in Vue via ProseBody"
```

---

## Phase 3 — Articles & notes collections + data migration

### Task 7: `articles` + `notes` collections and blueprints

**Files:**
- Create: `content/collections/articles.yaml`, `content/collections/notes.yaml`, `resources/blueprints/collections/articles/article.yaml`, `resources/blueprints/collections/notes/note.yaml`
- Test: `tests/Feature/Content/ContentCollectionsTest.php`

**Interfaces:**
- Produces: `articles` and `notes` collections with `date` behaviour (entries dated by `occurred_at`), fields: title, slug, excerpt, content (Bard), tags (articles only), plus published state.

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/Content/ContentCollectionsTest.php
use Statamic\Facades\Collection;
it('has date-ordered articles and notes collections', function (string $handle) {
    $c = Collection::findByHandle($handle);
    expect($c)->not->toBeNull();
    expect($c->dated())->toBeTrue();
})->with(['articles', 'notes']);
```

- [ ] **Step 2: Run — expect failure.**

- [ ] **Step 3: Create the collections + blueprints** (`date_behavior` set so entries carry `occurred_at`; `route: null`). Articles get a `tags` field; notes are minimal.

- [ ] **Step 4: Run — expect pass.**

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: articles and notes Statamic collections"
```

### Task 8: Editor.js → Bard converter + content migration command

**Files:**
- Create: `app/Support/EditorJsToBard.php`, `app/Console/Commands/MigrateContentToStatamic.php`
- Test: `tests/Unit/EditorJsToBardTest.php`, `tests/Feature/Content/MigrateContentTest.php`

**Interfaces:**
- Produces:
  - `EditorJsToBard::convert(array $editorJs): array` — returns a Bard (ProseMirror doc) array
  - `php artisan content:migrate` — copies `articles`/`notes`/`pages` rows into Statamic entries (idempotent by slug)

- [ ] **Step 1: Write the failing unit test**

```php
<?php // tests/Unit/EditorJsToBardTest.php
use App\Support\EditorJsToBard;
it('converts an editor.js paragraph block to a bard paragraph node', function () {
    $bard = EditorJsToBard::convert(['blocks' => [
        ['type' => 'paragraph', 'data' => ['text' => 'Hello <b>world</b>']],
    ]]);
    expect($bard[0]['type'])->toBe('paragraph');
    expect($bard[0]['content'][0]['text'])->toContain('Hello');
});
```

- [ ] **Step 2: Run — expect failure.**

- [ ] **Step 3: Implement `EditorJsToBard`** — map the block types the site actually uses (paragraph, header, list, quote, image via the existing `EditorJs.php` support as the reference for block shapes). Cover header→heading, list→bullet/ordered list, quote→blockquote, image→image node. Unknown blocks become a paragraph with the raw text (log a warning).

- [ ] **Step 4: Run unit test — expect pass.**

- [ ] **Step 5: Write the migration feature test**

```php
<?php // tests/Feature/Content/MigrateContentTest.php
use App\Models\Article;
use Statamic\Facades\Entry;
it('migrates an eloquent article into a statamic entry', function () {
    $a = Article::factory()->create([
        'slug' => 'first-post', 'title' => 'First', 'occurred_at' => '2024-01-02 09:00:00',
        'content' => ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Hi']]]],
    ]);
    $this->artisan('content:migrate')->assertSuccessful();
    $entry = Entry::query()->where('collection', 'articles')->where('slug', 'first-post')->first();
    expect($entry)->not->toBeNull();
    expect($entry->date()->toDateString())->toBe('2024-01-02');
});
```

- [ ] **Step 6: Run — expect failure; implement `MigrateContentToStatamic`** — for each `Article`/`Note`/`Page`, create a Statamic entry (collection by type), map title/slug/excerpt/tags/published(`!draft`), set the entry date from `occurred_at` (articles/notes), and convert `content` via `EditorJsToBard`. Idempotent: skip if an entry with that slug exists. Back up nothing (reads DB only).

- [ ] **Step 7: Run both tests — expect pass. Run `php artisan content:migrate` for real** (migrates the 1 live article; notes/pages empty).

- [ ] **Step 8: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: Editor.js to Bard converter + content:migrate command"
```

---

## Phase 4 — Re-plumb the six touchpoints to read content from Statamic

### Task 9: ContentRepository read model for articles/notes (the seam)

**Files:**
- Modify: `app/Content/ContentRepository.php`, `app/Content/ContentEntry.php`
- Test: `tests/Feature/Content/ContentRepositoryTest.php`

**Interfaces:**
- Produces (every later task consumes these):
  - `ContentRepository::articles(): Collection<ContentEntry>` / `notes()` / `all(): Collection<ContentEntry>` (articles+notes)
  - `ContentRepository::findByTypeAndSlug(string $type, string $slug): ?ContentEntry`
  - `ContentRepository::betweenDates(CarbonInterface $newest, CarbonInterface $oldest): Collection<ContentEntry>`
  - `ContentEntry::card(): array` returning the same shape as `Timelineable::card()` (`type,icon,title,subtitle,occurred_at,accent,meta`)
  - `ContentEntry::type(): string` ('article'|'note'), `occurredAt(): CarbonInterface`, `url(): string`, `tags(): array`

- [ ] **Step 1: Write the failing test** — assert `card()` shape and `betweenDates()` filtering match the old `Article::card()` output (type `article`, accent `article`, icon `file-text`, subtitle = excerpt).

```php
<?php // tests/Feature/Content/ContentRepositoryTest.php
use App\Content\ContentRepository;
use Statamic\Facades\Entry;
it('returns article cards matching the legacy card shape', function () {
    Entry::make()->collection('articles')->slug('p1')
        ->date('2024-05-01')->data(['title' => 'P1', 'excerpt' => 'sub'])->save();
    $card = app(ContentRepository::class)->articles()->first()->card();
    expect($card)->toMatchArray([
        'type' => 'article', 'icon' => 'file-text', 'title' => 'P1',
        'subtitle' => 'sub', 'accent' => 'article',
    ]);
});
```

- [ ] **Step 2: Run — expect failure.**
- [ ] **Step 3: Implement the read model** — `ContentEntry::card()` builds the array from the entry; `type()` from the collection handle; `url()` = `/{Y}/{m}/{d}/{slug}` from the entry date (matches the existing entry route). `betweenDates` queries both collections by entry `date`.
- [ ] **Step 4: Run — expect pass.**
- [ ] **Step 5: Pint + commit** (`git commit -m "feat: ContentRepository read model for article/note cards"`).

### Task 10: Timeline read-time merge

**Files:**
- Modify: `app/Http/Controllers/TimelineController.php`, `app/Actions/BuildTimelineFeed.php`
- Test: `tests/Feature/Timeline/TimelineMergeTest.php`

**Interfaces:**
- Consumes: `ContentRepository::betweenDates()`.
- Produces: timeline day-groups that interleave Statamic articles/notes with Eloquent `TimelineEntry` cards, ordered by `occurred_at`.

- [ ] **Step 1: Write the failing test** — seed an Eloquent activity and a Statamic article on the same day; assert the day group contains both cards in date order.
- [ ] **Step 2: Run — expect failure.**
- [ ] **Step 3: Implement the merge** — in `groupsForDates`, after building Eloquent cards, fetch `ContentRepository::betweenDates($newest,$oldest)`, map to the same card array, merge, sort by `occurred_at` desc, group by date. Day-pagination in `index()` must consider content dates too: union the distinct dates from `TimelineEntry` and `ContentRepository` before paginating (extract the date list into one query helper so `index()` and `groupsForDates()` share it).
- [ ] **Step 4: Run — expect pass; run full timeline tests.**
- [ ] **Step 5: Pint + commit.**

### Task 11: Archives (article/note types read Statamic)

**Files:**
- Modify: `app/Timeline/TypeRegistry.php`, `app/Http/Controllers/ArchiveController.php`
- Test: `tests/Feature/Archive/ContentArchiveTest.php`

**Interfaces:**
- Produces: `/articles`, `/notes`, and `/articles/tagged/{tag}` served from Statamic content.

- [ ] **Step 1: Write the failing test** — assert `/articles` lists a seeded Statamic article and `/notes` lists a note; assert an article tag taxonomy page filters by tag.
- [ ] **Step 2: Run — expect failure** (TypeRegistry article/note still point at Eloquent models).
- [ ] **Step 3: Implement** — give `TypeRegistry` article/note a content-source branch (a flag/closure) so `ArchiveController` reads them via `ContentRepository` instead of the model, keeping the same card payload and the tag taxonomy (articles). Verify no other type regresses.
- [ ] **Step 4: Run — expect pass.**
- [ ] **Step 5: Pint + commit.**

### Task 12: Entry pages (resolve article/note from Statamic)

**Files:**
- Modify: `app/Http/Controllers/EntryController.php`
- Modify: `resources/js/Components/Entry/ArticleDetail.vue`, `resources/js/Components/Entry/NoteDetail.vue`
- Test: `tests/Feature/Entry/ContentEntryPageTest.php`, `tests/Browser/ContentEntryContentTest.php`

**Interfaces:**
- Consumes: `ContentRepository::findByTypeAndSlug()`, `ContentEntry::bodyHtml()`.
- Produces: `/{Y}/{m}/{d}/{slug}` renders article/note content (Bard HTML) for content entries; unchanged for Eloquent types.

- [ ] **Step 1: Write the failing feature test** — seed a Statamic article dated 2024-01-02 slug `p1`; `GET /2024/01/02/p1` renders `Entry` with `type=article` and the body HTML.
- [ ] **Step 2: Run — expect failure** (EntryController only resolves via `TimelineEntry`).
- [ ] **Step 3: Implement** — in `EntryController::show`, first try `ContentRepository::findByTypeAndSlug` for the date+slug; if found, render `Entry` from the `ContentEntry` (card fields + `bodyHtml`); else fall back to the existing `TimelineEntry` path. Update `ArticleDetail.vue`/`NoteDetail.vue` to render `<ProseBody :html="entry.bodyHtml" />` instead of Editor.js.
- [ ] **Step 4: Write/adjust the browser test** — visit the entry URL, assert content shows, `assertNoJavaScriptErrors()`.
- [ ] **Step 5: Run — expect pass.**
- [ ] **Step 6: Pint + commit.**

### Task 13: Feeds include Statamic articles/notes

**Files:**
- Modify: `app/Models/TimelineEntry.php` (`getFeedItems`) or the feed source
- Create: `app/Content/ContentFeed.php` (maps `ContentEntry` to `Spatie\Feed\FeedItem`)
- Test: `tests/Feature/Feed/ContentFeedTest.php`

**Interfaces:**
- Produces: RSS/feed output that interleaves content entries with Eloquent entries.

- [ ] **Step 1: Write the failing test** — hit the feed route; assert a seeded Statamic article appears as a feed item with the correct link `/{Y}/{m}/{d}/{slug}`.
- [ ] **Step 2: Run — expect failure.**
- [ ] **Step 3: Implement** — extend the Feedable source so `getFeedItems()` merges `ContentRepository::all()` mapped through `ContentFeed::toFeedItem()` with the existing Eloquent feed items, sorted by date, limited to 50. Respect the existing `?filter=`/`?types=` selection (include content only when article/note is in the selection or none is set).
- [ ] **Step 4: Run — expect pass.**
- [ ] **Step 5: Pint + commit.**

### Task 14: Search indexes Statamic articles/notes

**Files:**
- Modify: `app/Http/Controllers/SearchController.php`
- Test: `tests/Feature/AdvancedSearchTest.php` (extend)

**Interfaces:**
- Produces: search results include content entries (title/excerpt/body), linking to the entry URL.

- [ ] **Step 1: Write the failing test** — seed a Statamic article whose title matches a query; assert `/search?q=...` returns it.
- [ ] **Step 2: Run — expect failure.**
- [ ] **Step 3: Implement** — add a content branch to the search source that queries `ContentRepository` (title/excerpt/plain-text body) and merges results with the existing model search, preserving the current result payload shape and ranking. Verify existing search tests stay green.
- [ ] **Step 4: Run — expect pass.**
- [ ] **Step 5: Pint + commit.**

### Task 15: OG images for content entries

**Files:**
- Modify: `app/Http/Controllers/OgImageController.php`, `app/Support/OgMeta.php`
- Test: `tests/Feature/Og/ContentOgTest.php`

**Interfaces:**
- Produces: `og` meta for content entry pages and an OG image that renders (article/note accent + title).

- [ ] **Step 1: Write the failing test** — the article entry page exposes `og.title` = the article title; the OG image route for that entry returns 200 / a PNG.
- [ ] **Step 2: Run — expect failure** (OgMeta::entry expects a `TimelineEntry`).
- [ ] **Step 3: Implement** — add an `OgMeta::contentEntry(ContentEntry $e, ...)` path and let `OgImageController` render content entries by type+slug (reuse the article/note accent + card). Keep the numeric `/og/entry/{id}` route for Eloquent entries; add content OG via the entry page meta (which already drives the shareable card).
- [ ] **Step 4: Run — expect pass.**
- [ ] **Step 5: Pint + commit.**

---

## Phase 5 — Retire Eloquent content + cleanup

### Task 16: Remove Article/Note/Page models, tables, and Editor.js support

**Files:**
- Delete: `app/Models/Article.php`, `app/Models/Note.php`, `app/Models/Page.php`, their factories, `app/Support/EditorJs.php`, `resources/js/Components/Ui/BlockContent.vue`, `resources/js/Components/Ui/EditorList.vue`
- Create: migration `drop_content_tables` (drops `articles`, `notes`, `pages`; deletes their `timeline_entries` morph rows first)
- Modify: `app/Timeline/TypeRegistry.php` (remove model imports for article/note), any remaining references
- Test: `tests/Feature/Content/NoEloquentContentTest.php`

**Interfaces:**
- Produces: no Eloquent path to article/note/page content; all content flows through `ContentRepository`.

- [ ] **Step 1: Write the failing test** — assert `class_exists(App\Models\Article::class)` is false and `Schema::hasTable('articles')` is false; assert `/articles`, a content entry page, the timeline, and the feed all still return the seeded Statamic content (regression guard).
- [ ] **Step 2: Run — expect failure.**
- [ ] **Step 3: Write the drop migration** — in `up()`, delete `timeline_entries` rows where `timelineable_type` is the Article/Note class (their content now lives in Statamic), then `Schema::dropIfExists` the three tables. `down()` recreates the tables from the original migrations (copy their schema) for reversibility.
- [ ] **Step 4: Delete the models/factories/Editor.js files; fix references** (grep for `App\\Models\\Article`, `Note`, `Page`, `EditorJs`, `BlockContent`, `EditorList` and remove/redirect each).
- [ ] **Step 5: Run — expect pass; run the FULL suite.**

Run: `php artisan test --compact` → all green.

- [ ] **Step 6: Pint + commit.**

### Task 17: Final verification + docs

**Files:**
- Modify: `CHANGELOG.md`, `docs/superpowers/specs/2026-07-01-statamic-migration-design.md` (mark implemented)
- Test: full suite + browser smoke

- [ ] **Step 1: Run the whole suite and a page/entry/timeline browser smoke** — `php artisan test --compact`; a Pest browser smoke over `/`, `/articles`, a content entry, `/cp` → `assertNoJavaScriptErrors()`.
- [ ] **Step 2: Confirm the isolated production build compiles** — `npx vite build --outDir <scratch> --emptyOutDir` (does not touch `public/build`); expect no errors.
- [ ] **Step 3: Add a CHANGELOG entry** (version-level: features, design rationale, caveats — two user systems, content now in `content/collections/`).
- [ ] **Step 4: Commit.**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "docs: changelog + mark Statamic migration implemented"
```

---

## Self-Review Notes

- **Spec coverage:** install+CP+users (Task 1), Runway all models incl. read-only reference (Tasks 2-4), pages flat-file (Tasks 5-6), articles/notes flat-file + Bard (Tasks 7-8), read-time timeline merge (Task 10), the other five touchpoints archives/entry/feeds/search/OG (Tasks 11-15), headless routing + no eloquent-driver (Task 1 constraints), retire Editor.js (Tasks 6/12/16). All spec sections map to a task.
- **Key seam:** `ContentRepository` + `ContentEntry` (Tasks 5, 9) is the single place every touchpoint reads content, so the six re-plumbing tasks share one interface rather than each re-querying Statamic.
- **Verify-against-docs spots (framework specifics, confirm as you implement):** Statamic Bard augmentation-to-HTML call (Tasks 5, 8); Runway `belongs_to` relation config and `read_only`/`findResource` API (Tasks 2-4); Statamic Entry factory `date()`/`published()` API in tests (Tasks 5, 7-9); whether any Runway feature requires Statamic Pro (Task 1).
- **Risk ordering:** the app stays fully working after every task — pages (empty) and Runway (additive) land first; articles/notes content is created in Statamic (Task 8) and read in parallel before the Eloquent models are dropped last (Task 16), so there is no window where content is missing.
