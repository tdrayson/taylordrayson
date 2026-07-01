# Changelog

## [Unreleased] - 2026-07-01

### Features

- Adopted Statamic 6 as the CMS and admin layer, running headless inside the existing Laravel app. The Control Panel is available at `/cp` with flat-file users (independent of the app `users` table).
- Runway surfaces every Eloquent lifelog and reference model in the CP without touching the models or their tables: editable resources for Flight, Activity, Calorie, Sleep, Fuel, Podcast, Checkin, Event, Appearance, and Project; read-only reference resources for Airline and Airport (large lookup tables, surfaced for browse and search only). Flight-to-Airline/Airport relationships are mapped as Runway `belongs_to` fields.
- Authored content (pages, articles, notes) moved to Statamic flat-file collections under `content/collections/` with Bard bodies. Collections are served headlessly through a `ContentRepository` seam that every site surface queries; the Inertia + Vue front-end is unchanged.
- Content flows through all six site surfaces: timeline (read-time merge of Eloquent `timeline_entries` and Statamic articles/notes), archives, entry pages, Atom/RSS feeds, search, and OG images.
- The single existing Editor.js article was converted to a Bard entry as a one-time migration.

### Changed

- Downgraded Inertia v3 to v2 (prerequisite: Statamic 6 requires `inertia-laravel ^2.0`). `setLayoutProps` replaced by a client-side `useLayout` store; `useHttp` replaced with an Axios factory.
- Article and note rows removed from the `timeline_entries` index; those types are now sourced from Statamic flat files at read time.

### Removed

- The Eloquent `Article` and `Note` models, their migration tables, and their factories.
- The Editor.js editor integration: `app/Support/EditorJs.php`, `Ui/BlockContent.vue`, `Ui/EditorList.vue`.

### Design Rationale

- Statamic's eloquent-driver was evaluated and rejected: it is all-or-nothing for entries and cannot mix flat-file content collections with database-backed ones. It would also flatten the relational lifelog models (Flight with Airline/Airport foreign keys, accessors, data-story SQL aggregations) into generic JSON rows. Runway keeps the Eloquent models intact while still surfacing them in the CP, giving both flat-file content and database-backed lifelog data in one admin interface.
- Headless operation (Statamic's own front-end routing and Runway front-end routing both disabled) means the app's routes and controllers remain authoritative with no risk of Statamic catch-all routes shadowing existing paths.
- Read-time merge for the timeline (rather than write-time sync hooks) avoids a secondary index for content that is low-volume and infrequently updated.

### Notes and Caveats

- Two user systems by design: Statamic flat-file CP users vs the app's `users` table. This is intentional and not a bug.
- The CP super-user was created with a placeholder password during installation; rotate it before deploying to production.
- After deploying to an environment that previously ran Inertia v3, run `php artisan view:clear` to clear any cached views.
- Statamic's flat-file stache can cause intermittent test failures under random or parallel test ordering; the default deterministic order is stable.
- All importers (Strava, Health Auto Export, Rovi, PocketCasts, CSV commands) are unchanged and continue writing directly to Eloquent models.
