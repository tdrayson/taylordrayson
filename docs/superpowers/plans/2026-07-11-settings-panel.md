# Settings Panel (Project A) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A settings modal (opened from a gear in the sidebar + mobile nav) housing the theme control, backed by a shared settings store — replacing the inline sidebar theme toggle and laying the foundation Project B extends.

**Architecture:** A `useSettings` composable holds the shared modal open-state and re-exports the existing `useTheme` controls (one settings surface). A `SettingsModal.vue` reuses the app's accessible-dialog pattern (Teleport + focus trap + Esc + restore focus, as in `ContentToc.vue`/`Lightbox.vue`). Task 1 relocates the existing `ThemeToggle` into the modal behind a gear; Task 2 upgrades that control to three theme **preview cards** and retires `ThemeToggle`.

**Tech Stack:** Laravel 13 + Inertia v3 + Vue 3 (client-only, no SSR), Tailwind v4, Hugeicons (`@hugeicons-pro/core-stroke-rounded` via `Icon.vue`), Pest 4 (browser plugin present on master).

## Global Constraints

- **Commit policy:** per-task commits on `feature/settings-panel` only; no push/PR without asking. NO "Claude-Session" trailer or attribution. Never stage `todo.md`, `.superpowers/`, `public/twemoji/`, or `.svg`.
- No new dependencies.
- No arbitrary Tailwind bracket values (standard scale). Comment non-obvious Vue/JS. Single root element per Vue component.
- a11y: dialog has `role="dialog"` + `aria-modal="true"` + a labelled title; focus trap; Esc closes; focus restores to the trigger; every hover has a mirrored `focus-visible` ring; `Icon.vue` is always `aria-hidden`; specific `aria-label`s.
- Theme mechanism is authoritative as-is: the pre-paint inline script in `app.blade.php` and `useTheme`'s `.dark` toggle + `localStorage['theme']` stay intact. Do NOT change them.
- Browser tests assert the RENDERED DOM element, never text that also appears in the Inertia `data-page` props JSON (known false-positive trap).

---

### Task 1: Settings store + modal shell + gear (relocate theme control)

**Files:**
- Create: `resources/js/useSettings.js`
- Create: `resources/js/Components/Layout/SettingsModal.vue`
- Modify: `resources/js/Components/Layout/AppSidebar.vue` (replace inline `ThemeToggle` with a gear button)
- Modify: `resources/js/Components/Layout/MobileNav.vue` (add a gear button)
- Modify: `resources/js/Layouts/AppLayout.vue` (render `<SettingsModal />` once)
- Test: `tests/Browser/SettingsModalTest.php`

