# Design Summary: Taylor Drayson Personal Site

## Database Overview

Data is consolidated into logical tables for easier maintenance:

| Table | Types | Description |
|-------|-------|-------------|
| `activities` | run, ride, walk, gym, swim, yoga | Physical activities |
| `sleep` | — | Sleep tracking |
| `calories` | — | Individual food items (one row per item) |
| `media` | film, tv_episode, book | Things consumed |
| `events` | concert, theatre, comedy, festival | Things attended |
| `appearances` | podcast, livestream, interview, talk | Guest spots on others' shows |
| `podcasts` | — | This Week With episodes (syndicated) |
| `flights` | — | Flight log |
| `checkins` | — | Location check-ins |
| `fuel` | — | Fuel tracking |
| `projects` | — | Side projects (code, home, etc.) |
| `articles` | — | Blog posts |
| `notes` | — | Short posts |
| `assets` | — | All images (polymorphic) |

**14 tables total.** Vehicles stored in config (rarely changes).

Adding a new film, TV show, or book = same `media` table with different `type`.
Adding a new concert or comedy show = same `events` table with different `type`.

### Assets Table

All images stored locally in one polymorphic table:

| Type | Count | Purpose |
|------|-------|---------|
| `cover` | One | Primary thumbnail/poster/hero |
| `photo` | Many | User photos attached to entry |
| `map` | One | Generated static map |

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

---

## The Core Data Types (Timeline)

All of these appear in the unified timeline feed with a date/timestamp.

### 1. Activities (Strava, Gym, etc.)

**Platform Agnostic** - could come from Strava, Apple Health, manual gym log, etc.

**Stored Data:**
- platform_type (strava, apple_health, manual, etc.)
- platform_id (nullable - external reference)
- activity_type (run, ride, walk, gym, workout, etc.)
- name/title
- occurred_at
- duration (seconds)
- calories (nullable)
- location (lat/lng, nullable)
- photos (array)
- is_public

**For cardio activities (run, ride, walk):**
- distance (metres)
- elevation_gain (metres)
- average_pace / average_speed
- map_polyline (encoded)
- kudos_count (if from Strava)

**For gym/strength activities:**
- exercises (JSON array):
  ```json
  [
    {
      "name": "Bench Press",
      "sets": [
        { "reps": 10, "weight_kg": 60 },
        { "reps": 8, "weight_kg": 65 },
        { "reps": 6, "weight_kg": 70 }
      ]
    },
    {
      "name": "Lat Pulldown",
      "sets": [
        { "reps": 12, "weight_kg": 50 },
        { "reps": 10, "weight_kg": 55 }
      ]
    }
  ]
  ```

**Timeline Card Shows:**

*Cardio:*
- Type icon + title
- Map thumbnail (if available)
- Distance + duration + pace
- Relative time

*Gym:*
- Dumbbell icon + title
- Exercise count ("5 exercises")
- Total sets/volume
- Duration

**Compact Widget:**
```
┌─────────────────┐
│ 🏃 Morning Run  │
│ 5.2km  26:43    │
│ ░░░░░░░░░░░░░░░ │
└─────────────────┘

┌─────────────────┐
│ 🏋️ Upper Body   │
│ 5 exercises     │
│ 18 sets · 52min │
└─────────────────┘
```

---

### 2. Sleep (Apple Health)

**Stored Data:**
- date
- platform_type (apple_health, etc.)
- platform_id (nullable)
- time_in_bed (minutes)
- time_asleep (minutes)
- bedtime
- wake_time
- stages (JSON, nullable):
  ```json
  {
    "awake": 23,
    "rem": 95,
    "light": 180,
    "deep": 62
  }
  ```

**Timeline Card Shows:**
- Moon icon
- Duration (e.g., "7h 32m")
- Bedtime → wake time
- Sleep stages bar (coloured segments)

