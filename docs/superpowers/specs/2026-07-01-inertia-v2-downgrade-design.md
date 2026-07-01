# Inertia v3 → v2 Downgrade Design

**Status:** Approved
**Date:** 2026-07-01
**Branch:** `feature/statamic-migration`

## Goal

Take the app's Inertia dependency from v3 to v2 so Statamic 6 (which hard-requires
`inertiajs/inertia-laravel ^2.0` for its Control Panel) can be installed in the
same Laravel app. This is a prerequisite phase for the Statamic migration.

## Why

Statamic 6 builds its CP on Inertia v2 and pins `inertiajs/inertia-laravel ^2.0`.
The app runs Inertia v3 (`inertiajs/inertia-laravel ^3.1`, `@inertiajs/vue3 ^3.4`).
A single Composer package cannot be both v2 and v3, so the app must move to v2.

## Scope (measured)

- **Version bumps:** `inertiajs/inertia-laravel ^3.1 → ^2.0`; `@inertiajs/vue3 ^3.4 → ^2.0`.
- **`setLayoutProps` — 17 files.** v3-only API. Replaced by a client-side reactive
  layout store (below), keeping the same call signature.
- **`useHttp` — 1 file.** v3-only hook. Rewritten to the built-in `router`/axios.
- **~50 files import `Link`/`useForm`/`router`/`Head`/`usePage` from `@inertiajs/vue3`.**
  These APIs are stable across v2/v3; only the package version changes.
- Not present (so no work): `useLayoutProps`, `router.cancelAll`, v3 events
  `httpException`/`networkError`, `Inertia::optional/defer/merge` on the server.

## Approach: client-side reactive layout store

Create `resources/js/composables/useLayout.js`:

- Exports a reactive `layoutProps` object and `setLayoutProps(props)` with the
  **same signature** pages already call.
- `setLayoutProps` merges into `layoutProps`; the store resets on Inertia
  navigation start (via a `router.on('start', ...)` listener) so props do not
  leak between pages (matching v3's per-page reset).

`AppLayout.vue` reads `breadcrumb` (and any other layout prop) from the store
instead of component props. The 17 pages change only their import line:
`from '@inertiajs/vue3'` → `from '@/composables/useLayout'` (path per the project's
alias/relative convention). No controller changes.

## Statamic coexistence note

After the downgrade, the app front-end is an Inertia v2 app AND Statamic's CP is
an Inertia v2 app in the same Laravel install. Statamic scopes its CP Inertia to
`/cp` with its own middleware and root view; the app keeps its own root view and
middleware. Verify the two do not share/clobber Inertia's root view or middleware
during the Statamic install (Statamic migration Task 1).

## Testing

- `composer update` + `npm install` resolve on v2; Statamic 6 becomes installable
  (a dry `composer require statamic/cms --dry-run` should now pass).
- `npm run build` succeeds.
- Inertia feature tests: representative pages still render their expected
  component + props (Timeline, a Day/Month, an Archive, SleepScore) via
  `assertInertia`. A new `LayoutStoreTest` (JS or feature-level) confirms
  `setLayoutProps` sets and resets breadcrumb.
- Full `php artisan test` green; existing story tests must not regress.
- Manual visual pass (no browser-test deps): breadcrumbs render on the pages that
  set them.

## Non-goals

- No feature rewrites beyond replacing v3-only APIs.
- No SSR (the app has none; v3's SSR simplifications are irrelevant).

## Rollback

Isolated on `feature/statamic-migration`. If abandoned, revert the branch; master
stays on Inertia v3.
