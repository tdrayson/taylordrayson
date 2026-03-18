# Sub-project 1: Core Infrastructure

**Date:** 2026-03-17
**Scope:** Database schema, models, factories, seeders, homepage timeline feed with sidebar, Blade component architecture, CSS/Tailwind setup.

---

## 1. Database Schema

16 migrations total: 14 data tables + `assets` + `timeline_entries`.

### Standardised Pattern

Every table uses:

```php
$table->id();
$table->timestamp('occurred_at');  // Primary timeline date
$table->index('occurred_at');      // Indexed for widget queries and filtering
$table->timestamps();              // created_at, updated_at
```

Platform-linked tables add:

```php
$table->string('platform_type')->nullable();
$table->string('platform_id')->nullable();
$table->unique(['platform_type', 'platform_id']);
```

### Table Definitions

**`activities`** — uses meta JSON

```php
$table->timestamp('occurred_at');
$table->string('type');                  // run, ride, walk, gym, swim, yoga
$table->string('name')->nullable();
$table->integer('duration_seconds');
$table->integer('calories')->nullable();
$table->decimal('distance_km', 8, 3)->nullable();
$table->text('polyline')->nullable();
$table->json('heart_rate')->nullable();  // [{time, bpm}, ...]
$table->string('platform_type')->nullable();
$table->string('platform_id')->nullable();
$table->json('meta')->nullable();
// meta: { description, elevation_gain, exercises, kudos_count }
```

**`sleep`**

```php
$table->timestamp('occurred_at');
$table->timestamp('bedtime');
$table->timestamp('wake_time');
$table->integer('duration_minutes');
$table->integer('awake_minutes')->nullable();
$table->integer('rem_minutes')->nullable();
$table->integer('core_minutes')->nullable();
$table->integer('deep_minutes')->nullable();
$table->json('stages')->nullable();      // Raw Apple Watch segments (nullable for manual entries)
$table->string('platform_type')->nullable();
$table->string('platform_id')->nullable();
```

**`calories`** — one row per food item

```php
$table->timestamp('occurred_at');
$table->string('name');
$table->string('meal');                  // breakfast, lunch, dinner, snacks
$table->decimal('quantity', 8, 2);
$table->string('units');
$table->integer('calories');
$table->decimal('fat', 8, 2)->nullable();
$table->decimal('protein', 8, 2)->nullable();
$table->decimal('carbs', 8, 2)->nullable();
$table->decimal('saturated_fat', 8, 2)->nullable();
$table->decimal('sugars', 8, 2)->nullable();
$table->decimal('fibre', 8, 2)->nullable();
$table->decimal('cholesterol', 8, 2)->nullable();
$table->decimal('sodium', 8, 2)->nullable();
```

**`media`** — uses meta JSON for type-specific fields

```php
$table->timestamp('occurred_at')->nullable(); // Watched/finished date. Null = in progress.
$table->string('type');                  // film, tv_episode, book
$table->string('title');
$table->string('status')->default('finished'); // finished, reading, watching, abandoned
$table->integer('rating')->nullable();   // 1-10
$table->string('platform_type')->nullable();
$table->string('platform_id')->nullable();
$table->string('imdb_id')->nullable();
$table->json('meta')->nullable();
// meta: {
//   film: { year, runtime, genres },
//   tv: { show_title, season_number, episode_number, episode_title, runtime },
//   book: { author, isbn }
// }
```

**Note:** `occurred_at` is nullable on `media` to support "currently reading/watching" state. When `status` is `reading` or `watching`, `occurred_at` is null and no timeline entry is created. The sidebar "currently reading" widget queries `Media::where('type', 'book')->where('status', 'reading')->latest('created_at')->first()`.

**`events`**

```php
$table->timestamp('occurred_at');
$table->string('type');                  // concert, theatre, comedy, festival
$table->string('name');
$table->string('venue_name')->nullable();
$table->string('address')->nullable();
$table->string('city')->nullable();
$table->string('country')->nullable();
$table->decimal('latitude', 10, 7)->nullable();
$table->decimal('longitude', 10, 7)->nullable();
$table->decimal('ticket_price', 8, 2)->nullable();
$table->text('notes')->nullable();
```

**`appearances`**

```php
$table->timestamp('occurred_at');
$table->string('type');                  // podcast, livestream, interview, talk
$table->string('title');
$table->string('show_name');
$table->string('url')->nullable();
$table->text('description')->nullable();
$table->integer('duration_seconds')->nullable();
```

**`podcasts`** — This Week With episodes