**Sleep Stages Visualisation:**
```
┌──────────────────────────────────────┐
│ 🌙 7h 32m                            │
│ 11:23pm → 6:55am                     │
│                                      │
│ ██░░████░░░░██████░░████████░░██░░░░ │
│ Awake  REM  Light  Deep              │
│                                      │
│ Deep: 1h 02m · REM: 1h 35m           │
└──────────────────────────────────────┘
```

The bar shows the actual sleep pattern over time - segments of different colours representing each stage as they occurred through the night.

**Compact Widget:**
```
┌─────────────────┐
│ 🌙 7h 32m       │
│ 11:23pm → 6:55am│
│ ██░░████░░██░░░ │
└─────────────────┘
```

---

### 3. Calories (`calories` table)

**Stored Data (one row per food item):**
- occurred_at
- name
- meal (breakfast, lunch, dinner, snacks)
- quantity
- units
- calories
- fat (g, nullable)
- protein (g, nullable)
- carbs (g, nullable)
- fibre (g, nullable)
- saturated_fat (g, nullable)
- sugars (g, nullable)
- cholesterol (mg, nullable)
- sodium (mg, nullable)

**Timeline Card Shows:**
- Fire icon
- Calorie total (computed: sum of items for that date)
- Macro totals (computed)
- Meal breakdown grouped by meal field
- All items shown, grouped by meal

**Timeline Card:**
```
┌──────────────────────────────────────┐
│ 🔥 2,150 kcal                        │
│ P: 120g · C: 240g · F: 85g           │
│                                      │
│ Breakfast · 485 kcal                 │
│   Weetabix (2) · 280                 │
│   Semi-skimmed milk · 95             │
│   Coffee with milk · 110             │
│                                      │
│ Lunch · 620 kcal                     │
│   Chicken sandwich · 450             │
│   Apple · 80                         │
│   Protein bar · 90                   │
│                                      │
│ Dinner · 850 kcal                    │
│   ...                                │
│                                      │
│ Snacks · 195 kcal                    │
│   ...                                │
└──────────────────────────────────────┘
```

**Compact Widget:**
```
┌─────────────────┐
│ 🔥 2,150 kcal   │
│ P:120 C:240 F:85│
│ Day 2,145 ────  │
└─────────────────┘
```

---

### 4. Media: Films (`media` table, type: `film`)

**Stored Data:**
- occurred_at
- type (film)
- title
- rating (1-10, nullable)
- platform_type (trakt, manual, etc.)
- platform_id (nullable)
- imdb_id (nullable)
- meta JSON: { year, runtime, genres }
- Cover image stored in assets table

**Timeline Card Shows:**
- Poster thumbnail (from assets)
- Title + year
- Rating (if given)
- Runtime

**Compact Widget:**
```
┌─────────────────┐
│ ┌────┐          │
│ │    │ The      │
│ │ 🎬 │ Brutalist│
│ │    │ ★★★★½    │
│ └────┘ 3h 35m   │
└─────────────────┘
```

---

### 5. Media: TV Episodes (`media` table, type: `tv_episode`)

**Stored Data:**
- occurred_at
- type (tv_episode)
- title (episode title)
- rating (nullable)
- platform_type (trakt, manual, etc.)
- platform_id (nullable)
- imdb_id (nullable)
- meta JSON: { show_title, season_number, episode_number, runtime }
- Cover image (show poster) stored in assets table

**Timeline Card Shows:**
- Show poster thumbnail (from assets)
- Show title
- S01E05 format + episode title
- Groups consecutive episodes ("Watched 3 episodes of...")

**Compact Widget:**
```
┌─────────────────┐
│ 📺 Severance    │
│ S2 E4 · "Woe's" │
│ +2 more today   │
└─────────────────┘
```

---

### 6. Flights

