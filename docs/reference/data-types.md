# Data Types Reference

Reference for every domain model in the personal timeline app: fields, allowed values, relationships, and example records. All examples below are **synthetic** — not real personal data.

**Source of truth:** `database/migrations/2026_03_18_010000_create_app_tables.php` and `app/Models/*`.

---

## How types fit together

```
TimelineEntry (timelineable_type + timelineable_id)
    └── Activity, Sleep, Calorie, Media, Event, Appearance, Podcast,
        Flight, Checkin, Fuel, Project, Article, Note

Asset (assetable_type + assetable_id)
    └── Attached to any model using HasAssets

Flight ──belongsTo──► Airline (airline_icao → icao_code)
Flight ──belongsTo──► Airport (origin_iata / destination_iata → iata_code)
```

Timeline models implement `Timelineable` and expose a `card()` method for the feed UI. Creating or updating them (via observers) also maintains a `timeline_entries` row with a matching `occurred_at`.

**Duration fields:** `duration` on activities, appearances, podcasts, and sleep is stored in **seconds**.

**Platform pattern:** `platform_type` + `platform_id` identify the external source (e.g. Strava, Trakt, Swarm). Unique per table where both are set.

---

## Timeline data types

### Activity

**Table:** `activities` · **Model:** `App\Models\Activity` · **CSV import:** `data/activities.csv`

Workouts and exercise. Cardio types (`run`, `cycle`, `swim`, `walk`, `hike`) show distance + duration in the feed; others show duration only.

| Field | Type | Notes |
|-------|------|-------|
| `occurred_at` | datetime | When the activity started |
| `type` | string | Sport/category — see values below |
| `name` | string? | Display title |
| `duration` | int | Seconds |
| `calories` | int? | Energy burned |
| `distance_km` | decimal? | Kilometres (cardio) |
| `average_heart_rate` | int? | BPM |
| `max_heart_rate` | int? | BPM |
| `heart_rate` | json? | Time-series HR data |
| `platform_type` | string? | e.g. `strava` |
| `platform_id` | string? | External activity ID |
| `meta` | json? | Elevation, polyline, exercises, etc. |

**Common `type` values:** `run`, `ride`, `walk`, `swim`, `workout`, `gym`, `yoga`, `weight_training`, `hike`, `cycle`

#### Gym / strength (`meta.sets`)

Strength sessions store **one flat array entry per set** in `meta.sets`. Each set has:

| Key | Type | Notes |
|-----|------|-------|
| `exercise` | string | Exercise name |
| `reps` | int | Rep count for that set |
| `weight` | float | Weight in kg (`0` for bodyweight) |

Shorthand in a gym log is normalised on import — e.g. `3 sets: 8 rep 20 kg` becomes **three** identical set objects. Use `App\Actions\ParseGymSets` to parse multi-line text into this array.

**Supported input patterns (one exercise per line, separated by `•`):**

| Pattern | Meaning |
|---------|---------|
| `8 rep: 29, 39, 44, 49 kg` | Same reps, different weight each set |
| `8 rep 20 kg, 12 rep 25 kg, …` | Comma-separated set specs |
| `3 sets: 8 rep 20 kg` | Repeat one set spec N times |
| `12 rep: 12.5, 12.5, 15 kg` | Same reps, comma-separated weights |

**Example input**

```
Bench Press • 8 rep: 29, 39, 44, 49 kg
chest press • 8 rep 20 kg, 12 rep 25 kg, 12 rep 25 kg, 8 rep 20 kg
Seated Shoulder Press • 3 sets: 8 rep 20 kg
Rope Tricep extension • 12 rep: 12.5, 12.5, 15 kg
```

**Stored as `meta.sets` (14 entries)**

