# Project Brief: Personal Lifelog

## The Idea

A personal website that tracks everything I do (runs, films, places I've been, what I ate, flights I've taken) all in one place.

It's like a private social media feed just for me, so I can look back and see exactly what I was doing on any day in the past. A digital scrapbook of my life that updates itself.

**Inspiration:** Aaron Parecki (aaronparecki.com), Luke/Ekul (ekul.me), Zach Leatherman (zachleat.com), Marco Cornacchia (marco.fyi), Trevor Morris (trovster.com).

---

## Status

This document describes the project as actually built, and flags what is still planned.

**Built:** unified timeline, per-type archives with taxonomy filters, single-entry pages, the `/now` bento dashboard, advanced search + command palette, RSS/Atom/JSON feeds, audio/video media player, flight/location maps, the This Week With podcast section, the design-system page, a 404 Snake game with leaderboard, a representative h-card, and CSV/API data import.

**Planned / not yet built:** an admin UI for manual entry, several live integrations (Trakt, Swarm/Foursquare webhooks, live weather), and some standalone pages from the original vision (`/photos`, `/map`, `/stats`, `/on-this-day`, `/about`).

---

## Tech Stack

The original plan was Blade + Alpine. The build instead uses an Inertia + Vue single-page app on a Laravel back end.

| Layer | Choice |
|-------|--------|
| Framework | Laravel 13 (PHP 8.4) |
| Front end | Inertia.js v3 + Vue 3 (client-side only, no SSR) |
| Styling | Tailwind CSS v4 (`@theme` design tokens) |
| Build | Vite 7 |
| Testing | Pest 4 |
| Media | spatie/laravel-medialibrary v11 (custom `attachments` table) |
| Feeds | spatie/laravel-feed v4 |
| Maps | maplibre-gl v5 (OpenFreeMap tiles) |
| Audio/Video | Plyr v3 (via a global player store) |
| Icons | Hugeicons Pro (stroke-rounded) through an `Icon.vue` wrapper |
| Utilities | clsx + tailwind-merge (`cn()`), chrono-node (date parsing) |

**Why this stack:** Inertia keeps server-side routing and Eloquent while delivering an SPA feel, with no separate API layer and no SSR daemon to run. Vue components carry the interactive widgets (live clock, maps, media player, command palette) that the original Blade/Alpine plan could not.

---

## Architecture

### Polymorphic timeline index

Each life-data type is its own model and table (`Activity`, `Sleep`, `Podcast`, ...). They do not share a table. Instead, a single `timeline_entries` table acts as a polymorphic index over all of them:

- Every timeline model implements the `Timelineable` interface (`card()`, `slug()`, `url()`) and uses the `HasTimelineEntry` trait (a `MorphOne` to `TimelineEntry`, plus the `url()` builder `/{year}/{month}/{day}/{slug}`).
- `TimelineEntryObserver` keeps the index in sync: on save (when `occurred_at` is set) it `updateOrCreate`s the matching `timeline_entries` row; on delete it removes it.
- `Calorie` is the exception: `CalorieTimelineObserver` aggregates food rows into one entry per day rather than one per item.
- The unified feed, the date pages, search, and the RSS/Atom/JSON feeds all read from `timeline_entries` (with `withCardRelations()` eager-loading), then render each model's `card()` payload.

### Type registry

`app/Timeline/TypeRegistry.php` is the single source of truth that maps each card `type` key to its model, public URL slug, label, singular noun, and optional taxonomy. The per-type archive routes and their taxonomy sub-routes are generated from it. Note that the internal key stays a generic category word while the slug/label can be branded:

| Key | Slug | Label | Model | Taxonomy |
|-----|------|-------|-------|----------|
| `activity` | `activities` | Activities | Activity | by `type` |
| `sleep` | `sleep` | Sleep | Sleep | none |
| `calorie` | `food` | Food | Calorie | none |
| `media` | `media` | Media | Media | films / tv / books |
| `event` | `events` | Events | Event | by `type` |
| `appearance` | `appearances` | Appearances | Appearance | by `type` |
| `podcast` | `this-week-with` | This Week With | Podcast | none |
| `flight` | `flights` | Flights | Flight | by airline |
| `checkin` | `places` | Places | Checkin | by `category` |
| `fuel` | `fuel` | Fuel | Fuel | by vehicle |
| `project` | `projects` | Projects | Project | by tag |
| `article` | `articles` | Articles | Article | by tag |
| `note` | `notes` | Notes | Note | none |

(`calorie` to "Food", `checkin` to "Places", and `podcast` to "This Week With" are deliberate: the key is the agnostic category, the slug/label is the presentation.)

### Front end

A client-rendered Inertia SPA. `resources/js/app.js` resolves pages from `resources/js/Pages/*.vue` by glob (no SSR). A persistent `AppLayout` holds the sidebar (profile + nav), the topbar (breadcrumb, search, status), the mobile nav, the global `MediaPlayer`, and the `CommandPalette`, so playback and overlays survive navigations.

---

## Data Models

Every timeline table keys off `occurred_at` (the moment it appears in the feed). Models use `meta` JSON only where fields are type-specific (`activities`, `media`, `flights`); the rest are flat columns. The migrations are the source of truth; key tables below.

**`timeline_entries`** (the index)
```
timelineable_type, timelineable_id   // morph to the owning model
occurred_at                          // indexed
// unique (timelineable_type, timelineable_id)
```

**`attachments`** (Spatie Media Library, renamed from the default `media` to avoid colliding with the `media` timeline type)
```
model_type, model_id                 // morph to any timeline model
collection_name                      // cover | photos | map
file_name, mime_type, disk, size
custom_properties, generated_conversions, responsive_images (json)
order_column
```

**`activities`** (`meta` JSON)
```
type (run/ride/walk/gym/...), name, duration, calories,
distance_km, average_heart_rate, max_heart_rate, heart_rate (json),
platform_type, platform_id, meta
// meta: elevation, exercises, etc.
```

**`sleep`**
```
bedtime, wake_time, duration,
awake, rem, core, deep, source, stages (json)
```

**`calories`** (one row per food item; aggregated to one timeline entry per day)
```
name, icon, meal, quantity, units, calories,
fat, protein, carbs, saturated_fat, sugars, fibre, cholesterol, sodium
```

**`media`** (`meta` JSON)
```
type (film/tv_episode/book), title, rating,
platform_type, platform_id, meta   // film/tv/book specifics
```

**`podcasts`** (This Week With)
```
season_number, episode_number, topic, show_notes, transcript,
duration, audio_url, video_url, thumbnail, cover_image
// title accessor: "Season {n}, Episode {n}"
// slug(): tww-s{n}-e{n}
```

**`flights`** (`meta` JSON; relates to `airlines` and `airports`)
```
flight_number, airline_icao, origin_iata, destination_iata,
distance_miles, duration_min, departure_timezone, arrival_timezone,
co2_kg, cabin_class, reason, meta
```

**`checkins`**
```
venue_name, category, address, city, county, country,
latitude, longitude, description, is_mayor, platform_type, platform_id
```

**`fuel`** (relates to `fuel_stations`)
```
vehicle_id (config/vehicles.php), litres, cost, fuel_card_cost,
price_per_litre, odometer, fuel_station_id
```

**`events`**, **`appearances`**, **`projects`**, **`articles`**, **`notes`** are flat-column tables. `articles` and `notes` store their body as Portable Text (see `app/Support/PortableText.php`), validated against `docs/reference/portable-text.schema.json`.

**Supporting tables:** `airlines`, `airports`, `fuel_stations` (lookups), `leaderboard_entries` (Snake game), plus framework `users` / `cache` / `jobs`.

### Media attachments