**Stored Data:**
- occurred_at (departure datetime)
- flight_number
- airline_iata
- origin_iata (e.g., LHR)
- destination_iata (e.g., JFK)
- origin_latitude, origin_longitude
- destination_latitude, destination_longitude
- distance_miles
- cabin_class (economy, business, first)
- reason (personal, business)
- meta JSON: { pnr, seat, seat_position, aircraft_type, airline_name,
  origin_name, origin_city, origin_country, origin_terminal, origin_gate,
  destination_name, destination_city, destination_country, destination_terminal, destination_gate,
  scheduled_departure, actual_departure, scheduled_arrival, actual_arrival,
  cancelled, diverted_to }
- Generated map stored in assets table

**Timeline Card Shows:**
- Plane icon
- Route: LHR → JFK
- Cities: London → New York
- Duration + distance
- Static map image with route arc

**Compact Widget:**
```
┌─────────────────┐
│ ✈️ LHR → JFK    │
│ London → NYC    │
│ 7h 45m · 5,555km│
└─────────────────┘
```

---

### 7. Checkins

**Stored Data:**
- occurred_at
- venue_name
- venue_category (coffee shop, restaurant, park, etc.)
- address
- city
- county
- country
- latitude, longitude
- description (nullable)
- platform_type (swarm, manual, etc.)
- platform_id (nullable)
- Photos stored in assets table
- Generated mini map in assets table

**Timeline Card Shows:**
- Category icon
- Venue name
- Category label
- Photo (if available)
- Mini map
- Description (if available)

**Compact Widget:**
```
┌─────────────────┐
│ 📍 Costa Coffee │
│ ☕ Coffee Shop   │
│ Croydon         │
└─────────────────┘
```

---

### 8. Fuel

**Stored Data:**
- occurred_at
- vehicle_id (references config)
- litres
- cost (decimal)
- price_per_litre
- odometer
- full_tank (boolean)
- station
- city
- latitude, longitude

**Vehicles in config:**
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

**Timeline Card Shows:**
- Fuel icon
- Litres + cost
- Price per litre
- Vehicle name (from config)
- Odometer

**Compact Widget:**
```
┌─────────────────┐
│ ⛽ 45.2L · Golf │
│ £74.58 · 165.1p │
│ 52,340 mi       │
└─────────────────┘
```

**Analytics Support:**
- Filter fuel entries by vehicle
- MPG calculations per vehicle
- Cost per mile per vehicle

---

### 9. Media: Books (`media` table, type: `book`)

**Stored Data:**
- occurred_at (finished date)
- type (book)
- title
- rating (nullable)
- platform_type (manual, goodreads, etc.)
- platform_id (nullable)
- meta JSON: { author, isbn }
- Cover image stored in assets table

**Timeline Card Shows (on finish):**
- Book cover thumbnail (from assets)
- Title + author
- Rating (if given)
- "Finished reading"

**Currently Reading (sidebar widget):**
```
┌─────────────────┐
│ 📖 READING      │
│ ┌────┐          │
│ │    │ Atomic   │
│ │    │ Habits   │
│ └────┘ J. Clear │
└─────────────────┘
```

---

### 10. Events (`events` table, types: concert, theatre, comedy, festival)

**Stored Data:**
- occurred_at
- type (concert, theatre, comedy, festival)
- name (artist/show name)
- venue_name
- address
- city
- country
- latitude, longitude
- ticket_price (nullable)
- notes (nullable)
- Cover image (flyer/poster) in assets table
- Photos in assets table

**Timeline Card Shows:**
- Music/theatre icon
- Artist/show name
- Venue + city
- Event type badge
- Photo (if available)

**Compact Widget:**
```
┌─────────────────┐
│ 🎵 Arctic Monkeys│
│ O2 Arena        │
│ London · Concert│
└─────────────────┘
```

---

### 11. Appearances (`appearances` table, types: podcast, livestream, interview, talk)

**Stored Data:**
- occurred_at
- type (podcast, livestream, interview, talk, video)
- title (episode/video name)
- show_name (podcast/channel name)
- url (nullable)
- description (nullable)
- duration_seconds (nullable)
- Cover image (show logo/thumbnail) in assets table