```json
[
  { "exercise": "Bench Press", "reps": 8, "weight": 29 },
  { "exercise": "Bench Press", "reps": 8, "weight": 39 },
  { "exercise": "Bench Press", "reps": 8, "weight": 44 },
  { "exercise": "Bench Press", "reps": 8, "weight": 49 },
  { "exercise": "chest press", "reps": 8, "weight": 20 },
  { "exercise": "chest press", "reps": 12, "weight": 25 },
  { "exercise": "chest press", "reps": 12, "weight": 25 },
  { "exercise": "chest press", "reps": 8, "weight": 20 },
  { "exercise": "Seated Shoulder Press", "reps": 8, "weight": 20 },
  { "exercise": "Seated Shoulder Press", "reps": 8, "weight": 20 },
  { "exercise": "Seated Shoulder Press", "reps": 8, "weight": 20 },
  { "exercise": "Rope Tricep extension", "reps": 12, "weight": 12.5 },
  { "exercise": "Rope Tricep extension", "reps": 12, "weight": 12.5 },
  { "exercise": "Rope Tricep extension", "reps": 12, "weight": 15 }
]
```

**Example 1 — morning run (Strava)**

```json
{
  "occurred_at": "2026-03-15 07:30:00",
  "type": "run",
  "name": "Morning Run",
  "duration": 2340,
  "calories": 312,
  "distance_km": 5.42,
  "average_heart_rate": 152,
  "max_heart_rate": 168,
  "heart_rate": null,
  "platform_type": "strava",
  "platform_id": "12345678901",
  "meta": {
    "polyline": "encoded_polyline_string",
    "elevation_gain": 45.2,
    "elapsed_time": 2380
  }
}
```

**Example 2 — gym session (Setgraph / manual)**

```json
{
  "occurred_at": "2026-03-16 18:00:00",
  "type": "weight_training",
  "name": "Gym Session",
  "duration": 3600,
  "calories": 420,
  "distance_km": null,
  "average_heart_rate": null,
  "max_heart_rate": null,
  "heart_rate": null,
  "platform_type": "setgraph",
  "platform_id": null,
  "meta": {
    "sets": [
      { "exercise": "Bench Press", "reps": 8, "weight": 29 },
      { "exercise": "Bench Press", "reps": 8, "weight": 39 },
      { "exercise": "Seated Shoulder Press", "reps": 8, "weight": 20 },
      { "exercise": "Seated Shoulder Press", "reps": 8, "weight": 20 },
      { "exercise": "Seated Shoulder Press", "reps": 8, "weight": 20 }
    ]
  }
}
```

---

### Sleep

**Table:** `sleep` · **Model:** `App\Models\Sleep`

Nightly sleep from Apple Health or similar.

| Field | Type | Notes |
|-------|------|-------|
| `occurred_at` | datetime | Night date (shown on timeline) |
| `bedtime` | datetime | When you got in bed |
| `wake_time` | datetime | When you woke up |
| `duration` | int | Total sleep, seconds |
| `awake` | int? | Awake time, seconds |
| `rem` | int? | REM sleep, seconds |
| `core` | int? | Core/light sleep, seconds |
| `deep` | int? | Deep sleep, seconds |
| `source` | string? | e.g. `apple_health` |
| `stages` | json? | Raw stage segments for charts |

**Example 1**

```json
{
  "occurred_at": "2026-03-15 00:00:00",
  "bedtime": "2026-03-14 22:45:00",
  "wake_time": "2026-03-15 06:30:00",
  "duration": 24300,
  "awake": 1800,
  "rem": 5400,
  "core": 12600,
  "deep": 4500,
  "source": "apple_health",
  "stages": [
    { "start": "2026-03-14 22:45:00", "end": "2026-03-14 23:30:00", "stage": "core" },
    { "start": "2026-03-14 23:30:00", "end": "2026-03-15 01:00:00", "stage": "deep" }
  ]
}
```

**Example 2**

```json
{
  "occurred_at": "2026-03-16 00:00:00",
  "bedtime": "2026-03-15 23:15:00",
  "wake_time": "2026-03-16 07:00:00",
  "duration": 26100,
  "awake": 900,
  "rem": 6300,
  "core": 14400,
  "deep": 4500,
  "source": "apple_health",
  "stages": null
}
```

---

### Calorie

**Table:** `calories` · **Model:** `App\Models\Calorie` · **CSV import:** `data/calories.csv`

One row per food item. The timeline card aggregates **daily total kcal** for the date.