**Interfaces:**
- Produces:
  - `useSettings()` → `{ theme, resolved, setTheme, settingsOpen, openSettings, closeSettings }` (theme trio delegated to `useTheme`; `settingsOpen` a shared `Ref<boolean>`).
  - `defineSetting(key, fallback, allowed?)` → `{ value: Ref, set(v) }` — the reusable, localStorage-backed setting factory (Project B's extension point; unused in A).
  - `SettingsModal.vue` — the dialog, reads `settingsOpen`, contains an Appearance section.

- [ ] **Step 1: Write the settings store**

Create `resources/js/useSettings.js`. It carries the modal open-state, re-exports theme, AND a generic `defineSetting` factory so adding a new preference later is one line (this is the extension point Project B relies on):
```js
import { ref } from 'vue';
import { useTheme } from './useTheme';

// localStorage namespace for factory-defined settings, so they never collide
// with the app's other keys (theme keeps its own bare 'theme' key, owned by
// useTheme + the pre-paint script, and is intentionally NOT routed through here).
const PREFIX = 'pref:';

// Module-level registry so each setting is a single shared reactive source
// across every consumer (idempotent per key).
const registry = {};

/**
 * Define a reactive, localStorage-backed preference in one line. `allowed`
 * (optional) whitelists valid values; anything stored outside it falls back to
 * `fallback`. Returns { value: Ref, set(v) }.
 *
 * This is the extension point. Project B adds settings like:
 *   const distanceUnit = defineSetting('distanceUnit', 'mi', ['mi', 'km']);
 *   const timeFormat  = defineSetting('timeFormat', '12h', ['12h', '24h']);
 * and reads `distanceUnit.value` reactively in its formatters.
 */
export function defineSetting(key, fallback, allowed = null) {
    if (registry[key]) {
        return registry[key];
    }
    const storageKey = PREFIX + key;
    const read = () => {
        const raw = localStorage.getItem(storageKey);
        if (raw === null || (allowed && !allowed.includes(raw))) {
            return fallback;
        }
        return raw;
    };
    const value = ref(read());
    const set = (next) => {
        if (allowed && !allowed.includes(next)) {
            return;
        }
        value.value = next;
        localStorage.setItem(storageKey, next);
    };
    registry[key] = { value, set };
    return registry[key];
}

// Shared, module-level open-state so the desktop gear and the mobile-nav gear
// control the same single SettingsModal instance.
const settingsOpen = ref(false);

function openSettings() {
    settingsOpen.value = true;
}

function closeSettings() {
    settingsOpen.value = false;
}

// One settings surface: theme controls (from useTheme) plus the modal state.
// New enumerated settings are added via defineSetting (see above); theme stays
// on useTheme because it has extra behaviour (system resolution + pre-paint).
export function useSettings() {
    return { ...useTheme(), settingsOpen, openSettings, closeSettings };
}
```
> A carries this factory but does not consume it (theme is the only setting, and it stays on `useTheme`). It exists so Project B — and any later setting — is a one-liner. Do not delete it as "unused"; it's the deliverable's whole point.

- [ ] **Step 2: Write `SettingsModal.vue`**

Create `resources/js/Components/Layout/SettingsModal.vue`, modelled on the app's dialog pattern (Teleport to body, backdrop, focus trap, Esc, restore focus):
```vue
<script setup>
import { ref, watch, nextTick, onBeforeUnmount } from 'vue';
import { Cancel01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';
import ThemeToggle from './ThemeToggle.vue';
import { useSettings } from '../../useSettings';

const { settingsOpen, closeSettings } = useSettings();

const panelEl = ref(null);
let lastFocused = null;

// Focusable elements within the modal, for the Tab trap.
function focusable() {
    if (!panelEl.value) {
        return [];
    }
    return [...panelEl.value.querySelectorAll(
        'a[href], button:not([disabled]), input, [tabindex]:not([tabindex="-1"])',
    )];
}

// Esc closes; Tab cycles within the modal.
function onKeydown(event) {
    if (event.key === 'Escape') {
        closeSettings();
        return;
    }
    if (event.key !== 'Tab') {
        return;
    }
    const items = focusable();
    if (items.length === 0) {
        return;
    }
    const first = items[0];
    const last = items[items.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

// On open: remember the trigger, move focus into the modal. On close: restore.
watch(settingsOpen, async (open) => {
    if (open) {
        lastFocused = document.activeElement;
        await nextTick();
        (focusable()[0] ?? panelEl.value)?.focus();
    } else if (lastFocused && typeof lastFocused.focus === 'function') {
        lastFocused.focus();
    }
});

onBeforeUnmount(() => {
    // Guard against a stray listener if the modal unmounts while open.
});
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div
                v-if="settingsOpen"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                @keydown="onKeydown"
            >
                <!-- Backdrop: click to close. -->
                <div class="absolute inset-0 bg-neutral-900/50" @click="closeSettings" />

                <div
                    ref="panelEl"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="settings-title"
                    tabindex="-1"
                    class="relative z-10 w-full max-w-md rounded-lg border border-neutral-100 bg-neutral-0 shadow-card focus-visible:outline-none"
                >
                    <div class="flex items-center justify-between border-b border-neutral-100 px-5 py-4">
                        <h2 id="settings-title" class="text-section font-bold text-neutral-900">Settings</h2>
                        <button
                            type="button"
                            aria-label="Close settings"
                            class="rounded-md p-1 text-neutral-500 transition hover:text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                            @click="closeSettings"
                        >
                            <Icon :icon="Cancel01Icon" class="size-5" />
                        </button>
                    </div>

                    <div class="space-y-6 p-5">
                        <section class="space-y-3">
                            <h3 class="text-label uppercase tracking-wide text-neutral-500">Appearance</h3>
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-body text-neutral-900">Theme</span>
                                <ThemeToggle />
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.15s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
```

- [ ] **Step 3: Replace the inline theme toggle with a gear in `AppSidebar.vue`**

In `AppSidebar.vue`, remove the `import ThemeToggle` and its use in the footer `<div class="mt-auto hidden space-y-4 pt-8 md:block">`. Add a gear button that opens the modal. Import:
```vue
import { Settings01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';
import { useSettings } from '../../useSettings';
```
```vue
const { openSettings } = useSettings();
```
Replace the `<ThemeToggle />` in the footer with:
```vue
            <button
                type="button"
                aria-label="Open settings"
                class="inline-flex items-center gap-2 rounded-lg px-2 py-1.5 text-nav text-neutral-500 transition hover:bg-neutral-50 hover:text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                @click="openSettings"
            >
                <Icon :icon="Settings01Icon" class="size-5" />
                Settings
            </button>
```
> Verify `Settings01Icon` is a real export of `@hugeicons-pro/core-stroke-rounded` (as the theme work verified `ComputerIcon`); if the exact name differs (e.g. `Settings02Icon`, `Setting07Icon`), use the installed one.

- [ ] **Step 4: Add a gear to `MobileNav.vue`**

In `MobileNav.vue`, import `useSettings` + `Icon` + the settings icon, and add a "Settings" gear button (opening the modal) in the mobile menu's footer area, matching that file's existing item styling. Reuse the same `openSettings` from `useSettings`.

- [ ] **Step 5: Render `<SettingsModal />` once in `AppLayout.vue`**

In `resources/js/Layouts/AppLayout.vue`, import and render `<SettingsModal />` once (e.g. just before the closing root element) so a single instance serves both gears:
```vue
import SettingsModal from '../Components/Layout/SettingsModal.vue';
```
```vue
        <SettingsModal />
```
(Match the file's actual structure; it must be inside the single root element.)

- [ ] **Step 6: Write the browser test**

Create `tests/Browser/SettingsModalTest.php`. Bind Browser to TestCase + RefreshDatabase locally (`uses(...)`). Visit the home page, assert the modal is NOT present, click the settings gear, assert the rendered dialog IS visible, click a theme option, assert `<html>.dark` toggles. Assert the RENDERED dialog element (`[role="dialog"]`), not props text.
```php
<?php

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

it('opens the settings modal from the gear and toggles theme', function () {
    $page = visit('/');
    $page->resize(1280, 800); // desktop, so the sidebar gear is visible

    // Modal closed initially.
    $page->assertScript("document.querySelector('[role=\"dialog\"]') === null", true);

    // Open it from the gear.
    $page->click('[aria-label="Open settings"]')
        ->assertScript("!!document.querySelector('[role=\"dialog\"]')", true);

    // Select Dark and assert the theme applied.
    $page->click('[aria-label="Dark"]')
        ->assertScript("document.documentElement.classList.contains('dark')", true)
        ->assertScript("localStorage.getItem('theme')", 'dark');
});
```
> Confirm the installed `pest-plugin-browser` assertion/interaction API against `tests/Browser/ThemeToggleTest.php` (which exists on master from the dark-mode work) and mirror it. The `[aria-label="Dark"]` target is the existing `ThemeToggle`'s radio button (unchanged in this task). The test MUST pass, not be skipped.

- [ ] **Step 7: Build + run the test**

Run: `npm run build` then `php artisan test tests/Browser/SettingsModalTest.php --compact`
Expected: build succeeds; test PASSES. (Ignore the unrelated pre-existing `ImportEventsTest`.)

- [ ] **Step 8: Checkpoint** — commit to `feature/settings-panel` (useSettings, SettingsModal, AppSidebar, MobileNav, AppLayout, the test).

---

### Task 2: Theme preview cards + retire `ThemeToggle`

**Files:**
- Create: `resources/js/Components/Layout/ThemeCards.vue`
- Modify: `resources/js/Components/Layout/SettingsModal.vue` (swap `ThemeToggle` → `ThemeCards`)
- Delete: `resources/js/Components/Layout/ThemeToggle.vue` (after confirming no other references)
- Test: extend `tests/Browser/SettingsModalTest.php`

**Interfaces:**
- Consumes: `useSettings()` (`theme`, `setTheme`).
- Produces: `ThemeCards.vue` — three selectable theme preview cards.

- [ ] **Step 1: Write `ThemeCards.vue`**

Create `resources/js/Components/Layout/ThemeCards.vue`. Each card is a stylised CSS thumbnail (a mock panel with a sidebar strip + content lines) in that theme's colours, a label, and a selected ring, driving `setTheme`:
```vue
<script setup>
import { useSettings } from '../../useSettings';

const { theme, setTheme } = useSettings();

// System is shown as a split light/dark thumbnail; light/dark are solid.
const cards = [
    { value: 'system', label: 'System' },
    { value: 'light', label: 'Light' },
    { value: 'dark', label: 'Dark' },
];
</script>

<template>
    <div role="radiogroup" aria-label="Colour theme" class="grid grid-cols-3 gap-3">
        <button
            v-for="card in cards"
            :key="card.value"
            type="button"
            role="radio"
            :aria-checked="theme === card.value"
            :aria-label="card.label"
            class="group flex flex-col gap-2 rounded-lg p-1.5 text-center focus-visible:outline-none"
            @click="setTheme(card.value)"
        >
            <!-- Stylised theme preview thumbnail. -->
            <span
                class="block overflow-hidden rounded-md border-2 transition"
                :class="theme === card.value ? 'border-accent-500' : 'border-neutral-100 group-hover:border-neutral-300'"
            >
                <span class="preview" :class="`preview-${card.value}`">
                    <span class="preview-bar" />
                    <span class="preview-line" />
                    <span class="preview-line short" />
                </span>
            </span>
            <span
                class="text-caption font-semibold"
                :class="theme === card.value ? 'text-neutral-900' : 'text-neutral-500'"
            >{{ card.label }}</span>
        </button>
    </div>
</template>

<style scoped>
.preview {
    display: block;
    aspect-ratio: 16 / 10;
    padding: 6px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.preview-bar {
    height: 6px;
    width: 40%;
    border-radius: 3px;
}

.preview-line {
    height: 4px;
    width: 85%;
    border-radius: 2px;
}

.preview-line.short {
    width: 60%;
}

/* Light thumbnail. */
.preview-light {
    background: #ffffff;
}
.preview-light .preview-bar { background: #3858e9; }
.preview-light .preview-line { background: #c9c9c9; }

/* Dark thumbnail. */
.preview-dark {
    background: #191919;
}
.preview-dark .preview-bar { background: #6c84f2; }
.preview-dark .preview-line { background: #3f3f3f; }

/* System thumbnail: diagonal split of light and dark. */
.preview-system {
    background: linear-gradient(135deg, #ffffff 0 50%, #191919 50% 100%);
}
.preview-system .preview-bar { background: #6c84f2; }
.preview-system .preview-line { background: #8c8c8c; }
</style>
```
> The thumbnail hex values are fixed (they depict the themes literally and must NOT follow the active theme), so hardcoded hex in scoped CSS is correct here — not the token system. Keep them.

- [ ] **Step 2: Swap the control in `SettingsModal.vue`**

In `SettingsModal.vue`, replace the `import ThemeToggle` with `import ThemeCards from './ThemeCards.vue';`, and in the Appearance section replace the `<span>Theme</span> + <ThemeToggle />` row with a stacked label + cards:
```vue
                        <section class="space-y-3">
                            <h3 class="text-label uppercase tracking-wide text-neutral-500">Appearance</h3>
                            <div class="space-y-2">
                                <span class="text-body text-neutral-900">Theme</span>
                                <ThemeCards />
                            </div>
                        </section>
```

- [ ] **Step 3: Retire `ThemeToggle.vue`**

Confirm no remaining references: `grep -rn "ThemeToggle" resources/js`. It should appear nowhere after Step 2 (Task 1 removed it from AppSidebar/MobileNav; Step 2 removed it from the modal). Then delete `resources/js/Components/Layout/ThemeToggle.vue`.

- [ ] **Step 4: Update the browser test to target the cards**

In `tests/Browser/SettingsModalTest.php`, the theme selection now happens via the cards (still `role="radio"` with `aria-label="Dark"`, so the existing `click('[aria-label="Dark"]')` still targets correctly). Add an assertion that after selecting Dark, the Dark card is `aria-checked`:
```php
    $page->assertScript(
        "document.querySelector('[aria-label=\"Dark\"]').getAttribute('aria-checked')",
        'true',
    );
```

- [ ] **Step 5: Build + test**

Run: `npm run build` then `php artisan test tests/Browser/SettingsModalTest.php --compact`
Expected: build succeeds (no dangling `ThemeToggle` import); test PASSES.

- [ ] **Step 6: Checkpoint** — commit to `feature/settings-panel` (ThemeCards, SettingsModal, deleted ThemeToggle, the test).

---

## Self-Review

**Spec coverage:**
- Modal opened from a gear in sidebar + mobile nav → Task 1 Steps 3-4. ✓
- Single-pane stacked sections (Appearance section; room for B) → SettingsModal. ✓
- `useSettings` reactive store, theme folded in, one surface → Task 1 Step 1. ✓
- Reuse accessible dialog pattern (focus trap, Esc, restore focus) → SettingsModal Step 2. ✓
- Theme as three preview cards → Task 2. ✓
- Inline ThemeToggle removed / retired → Task 1 Step 3 + Task 2 Step 3. ✓
- Pre-paint `.dark` mechanism untouched → constraint; store delegates to `useTheme`. ✓
- Browser test asserting rendered modal + `.dark` (not props JSON) → Task 1 Step 6, extended Task 2 Step 4. ✓
- No reduced-motion/density/emoji → not implemented, correct. ✓

**Placeholder scan:** The `Settings01Icon` name and the `pest-plugin-browser` API are genuine per-package verifications (against `ThemeToggleTest.php` on master), with concrete fallbacks — not deferred logic. All component code is complete.

**Type/name consistency:** `useSettings()` returns `{ theme, resolved, setTheme, settingsOpen, openSettings, closeSettings }`, used consistently across SettingsModal, AppSidebar, MobileNav, ThemeCards. `settingsOpen` is the single shared open-state. Theme option `aria-label`s (`System`/`Light`/`Dark`) are identical between `ThemeToggle` (Task 1) and `ThemeCards` (Task 2), so the browser test selector survives the swap.
