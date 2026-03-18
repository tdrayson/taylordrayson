# Core Infrastructure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the full database schema, Eloquent models, factories/seeders, CSS/Tailwind setup, and homepage timeline feed with sidebar — the foundation for the entire personal data hub.

**Architecture:** 14 data tables + assets + timeline_entries with a polymorphic feed pattern. Model observers auto-sync timeline entries. Single Blade layout with conditional sidebar. Card components extracted from mockup HTML in `project/`.

**Tech Stack:** Laravel 13, Blade, Tailwind CSS v4, Alpine.js, SQLite, Pest v4

**Spec:** `docs/superpowers/specs/2026-03-17-core-infrastructure-design.md`
**Design mockups:** `project/home.html`, `project/stats.html`, `project/{day}.html`, `project/{month}.html`, `project/{year}.html`
**CSS source:** `/Users/taylordrayson/Downloads/index.css` (Tailwind v3 format, port to v4)
**Tailwind config source:** `/Users/taylordrayson/Downloads/tailwind.config.ts`

---

## File Structure

### Migrations (16 files)

```
database/migrations/
├── xxxx_create_activities_table.php
├── xxxx_create_sleep_table.php
├── xxxx_create_calories_table.php
├── xxxx_create_media_table.php
├── xxxx_create_events_table.php
├── xxxx_create_appearances_table.php
├── xxxx_create_podcasts_table.php
├── xxxx_create_flights_table.php
├── xxxx_create_checkins_table.php
├── xxxx_create_fuel_table.php
├── xxxx_create_projects_table.php
├── xxxx_create_articles_table.php
├── xxxx_create_notes_table.php
├── xxxx_create_assets_table.php
└── xxxx_create_timeline_entries_table.php
```

### Models (15 files)

```
app/Models/
├── Activity.php
├── Sleep.php
├── Calorie.php
├── Media.php
├── Event.php
├── Appearance.php
├── Podcast.php
├── Flight.php
├── Checkin.php
├── Fuel.php
├── Project.php
├── Article.php
├── Note.php
├── Asset.php
└── TimelineEntry.php
```

### Traits & Interfaces (3 files)

```
app/Models/Concerns/
├── HasAssets.php
├── HasTimelineEntry.php
└── Timelineable.php
```

### Observers (2 files)

```
app/Observers/
├── TimelineEntryObserver.php
└── CalorieTimelineObserver.php
```

### Factories (13 files)

```
database/factories/
├── ActivityFactory.php
├── SleepFactory.php
├── CalorieFactory.php
├── MediaFactory.php
├── EventFactory.php
├── AppearanceFactory.php
├── PodcastFactory.php
├── FlightFactory.php
├── CheckinFactory.php
├── FuelFactory.php
├── ProjectFactory.php
├── ArticleFactory.php
├── NoteFactory.php
└── AssetFactory.php
```

### Seeders (1 file)

```
database/seeders/
└── DatabaseSeeder.php (modify)
```

### Config (1 file)

```
config/
└── vehicles.php
```

### Controllers (1 file)

```
app/Http/Controllers/
└── TimelineController.php
```

### Views (20 files)

```
resources/views/
├── layouts/
│   └── app.blade.php
├── components/
│   ├── sidebar/
│   │   ├── index.blade.php
│   │   ├── profile.blade.php
│   │   ├── nav.blade.php
│   │   ├── streak.blade.php
│   │   └── sparklines.blade.php
│   └── cards/
│       ├── dynamic.blade.php
│       ├── activity.blade.php
│       ├── sleep.blade.php
│       ├── calories.blade.php
│       ├── media.blade.php
│       ├── flight.blade.php
│       ├── checkin.blade.php
│       ├── fuel.blade.php
│       ├── event.blade.php
│       ├── appearance.blade.php
│       ├── podcast.blade.php
│       ├── project.blade.php
│       ├── article.blade.php
│       └── note.blade.php
├── pages/
│   └── home.blade.php
```

### CSS & JS (2 files, modify)

```
resources/css/app.css (modify)
resources/js/app.js (modify)
```

### Routes (1 file, modify)

```
routes/web.php (modify)
```

### Tests

```
tests/Feature/
├── Models/
│   ├── ActivityTest.php
│   ├── TimelineEntryTest.php
│   └── CalorieTimelineTest.php
├── TimelineControllerTest.php
```