| Field | Type | Notes |
|-------|------|-------|
| `occurred_at` | datetime | Date/time of the meal |
| `name` | string | Food name |
| `icon` | string? | UI icon key |
| `meal` | string | e.g. `breakfast`, `lunch`, `dinner`, `snacks` |
| `quantity` | decimal | Amount eaten |
| `units` | string | e.g. `Serving`, `g`, `ml` |
| `calories` | int | kcal for this item |
| `fat`, `protein`, `carbs` | decimal? | Macros (g) |
| `saturated_fat`, `sugars`, `fibre` | decimal? | Additional nutrition |
| `cholesterol`, `sodium` | decimal? | mg |

**Example 1**

```json
{
  "occurred_at": "2026-03-15 08:15:00",
  "name": "Porridge with Banana",
  "icon": "Default",
  "meal": "breakfast",
  "quantity": 1.0,
  "units": "Serving",
  "calories": 320,
  "fat": 6.5,
  "protein": 12.0,
  "carbs": 54.0,
  "saturated_fat": 1.2,
  "sugars": 18.0,
  "fibre": 7.0,
  "cholesterol": 0,
  "sodium": 120
}
```

**Example 2**

```json
{
  "occurred_at": "2026-03-15 13:00:00",
  "name": "Chicken Salad",
  "icon": "Default",
  "meal": "lunch",
  "quantity": 1.0,
  "units": "Serving",
  "calories": 450,
  "fat": 18.0,
  "protein": 35.0,
  "carbs": 22.0,
  "saturated_fat": null,
  "sugars": null,
  "fibre": null,
  "cholesterol": null,
  "sodium": null
}
```

---

### Media

**Table:** `media` · **Model:** `App\Models\Media`

Films, TV episodes, and books (typically from Trakt).

| Field | Type | Notes |
|-------|------|-------|
| `occurred_at` | datetime | Watched or finished date |
| `type` | string | `film`, `tv` / `tv_episode`, `book` |
| `title` | string | Film/book title or episode title |
| `rating` | int? | 1–10 |
| `platform_type` | string? | e.g. `trakt` |
| `platform_id` | string? | Trakt slug or ID |
| `meta` | json? | Type-specific fields (see below) |

**`meta` by type:**

| `type` | Typical `meta` keys |
|--------|---------------------|
| `film` | `year`, `runtime`, `genres` |
| `tv` / `tv_episode` | `show_title`, `season`, `episode`, `episode_title`, `runtime` |
| `book` | `author`, `isbn` |

> **Note:** The feed subtitle logic in `Media::card()` expects `type: "tv"` with `meta.season` and `meta.episode`. Factories may use `tv_episode` with `season_number` / `episode_number` — normalise on import.

**Example 1 — film**

```json
{
  "occurred_at": "2026-03-14 21:00:00",
  "type": "film",
  "title": "The Matrix",
  "rating": 9,
  "platform_type": "trakt",
  "platform_id": "movies/the-matrix-1999",
  "meta": {
    "year": 1999,
    "runtime": 136,
    "genres": ["Action", "Sci-Fi"]
  }
}
```

**Example 2 — TV episode**

```json
{
  "occurred_at": "2026-03-15 22:30:00",
  "type": "tv",
  "title": "Pilot",
  "rating": 8,
  "platform_type": "trakt",
  "platform_id": "shows/example-show/seasons/1/episodes/1",
  "meta": {
    "show_title": "Example Show",
    "season": 1,
    "episode": 1,
    "runtime": 42
  }
}
```

---

### Event

**Table:** `events` · **Model:** `App\Models\Event`

Concerts, theatre, comedy, festivals, etc.

| Field | Type | Notes |
|-------|------|-------|
| `occurred_at` | datetime | Event start |
| `type` | string | e.g. `concert`, `theatre`, `comedy`, `festival` |
| `name` | string | Artist or show name |
| `venue_name` | string? | |
| `address` | string? | |
| `city` | string? | |
| `country` | string? | |
| `latitude`, `longitude` | decimal? | Venue coordinates |
| `ticket_price` | decimal? | |
| `notes` | text? | |

**Example 1**

```json
{
  "occurred_at": "2026-04-12 19:30:00",
  "type": "concert",
  "name": "Example Band",
  "venue_name": "O2 Academy",
  "address": "211 Stockport Road",
  "city": "Manchester",
  "country": "United Kingdom",
  "latitude": 53.4647,
  "longitude": -2.2290,
  "ticket_price": 45.00,
  "notes": "Standing tickets, support act at 8pm"
}
```