`HasAttachments` registers three collections per model (`cover` single, `photos` gallery, `map` single) and a `card` conversion (max 640x640, WebP, q78, responsive widths, with a tiny blur placeholder). External images (posters, episode art, logos) are pulled in and stored as attachments rather than hot-linked.

---

## Routes

All public, no `/admin` group, no `/api` group (data arrives via console commands, see below).

**Timeline + entries**
- `GET /` timeline feed (`TimelineController@index`)
- `GET /{year}` / `/{year}/{month}` / `/{year}/{month}/{day}` date archives
- `GET /{year}/{month}/{day}/{slug}` single entry (`EntryController@show`)

**Per-type archives** (generated from `TypeRegistry`)
- `GET /{slug}` e.g. `/activities`, `/this-week-with`, `/food`, `/places`, `/flights`, `/projects`
- `GET /{taxonomy}/{value}` e.g. `/activities/run`, `/flights/{airline}`, `/places/{category}`, `/projects/{tag}`

**Other**
- `GET /now` bento dashboard (`NowController@index`)
- `GET /search` advanced query builder, `GET /search/suggest` command-palette suggestions
- `GET /design-system`
- `GET /feed`, `/feed/rss`, `/feed/json` (Spatie Feed)
- `GET /leaderboard` and the throttled `POST /snake/{token,score,rename}` endpoints (404 Snake game)

---

## How Data Gets In

There is no admin UI yet. Data is loaded through Artisan commands and CSV files under `data/`:

- **Import** (`app/Console/Commands/Import/`): `ImportCsv` bulk-loads any timeline model from CSV; `ImportAll` orchestrates the set; `FoursquareImport` converts a Swarm/Foursquare export into checkins.
- **Sync** (`Sync/`): `StravaSync` pulls activities, `StravaPolylines` backfills route lines, `PodcastSync` pulls This Week With episodes from the show's API into `data/podcasts.csv` and the database.
- **Fetch / enrich** (`Fetch/`): airline logos, flight enrichment (timezones, CO2, duration), appearance thumbnails.

An admin interface for manual entry (flights, fuel, notes, etc.) and live webhooks/Shortcuts endpoints remain on the roadmap.

---

## Search

- **Advanced search** (`/search`): a Linear/Notion-style query builder. A URL-encoded JSON filter (groups of conditions) is validated against `app/Search/SearchSchema.php` (per-type fields with data types and allowed operators), compiled to a `timeline_entries` query, and returned grouped by day (25 per page). The Vue `QueryBuilder` drives the UI.
- **Suggestions** (`/search/suggest`): free-text across the searchable types (max 8, 5 per type), powering the Cmd+K `CommandPalette`. Optional type and date-range filters.

---

## Design System

Defined as Tailwind v4 `@theme` tokens in `resources/css/app.css`. The design-system page (`/design-system`) is the living reference.

- **Type:** Bricolage Grotesque for display/headings, Inter for body. A full named scale (eyebrow, label, caption, meta, body, section, item-title, name, stat, display, display-xl).
- **Neutrals:** a 0 to 900 ramp from white to near-black ink, on a near-white canvas (paper feel, cool rather than cream).
- **Accent:** "blueberry" (`--color-accent-500: #3858e9`) with a 50 to 900 ramp.
- **Per-type accent colours** (HSL), one per timeline type, e.g. `--color-podcast` cyan, `--color-flight` sky blue, `--color-activity` green, `--color-sleep` purple-blue. Sleep also has stage colours (awake/rem/light/deep) and nutrition has macro colours (protein/carbs/fat).
- **Surfaces:** soft card shadow (hairline ring + diffuse drop), radius tokens (8/11/14px), and playful motion tokens (fade-in, the avatar "boop").

Conventions: split into small reusable components; never use arbitrary Tailwind bracket values (use the scale, or scoped CSS for genuine one-offs); shadcn-style variant primitives (`Button`, `Pill`, `StatGrid`) built on the `cn()` helper.

---

## Frontend Structure