```php
$table->timestamp('occurred_at');        // published_at
$table->integer('season_number');
$table->integer('episode_number');
$table->json('topics')->nullable();      // ["Business", "Side Projects", "AI"]
$table->text('show_notes')->nullable();
$table->text('transcript')->nullable();
$table->integer('duration_seconds')->nullable();
$table->string('audio_url')->nullable();
$table->string('youtube_url')->nullable();
```

**`flights`** — uses meta JSON

```php
$table->timestamp('occurred_at');        // Departure datetime
$table->string('flight_number');
$table->string('airline_iata');
$table->string('origin_iata');
$table->string('destination_iata');
$table->decimal('origin_latitude', 10, 7)->nullable();
$table->decimal('origin_longitude', 10, 7)->nullable();
$table->decimal('destination_latitude', 10, 7)->nullable();
$table->decimal('destination_longitude', 10, 7)->nullable();
$table->integer('distance_miles')->nullable();
$table->string('cabin_class')->nullable();     // economy, business, first
$table->string('reason')->nullable();          // personal, business
$table->json('meta')->nullable();
// meta: { pnr, seat, seat_position, aircraft_type, airline_name,
//         origin_name, origin_city, origin_country, origin_terminal, origin_gate,
//         destination_name, destination_city, destination_country, destination_terminal, destination_gate,
//         scheduled_departure, actual_departure, scheduled_arrival, actual_arrival,
//         cancelled, diverted_to }
```

**`checkins`**

```php
$table->timestamp('occurred_at');
$table->string('venue_name');
$table->string('venue_category')->nullable();
$table->string('address')->nullable();
$table->string('city')->nullable();
$table->string('county')->nullable();
$table->string('country')->nullable();
$table->decimal('latitude', 10, 7)->nullable();
$table->decimal('longitude', 10, 7)->nullable();
$table->text('description')->nullable();
$table->string('platform_type')->nullable();
$table->string('platform_id')->nullable();
```

**`fuel`**

```php
$table->timestamp('occurred_at');
$table->unsignedInteger('vehicle_id')->default(1);  // References config/vehicles.php
$table->decimal('litres', 8, 3);
$table->decimal('cost', 8, 2);
$table->decimal('price_per_litre', 8, 3)->nullable();
$table->integer('odometer')->nullable();
$table->boolean('full_tank')->default(true);
$table->string('station')->nullable();
$table->string('city')->nullable();
$table->decimal('latitude', 10, 7)->nullable();
$table->decimal('longitude', 10, 7)->nullable();
```

**`projects`**

```php
$table->timestamp('occurred_at');        // launched_at
$table->string('title');
$table->string('slug');
$table->text('description')->nullable();
$table->text('long_description')->nullable();
$table->string('url')->nullable();
$table->string('github_url')->nullable();
$table->string('status');                // active, maintained, archived, on_hold
$table->boolean('featured')->default(false);
$table->json('tags')->nullable();
$table->timestamp('started_at')->nullable();
```

**`articles`**

```php
$table->timestamp('occurred_at');        // published_at
$table->string('title');
$table->string('slug');
$table->text('excerpt')->nullable();
$table->longText('content');             // longText for full Markdown articles
$table->boolean('draft')->default(false);
$table->json('tags')->nullable();
```

**`notes`**

```php
$table->timestamp('occurred_at');
$table->text('content');
```

### Supporting Tables

**`assets`** — polymorphic images

```php
$table->id();
$table->morphs('assetable');             // assetable_type, assetable_id
$table->string('type');                  // photo, cover, map
$table->string('path');                  // 2024/01/15/a1b2c3d4.jpg
$table->string('original_filename')->nullable();
$table->integer('width')->nullable();
$table->integer('height')->nullable();
$table->string('mime_type')->nullable();
$table->integer('size_bytes')->nullable();
$table->integer('order')->default(0);
$table->timestamps();
```

Asset type matrix:

| Table | cover | photos | map |
|-------|-------|--------|-----|
| activities | — | yes | yes |
| sleep | — | — | — |
| calories | — | — | — |
| flights | — | — | yes |
| checkins | — | yes | yes |
| events | yes | yes | — |
| media | yes | — | — |
| fuel | — | — | — |
| appearances | yes | — | — |
| podcasts | yes | — | — |
| projects | yes | yes | — |
| articles | yes | — | — |
| notes | — | yes | — |

**`timeline_entries`** — lightweight feed table

```php
$table->id();
$table->morphs('timelineable');          // timelineable_type, timelineable_id
$table->timestamp('occurred_at');
$table->index('occurred_at');
$table->unique(['timelineable_type', 'timelineable_id']); // Prevent duplicate entries
$table->timestamps();
```

