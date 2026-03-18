# Project Brief: Personal Data Hub

## The Idea

A personal website that tracks everything I do — runs, films, places I've been, what I ate, flights I've taken — all in one place.

It's like a private social media feed just for me, so I can look back and see exactly what I was doing on any day in the past.

A digital scrapbook of my life that updates itself.

### Timeline Cards

Each card appears in the feed on the day it happened:

| Card | Shows |
|------|-------|
| Activity | Run/ride/gym with map, distance, duration |
| Sleep | Hours slept, sleep stages bar |
| Calories | Daily total, macros, meal breakdown |
| Film/TV/Book | Poster, title, rating |
| Flight | Route, cities, duration |
| Checkin | Venue, photo, mini map |
| Fuel | Litres, cost, vehicle |
| Event | Concert/show, venue |
| Appearance | Podcast/livestream I was on |
| Podcast | Episode from This Week With |
| Project | Something I launched |
| Article/Note | Something I wrote |

---

## Overview

A custom Laravel-based personal website that serves as a centralised hub for life data. The site functions as a "quantified self" platform - a personal social media timeline that aggregates activities, health metrics, media consumption, travel, and written content into a single, permanent record.

**Inspiration:** Aaron Parecki (aaronparecki.com), Luke/Ekul (ekul.me), Zach Leatherman (zachleat.com), Marco Cornacchia (marco.fyi)

---

## Design Aesthetic

**Style:** Warm minimalism — clean and restrained, but not cold. Feels like a digital journal, not a dashboard.

**Key elements:**