```
resources/js/
  app.js                  // Inertia bootstrap, glob page resolution, no SSR
  Layouts/AppLayout.vue   // persistent sidebar + topbar + overlays
  Pages/                  // Timeline, Year, Month, Day, Entry, Archive,
                          // Search, Now, Leaderboard, DesignSystem, Error
  Components/
    Layout/   AppSidebar, AppTopbar, MobileNav, Breadcrumb
    Profile/  ProfileCard (h-card), SocialLinks
    Timeline/ FeedItem, day grouping
    Entry/    per-type detail components (ActivityDetail, PodcastDetail, ...)
    Now/      widgets/ (Time, Weather, Location, Charging, Activity, Sleep,
                        Photos, Entries, Reading, Podcast)
    Overlays/ MediaPlayer, CommandPalette
    Search/   QueryBuilder
    Stats/    StatGrid, heatmaps
    Snake/    404 game + leaderboard
    Ui/       Button, Pill, Card, Input, Alert, Tooltip, ... (variant primitives)
  lib/        player.js, maplibre.js, geo.js, format.js, cn.js, youtube.js
  composables/ useClock, useCommandPalette
```

The `/now` page is a bento grid of container-query-scaled widgets (each sized in `cqw` units off a reference width). The media player state lives in `lib/player.js` (reactive, outside the page tree) so audio keeps playing across Inertia visits and video can dock inline on entry pages.

---

## Media & Storage

- Spatie Media Library on the `attachments` table; conversions queued and optimised (WebP via the configured image driver).
- Disks (env-driven): `public` for local dev, with `s3` and Cloudflare `r2` (S3-compatible, path-style) available for production media. Max upload 10 MB.

---

## Hosting / Infra

- **Local dev:** Laravel Herd serves the site at `https://taylordrayson.test`. SQLite database; `database`-backed queue, cache, and sessions; mail to log.
- **Production:** intended target is a managed VPS (xCloud) with queue workers and cron for the sync/fetch commands and queued image conversions, and R2 for media. No production deploy config is committed yet; the app runs local-only for now.

---

## Design References

| Site | What to take |
|------|--------------|
| Aaron Parecki (aaronparecki.com) | Monthly calendar with per-day activity, transport summaries, photo grids, timeline structure |
| Luke / Ekul (ekul.me) | Recontextualised stats, sidebar widgets, heatmaps, sparklines, personal records, a "Now" section |
| Zach Leatherman (zachleat.com) | Inline avatars/icons in prose, stats as narrative, clean sidebar |
| Marco Cornacchia (marco.fyi) | Interactive cards, polish, playful but professional |
| Trevor Morris (trovster.com) | Movies/photos/music/activities/books as first-class logged sections, each with its own metadata; monthly recap posts that turn logs into running-total narratives ("149 for the year"); unified latest-across-everything feed; syndication links out to Letterboxd/Strava/Last.fm |

---

## Ideas Backlog

Vision pieces from the original brief that are still worth building:

- **Stats & insights:** Year in Review pages, monthly recaps, streaks (calorie logging is the headline one), personal records with dates, and recontextualised "fun" stats (percent around the Earth, marathons equivalent, bathtubs of petrol, days asleep this year).
- **Maps page:** all activities, checkins, and flight arcs on one full-width map.
- **Photos page:** an aggregated grid pulling `photos` attachments from every entry, each linking back to its parent.
- **On This Day:** entries from 1, 2, 5+ years ago.
- **About page:** conversational, first-person narrative with inline logos/links.
- **More integrations:** Trakt and Swarm webhooks, live weather attached at entry time, an admin UI, and Apple Shortcuts endpoints for quick capture (notes, fuel, flights).

---

## References

- IndieWeb: https://indieweb.org  ·  Quantified Self: https://quantifiedself.com
- Strava API: https://developers.strava.com
- Trakt API: https://trakt.docs.apiary.io
- Foursquare / Swarm API: https://developer.foursquare.com
- OpenFreeMap tiles: https://openfreemap.org
- This Week With: https://www.thisweekwith.co.uk