**Example 2**

```json
{
  "occurred_at": "2026-05-03 14:00:00",
  "type": "theatre",
  "name": "Hamlet",
  "venue_name": "Royal Exchange Theatre",
  "address": "St Ann's Square",
  "city": "Manchester",
  "country": "United Kingdom",
  "latitude": 53.4831,
  "longitude": -2.2446,
  "ticket_price": 32.50,
  "notes": null
}
```

---

### Appearance

**Table:** `appearances` · **Model:** `App\Models\Appearance` · **CSV import:** `data/appearances.csv`

Podcast, livestream, or interview appearances on other shows.

| Field | Type | Notes |
|-------|------|-------|
| `occurred_at` | datetime | Publish or air date |
| `type` | string | `podcast`, `livestream`, `interview`, `talk` |
| `title` | string | Episode or session title |
| `show_name` | string | Host show name |
| `url` | string? | Article or show page |
| `video_url` | string? | YouTube or video link |
| `description` | text? | |
| `duration` | int? | Seconds |

**Example 1**

```json
{
  "occurred_at": "2026-02-20 10:00:00",
  "type": "podcast",
  "title": "Building with Laravel",
  "show_name": "Dev Chats Podcast",
  "url": "https://example.com/episodes/42",
  "video_url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
  "description": "Discussion about Laravel patterns and personal projects.",
  "duration": 3420
}
```

**Example 2**

```json
{
  "occurred_at": "2026-01-15 18:00:00",
  "type": "livestream",
  "title": "Live Q&A",
  "show_name": "WP Weekly Live",
  "url": null,
  "video_url": "https://www.youtube.com/watch?v=example123",
  "description": null,
  "duration": 3600
}
```

---

### Podcast

**Table:** `podcasts` · **Model:** `App\Models\Podcast`

Episodes of your own show (*This Week With*). Title is derived: `"Season {n}, Episode {n}"`.

| Field | Type | Notes |
|-------|------|-------|
| `occurred_at` | datetime | Publish date |
| `season_number` | int | |
| `episode_number` | int | |
| `topic` | string? | Episode topic |
| `show_notes` | text? | |
| `transcript` | text? | Full transcript |
| `duration` | int? | Seconds |
| `audio_url` | string? | Hosted audio |
| `youtube_url` | string? | |
| `thumbnail` | string? | |
| `cover_image` | string? | |

**Example 1**

```json
{
  "occurred_at": "2026-03-10 09:00:00",
  "season_number": 3,
  "episode_number": 12,
  "topic": "Side projects and motivation",
  "show_notes": "Links and resources discussed in this episode.",
  "transcript": null,
  "duration": 2847,
  "audio_url": "https://cdn.example.com/s3/e12.mp3",
  "youtube_url": "https://www.youtube.com/watch?v=example456",
  "thumbnail": "/assets/podcast/s3e12-thumb.jpg",
  "cover_image": "/assets/podcast/s3e12-cover.jpg"
}
```

**Example 2**

```json
{
  "occurred_at": "2026-03-17 09:00:00",
  "season_number": 3,
  "episode_number": 13,
  "topic": "WordPress and the block editor",
  "show_notes": null,
  "transcript": "Full transcript text would go here...",
  "duration": 3105,
  "audio_url": "https://cdn.example.com/s3/e13.mp3",
  "youtube_url": null,
  "thumbnail": null,
  "cover_image": null
}
```

---

### Flight

**Table:** `flights` · **Model:** `App\Models\Flight` · **CSV import:** `data/flights.csv`

Requires matching rows in `airlines` and `airports` for relationships.

| Field | Type | Notes |
|-------|------|-------|
| `occurred_at` | datetime | Departure datetime |
| `flight_number` | string | e.g. `BA123` |
| `airline_icao` | string | FK → `airlines.icao_code` |
| `origin_iata` | string | FK → `airports.iata_code` |
| `destination_iata` | string | FK → `airports.iata_code` |
| `distance_miles` | int? | Great-circle distance |
| `cabin_class` | string? | `economy`, `premium_economy`, `business`, `first` |
| `reason` | string? | e.g. `personal`, `business` |
| `meta` | json? | Aircraft, terminals, schedule times |