| Element | Detail |
|---------|--------|
| Background | Cream/off-white (#f5f5f7) — feels like paper, not screen |
| Cards | Borderless, content floats on background |
| Shadows | Very subtle or none |
| Corners | Softly rounded (12-16px) |
| Typography | System sans-serif, comfortable sizing |
| Icons | Soft muted colours (sage green, coral, muted blue) |
| Photos | Full-width within cards, rounded corners |
| Whitespace | Generous — content breathes |
| Dates | Actual dates shown (not "3 days ago") — "Fri 10:30am, 27 Feb 2026" |

**Layout:**
- Sidebar-first (no traditional header)
- Sidebar: profile photo, tagline, navigation, live widgets (streak, sparklines, currently reading)
- Top-right status bar: time, weather, location, battery, date filter
- Main content: timeline feed of cards

**Colour accents per type:**
- Checkin: Sage green
- Activity: Coral/orange
- Film: Purple
- Flight: Blue
- Food: Orange/red

**The vibe:** Opening a nice notebook, not a tech dashboard. Personal. Lived-in. Cozy web.

---

## Core Concept

- **Timeline-based:** All entries flow into a chronological feed
- **Automated where possible:** Integrations pull data automatically; manual entry only where necessary
- **Publicly accessible:** Most content is public (health metrics may be private)
- **Historically complete:** Import existing data going back to 2006 in some cases
- **Self-hosted data ownership:** All data stored locally in your own database

---

## Data Types & Sources

### Timeline Data Types

All entries appear in the unified timeline feed. Data is stored in a **platform-agnostic** format (platform_type + platform_id pattern) so it survives if services die.

Data is consolidated into logical tables to keep the schema manageable while allowing easy addition of new types.

| Table | Types | Icon | Source | Method |
|-------|-------|------|--------|--------|
| `activities` | run, ride, walk, gym, swim, yoga | 🏃🚴🏋️ | Strava, Apple Health, Manual | Webhook / Health Export / Admin |
| `sleep` | — | 🌙 | Apple Health | Health Auto Export |
| `calories` | — | 🔥 | Lose It | Daily CSV email parse |
| `media` | film, tv_episode, book | 🎬📺📖 | Trakt, Manual | Webhook / Admin form |
| `events` | concert, theatre, comedy, festival | 🎵🎭 | Manual | Admin form |
| `appearances` | podcast, livestream, interview, talk | 🎙️ | Manual | Admin form |
| `podcasts` | — | 🎧 | Manual / Sync | This Week With episodes |
| `flights` | — | ✈️ | Manual | Admin form / Apple Shortcut |
| `checkins` | — | 📍 | Swarm | Import historic + poll API |
| `fuel` | — | ⛽ | Manual | Admin form / Apple Shortcut |
| `projects` | — | 🚀 | Manual | Admin / Markdown |
| `articles` | — | 📝 | Manual | Markdown / Admin form |
| `notes` | — | 💬 | Manual | Admin form / Apple Shortcut |

**14 tables total** (13 main + `assets`) — adding a new media type, event type, or activity type requires no migration, just a new `type` value.

### Supporting Data

| Table | Purpose |
|-------|---------|
| `assets` | All images — photos, covers, generated maps (polymorphic) |

Vehicles stored in config (rarely changes).

---

## Features

### Timeline / Feed

- Unified chronological stream across all entry types
- Books appear when finished, projects when launched, events on event date
- Filter by type (e.g. show only runs, only films)
- Year/month archives with calendar view
- "On This Day" - entries from 1, 2, 5+ years ago
- TV episodes grouped ("Watched 3 episodes of Severance")
- Actual dates shown (not just relative time) for archive browsing

### Photos

- Not a separate entry type
- Attached to parent entries (activities, checkins, events, notes, etc.)
- Aggregated `/photos` page pulling from all entries
- Clicking a photo navigates to its parent entry

### Stats & Insights

- Year in Review auto-generated pages
- Monthly summaries
- Streaks (calorie logging is 2,145+ days!)
- Personal records with dates (longest run, most elevation, etc.)
- Fun contextual stats ("X% around Earth", "X marathons equivalent")
- Graphs and trends over time
- Per-vehicle fuel analytics (MPG, cost-per-mile)

### Data Stories

Editorial pages with narrative + embedded charts:
- **Templated** (auto-generated): Year in Review, Monthly Recap, Activity Stats
- **Editorial** (handcrafted): "The Streak", "Sleep vs Running", "Heart Rate Deep Dive"
- Charts pull live data but narrative is written
- Filterable by year/date range where relevant

### Search

Powerful search with filter chips (Linear/Notion style):
- Single search bar with "+ Add filter" button
- Filter chips stack and combine (e.g., `Type: Run` + `Year: 2024`)
- Available filters: Type, Date range, Year, Month, Location, Has photo, Rating
- Segmented tabs for quick filtering: All, Activities, Media, Places, Other
- Sub-filters adapt to segment (distance for Activities, rating for Media)
- "Random" button for surprise discovery

### Dynamic Profile Content

Bio and sidebar pull live data:

**Main intro** (with inline icons/links):
```
Hey! I'm Taylor, a web developer in London who's been tracking everything — 
including every calorie for {{ $calorieStreak }} days straight (and counting). 
I run a small web agency I started at 19, develop a WordPress plugin, and 
co-host a weekly podcast with my dad with over {{ $episodeCount }} episodes. 
When I close my laptop, I'm on a squash court or making another coffee.
```

**Sidebar tagline:**
```
Building things, tracking everything.
```

**Short bio:**
```
I build stuff on the internet, track everything, and drink too much coffee.
```

**Sidebar widgets with live data:**
- NOW: Current time/timezone, location, weather, phone battery
- STREAK: {{ $calorieStreak }} days calorie logging
- LAST 14 DAYS: Running avg, Calories avg, Sleep avg (with sparklines)
- READING: Current book from `media` table

### Maps

- All activities plotted (runs, rides, walks)
- All checkins/places visited
- Flight routes as arcs
- Countries/cities visited

### Activity Rings

- Daily Move/Exercise/Stand ring display (Apple Watch style)
- Weekly/monthly completion views
- Shown in sidebar widget

### Sleep Visualisation

- Sleep stages as coloured bar (REM, light, deep, awake)
- Shows actual pattern through the night
- Duration and quality metrics

### Food Breakdown

- Full meal-by-meal detail (breakfast, lunch, dinner, snacks)
- Individual items with servings and calories
- Daily totals and macro breakdown

### Weather Context

- Automatically attached to entries based on timestamp and location
- Fetched from weather API at time of entry creation

---

## Technical Architecture

### Stack

- **Laravel** - Core framework
- **Blade** - Templating (server-rendered)
- **Tailwind CSS** - Styling
- **Alpine.js** - Interactivity (dropdowns, modals, transitions, toggles)
- **No JS framework** - No React/Vue/etc., keep it simple
- **No admin panel package** - Custom admin forms (simple CRUD)

### Why This Stack Works

- Server-rendered = fast initial load, good SEO
- Blade components = reusable UI pieces without framework overhead
- Tailwind = achieves the Apple aesthetic with utility classes (blur, shadows, rounded corners, spacing)
- Alpine = just enough JS for interactivity without build complexity
- Easy to maintain long-term for a solo project

### Achieving the Design with This Stack

**Tailwind classes that get you the Apple look:**
```html
<!-- Frosted glass card -->
<div class="bg-white/70 backdrop-blur-xl rounded-2xl shadow-sm border border-white/20">

<!-- Subtle hover lift -->
<div class="transition hover:shadow-lg hover:-translate-y-1">

<!-- Soft shadows -->
<div class="shadow-[0_2px_20px_rgba(0,0,0,0.04)]">
```

**Alpine.js for interactions:**
```html
<!-- Hover reveal (like Marco's photo card) -->
<div x-data="{ hover: false }" @mouseenter="hover = true" @mouseleave="hover = false">
  <div x-show="hover" x-transition.opacity>
    <!-- Overlay content -->
  </div>
</div>

<!-- Expandable cards -->
<div x-data="{ expanded: false }">
  <button @click="expanded = !expanded">Toggle</button>
  <div x-show="expanded" x-collapse>...</div>
</div>
```

**Blade components for consistency:**
```
/resources/views/components/
  card.blade.php          <!-- Base card with frosted glass -->
  stat-card.blade.php     <!-- Small stat display -->
  activity-card.blade.php <!-- Strava activity -->
  film-card.blade.php     <!-- Movie poster + details -->
  now-widget.blade.php    <!-- Sidebar "now" section -->
  calendar-day.blade.php  <!-- Day cell for calendar view -->
```

### API endpoints

### Content Storage

**Database (14 tables):**
- Activities, sleep, calories
- Media (films, TV, books)
- Events, appearances, podcasts
- Flights, checkins, fuel
- Projects, articles, notes
- Assets (polymorphic images)

Vehicles stored in config (rarely changes).

**Asset Storage:**

All images in polymorphic `assets` table:

```php
// assets table
$table->morphs('assetable');  // links to any model
$table->string('type');       // cover, photo, map
$table->string('path');       // 2024/01/15/uuid.jpg
```

**File path structure:**
```
storage/app/public/
└── 2024/
    └── 01/
        └── 15/
            ├── a1b2c3d4.jpg
            └── e5f6g7h8.png
```

Path format: `{year}/{month}/{day}/{uuid}.{ext}`

### Automation Infrastructure

**Webhook Endpoints:**
- `POST /webhooks/strava` - receives activity updates
- `POST /webhooks/github` - receives commit/push events
- `POST /webhooks/trakt` - receives watch history
- `POST /api/health` - receives Health Auto Export data

**Scheduled Jobs (Laravel Scheduler):**
- Poll Swarm API for new checkins (every 15-30 mins)
- Poll Trakt API as backup (if not using webhooks)
- Check email for Lose It CSV (daily)
- Fetch daily weather summary

**Email Parsing (for Lose It):**
- Option A: Dedicated mailbox checked via IMAP
- Option B: Mailgun/Postmark inbound parsing → webhook to your endpoint
- Parse CSV attachment, insert individual food items

### Authentication

- Laravel auth for admin section
- API tokens for Apple Shortcuts
- No public registration - single user site

### Admin Interface (Custom)

Simple forms, no packages:
- Flights (with airport search)
- Fuel (vehicle from config)
- Books (with ISBN lookup)
- Events (concerts, theatre, etc.)
- Appearances (podcasts, livestreams)
- Projects (with tags)
- Podcasts (sync from This Week With)
- Notes
- Manual photo uploads

~10 forms total. Basic Laravel CRUD.

### Apple Shortcuts Integration

Shortcuts can POST to authenticated API endpoints:
- Quick note capture
- Log fuel at petrol station
- Log flight
- Any other manual entry type

---

## Data Models

### Standardised Pattern

Every table uses `occurred_at` as the primary timeline date:

```php
$table->id();
$table->timestamp('occurred_at');  // When it shows in timeline
$table->timestamps();              // created_at, updated_at
```

Platform URLs built in model (not stored):

```php
public function getPlatformUrlAttribute(): ?string
{
    return match($this->platform_type) {
        'strava' => "https://www.strava.com/activities/{$this->platform_id}",
        'swarm' => "https://www.swarmapp.com/c/{$this->platform_id}",
        'trakt' => "https://trakt.tv/id/{$this->platform_id}",
        default => null,
    };
}
```

### Assets (Polymorphic)

All images stored locally. One table for everything:

```php
// assets
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

**Asset types:**

| Type | Count | Purpose |
|------|-------|---------|
| `cover` | One | Primary thumbnail/poster/hero for the entry |
| `photo` | Many | User photos attached to entry |
| `map` | One | Generated static map (polyline/flight path) |

**Path structure:** `{year}/{month}/{day}/{uuid}.jpg`

**Which tables get what:**

| Table | cover | photos | map |
|-------|-------|--------|-----|
| activities | — | ✓ | ✓ |
| sleep | — | — | — |
| calories | — | — | — |
| flights | — | — | ✓ |
| checkins | — | ✓ | ✓ |
| events | ✓ | ✓ | — |
| media | ✓ | — | — |
| fuel | — | — | — |
| appearances | ✓ | — | — |
| podcasts | ✓ | — | — |
| projects | ✓ | ✓ | — |
| articles | ✓ | — | — |
| notes | — | ✓ | — |

**External images downloaded locally** — film posters from TMDB, podcast logos, etc. are fetched and stored as assets, not linked externally.

### Core Entities

**Activity** (`activities`) — uses meta JSON
```php
$table->timestamp('occurred_at');
$table->string('type');                  // run, ride, walk, gym, swim, yoga
$table->string('name')->nullable();
$table->integer('duration_seconds');
$table->integer('calories')->nullable();
$table->decimal('distance_km', 8, 3)->nullable();
$table->text('polyline')->nullable();
$table->json('heart_rate')->nullable();  // [{time, bpm}, ...] for chart
$table->string('platform_type')->nullable();
$table->string('platform_id')->nullable();
$table->json('meta')->nullable();
// meta: { description, elevation_gain, exercises, kudos_count }
```

**Sleep** (`sleep`)
```php
$table->timestamp('occurred_at');        // The night (shows in timeline)
$table->timestamp('bedtime');
$table->timestamp('wake_time');
$table->integer('duration_minutes');
$table->integer('awake_minutes')->nullable();
$table->integer('rem_minutes')->nullable();
$table->integer('core_minutes')->nullable();
$table->integer('deep_minutes')->nullable();
$table->json('stages');                  // Raw Apple Watch segments for chart
```

**Calories** (`calories`) — one row per food item
```php
$table->timestamp('occurred_at');        // Date of meal
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

**Media** (`media`) — uses meta JSON for type-specific fields
```php
$table->timestamp('occurred_at');        // Watched/finished date
$table->string('type');                  // film, tv_episode, book
$table->string('title');
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

**Event** (`events`)
```php
$table->timestamp('occurred_at');
$table->string('type');                  // concert, theatre, comedy, festival
$table->string('name');                  // Artist/show name
$table->string('venue_name')->nullable();
$table->string('address')->nullable();
$table->string('city')->nullable();
$table->string('country')->nullable();
$table->decimal('latitude', 10, 7)->nullable();
$table->decimal('longitude', 10, 7)->nullable();
$table->decimal('ticket_price', 8, 2)->nullable();
$table->text('notes')->nullable();
```

**Appearance** (`appearances`)
```php
$table->timestamp('occurred_at');
$table->string('type');                  // podcast, livestream, interview, talk
$table->string('title');                 // Episode/video name
$table->string('show_name');
$table->string('url')->nullable();
$table->text('description')->nullable();
$table->integer('duration_seconds')->nullable();
```

**Podcast** (`podcasts`) — This Week With episodes
```php
$table->timestamp('occurred_at');        // published_at
$table->integer('season_number');
$table->integer('episode_number');
$table->json('topics')->nullable();      // ["Business", "Side Projects", "AI"]
$table->text('show_notes')->nullable();
$table->text('transcript')->nullable();
$table->integer('duration_seconds')->nullable();
$table->string('audio_url')->nullable(); // Hosted audio file
$table->string('youtube_url')->nullable();
```

Title generated in model:
```php
public function getTitleAttribute(): string
{
    return "Season {$this->season_number}, Episode {$this->episode_number}";
}
```

Canonical URL computed: `https://www.thisweekwith.co.uk/season-{n}/episode-{n}/`
Cover image (episode art) stored in assets table.

**Flight** (`flights`) — uses meta JSON
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

**Checkin** (`checkins`)
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

**Fuel** (`fuel`)
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

Vehicles stored in config (rarely changes):
```php
// config/vehicles.php
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

**Project** (`projects`)
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

**Article** (`articles`)
```php
$table->timestamp('occurred_at');        // published_at
$table->string('title');
$table->string('slug');
$table->text('excerpt')->nullable();
$table->text('content');
$table->boolean('draft')->default(false);
$table->json('tags')->nullable();
```

**Note** (`notes`)
```php
$table->timestamp('occurred_at');
$table->text('content');
```

### Summary

| Table | Approach | Notes |
|-------|----------|-------|
| activities | meta JSON | Type-specific (exercises, elevation) |
| flights | meta JSON | Lots of airport/terminal info |
| media | meta JSON | Type-specific (film/tv/book) |
| sleep | columns | |
| calories | columns | |
| checkins | columns | |
| events | columns | |
| fuel | columns | vehicle_id refs config |
| appearances | columns | |
| podcasts | columns | |
| projects | columns | |
| articles | columns | |
| notes | columns | |
| assets | columns | Supporting, polymorphic |

**14 tables. 3 use meta JSON. Vehicles in config.**

---

## External APIs & Services

### Required Integrations

| Service | Purpose | Auth |
|---------|---------|------|
| Strava API | Activities | OAuth |
| Trakt API | Films & TV | OAuth |
| Swarm/Foursquare API | Checkins | OAuth |
| GitHub API | Code activity | Personal access token |
| OpenFlights Database | Airport data (IATA, coordinates) | Static dataset |
| Open Library / Google Books | ISBN lookup for books | Public API |
| Weather API (OpenWeatherMap / Pirate Weather) | Weather context | API key |
| Mailgun or Postmark | Inbound email parsing for Lose It | API key |

### Optional / Future

| Service | Purpose |
|---------|---------|
| Foursquare Places API | Venue search for manual checkins |
| Nominatim (OpenStreetMap) | Location/address search |
| Mapbox or Leaflet | Map rendering |

---

## Pages / Routes

### Public

- `/` - Homepage - timeline feed + sidebar
- `/about` - Bio page
- `/[year]` - Year archive with stats summary
- `/[year]/[month]` - Month archive with calendar view
- `/on-this-day` - Historical entries from this date
- `/photos` - Aggregated photo grid
- `/map` - Full-width map (all activities, checkins, flights)
- `/stats` - Full-width stats dashboard
- `/stats/[year]` - Year in review
- `/search` - Search with filter chips

**Activities** (dynamic: `/activities/[type?]`)
- `/activities` - All activities
- `/activities/runs` - Runs only
- `/activities/rides` - Rides only
- `/activities/gym` - Gym workouts only
- `/activities/walks` - Walks only

**Media** (dynamic: `/media/[type?]`)
- `/media` - All media (films, TV, books)
- `/media/films` - Films only
- `/media/tv` - TV episodes only
- `/media/books` - Books only

**Events** (dynamic: `/events/[type?]`)
- `/events` - All events
- `/events/concerts` - Concerts only
- `/events/theatre` - Theatre only
- `/events/comedy` - Comedy only

**Appearances** (dynamic: `/appearances/[type?]`)
- `/appearances` - All appearances
- `/appearances/podcasts` - Podcasts only
- `/appearances/livestreams` - Livestreams only

**Other**
- `/flights` - Flight log + route map
- `/fuel` - Fuel log + vehicle stats
- `/checkins` - Checkins feed
- `/projects` - Project portfolio
- `/projects/[slug]` - Single project
- `/articles` - Blog posts
- `/articles/[slug]` - Single article

**Podcast** (This Week With episodes, syndicated)
- `/podcast` - All episodes
- `/podcast/season-[n]` - Filtered by season
- `/podcast/season-[n]/episode-[n]` - Single episode (mirrors thisweekwith.co.uk structure)

*Each episode page includes `<link rel="canonical" href="https://www.thisweekwith.co.uk/...">` for SEO.*

### Dynamic Route Pattern

Routes follow a `/[table]/[type?]` pattern where type is optional:

```php
// routes/web.php
Route::get('/activities/{type?}', [ActivityController::class, 'index']);
Route::get('/media/{type?}', [MediaController::class, 'index']);
Route::get('/events/{type?}', [EventController::class, 'index']);
Route::get('/appearances/{type?}', [AppearanceController::class, 'index']);
```

```php
// ActivityController.php
public function index(?string $type = null)
{
    $query = Activity::query();
    
    if ($type) {
        // Convert URL slug to type: 'runs' -> 'run', 'gym' -> 'gym'
        $query->where('type', Str::singular($type));
    }
    
    return view('activities.index', [
        'activities' => $query->latest('occurred_at')->paginate(20),
        'currentType' => $type,
    ]);
}
```

### Admin (Auth Required)

Simple custom admin at `/admin`:
- `/admin` - Dashboard
- `/admin/activities` - Manage activities
- `/admin/media` - Manage films, TV, books
- `/admin/events` - Manage concerts, theatre, etc.
- `/admin/appearances` - Manage podcast appearances, etc.
- `/admin/flights` - Manage flights
- `/admin/fuel` - Manage fuel
- `/admin/projects` - Manage projects
- `/admin/articles` - Manage articles
- `/admin/notes` - Manage notes

### API

- `POST /webhooks/strava` - Activity webhook
- `POST /webhooks/github` - Commit webhook
- `POST /webhooks/trakt` - Watch history webhook
- `POST /webhooks/swarm` - Checkin webhook
- `POST /api/health` - Health Auto Export (sleep, activity rings, gym)
- `POST /api/inbound-email` - Lose It CSV parsing
- `POST /api/notes` - Apple Shortcut
- `POST /api/flights` - Apple Shortcut
- `POST /api/fuel` - Apple Shortcut
- `POST /api/gym` - Apple Shortcut for gym workouts

---

## Historic Data Import

### Priority Imports

| Data | Source | Format | Notes |
|------|--------|--------|-------|
| Strava | Strava bulk export | JSON/GPX | Full activity history |
| Swarm | Foursquare export | JSON | 2020-2023 checkins |
| Flights | Current records | ? | Since 2006 |
| Fuel | Current records | ? | Since 2018 |
| Calories | Lose It export | CSV | Sept 2019-present |
| Sleep | Apple Health export | XML | Parse and import |
| Trakt | Trakt export | JSON | Full watch history |
| GitHub | API or export | - | Commit history |

### Backfill Tasks

- Shows/concerts: Scrape from email confirmations (Ticketmaster, Dice, etc.)
- Projects: Update from existing site/records

---

## Design & UX Direction

### Design References

| Site | What to Take |
|------|--------------|
| **Aaron Parecki** (aaronparecki.com) | Monthly calendar view with activity per day, transport time/distance summaries, photo grids, timeline structure |
| **Luke / Ekul** (ekul.me) | Fun recontextualised stats, sidebar widgets, heatmaps, sparklines, personal records, "Now" section |
| **Zach Leatherman** (zachleat.com) | Inline avatars/icons in text, stats as narrative prose, clean sidebar layout |
| **Marco Cornacchia** (marco.fyi) | Interactive cards, polish, playful but professional |

### Layout Structure

**Sidebar + Main Content Approach**

```
┌─────────────────────────────────────────────────────────┐
│  Header / Nav                                           │
├────────────────┬────────────────────────────────────────┤
│                │                                        │
│    Sidebar     │           Main Content                 │
│    (fixed)     │           (scrollable)                 │
│                │                                        │
│  - Profile     │   Timeline / Stats / Content           │
│  - Now section │                                        │
│  - Quick stats │                                        │
│  - Nav links   │                                        │
│                │                                        │
└────────────────┴────────────────────────────────────────┘
```

**Page Layout Rules:**

| Page Type | Sidebar | Reason |
|-----------|---------|--------|
| Homepage / Timeline | Yes | Core experience, consistent navigation |
| Filtered feeds (runs, films, etc.) | Yes | Same as homepage |
| Single entry detail | Yes | Maintains context |
| Monthly archive | Yes | Calendar + timeline below |
| Stats overview | No | Full width for charts and visualisations |
| Year in review | No | Rich layouts need space |
| Maps page | No | Map needs maximum space |
| Single article | Optional | Full width may read better |
| Projects | Yes or No | Depends on layout needs |
| Admin pages | No | Functional app-style interface |

### About Page Style

Conversational narrative approach:

**Sections like:**
- Where I'm from
- What I used to do  
- What I do now
- Where I'm at now
- What I'm looking for / interested in

**Characteristics:**
- Written in first person, conversational tone
- Personal stories and anecdotes
- Inline links with icons to companies/projects mentioned
- Bold text for key roles/companies
- Feels human, not corporate

### Sidebar Widgets

**Static/Profile:**
- Profile photo
- Name / tagline
- Location

**"Now" Section (live data):**
- Current time + timezone
- Current location + weather
- Phone battery level (fun touch)
- Activity rings (today)
- Currently reading / watching
- What I'm working on
- Last activity (recent run, checkin, etc.)

**Quick Stats:**
- Current streaks (calories logged, running, etc.)
- This month: X runs, X films, X checkins
- Mini sparkline of recent activity

**Navigation:**
- Primary nav links
- Or keep nav in header, sidebar just for widgets

### Visual Elements

**Sparklines & Mini Charts**
- Inline sparklines showing trends (weekly distance, monthly calories)
- Small enough to fit in sidebar or inline with stats
- Used on timeline entries too (e.g., elevation profile on a run)

**Heatmaps**
- GitHub-style contribution grid for yearly activity overview
- Per data type or combined
- Shows patterns at a glance

**Inline Avatars & Icons**
- When mentioning people, show their avatar inline
- When mentioning places, show venue category icon
- When listing technologies, show logos
- Makes dense text scannable and visually interesting

Example:
> "Built with [Laravel logo] Laravel, [Tailwind logo] Tailwind, and hosted on [Forge logo] Forge"

**Activity Rings**
- Apple Watch-style ring visualisation
- Daily, weekly, monthly views
- Colour-coded: Move (red), Exercise (green), Stand (blue)

### Calendar / Monthly View

Based on Aaron Parecki's approach, enhanced with your existing design:

**Monthly Summary Stats (top of page):**
- Total distance run/cycled
- Films watched
- Checkins made
- Hours slept (average)
- Calories (average or total)

**Calendar Grid:**
Each day cell shows:
- Date number
- Sleep duration (moon icon)
- Calories (fire icon)  
- Checkin location (pin icon)
- Activity indicator (run/ride icon + distance)
- Film/TV watched
- Clickable → expands or navigates to day view

```
┌─────────────────────────────────────┐
│ 15                                  │
│ 🌙 7h 32m                           │
│ 🔥 2,150 kcal                       │
│ 🏃 5.2km                            │
│ 📍 Costa Coffee                     │
│ 🎬 The Brutalist                    │
│ ○○○ (activity rings)               │
└─────────────────────────────────────┘
```

**Below Calendar:**
- Photo grid from that month
- Timeline of entries (optional, or just use calendar as navigation)

### Fun / Recontextualised Stats

Raw numbers are forgettable. Translate them into relatable comparisons:

**Running/Cycling:**
- "X% around Earth" (40,075km = 100%)
- "Equivalent to X marathons"
- "X times up Mt. Snowdon" (elevation)
- "Could've run to Paris and back"

**Flights:**
- "X times around the world"
- "X% of the way to the moon"
- "Equivalent to X London-Sydney flights"

**Calories:**
- "X Big Macs worth of energy"
- "Could power a lightbulb for X days"

**Fuel:**
- "X bathtubs of petrol"
- "£X spent on fuel (enough for X holidays)"

**Sleep:**
- "X days of your life asleep this year"
- "Average X hours (X% of recommended)"

**Films/TV:**
- "X days of screen time"
- "Equivalent to watching the LOTR extended trilogy X times"

**General:**
- "Streak: X days" (prominently displayed)
- Personal records with dates ("Longest run: 21km on 15 March 2024")

### Personal Records Section

Prominently displayed, automatically tracked:

| Record | Value | Date |
|--------|-------|------|
| Longest run | 21.1km | 15 Mar 2024 |
| Most elevation (single activity) | 892m | 3 Sep 2023 |
| Longest flight | 12h 15m (LHR→SIN) | 8 Jan 2022 |
| Most calories in a day | 3,850 kcal | 25 Dec 2025 |
| Longest sleep | 11h 23m | 2 Jan 2024 |
| Most films in a month | 14 | October 2023 |

### Mobile Considerations

- Sidebar collapses to hamburger menu or bottom nav
- Calendar view adapts (maybe week view on mobile)
- Admin forms optimised for quick mobile entry
- Touch-friendly tap targets

---

## Open Questions / Decisions

1. **Health metrics privacy** - Public or private? Could show aggregate stats publicly but hide daily detail.

2. **Calories display** - Show daily totals? Weekly averages? Or keep private entirely?

3. **IndieWeb features** - Webmentions? POSSE syndication? Or keep it simple for now?

4. **Notifications/alerts** - Any need for streak reminders, etc.?

5. **Search** - Full-text search across all entries?

6. **Export** - Ability to export all data back out?

---

## Phase 1 MVP

Suggested initial scope:

1. **Core infrastructure**
   - Laravel setup
   - Database schema
   - Basic auth

2. **Automated imports working**
   - Strava webhook
   - Health Auto Export endpoint
   - Trakt integration

3. **Manual entry forms**
   - Flights
   - Fuel

4. **Basic timeline view**
   - Unified feed
   - Filter by type

5. **Historic import scripts**
   - Strava bulk import
   - Existing flights/fuel

**Phase 2:** Swarm, books, shows, articles, maps, stats
**Phase 3:** Year in review, "on this day", Apple Shortcuts, polish

---

## References

### Design Inspiration
- Aaron Parecki: https://aaronparecki.com (IndieWeb, timeline, monthly views)
- Luke / Ekul: https://ekul.me (stats, widgets, fun data viz)
- Zach Leatherman: https://zachleat.com (inline icons, sidebar, narrative stats)
- Marco Cornacchia: https://marco.fyi (interactive cards, polish)
- Current site: https://taylordrayson.com (existing foundation)

### IndieWeb / Concepts
- IndieWeb: https://indieweb.org
- Quantified Self: https://quantifiedself.com

### APIs & Services
- Strava API: https://developers.strava.com
- Trakt API: https://trakt.docs.apiary.io
- Foursquare/Swarm API: https://developer.foursquare.com
- GitHub API: https://docs.github.com/en/rest
- OpenFlights (airport data): https://openflights.org/data.html
- Open Library: https://openlibrary.org/developers/api