### Config

**`config/vehicles.php`**

```php
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

---

## 2. Models & Relationships

### Timeline Models (13)

`Activity`, `Sleep`, `Calorie`, `Media`, `Event`, `Appearance`, `Podcast`, `Flight`, `Checkin`, `Fuel`, `Project`, `Article`, `Note`

### Supporting Models

- `Asset` — polymorphic image model
- `TimelineEntry` — polymorphic feed reference

### Shared Traits

**`HasAssets` trait:**
- `assets()` — morphMany to Asset
- `cover()` — morphOne to Asset, scoped to type `cover`
- `photos()` — morphMany to Asset, scoped to type `photo`
- `map()` — morphOne to Asset, scoped to type `map`

All four relationships are defined on the trait. Unused relationships simply return null/empty collections — this is by design. The asset matrix documents which models actually have data for each relationship, but the trait is universal to keep things simple.

**`HasTimelineEntry` trait:**
- `timelineEntry()` — morphOne to TimelineEntry
- Registers a model observer that creates/updates/deletes the TimelineEntry row on save/delete
- Syncs `occurred_at` from the parent model
- For Media: observer skips timeline entry creation when `status` is not `finished` (i.e., `occurred_at` is null)

### `Timelineable` Interface

Enforces:
```php
public function toTimelineCard(): array;
```

Returns a defined array shape:

```php
[
    'type'         => string,   // Table name: 'activity', 'media', 'flight', etc.
    'icon'         => string,   // Lucide icon name: 'footprints', 'film', 'plane', etc.
    'title'        => string,   // Primary display text
    'subtitle'     => ?string,  // Secondary display text
    'occurred_at'  => Carbon,   // Timestamp for display
    'accent'       => string,   // CSS variable name: 'activity', 'media', 'flight', etc.
    'meta'         => array,    // Type-specific data for the card component
]
```

**Note:** `toTimelineCard()` is used by the controller for any shared rendering logic (e.g., date grouping). The `dynamic.blade.php` component receives the full model and delegates to the type-specific card — individual cards access model properties directly, not via `toTimelineCard()`.

### `dynamic.blade.php` Resolution Strategy

The component resolves the card using a static mapping:

```php
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
$component = $componentMap[get_class($entry)];
@endphp

<x-dynamic-component :component="$component" :entry="$entry" />
```

### Model Configuration

All models:
- Cast `occurred_at` to `datetime`
- Cast JSON columns (`meta`, `tags`, `topics`, `stages`, `heart_rate`) to `array`
- Define `$fillable` for mass assignment

Platform-linked models:
- Computed `platform_url` accessor that builds URL from `platform_type` + `platform_id`

Podcast model:
- `getTitleAttribute()` accessor generates "Season {n}, Episode {n}" from `season_number` and `episode_number`
- Card component uses this accessor for display, accesses `season_number`/`episode_number` directly for the S7E238 compact format

### Calorie Timeline Entry Behaviour

The `Calorie` model has special observer behaviour because multiple rows (food items) exist per day, but the timeline should show one card per day:

1. **On create/update:** The observer truncates `occurred_at` to date-only and finds-or-creates a `TimelineEntry` where `timelineable_type = Calorie::class` and `timelineable_id` = the ID of the **first** calorie row for that date (determined by `Calorie::whereDate('occurred_at', $date)->orderBy('id')->first()->id`). The `occurred_at` on the timeline entry is set to noon on that date.

2. **On delete:** The observer checks if any other calorie rows exist for that date. If none remain, the `TimelineEntry` is deleted. If rows remain but the deleted row was the one referenced by `timelineable_id`, the timeline entry is updated to point to the new first row for that date.

3. **Card rendering:** The `calories.blade.php` card receives the single referenced `Calorie` model but queries all calories for that date to display the full daily breakdown (grouped by meal, with totals).

---

## 3. Blade Component Architecture

### Layout

**`layouts/app.blade.php`** — single layout with `$fullWidth = false` prop.

Always renders:
- Mobile header bar (logo + date filter + hamburger)
- Mobile slide-out navigation
- Top-right status bar (time, weather, location, battery, date filter)

Conditionally renders sidebar when `$fullWidth` is false.

`{{ $slot }}` for main page content.

### Sidebar Components

- `sidebar.blade.php` — wrapper (sticky, 280px, h-screen, scrollable)
- `sidebar.profile.blade.php` — headshot, name, tagline, social icons
- `sidebar.nav.blade.php` — navigation links with active state (Timeline, Stats, Map, About, Writing)
- `sidebar.streak.blade.php` — calorie streak counter with flame icon
- `sidebar.sparklines.blade.php` — last 14 days averages (running, calories, sleep) with sparkline SVGs
- `sidebar.reading.blade.php` — currently reading book cover + title + author

### Timeline Card Components

One per table, all in `resources/views/components/cards/`:

| Component | Table | Displays |
|-----------|-------|----------|
| `activity.blade.php` | activities | Map thumbnail, distance, duration, pace (or exercise count for gym) |
| `sleep.blade.php` | sleep | Duration, bedtime/wake, coloured stages bar |
| `calories.blade.php` | calories | Daily total, macros, meal-by-meal breakdown |
| `media.blade.php` | media | Poster, title, rating. Adapts subtitle for film/tv/book |
| `flight.blade.php` | flights | Route (IATA codes), cities, duration, map |
| `checkin.blade.php` | checkins | Venue, category, photo, mini map |
| `fuel.blade.php` | fuel | Litres, cost, price per litre, vehicle name |
| `event.blade.php` | events | Artist/show name, venue, city, type badge |
| `appearance.blade.php` | appearances | Episode title, show name, link |
| `podcast.blade.php` | podcasts | Season/episode, topic badges, play button |
| `project.blade.php` | projects | Title, tags as badges, launch indicator |
| `article.blade.php` | articles | Title, excerpt, read time |
| `note.blade.php` | notes | Short text content |

**`cards/dynamic.blade.php`** — resolves the correct card component via static class-to-component mapping (see Section 2).

### Timeline Rendering

```blade
@foreach ($entries as $entry)
    <x-cards.dynamic :entry="$entry->timelineable" />