**Timeline Card Shows:**
- Microphone/video icon based on type
- Episode/video title
- Show/channel name
- Link to listen/watch

**Compact Widget:**
```
┌─────────────────────────┐
│ 🎙️ Podcast · 15 Feb     │
│                         │
│ Building Plugins Right  │
│ Some WordPress Podcast  │
│ [Listen →]              │
└─────────────────────────┘
```

---

### 12. Podcasts (`podcasts` table) — This Week With episodes

**Stored Data:**
- occurred_at (published_at)
- season_number
- episode_number
- topics (JSON array)
- show_notes (markdown)
- transcript (nullable)
- duration_seconds
- audio_url (hosted audio file)
- youtube_url
- Cover image (episode art) in assets table

Title generated: "Season {n}, Episode {n}"

**Timeline Card Shows:**
- Headphones icon
- Generated title (S7E238)
- Topics as small badges
- Duration
- Play button / YouTube link

**Compact Widget:**
```
┌─────────────────────────┐
│ 🎧 S7 E238 · 45m        │
│                         │
│ Business, AI, Projects  │
│ [▶ Listen] [YouTube]    │
└─────────────────────────┘
```

Canonical URL: `https://www.thisweekwith.co.uk/season-{n}/episode-{n}/`

---

### 14. Projects (`projects` table)

**Stored Data:**
- occurred_at (launched_at)
- title
- slug
- description (short)
- long_description (markdown, nullable)
- url (nullable)
- github_url (nullable)
- status (active, maintained, archived, on_hold)
- started_at (nullable)
- tags (JSON array)
- featured (boolean)
- Cover image in assets table
- Photos in assets table

**Timeline Card Shows (when launched):**
- Rocket/code icon
- Project title
- "Launched" or "Completed"
- Tags as small badges
- Link to project

**Appears in timeline when:**
- launched_at is set (project launches/ships)

**Also has dedicated /projects page for browsing all projects.**

---

### 15. Articles / Notes (`articles` and `notes` tables)

**Stored Data:**

Articles:
- occurred_at (published_at)
- title
- slug
- excerpt (nullable)
- content (markdown)
- draft (boolean)
- tags (JSON array)
- Cover image in assets table

Notes:
- occurred_at
- content
- Photos in assets table

**Timeline Card Shows:**
- Article/note icon
- Title
- Excerpt or first few lines
- Read time estimate
- Tags

**Compact Widget:**
```
┌─────────────────┐
│ 📝 New Article  │
│ Building a      │
│ Personal Data...│
│ 5 min read      │
└─────────────────┘
```

---

## Platform Agnostic Pattern

Every data type follows this pattern for external service references:

```php
$table->string('platform_type')->nullable();  // 'strava', 'trakt', 'swarm', 'manual', etc.
$table->string('platform_id')->nullable();    // External ID from that platform
$table->unique(['platform_type', 'platform_id']); // Prevent duplicates
```

**Benefits:**
- Not tied to any single service
- Can migrate data if a service dies
- Can have multiple sources for same data type
- Manual entries work alongside automated ones
- Easy to add new integrations

---

## Summary: Timeline Data Types

| # | Table | Types | Icon | Primary Display | Source |
|---|-------|-------|------|-----------------|--------|
| 1 | `activities` | run, ride, walk, gym, swim | 🏃🚴🏋️ | Distance/exercises + duration | Strava, Apple Health |
| 2 | `sleep` | — | 🌙 | Duration + stages bar | Apple Health |
| 3 | `calories` | — | 🔥 | Calories + meal breakdown | Lose It |
| 4 | `media` | film, tv_episode, book | 🎬📺📖 | Poster/cover + title + rating | Trakt, Manual |
| 5 | `events` | concert, theatre, comedy | 🎵🎭 | Artist + venue | Manual |
| 6 | `appearances` | podcast, livestream, talk | 🎙️ | Episode + show name | Manual |
| 7 | `podcasts` | — | 🎧 | S{n}E{n} + topics | Manual |
| 8 | `flights` | — | ✈️ | Route + cities + duration | Manual |
| 9 | `checkins` | — | 📍 | Venue + category + photo | Swarm |
| 10 | `fuel` | — | ⛽ | Litres + cost + vehicle | Manual |
| 11 | `projects` | — | 🚀 | Title + tags (on launch) | Manual |
| 12 | `articles` | — | 📝 | Title + excerpt | Manual |
| 13 | `notes` | — | 💬 | Short text | Manual |

