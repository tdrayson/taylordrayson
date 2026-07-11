# User settings panel (Project A) — design

**Date:** 2026-07-11
**Branch:** `feature/settings-panel` (off `master`, which now has the merged dark-mode work)
**Status:** Approved, pending implementation plan

## Context

This is **Project A** of a two-part effort. A delivers the settings **modal + a reactive preference store + the Theme control** (relocated from the dark-mode sidebar toggle). **Project B** (separate spec, later) adds the client-side formatting layer and unit/date-time settings, reading the same store. Decomposed because B is a large cross-cutting refactor (raw values in props + client formatters) that depends on A's store existing.

Reference scan (Mobbin, web): settings modals converge on two shapes — two-pane (nav + content) and single-pane stacked sections. Skiff's single-pane appearance panel (theme preview cards + time/date format rows) is effectively the A→B end state, so A adopts the single-pane stacked-section shape.

## Decisions

| Decision | Choice |
| --- | --- |
| Presentation | Modal dialog, opened from a **Settings gear** in the sidebar + mobile nav |
| Layout | Single-pane, stacked labelled sections (grows vertically as B adds sections) |
| Theme control | Three **preview cards** — System / Light / Dark (visual thumbnails, not a segmented toggle) |
| Store | A reactive, localStorage-backed `useSettings`; the existing theme logic folds in |
| Scope | Theme only for now. **No** reduced-motion, density, or emoji settings in A |
| Prefs storage | Per-browser localStorage (no visitor auth) |

## Architecture

### 1. Settings store — `useSettings`

- A single reactive, localStorage-backed source of truth for client preferences (`resources/js/useSettings.js`), so A and B both read/write one store.
- Theme is its first key. The existing `resources/js/useTheme.js` (from dark mode) is folded in: either `useSettings` absorbs its `theme`/`resolved`/`setTheme` logic, or `useTheme` is kept as the theme slice and re-exported through `useSettings`. Whichever, there is ONE store and the pre-paint `.dark` mechanism (the inline script in `app.blade.php`) stays intact and authoritative for first paint.
- Shape: each setting has a current value, a setter that persists to localStorage and updates reactively. Designed so B can add keys (units, timeFormat, dateFormat) without reshaping it.

### 2. The modal — `SettingsModal.vue`

- Reuses the site's existing accessible dialog pattern (as used by `Lightbox` / `ContentToc`): backdrop, focus trap, Esc to close, focus-visible rings, `role="dialog"` + `aria-modal` + a labelled title.
- Single pane: "Settings" heading + close button; stacked labelled sections. A ships one section: **Appearance**. (B appends a **Formatting** section.)
- Open/close state lives where the gear does (sidebar/mobile nav), or in a tiny shared `useSettingsModal` open-state ref so both the desktop gear and the mobile-nav gear toggle the same modal.

### 3. Theme preview cards

- The Appearance section renders three selectable cards: **System**, **Light**, **Dark**. Each card is a small stylised thumbnail of the theme (a rounded panel with a mock sidebar + content lines in that theme's colours; System = split light/dark), a label beneath, and a selected state (ring/checkmark) bound to the current theme.
- Selecting a card calls the store's `setTheme(...)`, driving the same `.dark` toggle + localStorage the dark-mode work already established. The thumbnails are stylised CSS (design-token colours), not screenshots.

### 4. Reconciliation with the merged dark-mode work

- Dark mode placed `ThemeToggle.vue` inline in `AppSidebar.vue` and `MobileNav.vue`. Project A **removes those inline toggles** and adds a **Settings gear** in their place (both surfaces), which opens `SettingsModal`. The modal's Appearance section becomes the single home for theme control.
- `ThemeToggle.vue` is either retired (its logic superseded by the preview cards) or left unused; prefer removing it to avoid two theme controls. Keep `useTheme`'s underlying logic (now via `useSettings`).

## Testing

- **Store unit-ish coverage:** `useSettings` persists to and reads from localStorage; setting theme updates the reactive value and toggles the `<html>.dark` class. (Verified via the browser test below, since there is no JS unit runner.)
- **Pest browser test** (`pest-plugin-browser` is on master via the dark-mode merge): open the settings modal from the gear; assert the **rendered dialog element** is visible (NOT text that also lives in props JSON — see the browser-test gotcha in project memory); click the **Dark** card → assert `<html>` has `.dark` and `localStorage.theme === 'dark'`; click **Light** → assert reverted; reopen and assert the selected card reflects the stored theme.

## Out of scope (A)

- Reduced-motion, timeline density, emoji-style settings.
- Units, time format, date format, and any formatting refactor (all Project B).
- Server-side / per-account preferences (no visitor auth exists).

## Notes

- Branches off master with dark mode merged, so `useTheme`, `ThemeToggle`, the `.dark` palette, and `pest-plugin-browser` are all present to build on.
- Establishes the store + modal that **Project B** extends.