@endforeach
```

### Homepage View

`pages/home.blade.php` — extends `layouts/app`:
- Bio intro paragraphs with inline icons/links and dynamic data (`$calorieStreak`, `$episodeCount`)
- Timeline feed with pagination
- Date grouping headers (e.g., "Friday 27 February 2026")

### Date Display

All cards show actual dates in the format: `Fri 10:30am` + `27 Feb 2026`. Never relative time ("3 days ago").

---

## 4. Routes & Controller

### Routes (Sub-project 1 only)

```php
Route::get('/', [TimelineController::class, 'index']);
```

### TimelineController@index

- Queries `TimelineEntry::query()->orderByDesc('occurred_at')->paginate(20)`
- Eager-loads `timelineable` using morphTo type mapping to avoid N+1
- Groups calorie entries by date (one card per day — already handled by the observer's upsert, so no grouping needed at query time)
- Computes sidebar widget data:
  - Calorie streak (consecutive days with logged calories, queried from `calories` table directly)
  - 14-day averages (running distance, calories, sleep hours — queried from source tables directly)
  - Currently reading book (`Media::where('type', 'book')->where('status', 'reading')->latest('created_at')->first()`)
  - Episode count (`Podcast::count()`)
- Returns `pages/home` view

---

## 5. Factories & Seeders

### Factories (13)

One per timeline model, generating realistic data:

- **ActivityFactory** — realistic run/ride/walk/gym data (5-21km, 20-90min, exercises JSON for gym)
- **SleepFactory** — 6-9hr durations, realistic bedtime/wake windows, stage breakdowns that sum correctly
- **CalorieFactory** — real food names grouped by meal, realistic macro splits
- **MediaFactory** — real-ish titles, 1-10 ratings, proper meta per type. Status defaults to `finished`.
- **EventFactory** — venue names, cities, type-appropriate names
- **AppearanceFactory** — episode titles, show names, durations
- **PodcastFactory** — sequential season/episode numbers, topic arrays
- **FlightFactory** — real IATA codes, coordinates, distances, airline codes, realistic meta
- **CheckinFactory** — venue names with categories, cities, coordinates
- **FuelFactory** — realistic litres (30-55L), costs, price-per-litre, odometer readings
- **ProjectFactory** — tech project names, slugs, tags, status values
- **ArticleFactory** — blog post titles, markdown content, excerpts, tags
- **NoteFactory** — short text content

### DatabaseSeeder

- Creates 1 user
- Seeds 6 months of data backward from today across all types:
  - Activities: 3-4 per week
  - Sleep: daily
  - Calories: daily (multiple items per day, grouped by meal)
  - Media: 2-3 films per week, TV episodes in clusters, 1 book per month, 1 book with `status = 'reading'` and null `occurred_at` (for sidebar widget)
  - Checkins: 1-2 per day
  - Flights: 1-2 per month
  - Fuel: 1-2 per month
  - Events: 1-2 per month
  - Appearances, podcasts, projects, articles, notes: occasional
- Additionally seeds calorie data going back ~2,145 days (just 1 item per day for old dates) to demonstrate the streak widget
- Timeline entries created automatically via model observers
- Asset records created for applicable models with placeholder paths
- No real image files — cards handle missing images gracefully

---

## 6. CSS & Tailwind Setup

### Porting from Tailwind v3 to v4

The mockup source files (`index.css` + `tailwind.config.ts`) use Tailwind v3 format. These are translated to Tailwind v4's CSS-first `@theme` approach in `resources/css/app.css`.

### Design Tokens (CSS Custom Properties)

All values from the mockup's `:root` block, with the following consolidation:

**13 data-type accent colours (one per table):**

| Variable | HSL Value | Used by |
|----------|-----------|---------|
| `--color-activity` | `152 55% 40%` | run, ride, walk, gym, swim, yoga |
| `--color-sleep` | `245 50% 58%` | sleep |
| `--color-food` | `25 90% 52%` | calories |
| `--color-media` | `340 60% 52%` | film, tv_episode, book |
| `--color-event` | `270 55% 52%` | concert, theatre, comedy, festival |
| `--color-appearance` | `320 55% 50%` | podcast, livestream, interview, talk |
| `--color-podcast` | `210 65% 48%` | This Week With episodes |
| `--color-flight` | `200 75% 50%` | flights |
| `--color-checkin` | `170 55% 40%` | checkins |
| `--color-fuel` | `45 85% 48%` | fuel |
| `--color-project` | `185 60% 42%` | projects |
| `--color-article` | `215 18% 48%` | articles |
| `--color-note` | `36 40% 50%` | notes |

**Removed:** `--color-gym`, `--color-film`, `--color-tv`, `--color-book`, `--color-show` (consolidated into their parent table colours).

**Note on colour names vs. brief:** The project brief describes Activity as "Coral/orange" but the mockup CSS defines `--color-activity` as green (HSL 152). The spec uses the mockup's values as authoritative — the mockup represents the finalised design, superseding the brief's colour descriptions.

**UI tokens preserved from mockup:**
- `--background`, `--foreground`, `--card`/`--card-foreground`, `--popover`/`--popover-foreground`
- `--primary`/`--primary-foreground`, `--secondary`/`--secondary-foreground`
- `--muted`/`--muted-foreground`, `--accent`/`--accent-foreground`
- `--destructive`/`--destructive-foreground`
- `--border`, `--input`, `--ring`, `--radius`
- `--sidebar-*` variants
- `--widget-bg`, `--widget-shadow`
- `--sleep-awake`, `--sleep-rem`, `--sleep-light`, `--sleep-deep`

**Three themes:**
- Light (`:root`) — warm cream background
- Dark (`.dark` class) — full dark mode overrides
- Cinematic bento (`.theme-b` class) — Space Grotesk + JetBrains Mono, dark with brighter accents

### Fonts

Loaded via Google Fonts CDN in the layout:
- `font-display`: Instrument Serif, Georgia, serif (headings)
- `font-body`: DM Sans, system-ui, sans-serif (body)
- `font-mono`: JetBrains Mono, monospace (theme-b)
- Space Grotesk (theme-b headings)

### Custom Utilities

- `.font-display`, `.font-body` — font family helpers
- `.widget-card` — widget background + shadow + border-radius
- `.glass-card` — frosted glass effect (theme-b)
- `.data-accent-{table}` — text colour per data type (13 utilities)
- `.data-bg-{table}` — background colour at 10% opacity per data type (13 utilities)

### Body Background

Subtle grid pattern from mockup:
```css
background-image:
    radial-gradient(ellipse at 50% 0%, transparent 0%, hsl(var(--background)) 70%),
    linear-gradient(hsl(var(--foreground) / 0.035) 1px, transparent 1px),
    linear-gradient(90deg, hsl(var(--foreground) / 0.035) 1px, transparent 1px);
background-size: 100% 100%, 40px 40px, 40px 40px;
```

### Animations

- `fade-in`: 0.4s ease-out (opacity 0 + translateY 8px → visible)
- `slide-in`: 0.3s ease-out (opacity 0 + translateX -8px → visible)

### Alpine.js

Added to `package.json`, initialised in `resources/js/app.js`. Used for:
- Mobile nav slide-out toggle
- Expandable card sections
- Hover interactions