---

## Pages

Routes follow a `/[table]/[type?]` pattern where type is optional.

| Page | Description |
|------|-------------|
| `/` | Homepage - timeline feed + sidebar |
| `/about` | Bio page |
| `/[year]` | Year archive with stats summary |
| `/[year]/[month]` | Month archive with calendar view |
| `/stats` | Full-width stats dashboard |
| `/stats/[year]` | Year in review |
| `/map` | Full-width map (all activities, checkins, flights) |
| `/search` | Search with filter chips |
| **Activities** | |
| `/activities` | All activities |
| `/activities/runs` | Runs only |
| `/activities/rides` | Rides only |
| `/activities/gym` | Gym workouts only |
| **Media** | |
| `/media` | All media (films, TV, books) |
| `/media/films` | Films only |
| `/media/tv` | TV episodes only |
| `/media/books` | Books only |
| **Events** | |
| `/events` | All events |
| `/events/concerts` | Concerts only |
| `/events/theatre` | Theatre only |
| **Appearances** | |
| `/appearances` | All appearances |
| `/appearances/podcasts` | Podcasts only |
| **Other** | |
| `/flights` | Flight log + route map |
| `/fuel` | Fuel log + vehicle stats |
| `/checkins` | Checkins feed |
| `/projects` | Project portfolio |
| `/projects/[slug]` | Single project |
| `/articles` | Blog posts |
| `/articles/[slug]` | Single article |
| `/on-this-day` | Historical entries from this date |

---

## Bio / Profile Content

### Main Intro (with inline icons/links)

Dynamic data pulled from database. Two paragraphs:

```
Hey! I'm Taylor [photo], a web developer in London who tracks everything 
including every calorie for {{ $calorieStreak }} days straight (and counting).

I run a [💡 icon] small web agency I started at 19, develop a [WPE icon] WordPress 
plugin, and co-host a [photo] weekly podcast with my dad with over {{ $episodeCount }} 
episodes. When I touch grass, I'm probably playing a racket sport or making another coffee.
```

**Inline icons/links:**
- [photo] after "Taylor" — your headshot
- [💡] small web agency → thecreativetinker.com  
- [WPE icon] WordPress plugin → wpextended.io
- [podcast photo] weekly podcast → thisweekwith.co.uk

### Sidebar Tagline

```
I build stuff on the internet, track everything, and drink too much coffee.
```

### Short Bio (for meta/elsewhere)

```
I build stuff on the internet, track everything, and drink too much coffee.
```

---

## Search Page

Filter chips approach (Linear/Notion style):

```
┌────────────────────────────────────────────────────────────┐
│ 🔍 Search...                                          [🎲] │
└────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────┐
│ + Add filter                                               │
│                                                            │
│ ┌──────────────────┐ ┌──────────────────┐                 │
│ │ Type is Run    ✕ │ │ Year is 2024   ✕ │                 │
│ └──────────────────┘ └──────────────────┘                 │
└────────────────────────────────────────────────────────────┘
```

**Segmented Tabs (optional):**
```
[All]  [Activities]  [Media]  [Places]  [Other]
 ───
```

**Available Filters:**
- Type (run, film, checkin, etc.)
- Date range
- Year
- Month
- Location
- Has photo
- Rating (for media)