**Example 1**

```json
{
  "occurred_at": "2026-06-01 08:15:00",
  "flight_number": "BAW117",
  "airline_icao": "BAW",
  "origin_iata": "LHR",
  "destination_iata": "JFK",
  "distance_miles": 3451,
  "cabin_class": "economy",
  "reason": "personal",
  "meta": {
    "aircraft": "Boeing 777-300ER",
    "dep_terminal": "5",
    "departed_scheduled": "2026-06-01T08:15:00",
    "departed_actual": "2026-06-01T08:42:00",
    "arrived_scheduled": "2026-06-01T11:30:00",
    "arrived_actual": "2026-06-01T11:18:00"
  }
}
```

**Example 2**

```json
{
  "occurred_at": "2026-06-15 14:00:00",
  "flight_number": "KLM842",
  "airline_icao": "KLM",
  "origin_iata": "AMS",
  "destination_iata": "BCN",
  "distance_miles": 765,
  "cabin_class": "business",
  "reason": "business",
  "meta": {
    "aircraft": "Boeing 737-800",
    "departed_scheduled": "2026-06-15T14:00:00",
    "arrived_scheduled": "2026-06-15T16:15:00"
  }
}
```

---

### Checkin

**Table:** `checkins` · **Model:** `App\Models\Checkin` · **CSV import:** `data/checkins.csv`

Places visited (Swarm / Foursquare).

| Field | Type | Notes |
|-------|------|-------|
| `occurred_at` | datetime | Check-in time |
| `venue_name` | string | |
| `category` | string? | e.g. `Coffee Shop`, `Office` |
| `address` | string? | |
| `city` | string? | |
| `county` | string? | |
| `country` | string? | |
| `latitude`, `longitude` | decimal? | |
| `description` | text? | User note |
| `is_mayor` | bool | Swarm mayor status |
| `platform_type` | string? | e.g. `swarm` |
| `platform_id` | string? | Swarm check-in ID |

**Example 1**

```json
{
  "occurred_at": "2026-03-15 09:30:00",
  "venue_name": "Example Coffee Co.",
  "category": "Coffee Shop",
  "address": "42 High Street",
  "city": "Manchester",
  "county": "Greater Manchester",
  "country": "United Kingdom",
  "latitude": 53.4808,
  "longitude": -2.2426,
  "description": "Working from here this morning.",
  "is_mayor": false,
  "platform_type": "swarm",
  "platform_id": "abc123def456"
}
```

**Example 2**

```json
{
  "occurred_at": "2026-03-15 12:45:00",
  "venue_name": "City Library",
  "category": "Library",
  "address": "St Peter's Square",
  "city": "Manchester",
  "county": null,
  "country": "United Kingdom",
  "latitude": 53.4778,
  "longitude": -2.2431,
  "description": null,
  "is_mayor": true,
  "platform_type": "swarm",
  "platform_id": "xyz789ghi012"
}
```

---

### Fuel

**Table:** `fuel` · **Model:** `App\Models\Fuel` · **CSV import:** `data/fuel.csv`

Fuel purchases. `vehicle_id` maps to `config/vehicles.php`.

| Field | Type | Notes |
|-------|------|-------|
| `occurred_at` | datetime | Fill-up date |
| `vehicle_id` | string | Key in `config/vehicles.php` |
| `litres` | decimal | |
| `cost` | decimal | Amount paid (£) |
| `fuel_card_cost` | decimal? | If paid on fuel card |
| `price_per_litre` | decimal? | |
| `odometer` | int? | Miles on clock |
| `station` | string? | Petrol station name |
| `city` | string? | |

**Example 1**

```json
{
  "occurred_at": "2026-03-01 17:30:00",
  "vehicle_id": "hn14wxp",
  "litres": 42.500,
  "cost": 58.75,
  "fuel_card_cost": null,
  "price_per_litre": 1.382,
  "odometer": 45230,
  "station": "Shell M60 Services",
  "city": "Manchester"
}
```

**Example 2**