---

## Task 1: CSS & Tailwind Setup

**Files:**
- Modify: `resources/css/app.css`
- Modify: `resources/js/app.js`
- Modify: `package.json`

Port the design tokens from the mockup source (`/Users/taylordrayson/Downloads/index.css`) to Tailwind v4 format. Install Alpine.js.

- [ ] **Step 1: Install Alpine.js**

Run: `npm install alpinejs`

- [ ] **Step 2: Initialise Alpine.js in app.js**

```js
// resources/js/app.js
import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();
```

- [ ] **Step 3: Replace resources/css/app.css with design tokens**

Port the `:root` CSS variables, dark mode overrides, font imports, body background, custom utilities, and animations from `/Users/taylordrayson/Downloads/index.css` into Tailwind v4 format.

Key changes from v3 to v4:
- Replace `@tailwind base/components/utilities` with `@import 'tailwindcss'`
- Use `@theme` directive for custom values
- Keep `@layer base` for `:root` variables and body styles
- Keep `@layer utilities` for custom utility classes
- Consolidate data-type colours to 13 (one per table): remove `--color-gym`, `--color-film`, `--color-tv`, `--color-book`, `--color-show`. Add `--color-media`, `--color-event`, `--color-podcast`, `--color-note`.
- Remove theme-b and glass-card entirely
- Remove Space Grotesk and JetBrains Mono font imports

Reference the source file for exact HSL values. The existing `@source` directives and `@theme` block in the current `app.css` should be preserved/adapted.

- [ ] **Step 4: Build to verify no errors**

Run: `npm run build`
Expected: Build succeeds with no errors.

- [ ] **Step 5: Commit**

```bash
git add resources/css/app.css resources/js/app.js package.json package-lock.json
git commit -m "feat: add design tokens, Alpine.js, and Tailwind v4 CSS setup"
```

---

## Task 2: Database Migrations

**Files:**
- Create: 16 migration files in `database/migrations/`
- Create: `config/vehicles.php`

Create all migrations using `php artisan make:migration`. Follow the exact schemas from the spec.

- [ ] **Step 1: Create all 16 migrations**

Run each command (all use `--no-interaction`):

```bash
php artisan make:migration create_activities_table --no-interaction
php artisan make:migration create_sleep_table --no-interaction
php artisan make:migration create_calories_table --no-interaction
php artisan make:migration create_media_table --no-interaction
php artisan make:migration create_events_table --no-interaction
php artisan make:migration create_appearances_table --no-interaction
php artisan make:migration create_podcasts_table --no-interaction
php artisan make:migration create_flights_table --no-interaction
php artisan make:migration create_checkins_table --no-interaction
php artisan make:migration create_fuel_table --no-interaction
php artisan make:migration create_projects_table --no-interaction
php artisan make:migration create_articles_table --no-interaction
php artisan make:migration create_notes_table --no-interaction
php artisan make:migration create_assets_table --no-interaction
php artisan make:migration create_timeline_entries_table --no-interaction
```

- [ ] **Step 2: Implement each migration's `up()` method**

Use the exact column definitions from the spec (`docs/superpowers/specs/2026-03-17-core-infrastructure-design.md` Section 1). Every table gets `id()`, `occurred_at` (with index), and `timestamps()`. Platform-linked tables get `platform_type`, `platform_id`, and the unique composite index.