**"Random" Button:**
- Surprise discovery
- Options: Random entry, Random day, Random year

---

## Layout Concept: Sidebar-First

No traditional header. The sidebar IS the navigation and identity.

```
┌──────────────────────────────────────────────────────────────────────┐
│                                      ⏱ 19:14 GMT · ☁ 8°C · 📍 London │
│                                                      · 🔋 72% · Today │
│  ┌─────────────┐  ┌──────────────────────────────────────────────────┤
│  │             │  │                                                  │
│  │  ┌─────┐    │  │  Hey! I'm Taylor [photo], a web developer...    │
│  │  │ 👤  │    │  │                                                  │
│  │  └─────┘    │  │  ┌────────────────────────────────────────────┐  │
│  │  Taylor     │  │  │ 📍 Costa Coffee           Fri 10:30am     │  │
│  │  Drayson    │  │  │    High Street, Croydon    27 Feb 2026    │  │
│  │             │  │  │                                            │  │
│  │  I build... │  │  │    [        photo        ] [map]          │  │
│  │             │  │  │                                            │  │
│  │  🐙 🐦 📡   │  │  │    ↗ View on Swarm                        │  │
│  │             │  │  └────────────────────────────────────────────┘  │
│  ├─────────────┤  │                                                  │
│  │ 🏠 Timeline │  │  ┌────────────────────────────────────────────┐  │
│  │ 📊 Stats    │  │  │ 🏃 Morning Run             Fri 7:15am     │  │
│  │ 🗺️ Map      │  │  │                            27 Feb 2026    │  │
│  │ 👤 About    │  │  │                                            │  │
│  │ 📝 Writing  │  │  │    [        photo        ]                │  │
│  │             │  │  └────────────────────────────────────────────┘  │
│  ├─────────────┤  │                                                  │
│  │ STREAK      │  │                                                  │
│  │ 🔥 2,145    │  │                                                  │
│  │ days        │  │                                                  │
│  ├─────────────┤  │                                                  │
│  │ LAST 14 DAYS│  │                                                  │
│  │ 🏃 5.2km avg│  │                                                  │
│  │ ▁▂▃▄▅▆▇    │  │                                                  │
│  │ 🔥 2,172 avg│  │                                                  │
│  │ ▁▂▃▄▅▆▇    │  │                                                  │
│  │ 😴 7.2h avg │  │                                                  │
│  │ ▁▂▃▄▅▆▇    │  │                                                  │
│  ├─────────────┤  │                                                  │
│  │ READING     │  │                                                  │
│  │ 📖 Atomic   │  │                                                  │
│  │    Habits   │  │                                                  │
│  │    J. Clear │  │                                                  │
│  └─────────────┘  └──────────────────────────────────────────────────┘
└──────────────────────────────────────────────────────────────────────┘
```

### Top Status Bar

Live data displayed in top-right:
- Current time + timezone (19:14 GMT)
- Weather icon + temperature (☁ 8°C)
- Location (London)
- Phone battery (72%)
- Date filter dropdown (Today)

### Sidebar Contents

**Identity Block:**
- Profile photo (rounded)
- Name: "Taylor Drayson"
- Tagline: "I build stuff on the internet, track everything, and drink too much coffee."
- Social icons (GitHub, Twitter/X, RSS)

**Navigation:**
- Timeline (home)
- Stats
- Map
- About
- Writing

**Widgets:**

```
STREAK
🔥 2,145 days
Calorie logging

LAST 14 DAYS
🏃 Running    5.2 km avg  [sparkline]
🔥 Calories   2,172 avg   [sparkline]
😴 Sleep      7.2h avg    [sparkline]

READING
📖 Atomic Habits
   James Clear
```

### Full-Width Pages

Stats, Map, and Year in Review pages drop the sidebar entirely:

```
┌──────────────────────────────────────────────────────────────┐
│ ← Back to Home                          Taylor Drayson       │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│                    Full Width Content                        │
│                                                              │
│                    Charts, Maps, etc.                        │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

---

## Widget Design Language

Inspired by Apple Watch complications - dense, glanceable, consistent.

### Widget Sizes

**Small (1x1):**
```
┌───────┐
│ 🏃 12 │
│ runs  │
└───────┘
```

**Medium (2x1):**
```
┌─────────────────┐
│ 🔥 2,145 kcal   │
│ P:120 C:240 F:85│
└─────────────────┘
```

**Large (2x2):**
```
┌─────────────────┐
│ 🏃 Morning Run  │
│                 │
│ 5.2km · 26:43   │
│ ░░░░░░░░░░░░░░░ │
└─────────────────┘
```

### Widget Characteristics

- Rounded corners (consistent radius)
- Subtle background (white/80 with blur on coloured backgrounds)
- Icon + primary metric prominent
- Secondary info smaller/muted
- Optional sparkline or progress indicator
- No borders - use shadow/elevation

### Data Density

Each widget shows:
1. **Icon** - instant recognition of type
2. **Primary value** - the main number/info
3. **Context** - unit, comparison, or secondary detail
4. **Optional trend** - sparkline, progress bar, or indicator

---

## Visual Style Summary

**Style:** Warm minimalism — clean and restrained, but not cold. Feels like a digital journal, not a dashboard.

| Element | Style |
|---------|-------|
| Background | Cream/off-white (#f5f5f7) — feels like paper |
| Cards | Borderless, content floats on background |
| Shadows | Very subtle or none |
| Corners | Softly rounded (12-16px) |
| Typography | System sans-serif, comfortable sizing |
| Icons | Soft muted colours per type |
| Photos | Full-width within cards, rounded corners |
| Whitespace | Generous — content breathes |
| Borders | Avoid entirely — use background contrast |
| Transitions | Smooth, subtle (150-200ms) |

**Colour accents per type:**
| Type | Colour |
|------|--------|
| Checkin | Sage green |
| Activity | Coral/orange |
| Film | Purple |
| Flight | Blue |
| Food | Orange/red |
| Sleep | Indigo |

**Date display:**
Always show actual dates, not relative time:
- "Fri 10:30am" + "27 Feb 2026"
- Not "3 days ago"

**The vibe:** Opening a nice notebook, not a tech dashboard. Personal. Lived-in. Cozy web.

---

## Unique Touches

Things that make it distinctly *your* site:

1. **The sidebar is the brand** - Your identity is always present, not tucked in a header

2. **Apple Watch widgets** - Compact, glanceable, consistent visual language

3. **Live "Now" data** - Weather, time, current activity - feels alive

4. **Streak prominence** - That 2,145+ day calorie streak deserves to be shown off

5. **Fun contextual stats** - "X% around Earth", "X marathons equivalent"

6. **Activity rings** - Apple Watch style, shown in sidebar and on day views

7. **Minimal chrome** - Content is the interface, UI gets out of the way

8. **Consistency** - Same widget language everywhere (sidebar, timeline, stats pages)

9. **Sleep stages visualisation** - Coloured bar showing REM/light/deep/awake pattern through the night

10. **Detailed food breakdown** - Full meal-by-meal view with individual items, not just daily totals

11. **Vehicle tracking** - Fuel entries linked to cars, enabling per-vehicle analytics over time

12. **Platform agnostic** - Data survives if services die, you own everything

---

## Mobile Behaviour

- Sidebar collapses to bottom tab bar or hamburger
- Widgets stack vertically
- Timeline cards go full-width
- Full-width pages stay full-width
- Quick-add actions accessible via FAB or bottom bar

---

## What Makes This Different

Most personal sites are either:
- Blog-first (content heavy, minimal data)
- Dashboard-first (data heavy, feels clinical)
- Portfolio-first (work focused, not personal)

This is **life-first** - a living record that feels personal, modern, and uniquely you. The sidebar anchors your identity while the content tells your story through data.
