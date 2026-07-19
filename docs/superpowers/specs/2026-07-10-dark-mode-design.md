# Dark mode — design

**Date:** 2026-07-10
**Branch:** `feature/dark-mode`
**Status:** Approved, pending implementation plan

## Goal

Add full dark-mode support to the site: a visitor-controllable theme that defaults to the OS preference, covering every surface and the full data-visualisation palette (not just the chrome).

## Decisions

| Decision | Choice |
| --- | --- |
| Theme control | System default (`prefers-color-scheme`) + manual override, persisted |
| Toggle states | Three-way: **System / Light / Dark** |
| v1 scope | Full pass — chrome, surfaces, text **and** all data-viz palettes tuned |
| Technical approach | **Variable override** — redefine `--color-*` tokens in a `.dark` scope |
| Toggle placement | Sidebar footer |

## Why the variable-override approach

The colour system has no semantic layer: components use the neutral ramp *positionally* (`bg-neutral-0` = page background, `text-neutral-900` = ink) and every colour — Tailwind utilities (`bg-food`, `text-sleep`, `bg-activity/10`), inline `var(--color-flight)`, and chart/map `color` props — resolves to the same `--color-*` custom properties defined in `resources/css/app.css`.

Because of that single indirection, redefining the `--color-*` tokens inside a `.dark { }` scope cascades everywhere with near-zero component edits. This fits the existing "purpose-free ramp" design instead of fighting it, and future components inherit dark mode for free. The alternatives (semantic-token migration across ~131 Vue files, or `dark:` variants everywhere) were rejected for churn and risk.

## Architecture

### 1. Theme resolution & no-flash plumbing

- Preference stored in `localStorage['theme']` with values `system | light | dark`. Absent = `system`.
- A **blocking inline script** in `resources/views/app.blade.php` `<head>`, before the app mounts, reads `localStorage['theme']` + `matchMedia('(prefers-color-scheme: dark)')`, resolves to `light | dark`, and sets `class="dark"` on `<html>` **before first paint**. This is the only inline/blocking piece (~10 lines) and exists solely to prevent a flash of the wrong theme (the site is client-rendered, no SSR).
- When the resolved preference is `system`, a `matchMedia` change listener updates the class live if the OS theme flips.

### 2. The dark palette — `resources/css/app.css`

- Add `@custom-variant dark (&:where(.dark, .dark *));` so `dark:` utilities are available for the few spot-fixes.
- Add a `.dark { … }` block (outside `@theme`, so it overrides at the `:root`-scoped cascade) that redefines the `--color-*` tokens:
  - **Neutral ramp** inverted and tuned for comfortable dark contrast — `neutral-0` → near-black surface (~`#191919`, deliberately not pure black), `neutral-25/50` → slightly raised surfaces, `neutral-900` → near-white ink (~`#f2f2f2`). Mid greys stay mid. Body `background`/`color` already read these vars, so they flip automatically.
  - **Accent ramp** and **heat ramp** lightened so they read against dark.
  - **13 data-type hues** + **sleep-stage**, **macro**, and **sleep-score** palettes — lightness/saturation nudged so saturated colours read on dark without glaring.
  - **`--shadow-card`** swapped to a subtle hairline border + deeper shadow (pure-black shadows are invisible on dark surfaces).

### 3. Vue layer

- **`resources/js/useTheme.js`** — a composable exposing a shared reactive `theme` (`system|light|dark`) and `resolved` (`light|dark`). `setTheme(value)` writes `localStorage`, toggles the `<html>` class, and (re)binds the matchMedia listener. Single shared instance across the app.
- **`resources/js/Components/.../ThemeToggle.vue`** — a three-way segmented control (System / Light / Dark) rendering Hugeicons through the existing `Icon.vue` wrapper, with focus-visible rings mirroring hover and specific `aria-label`/`aria-pressed`, per the project a11y conventions.
- **Placement:** sidebar footer.

### 4. Spot fixes (non-variable colours)

- **`CodeBlock.vue`** — the hardcoded syntax-highlight `hsl()` literals get dark values via the `dark:` variant.
- **maplibre-gl basemaps** — marker/route `color` props are vars (flip for free), but the map *tiles* need a dark basemap style (or filter) selected when the resolved theme is dark. Affects `LocationMap.vue`, `FlightsMap.vue`, `RouteThumb.vue`.
- **Chart.js** — series colours are vars (free); any hardcoded axis/gridline/tick neutrals are pointed at the CSS vars (read via `getComputedStyle`) or given theme-aware values.
- **Content imagery** (photos, OG images) — untouched.

## Testing

- **Unit** — `useTheme`: localStorage precedence over system, system fallback when unset, live update on matchMedia change.
- **Browser (Pest/Playwright) smoke** — toggle to dark, assert `<html>` carries `dark` and a key surface's computed background equals the dark value; toggle back to light and assert the reverse.
- **Manual visual audit** — walk the key pages (Timeline, Year, Month archive, an Article, an Event single-view, `/more`, a Sleep detail with charts, a map) in dark mode and note any against-the-grain neutral to spot-fix.

## Out of scope

- No semantic-token migration.
- No per-page bespoke dark art direction beyond palette tuning.
- Content images are not dark-adjusted.