```json
{
  "occurred_at": "2026-03-18 08:00:00",
  "vehicle_id": "hn14wxp",
  "litres": 38.200,
  "cost": 51.20,
  "fuel_card_cost": 48.00,
  "price_per_litre": 1.340,
  "odometer": 45680,
  "station": "Tesco Petrol",
  "city": "Stockport"
}
```

---

### Project

**Table:** `projects` · **Model:** `App\Models\Project`

Things you've built or launched.

| Field | Type | Notes |
|-------|------|-------|
| `occurred_at` | datetime | Launch date (timeline) |
| `title` | string | |
| `slug` | string | URL slug |
| `description` | text? | Short summary |
| `long_description` | text? | Full write-up |
| `url` | string? | Live site |
| `github_url` | string? | |
| `status` | string | `active`, `maintained`, `archived`, `on_hold` |
| `featured` | bool | Highlight on site |
| `tags` | json? | Array of tag strings |
| `started_at` | datetime? | When work began |

**Example 1**

```json
{
  "occurred_at": "2026-01-20 00:00:00",
  "title": "Timeline App",
  "slug": "timeline-app",
  "description": "Personal data hub built with Laravel.",
  "long_description": "A full write-up of the project goals and stack.",
  "url": "https://example.com",
  "github_url": "https://github.com/example/timeline-app",
  "status": "active",
  "featured": true,
  "tags": ["laravel", "php", "personal"],
  "started_at": "2025-11-01 00:00:00"
}
```

**Example 2**

```json
{
  "occurred_at": "2024-06-10 00:00:00",
  "title": "Old Side Project",
  "slug": "old-side-project",
  "description": "A small utility tool.",
  "long_description": null,
  "url": null,
  "github_url": "https://github.com/example/old-tool",
  "status": "archived",
  "featured": false,
  "tags": ["javascript"],
  "started_at": "2024-03-01 00:00:00"
}
```

---

### Article

**Table:** `articles` · **Model:** `App\Models\Article`

Long-form writing published on the site.

| Field | Type | Notes |
|-------|------|-------|
| `occurred_at` | datetime | Publish date |
| `title` | string | |
| `slug` | string | URL slug |
| `excerpt` | text? | Summary for cards |
| `content` | longtext | Full body (Markdown/HTML) |
| `draft` | bool | Unpublished if true |
| `tags` | json? | Array of tag strings |

**Example 1**

```json
{
  "occurred_at": "2026-02-01 10:00:00",
  "title": "Why I Track Everything",
  "slug": "why-i-track-everything",
  "excerpt": "A short intro to quantified self and personal archives.",
  "content": "# Why I Track Everything\n\nFull article content here...",
  "draft": false,
  "tags": ["personal", "data"]
}
```

**Example 2**

```json
{
  "occurred_at": "2026-03-01 10:00:00",
  "title": "Draft Post",
  "slug": "draft-post",
  "excerpt": null,
  "content": "Work in progress...",
  "draft": true,
  "tags": ["laravel"]
}
```

---

### Note

**Table:** `notes` · **Model:** `App\Models\Note`

Short freeform notes on a given day.

| Field | Type | Notes |
|-------|------|-------|
| `occurred_at` | datetime | When noted |
| `content` | text | Note body |

**Example 1**

```json
{
  "occurred_at": "2026-03-15 20:00:00",
  "content": "Good day — finished the migration and went for a run."
}
```

**Example 2**

```json
{
  "occurred_at": "2026-03-16 07:30:00",
  "content": "Early start. Coffee and planning session before meetings."
}
```

---

## Supporting data types

These do not appear directly in the timeline feed but support other models.

### Airport

**Model:** `App\Models\Airport` · Sushi-backed lookup, no database table · **CSV:** `database/lookups/airports.csv`

| Field | Type | Notes |
|-------|------|-------|
| `iata_code` | string | Unique, 3-letter |
| `icao_code` | string? | 4-letter |
| `name` | string | Full airport name |
| `city` | string? | |
| `country` | string? | |
| `latitude`, `longitude` | decimal? | |

**Example 1**

```json
{
  "iata_code": "LHR",
  "icao_code": "EGLL",
  "name": "London Heathrow Airport",
  "city": "London",
  "country": "United Kingdom",
  "latitude": 51.4700,
  "longitude": -0.4543
}
```

