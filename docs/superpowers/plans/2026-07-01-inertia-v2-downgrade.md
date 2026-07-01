# Inertia v3 → v2 Downgrade Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development. Prerequisite phase for the Statamic migration.

**Goal:** Move the app from Inertia v3 to v2 (so Statamic 6 is installable) with no user-visible behaviour change.

**Architecture:** Replace the two v3-only client APIs the app uses (`setLayoutProps` in 17 files, `useHttp` in 1) with v2-compatible equivalents FIRST — while still on v3, so the build never breaks — then bump the Inertia packages to v2. A small client-side reactive `useLayout` store reproduces `setLayoutProps` with the same signature.

**Tech Stack:** Inertia v2 (`inertiajs/inertia-laravel ^2.0`, `@inertiajs/vue3 ^2.0`), Vue 3, Laravel 13.

## Global Constraints

- Target versions (verified installable together with `statamic/cms:^6.5` via dry-run: statamic 6.23.0 + inertia-laravel 2.0.24): `inertiajs/inertia-laravel ^2.0`, `@inertiajs/vue3 ^2.0`.
- No behaviour change: every page renders the same component + props; breadcrumbs still show where they did.
- NO browser-testing deps: verify with Inertia/feature tests (`assertInertia`), `npm run build`, and a manual visual pass.
- No em dashes. Comment Vue/JS with JSDoc. Run `vendor/bin/pint --dirty --format agent` before finalizing PHP changes. Do NOT run `npm run build` while the Vite watcher runs — stop it first or rely on it, but a one-off `npx vite build --outDir <scratch>` is fine for verification.
- Full `php artisan test --compact` green; existing story tests must not regress.
- Order matters: de-v3-ify the client (Task 1) BEFORE bumping versions (Task 2), so the build never depends on a removed API.

---

### Task 1: Replace v3-only client APIs (still on v3, build stays green)

**Files:**
- Create: `resources/js/composables/useLayout.js`
- Modify: `resources/js/Layouts/AppLayout.vue`; the 17 pages importing `setLayoutProps` from `@inertiajs/vue3`; the 1 file using `useHttp`
- Test: `tests/Feature/Inertia/LayoutPropsTest.php` (feature-level: a page that sets a breadcrumb exposes it), plus existing page tests

**Interfaces:**
- Produces: `useLayout()` / `setLayoutProps(props)` from `resources/js/composables/useLayout.js` with the SAME signature pages already call; `layoutProps` reactive object read by `AppLayout`.

- [ ] **Step 1: Create the `useLayout` store**

```js
// resources/js/composables/useLayout.js
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';

// Reactive layout props (e.g. breadcrumb) set by a page and read by AppLayout.
export const layoutProps = reactive({ breadcrumb: [] });

/**
 * Merge per-page props into the shared layout store (drop-in for Inertia v3's
 * removed setLayoutProps).
 * @param {object} props
 */
export function setLayoutProps(props) {
    Object.assign(layoutProps, props);
}

// Reset between page visits so props do not leak across navigations.
router.on('start', () => {
    layoutProps.breadcrumb = [];
});
```

- [ ] **Step 2: Point AppLayout at the store** — read `breadcrumb` from `layoutProps` instead of a component prop. Keep the existing `breadcrumb` prop as a fallback default of `[]` OR remove it and use the store; whichever keeps `<Breadcrumb :items="...">`/`<AppTopbar :breadcrumb="...">` fed. Import `{ layoutProps }` from the composable.

- [ ] **Step 3: Swap the 17 imports** — in each page, change `import { setLayoutProps } from '@inertiajs/vue3'` to import from the composable (`'@/composables/useLayout'` or the project's relative-path convention — match sibling imports). Leave the `setLayoutProps({...})` call sites unchanged. Where a file imports other symbols in the same line (e.g. `import { Link, setLayoutProps, usePage } from '@inertiajs/vue3'`), split: keep `Link`/`usePage` from `@inertiajs/vue3`, move `setLayoutProps` to the composable import.

- [ ] **Step 4: Rewrite the 1 `useHttp` usage** — replace the v3 `useHttp` hook with the v2-supported approach (axios, which v2 ships, or `router` visits). Preserve the same behaviour.

- [ ] **Step 5: Write/adjust the feature test** — a page that calls `setLayoutProps({ breadcrumb: [...] })` still renders correctly (`assertInertia` on component + any server props); the store change is client-side so assert at page-render level.

- [ ] **Step 6: Verify build (still v3) + tests**

Run: `npx vite build --outDir /private/tmp/inertia-verify --emptyOutDir` → no errors (uses a scratch dir, does not race the watcher).
Run: `php artisan test --compact` → green.

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "refactor: replace Inertia v3-only APIs (setLayoutProps, useHttp) with v2-compatible equivalents"
```

### Task 2: Bump Inertia to v2 and verify

**Files:**
- Modify: `composer.json`, `package.json`
- Test: existing suite + a fresh build

**Interfaces:**
- Consumes: Task 1 (no code path uses a v3-only API).
- Produces: app running on Inertia v2; `statamic/cms` installable.

- [ ] **Step 1: Bump PHP dep** — set `inertiajs/inertia-laravel` to `^2.0` in `composer.json`; `composer update inertiajs/inertia-laravel --no-interaction` (network — if the sandbox denies it, retry that command with `dangerouslyDisableSandbox: true`).

- [ ] **Step 2: Bump JS dep** — set `@inertiajs/vue3` to `^2.0` in `package.json`; `npm install`.

- [ ] **Step 3: Verify build + full suite on v2**

Run: `npx vite build --outDir /private/tmp/inertia-verify --emptyOutDir` → no errors.
Run: `php artisan test --compact` → green (story tests included).

- [ ] **Step 4: Verify Statamic is now installable** (gate, do not install here)

Run: `composer require "statamic/cms:^6.5" --dry-run --no-interaction --no-scripts` → resolves (shows statamic/cms would install, inertia-laravel already v2). No conflict.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "chore: downgrade Inertia to v2 (unblocks Statamic install)"
```

- [ ] **Step 6: Manual visual pass note** — after this lands, a human checks breadcrumbs render on Day/Month/Archive/SleepScore and forms still submit (no browser test in the suite).

---

## Self-Review Notes

- **Coverage:** version bumps (Task 2), setLayoutProps→store (Task 1), useHttp (Task 1), no-behaviour-change verified by feature tests + build (both tasks). Statamic-installable gate (Task 2 Step 4).
- **Ordering:** client de-v3-ification precedes the version bump so the build never references a removed API.
- **Verify-against-reality:** exact `@inertiajs/vue3 ^2.0` latest and any peer changes confirmed at `npm install`; the composer set already dry-run-verified (statamic 6.23.0 + inertia-laravel 2.0.24).