Key details:
- `sleep`: has `source` column (not platform_type/platform_id), `stages` is `->nullable()`
- `media`: no `imdb_id` column (it's in meta JSON), no `status` column
- `calories`: no platform columns
- `articles`: uses `longText('content')`
- `fuel`: no `full_tank`, no `latitude`/`longitude`
- `checkins`: column is `category` (not `venue_category`)
- `podcasts`: `topic` is `string` (not JSON)
- `timeline_entries`: has `unique(['timelineable_type', 'timelineable_id'])`
- `assets`: has `morphs('assetable')`, `order` defaults to 0

- [ ] **Step 3: Create config/vehicles.php**

```php
<?php

return [
    1 => [
        'name' => 'Golf',
        'make' => 'Volkswagen',
        'model' => 'Golf',
        'year' => 2019,
        'fuel_type' => 'petrol',
        'active' => true,
    ],
];
```

- [ ] **Step 4: Run migrations**

Run: `php artisan migrate`
Expected: All 16 migrations run successfully.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/ config/vehicles.php
git commit -m "feat: add all 16 database migrations and vehicles config"
```

---

## Task 3: Traits, Interface & Observers

**Files:**
- Create: `app/Models/Concerns/Timelineable.php`
- Create: `app/Models/Concerns/HasAssets.php`
- Create: `app/Models/Concerns/HasTimelineEntry.php`
- Create: `app/Observers/TimelineEntryObserver.php`
- Create: `app/Observers/CalorieTimelineObserver.php`

- [ ] **Step 1: Create the Timelineable interface**

```php
<?php

namespace App\Models\Concerns;

interface Timelineable
{
    /**
     * @return array{
     *     type: string,
     *     icon: string,
     *     title: string,
     *     subtitle: ?string,
     *     occurred_at: \Carbon\Carbon,
     *     accent: string,
     *     meta: array,
     * }
     */
    public function toTimelineCard(): array;
}
```

- [ ] **Step 2: Create the HasAssets trait**

```php
<?php

namespace App\Models\Concerns;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasAssets
{
    public function assets(): MorphMany
    {
        return $this->morphMany(Asset::class, 'assetable');
    }

    public function cover(): MorphOne
    {
        return $this->morphOne(Asset::class, 'assetable')->where('type', 'cover');
    }

    public function photos(): MorphMany
    {
        return $this->morphMany(Asset::class, 'assetable')->where('type', 'photo')->orderBy('order');
    }

    public function map(): MorphOne
    {
        return $this->morphOne(Asset::class, 'assetable')->where('type', 'map');
    }
}
```

- [ ] **Step 3: Create the HasTimelineEntry trait**

```php
<?php

namespace App\Models\Concerns;

use App\Models\TimelineEntry;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasTimelineEntry
{
    public function timelineEntry(): MorphOne
    {
        return $this->morphOne(TimelineEntry::class, 'timelineable');
    }

    public static function bootHasTimelineEntry(): void
    {
        static::observe(\App\Observers\TimelineEntryObserver::class);
    }
}
```

- [ ] **Step 4: Create TimelineEntryObserver**

Handles creating/updating/deleting timeline entries for all models except Calorie (which has its own observer).

```php
<?php

namespace App\Observers;

use App\Models\Calorie;
use App\Models\TimelineEntry;
use Illuminate\Database\Eloquent\Model;

class TimelineEntryObserver
{
    public function saved(Model $model): void
    {
        if ($model instanceof Calorie) {
            return;
        }

        if ($model->occurred_at === null) {
            return;
        }

        $model->timelineEntry()->updateOrCreate(
            ['timelineable_type' => $model->getMorphClass(), 'timelineable_id' => $model->getKey()],
            ['occurred_at' => $model->occurred_at],
        );
    }

    public function deleted(Model $model): void
    {
        if ($model instanceof Calorie) {
            return;
        }

        $model->timelineEntry()?->delete();
    }
}
```

- [ ] **Step 5: Create CalorieTimelineObserver**

One timeline entry per date, pointing to the first calorie row for that day.

```php
<?php

namespace App\Observers;

use App\Models\Calorie;
use App\Models\TimelineEntry;

class CalorieTimelineObserver
{
    public function saved(Calorie $calorie): void
    {
        $date = $calorie->occurred_at->toDateString();

        $firstCalorie = Calorie::whereDate('occurred_at', $date)
            ->orderBy('id')
            ->first();

        if (! $firstCalorie) {
            return;
        }

        TimelineEntry::updateOrCreate(
            [
                'timelineable_type' => Calorie::class,
                'timelineable_id' => $firstCalorie->id,
            ],
            [
                'occurred_at' => $calorie->occurred_at->copy()->setTime(12, 0),
            ],
        );
    }

    public function deleted(Calorie $calorie): void
    {
        $date = $calorie->occurred_at->toDateString();

        $remaining = Calorie::whereDate('occurred_at', $date)
            ->where('id', '!=', $calorie->id)
            ->orderBy('id')
            ->first();

        if (! $remaining) {
            TimelineEntry::where('timelineable_type', Calorie::class)
                ->where('timelineable_id', $calorie->id)
                ->delete();

            return;
        }

        TimelineEntry::where('timelineable_type', Calorie::class)
            ->where('timelineable_id', $calorie->id)
            ->update(['timelineable_id' => $remaining->id]);
    }
}
```

- [ ] **Step 6: Commit**

```bash
git add app/Models/Concerns/ app/Observers/
git commit -m "feat: add Timelineable interface, HasAssets/HasTimelineEntry traits, and observers"
```

---

## Task 4: Eloquent Models

**Files:**
- Create: 15 model files in `app/Models/`

Create all models using `php artisan make:model`. Then implement each with the correct `$fillable` (using Laravel 13's attribute syntax), casts, traits, and interface.

- [ ] **Step 1: Generate all model files**

```bash
php artisan make:model Activity --no-interaction
php artisan make:model Sleep --no-interaction
php artisan make:model Calorie --no-interaction
php artisan make:model Media --no-interaction
php artisan make:model Event --no-interaction
php artisan make:model Appearance --no-interaction
php artisan make:model Podcast --no-interaction
php artisan make:model Flight --no-interaction
php artisan make:model Checkin --no-interaction
php artisan make:model Fuel --no-interaction
php artisan make:model Project --no-interaction
php artisan make:model Article --no-interaction
php artisan make:model Note --no-interaction
php artisan make:model Asset --no-interaction
php artisan make:model TimelineEntry --no-interaction
```

- [ ] **Step 2: Implement each model**

Every timeline model must:
- Use `HasFactory`, `HasAssets`, `HasTimelineEntry` traits
- Implement `Timelineable` interface with `toTimelineCard()` method
- Use `#[Fillable([...])]` attribute (Laravel 13 pattern from User model)
- Define `casts()` method for `occurred_at => 'datetime'` and any JSON columns
- `Calorie` model: override `bootHasTimelineEntry()` to use `CalorieTimelineObserver` instead

Platform-linked models (`Activity`, `Media`, `Checkin`) need a `getPlatformUrlAttribute()` accessor:
```php
public function getPlatformUrlAttribute(): ?string
{
    return match ($this->platform_type) {
        'strava' => "https://www.strava.com/activities/{$this->platform_id}",
        'trakt' => "https://trakt.tv/id/{$this->platform_id}",
        'swarm' => "https://www.swarmapp.com/c/{$this->platform_id}",
        default => null,
    };
}
```

`Podcast` model needs `getTitleAttribute()`:
```php
public function getTitleAttribute(): string
{
    return "Season {$this->season_number}, Episode {$this->episode_number}";
}
```

`Fuel` model needs `getVehicleAttribute()`:
```php
public function getVehicleAttribute(): ?array
{
    return config("vehicles.{$this->vehicle_id}");
}
```

**Asset model:**
- `assetable()` morphTo relationship
- No traits needed

**TimelineEntry model:**
- `timelineable()` morphTo relationship
- Cast `occurred_at` to `datetime`
- No traits needed

`toTimelineCard()` return values per model — use the spec's defined array shape. The `icon` values should be Lucide icon names matching the mockup HTML (e.g., `footprints` for activity, `bed` for sleep, `utensils` for calories, `film` for media, `plane` for flight, `map-pin` for checkin, `fuel` for fuel, `music` for event, `mic` for appearance, `headphones` for podcast, `rocket` for project, `file-text` for article, `message-circle` for note). The `accent` values should match the CSS variable names (e.g., `activity`, `sleep`, `food`, `media`, `event`, `appearance`, `podcast`, `flight`, `checkin`, `fuel`, `project`, `article`, `note`).

- [ ] **Step 3: Verify models load**

Run: `php artisan tinker --execute "new App\Models\Activity;"`
Expected: No errors.

- [ ] **Step 4: Commit**

```bash
git add app/Models/
git commit -m "feat: add all 15 Eloquent models with traits and relationships"
```

---

## Task 5: Model Tests

**Files:**
- Create: `tests/Feature/Models/ActivityTest.php`
- Create: `tests/Feature/Models/TimelineEntryTest.php`
- Create: `tests/Feature/Models/CalorieTimelineTest.php`
- Modify: `tests/Pest.php` — uncomment RefreshDatabase

- [ ] **Step 1: Enable RefreshDatabase in Pest.php**

Uncomment the `->use(Illuminate\Foundation\Testing\RefreshDatabase::class)` line in `tests/Pest.php`.

- [ ] **Step 2: Write Activity model test**

```bash
php artisan make:test Models/ActivityTest --pest --no-interaction
```

Test that:
- An Activity can be created with factory
- Creating an Activity automatically creates a TimelineEntry
- The TimelineEntry's `occurred_at` matches the Activity's
- Deleting the Activity deletes the TimelineEntry
- `toTimelineCard()` returns the expected array shape

- [ ] **Step 3: Write TimelineEntry observer test**

```bash
php artisan make:test Models/TimelineEntryTest --pest --no-interaction
```

Test that the observer works for multiple model types (Activity, Flight, Media, etc.):
- Creating a model creates a timeline entry
- Updating `occurred_at` updates the timeline entry
- Deleting a model deletes the timeline entry
- The unique constraint prevents duplicate entries

- [ ] **Step 4: Write Calorie timeline test**

```bash
php artisan make:test Models/CalorieTimelineTest --pest --no-interaction
```

Test the special calorie observer behaviour:
- Creating the first calorie for a date creates one timeline entry
- Creating a second calorie for the same date does not create a second timeline entry
- The timeline entry points to the first calorie row (lowest ID)
- Deleting the referenced calorie row updates the timeline entry to point to the next row
- Deleting all calories for a date deletes the timeline entry

- [ ] **Step 5: Run tests**

Run: `php artisan test --compact`
Expected: All tests pass.

- [ ] **Step 6: Commit**

```bash
git add tests/
git commit -m "test: add model and timeline entry observer tests"
```

---

## Task 6: Factories

**Files:**
- Create: 13 factory files in `database/factories/`

- [ ] **Step 1: Generate all factory files**

```bash
php artisan make:factory ActivityFactory --no-interaction
php artisan make:factory SleepFactory --no-interaction
php artisan make:factory CalorieFactory --no-interaction
php artisan make:factory MediaFactory --no-interaction
php artisan make:factory EventFactory --no-interaction
php artisan make:factory AppearanceFactory --no-interaction
php artisan make:factory PodcastFactory --no-interaction
php artisan make:factory FlightFactory --no-interaction
php artisan make:factory CheckinFactory --no-interaction
php artisan make:factory FuelFactory --no-interaction
php artisan make:factory ProjectFactory --no-interaction
php artisan make:factory ArticleFactory --no-interaction
php artisan make:factory NoteFactory --no-interaction
php artisan make:factory AssetFactory --no-interaction
```

- [ ] **Step 2: Implement each factory with realistic data**

Key details per factory:

**ActivityFactory:** Random type from `[run, ride, walk, gym, swim, yoga]`. For cardio types: realistic distance (3-21km), duration (15-120min), polyline placeholder. For gym: exercises JSON array with 3-6 exercises, each with 2-4 sets.

**SleepFactory:** Bedtime between 22:00-01:00, wake between 05:30-08:00, duration calculated from bed/wake. Stage minutes (awake, rem, core, deep) that sum to duration. Stages JSON as array of segments.

**CalorieFactory:** Real food names from a curated list per meal type. Realistic calories (50-800 per item), macro splits that roughly correspond to the food type.

**MediaFactory:** Random type. Films: real-ish titles, year 1990-2026, runtime 80-200min. TV: show_title, season 1-8, episode 1-24. Books: title + author in meta. Ratings 1-10.

**FlightFactory:** Real IATA codes from a curated list (LHR, JFK, CDG, AMS, DXB, SIN, LAX, etc.) with matching coordinates. Realistic distances. Airlines from curated list (BA, EK, KL, etc.). Meta with origin/destination city/country names.

**CheckinFactory:** Venue names with matching categories (e.g., "Costa Coffee" → "Coffee Shop", "The Crown" → "Pub", "Tesco" → "Supermarket"). Cities from curated UK list.

**FuelFactory:** Litres 30-55, cost calculated from litres * price_per_litre (150-180p). Odometer incrementing. Station names from curated list. Vehicle_id defaults to 1.

**PodcastFactory:** Sequential season (1-7) and episode (1-50) numbers. Topic as comma-separated string from curated list. Duration 1800-5400 seconds.

**EventFactory, AppearanceFactory, ProjectFactory, ArticleFactory, NoteFactory:** Use `fake()` for appropriate fields with realistic values.

**AssetFactory:** Generates asset records with `type` (cover/photo/map), placeholder `path` in `{year}/{month}/{day}/{uuid}.jpg` format, and realistic `width`/`height`/`mime_type`/`size_bytes` values. The `assetable_type` and `assetable_id` should be set by the caller.

- [ ] **Step 3: Verify factories work**

Run: `php artisan tinker --execute "App\Models\Activity::factory()->make();"`
Expected: Returns an Activity instance with realistic data.

- [ ] **Step 4: Commit**

```bash
git add database/factories/
git commit -m "feat: add realistic factories for all 13 timeline models"
```

---

## Task 7: Database Seeder

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Implement DatabaseSeeder**

The seeder should:
1. Create 1 user (Taylor Drayson)
2. Seed 6 months of data backward from today at natural frequencies
3. Additionally seed minimal calorie data going back ~2,145 days for the streak widget (just 1 item per day for old dates)

Frequencies per the spec:
- Activities: 3-4 per week
- Sleep: daily
- Calories: daily (4-8 items per day, distributed across meals)
- Media: 2-3 films per week, 3-5 TV episodes per week (in clusters of same show), 1 book per month
- Checkins: 1-2 per day
- Flights: 1-2 per month
- Fuel: 1-2 per month
- Events: 1-2 per month
- Appearances: 1-2 total
- Podcasts: ~1 per week
- Projects: 2-3 total
- Articles: 1-2 per month
- Notes: 2-3 per week

Use `WithoutModelEvents` for the old calorie streak data (to avoid creating 2,145 timeline entries for old data), then seed recent data normally so observers fire and create timeline entries.

After creating each model, create associated Asset records using AssetFactory per the asset type matrix in the spec:
- Covers: media, events, appearances, podcasts, projects, articles
- Photos: activities (50% chance), checkins (30% chance), events (50% chance), projects (30% chance), notes (20% chance)
- Maps: activities (for cardio types), flights, checkins
- No real image files — cards handle missing images gracefully

- [ ] **Step 2: Run seeder**

Run: `php artisan migrate:fresh --seed`
Expected: Database populated with realistic data. No errors.

- [ ] **Step 3: Verify timeline entries were created**

Run: `php artisan tinker --execute "echo App\Models\TimelineEntry::count();"`
Expected: A number in the hundreds (from 6 months of varied data).

- [ ] **Step 4: Commit**

```bash
git add database/seeders/DatabaseSeeder.php
git commit -m "feat: add comprehensive database seeder with 6 months of realistic data"
```

---

## Task 8: Layout & Sidebar Components

**Files:**
- Create: `resources/views/layouts/app.blade.php`
- Create: `resources/views/components/sidebar/index.blade.php`
- Create: `resources/views/components/sidebar/profile.blade.php`
- Create: `resources/views/components/sidebar/nav.blade.php`
- Create: `resources/views/components/sidebar/streak.blade.php`
- Create: `resources/views/components/sidebar/sparklines.blade.php`

Extract directly from `project/home.html`. The mockup HTML is the authoritative design reference.

- [ ] **Step 1: Create the layout**

`layouts/app.blade.php` — the single layout file. Extract the outer structure from `project/home.html`:
- `$fullWidth` prop (defaults to false)
- Google Fonts link tag (DM Sans, Instrument Serif)
- Vite CSS/JS includes
- Mobile header bar (lines 13-35 of home.html)
- Mobile overlay + slide-out nav (lines 36-124)
- Flex container with conditional sidebar + main content slot
- Top-right status bar (from lines 312-351 of home.html) — hardcoded placeholder data for now (time, weather, location, battery)
- `{{ $slot }}` for main content

The sidebar is rendered via `<x-sidebar.index>` when `$fullWidth` is false.

Alpine.js `x-data` on the body for mobile nav toggle state.

- [ ] **Step 2: Create sidebar components**

Extract each piece from the sidebar in `project/home.html` (lines 127-310):

**`sidebar/index.blade.php`:** The `<aside>` wrapper — accepts `$streak`, `$sparklines` props. Renders profile, nav, streak, sparklines in order.

**`sidebar/profile.blade.php`:** Headshot image, name, tagline, social icons (GitHub, Twitter/X, RSS). Lines 128-157 of home.html.

**`sidebar/nav.blade.php`:** Navigation links (Timeline, Stats, Map, About, Writing) with active state detection using `request()->is()`. Lines 158-203 of home.html. Use Lucide SVG icons matching the mockup.

**`sidebar/streak.blade.php`:** Accepts `$count` prop. Flame icon + count + "days" + "Calorie logging". Lines 204-219 of home.html.

**`sidebar/sparklines.blade.php`:** Accepts `$sparklines` prop (array with running, calories, sleep data). Three rows each with icon, label, value, and SVG polyline sparkline. Lines 220-289 of home.html.

- [ ] **Step 3: Verify layout renders**

Create a temporary test route or modify the welcome route to use the new layout. Ensure it renders without errors.

Run: `npm run build && php artisan serve`
Check: Page loads with sidebar visible.

- [ ] **Step 4: Commit**

```bash
git add resources/views/layouts/ resources/views/components/sidebar/
git commit -m "feat: add app layout and sidebar components extracted from mockup"
```

---

## Task 9: Timeline Card Components

**Files:**
- Create: 14 Blade files in `resources/views/components/cards/`

Extract card designs from `project/home.html`. Each card follows the same structural pattern from the mockup:
- Absolute-positioned icon circle (left of content, -left-[52px])
- Title + date in a flex row
- Type-specific content below

- [ ] **Step 1: Create dynamic.blade.php**

The component resolver:

```blade
@props(['entry'])

@php
$componentMap = [
    \App\Models\Activity::class   => 'cards.activity',
    \App\Models\Sleep::class      => 'cards.sleep',
    \App\Models\Calorie::class    => 'cards.calories',
    \App\Models\Media::class      => 'cards.media',
    \App\Models\Event::class      => 'cards.event',
    \App\Models\Appearance::class => 'cards.appearance',
    \App\Models\Podcast::class    => 'cards.podcast',
    \App\Models\Flight::class     => 'cards.flight',
    \App\Models\Checkin::class    => 'cards.checkin',
    \App\Models\Fuel::class       => 'cards.fuel',
    \App\Models\Project::class    => 'cards.project',
    \App\Models\Article::class    => 'cards.article',
    \App\Models\Note::class       => 'cards.note',
];
$component = $componentMap[get_class($entry)] ?? null;
@endphp

@if($component)
    <x-dynamic-component :component="$component" :entry="$entry" />
@endif
```

- [ ] **Step 2: Create each card component**

Extract from `project/home.html`. Each card accepts an `$entry` prop (the Eloquent model instance).

All cards share this outer structure (from the mockup):
```blade
<article class="group relative">
    {{-- Icon circle --}}
    <div class="absolute -left-[52px] top-0 h-10 w-10 rounded-full flex items-center justify-center shrink-0"
         style="background: hsl(var(--color-{accent}) / 0.12);">
        {{-- Lucide SVG icon --}}
    </div>

    {{-- Header: title + date --}}
    <div class="flex items-start justify-between gap-4 mb-1">
        <p class="text-lg font-medium text-foreground min-w-0 truncate">{{ $title }}</p>
        <div class="text-right shrink-0">
            <p class="text-sm font-medium text-muted-foreground leading-tight">{{ $entry->occurred_at->format('D g:ia') }}</p>
            <p class="text-xs text-muted-foreground/70 leading-tight">{{ $entry->occurred_at->format('j M Y') }}</p>
        </div>
    </div>

    {{-- Type-specific content --}}
    ...
</article>
```

Key card-specific content from the mockup:

**sleep.blade.php:** Duration text, bedtime→wake range, coloured stages bar (flexbox with inline-styled segments for awake/rem/light/deep). Stages legend with duration per stage.

**activity.blade.php:** Distance + duration + pace stats. SVG map with polyline (if available). Photos grid (if available).

**calories.blade.php:** Daily total + macros row. Grouped by meal with individual items listed.

**media.blade.php:** Cover image (if available). Title, year/show info, rating as stars. Adapts based on `$entry->type` (film/tv_episode/book).

**checkin.blade.php:** Venue name, category badge, description, photo (if available), mini map.

**flight.blade.php:** IATA codes route display, city names, distance, map with arc.

**fuel.blade.php:** Litres, cost, price per litre, vehicle name from config, odometer.

**event.blade.php:** Name, venue, city, type badge, cover image.

**appearance.blade.php:** Title, show name, type badge, link.

**podcast.blade.php:** Season/episode display, topic, duration, play/YouTube links.

**project.blade.php:** Title, description, tags as badges, status badge, links.

**article.blade.php:** Title, excerpt, estimated read time, tags.

**note.blade.php:** Content text (simplest card).

- [ ] **Step 3: Commit**

```bash
git add resources/views/components/cards/
git commit -m "feat: add 14 timeline card components extracted from mockup"
```

---

## Task 10: Timeline Controller & Homepage

**Files:**
- Create: `app/Http/Controllers/TimelineController.php`
- Create: `resources/views/pages/home.blade.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Create TimelineController**

```bash
php artisan make:controller TimelineController --no-interaction
```

Implement the `index()` method:
- Query `TimelineEntry` ordered by `occurred_at` desc, paginated (20)
- Eager-load `timelineable` with morphTo mapping
- Compute sidebar data:
  - `$calorieStreak`: consecutive days with logged calories (query `calories` table, count backward from today)
  - `$sparklines`: last 14 days averages for running (from activities where type=run), calories (daily sum), sleep (duration)
  - `$episodeCount`: `Podcast::count()`
- Return `pages.home` view with timeline entries and sidebar data

- [ ] **Step 2: Create the homepage view**

`pages/home.blade.php` — extends `layouts/app`:

Extract the bio intro section from `project/home.html` (lines 353-378):
- Two paragraphs with inline images and links
- Dynamic `{{ $calorieStreak }}` and `{{ $episodeCount }}` values

Timeline feed section (lines 380+):
- Wrapped in `<div class="space-y-8 max-w-[680px] mx-auto pl-[52px]">`
- Date grouping: group entries by date, show date header before each group
- Loop through entries rendering `<x-cards.dynamic :entry="$entry->timelineable" />`
- Pagination links at bottom

Date header format from mockup: day of week header when the date changes.

- [ ] **Step 3: Update routes/web.php**

```php
<?php

use App\Http\Controllers\TimelineController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TimelineController::class, 'index']);
```

- [ ] **Step 4: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 5: Build and verify**

Run: `npm run build && php artisan migrate:fresh --seed && php artisan serve`
Expected: Homepage renders with sidebar, bio intro, and timeline cards populated from seeded data.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/TimelineController.php resources/views/pages/home.blade.php routes/web.php
git commit -m "feat: add timeline controller and homepage with feed"
```