**Example 2**

```json
{
  "iata_code": "JFK",
  "icao_code": "KJFK",
  "name": "John F. Kennedy International Airport",
  "city": "New York",
  "country": "United States",
  "latitude": 40.6413,
  "longitude": -73.7781
}
```

---

### Airline

**Model:** `App\Models\Airline` · Sushi-backed lookup, no database table · **CSV:** `database/lookups/airlines.csv`

| Field | Type | Notes |
|-------|------|-------|
| `iata_code` | string? | 2-letter |
| `icao_code` | string | Unique, 3-letter — used by flights |
| `name` | string | |
| `country` | string? | |

**Example 1**

```json
{
  "iata_code": "BA",
  "icao_code": "BAW",
  "name": "British Airways",
  "country": "United Kingdom"
}
```

**Example 2**

```json
{
  "iata_code": "KL",
  "icao_code": "KLM",
  "name": "KLM Royal Dutch Airlines",
  "country": "Netherlands"
}
```

---

### Asset

**Table:** `assets` · **Model:** `App\Models\Asset`

Polymorphic file attachments (photos, covers, maps) for any model using `HasAssets`.

| Field | Type | Notes |
|-------|------|-------|
| `assetable_type` | string | e.g. `App\Models\Activity` |
| `assetable_id` | int | Parent record ID |
| `type` | string | e.g. `photo`, `cover`, `map` |
| `path` | string | Storage path |
| `original_filename` | string? | |
| `width`, `height` | int? | Pixels |
| `mime_type` | string? | |
| `size_bytes` | int? | |
| `order` | int | Sort order (default 0) |

**Example 1 — activity photo**

```json
{
  "assetable_type": "App\\Models\\Activity",
  "assetable_id": 1,
  "type": "photo",
  "path": "assets/activities/1/photo-1.jpg",
  "original_filename": "run-screenshot.jpg",
  "width": 1200,
  "height": 800,
  "mime_type": "image/jpeg",
  "size_bytes": 245000,
  "order": 0
}
```

**Example 2 — media cover**

```json
{
  "assetable_type": "App\\Models\\Media",
  "assetable_id": 5,
  "type": "cover",
  "path": "assets/media/5/poster.jpg",
  "original_filename": "matrix-poster.jpg",
  "width": 500,
  "height": 750,
  "mime_type": "image/jpeg",
  "size_bytes": 89000,
  "order": 0
}
```

---

### TimelineEntry

**Table:** `timeline_entries` · **Model:** `App\Models\TimelineEntry`

Index row that powers the home feed and day views. Created automatically by observers when timelineable models are saved.

| Field | Type | Notes |
|-------|------|-------|
| `timelineable_type` | string | Fully-qualified model class |
| `timelineable_id` | int | |
| `occurred_at` | datetime | Copied from the parent model |

**Example 1 — links to an activity**

```json
{
  "timelineable_type": "App\\Models\\Activity",
  "timelineable_id": 42,
  "occurred_at": "2026-03-15 07:30:00"
}
```

**Example 2 — links to a checkin**

```json
{
  "timelineable_type": "App\\Models\\Checkin",
  "timelineable_id": 108,
  "occurred_at": "2026-03-15 09:30:00"
}
```

---

## Quick reference

| Model | Timeline | CSV import | Uses `meta` JSON |
|-------|----------|------------|------------------|
| Activity | ✓ | `activities.csv` | ✓ |
| Sleep | ✓ | — | ✓ (`stages`) |
| Calorie | ✓ | `calories.csv` | — |
| Media | ✓ | — | ✓ |
| Event | ✓ | — | — |
| Appearance | ✓ | `appearances.csv` | — |
| Podcast | ✓ | — | — |
| Flight | ✓ | `flights.csv` | ✓ |
| Checkin | ✓ | `checkins.csv` | — |
| Fuel | ✓ | `fuel.csv` | — |
| Project | ✓ | — | ✓ (`tags`) |
| Article | ✓ | — | ✓ (`tags`) |
| Note | ✓ | — | — |
| Airport | — | `airports.csv` | — |
| Airline | — | `airlines.csv` | — |
| Asset | — | — | — |
| TimelineEntry | — | — | — |