---

## Task 11: Timeline Controller Test

**Files:**
- Create: `tests/Feature/TimelineControllerTest.php`

- [ ] **Step 1: Create test file**

```bash
php artisan make:test TimelineControllerTest --pest --no-interaction
```

- [ ] **Step 2: Write tests**

Test that:
- `GET /` returns 200
- The view contains the bio intro text
- Timeline entries are displayed in reverse chronological order
- Sidebar streak count is visible
- Sidebar sparklines section is visible
- Pagination works (create >20 entries, verify pagination links)
- Different card types render correctly (create one of each type, verify they appear)

- [ ] **Step 3: Run all tests**

Run: `php artisan test --compact`
Expected: All tests pass.

- [ ] **Step 4: Run Pint on all modified PHP files**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/TimelineControllerTest.php
git commit -m "test: add timeline controller integration tests"
```

---

## Task 12: Final Verification

- [ ] **Step 1: Fresh database with seed**

Run: `php artisan migrate:fresh --seed`

- [ ] **Step 2: Build assets**

Run: `npm run build`

- [ ] **Step 3: Run full test suite**

Run: `php artisan test --compact`
Expected: All tests pass.

- [ ] **Step 4: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 5: Visual verification**

Run: `php artisan serve`
Verify in browser:
- Homepage loads with warm cream background and grid pattern
- Sidebar shows profile, navigation, streak counter, sparklines
- Timeline shows cards of various types with correct icons and accent colours
- Date headers separate entries by day
- Cards show actual dates (not relative time)
- Mobile responsive: sidebar collapses, mobile nav works
- Pagination works at bottom of feed

- [ ] **Step 6: Final commit if any fixes needed**

```bash
git add -A
git commit -m "fix: final polish from visual verification"
```
