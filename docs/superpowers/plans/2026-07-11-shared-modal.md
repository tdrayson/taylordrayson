# Shared Modal Primitive Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extract the duplicated dialog logic from five overlays into a `useDialog()` composable (focus trap, Esc, scroll-lock, focus save/restore) plus a thin `Modal.vue` centered-card wrapper, then migrate all five onto it with behaviour parity.

**Architecture:** `useDialog({ isOpen, onClose, closeOnEsc, onKeydown }) → { panelEl }` owns the shared behaviour; consumers keep their own open-state and bespoke chrome and bind `panelEl` to their dialog element. `Modal.vue` (built on `useDialog`) serves the centered-card case (`SettingsModal`). `Lightbox`/`ContentToc`/`StoryToc` use `useDialog` directly; `CommandPalette` uses it for trap+scroll+restore only (`closeOnEsc: false`), keeping its global ⌘K/Esc/arrow/snake logic.

**Tech Stack:** Vue 3 (client-only, no SSR), Inertia v3, Tailwind v4, Hugeicons (`Icon.vue`), Pest 4 browser plugin.

## Global Constraints

- **Commit policy:** per-task commits on `feature/shared-modal` only; no push/PR without asking. NO "Claude-Session" trailer or attribution. Never stage `todo.md`, `.superpowers/`, `public/twemoji/`, or `.svg`.
- No new dependencies. No arbitrary Tailwind bracket values (standard scale / existing tokens). Comment non-obvious Vue/JS. Single root element per Vue component.
- **Behaviour parity is the acceptance bar.** No visual or interaction change to any overlay. Each overlay must still: open, Escape-close (except CommandPalette, which owns Escape — its existing close paths must still work), trap Tab (wrap first↔last, and Shift+Tab from outside returns into the panel), lock body scroll while open, restore focus to the trigger on close, and keep its own extras (Lightbox arrows/swipe, CommandPalette arrows/⌘K/snake, TOC scroll-spy).
- a11y: `useDialog`'s focusable selector is `button, a[href], input, select, textarea, [tabindex]:not([tabindex="-1"])` filtered by `!disabled && offsetParent !== null`. This is the corrected (input-inclusive) selector — do NOT drop `input`/`select`/`textarea`.
- Browser tests assert the RENDERED DOM element/behaviour, never text that also appears in the Inertia `data-page` props JSON (known false-positive trap). Tests must PASS, not skip.

---

### Task 1: `useDialog` composable + `Modal.vue` + migrate `SettingsModal`

**Files:**
- Create: `resources/js/composables/useDialog.js`
- Create: `resources/js/Components/Ui/Modal.vue`
- Modify: `resources/js/Components/Layout/SettingsModal.vue` (render through `Modal.vue`, drop its dialog plumbing)
- Test: `tests/Browser/SettingsModalTest.php` + `tests/Browser/FormattingSettingsTest.php` are the parity guard (already exist; must stay green)

**Interfaces:**
- Produces:
  - `useDialog({ isOpen, onClose, closeOnEsc = true, onKeydown }) → { panelEl: Ref }`. `isOpen` is a reactive getter `() => boolean`; `onClose` is called on Escape (when `closeOnEsc`); `onKeydown(event)` is an optional extra handler for keys `useDialog` does not own (arrows); `panelEl` is bound to the dialog element by the consumer.
  - `Modal.vue` — props `open` (v-model), `title`, `ariaLabel`, `closeOnBackdrop = true`, `panelClass`; emits `update:open`; default slot for body, `#header` slot override.

- [ ] **Step 1: Write `useDialog.js`**

Create `resources/js/composables/useDialog.js` (this is the exact behaviour lifted from the current, corrected `SettingsModal.vue`, generalised):
```js
import { ref, watch, nextTick, onBeforeUnmount } from 'vue';

// Elements that can receive keyboard focus inside a dialog. Includes form
// controls so sr-only radios etc. stay in the trap (the a11y fix, centralised).
function focusableWithin(el) {
    if (!el) {
        return [];
    }
    return [...el.querySelectorAll('button, a[href], input, select, textarea, [tabindex]:not([tabindex="-1"])')].filter(
        (node) => !node.hasAttribute('disabled') && node.offsetParent !== null,
    );
}

/**
 * Shared dialog behaviour: focus trap, Escape-to-close, body scroll lock, and
 * focus save/restore. Consumers own their open-state and chrome; this owns the
 * cross-cutting a11y that was previously copied into every overlay.
 *
 * @param {object} options
 * @param {() => boolean} options.isOpen   Reactive getter for the open state.
 * @param {() => void} options.onClose     Called when the dialog requests close (Escape).
 * @param {boolean} [options.closeOnEsc=true]  Set false when the consumer owns Escape itself.
 * @param {(event: KeyboardEvent) => void} [options.onKeydown]  Extra key handling (arrows) on the same listener.
 * @returns {{ panelEl: import('vue').Ref }}  Bind panelEl to the dialog element.
 */
export function useDialog({ isOpen, onClose, closeOnEsc = true, onKeydown }) {
    const panelEl = ref(null);
    let lastFocused = null;

    function handleKeydown(event) {
        if (closeOnEsc && event.key === 'Escape') {
            onClose();
            return;
        }

        if (event.key === 'Tab') {
            const focusable = focusableWithin(panelEl.value);

            if (focusable.length === 0) {
                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            const active = document.activeElement;

            if (event.shiftKey && (active === first || !panelEl.value.contains(active))) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && active === last) {
                event.preventDefault();
                first.focus();
            }
            return;
        }

        // Any other key: let the consumer handle it (arrow nav, etc.).
        onKeydown?.(event);
    }

    watch(isOpen, (open) => {
        document.body.style.overflow = open ? 'hidden' : '';

        if (open) {
            lastFocused = document.activeElement;
            document.addEventListener('keydown', handleKeydown);
            nextTick(() => {
                (focusableWithin(panelEl.value)[0] ?? panelEl.value)?.focus();
            });
        } else {
            document.removeEventListener('keydown', handleKeydown);

            if (lastFocused && typeof lastFocused.focus === 'function') {
                lastFocused.focus();
            }

            lastFocused = null;
        }
    });

    onBeforeUnmount(() => {
        document.removeEventListener('keydown', handleKeydown);
        document.body.style.overflow = '';
    });

    return { panelEl };
}
```

- [ ] **Step 2: Write `Modal.vue`**

Create `resources/js/Components/Ui/Modal.vue`. Reproduce `SettingsModal`'s CURRENT panel markup/classes/transition exactly (read the current `SettingsModal.vue` template first and copy its panel container classes, header layout, close-button classes, and the `fade` transition) so the settings modal is pixel-identical after migration:
```vue
<script setup>
import { Cancel01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from './Icon.vue';
import { useDialog } from '../../composables/useDialog';

const props = defineProps({
    open: { type: Boolean, default: false },
    // Visible dialog title; renders a labelled header + a close button.
    title: { type: String, default: null },
    // Accessible name when there is no visible title.
    ariaLabel: { type: String, default: null },
    closeOnBackdrop: { type: Boolean, default: true },
    // Size / positioning override for the panel.
    panelClass: { type: [String, Array, Object], default: '' },
});

const emit = defineEmits(['update:open']);

function close() {
    emit('update:open', false);
}

const { panelEl } = useDialog({ isOpen: () => props.open, onClose: close });
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <!-- Backdrop: click to close (opt-out via closeOnBackdrop). -->
                <div class="absolute inset-0 bg-neutral-900/50" @click="closeOnBackdrop && close()" />

                <div
                    ref="panelEl"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="title ? null : ariaLabel"
                    :aria-labelledby="title ? 'modal-title' : null"
                    tabindex="-1"
                    :class="['relative z-10 w-full max-w-md rounded-lg border border-neutral-100 bg-neutral-0 shadow-card focus-visible:outline-none', panelClass]"
                >
                    <div v-if="title || $slots.header" class="flex items-center justify-between border-b border-neutral-100 px-5 py-4">
                        <slot name="header">
                            <h2 id="modal-title" class="text-section font-bold text-neutral-900">{{ title }}</h2>
                        </slot>
                        <button
                            type="button"
                            aria-label="Close"
                            class="rounded-md p-1 text-neutral-500 transition hover:text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                            @click="close"
                        >
                            <Icon :icon="Cancel01Icon" class="size-5" />
                        </button>
                    </div>

                    <div class="p-5">
                        <slot />
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
> If the current `SettingsModal` panel uses different token classes than shown, use SettingsModal's actual ones — Modal.vue must match the shipped look. Keep `Icon.vue`'s import path correct (`../Ui/Icon.vue` is `./Icon.vue` from within `Ui/`).

- [ ] **Step 3: Migrate `SettingsModal.vue` onto `Modal.vue`**

Rewrite `SettingsModal.vue` to render through `Modal`, removing ALL its own dialog plumbing (`panelEl`, `focusableInPanel`, `onKeydown`, the `watch(settingsOpen)` scroll/focus/listener block, `onBeforeUnmount`, and the `Teleport`/backdrop/panel/header template). Keep the Appearance (`ThemeCards`) + Formatting (`SettingToggle` ×2) content and the `useSettings`/`useFormat` wiring:
```vue
<script setup>
import ThemeCards from './ThemeCards.vue';
import SettingToggle from './SettingToggle.vue';
import Modal from '../Ui/Modal.vue';
import { useSettings } from '../../useSettings';
import { useFormat } from '../../composables/useFormat';

const { settingsOpen, closeSettings } = useSettings();
const { distanceUnit, setDistanceUnit, weightUnit, setWeightUnit } = useFormat();

const distanceOptions = [
    { value: 'mi', label: 'mi' },
    { value: 'km', label: 'km' },
];
const weightOptions = [
    { value: 'kg', label: 'kg' },
    { value: 'lbs', label: 'lbs' },
];

// Modal emits update:open(false) on backdrop/Esc/close; funnel that to the store.
function onOpenChange(open) {
    if (!open) {
        closeSettings();
    }
}
</script>

<template>
    <Modal :open="settingsOpen" title="Settings" @update:open="onOpenChange">
        <div class="space-y-6">
            <section class="space-y-3">
                <h3 class="text-label uppercase tracking-wide text-neutral-500">Appearance</h3>
                <ThemeCards />
            </section>

            <section class="space-y-3">
                <h3 class="text-label uppercase tracking-wide text-neutral-500">Formatting</h3>
                <div class="space-y-3">
                    <SettingToggle :model-value="distanceUnit" :options="distanceOptions" label="Distance" aria-label="Distance unit" @update:model-value="setDistanceUnit" />
                    <SettingToggle :model-value="weightUnit" :options="weightOptions" label="Weight" aria-label="Weight unit" @update:model-value="setWeightUnit" />
                </div>
            </section>
        </div>
    </Modal>
</template>
```
> Read the current `SettingsModal.vue` template first and preserve its exact section markup (headings, wrappers, the ThemeCards/SettingToggle usage) — only the dialog shell changes. `Modal` supplies the `p-5`, so the inner wrapper is just `space-y-6`.

- [ ] **Step 4: Build + run the settings parity tests**

Run: `npm run build`, then `php artisan test tests/Browser/SettingsModalTest.php tests/Browser/FormattingSettingsTest.php --compact`
Expected: build succeeds; ALL pass (these already assert open-from-gear, the rendered dialog, keyboard reachability/operation, and live formatting — they are the parity guard for the migration).

- [ ] **Step 5: Commit** to `feature/shared-modal` (useDialog.js, Modal.vue, SettingsModal.vue).

---

### Task 2: Migrate `Lightbox` onto `useDialog`

**Files:**
- Modify: `resources/js/Components/Overlays/Lightbox.vue`
- Test: `tests/Browser/LightboxDialogTest.php` (create if no Lightbox browser test exists)

**Interfaces:**
- Consumes: `useDialog({ isOpen, onClose, onKeydown }) → { panelEl }`.

- [ ] **Step 1: Adopt `useDialog`, keep arrows + viewer chrome**

In `Lightbox.vue`:
- Add `import { useDialog } from '../../composables/useDialog';`.
- Remove `focusableInDialog()` entirely.
- Split the current `onKeydown` (lines ~62-98): keep ONLY the arrow handling in a new `onArrowKeys(event)`:
```js
function onArrowKeys(event) {
    if (event.key === 'ArrowLeft') {
        slideTo(-1);
    } else if (event.key === 'ArrowRight') {
        slideTo(1);
    }
}
```
  (Use the same `slideTo` the current arrow branch calls.) Delete the Escape/Tab branches — `useDialog` owns them.
- Replace `const dialogEl = ref(null);` usage: call
```js
const { panelEl } = useDialog({ isOpen: () => isOpen.value, onClose: close, onKeydown: onArrowKeys });
```
  and change the template `ref="dialogEl"` (line ~282) to `ref="panelEl"`. Remove the standalone `dialogEl` ref declaration.
- Remove the dialog parts of `watch(isOpen)` (lines ~241-266): the `document.body.style.overflow`, `lastFocused` save/restore, `addEventListener('keydown', onKeydown)`/remove, and focus-in — all now in `useDialog`. KEEP any non-dialog work in that watch (e.g. `preloadNeighbours` if it's called there; note `watch(() => props.index)` for preloading is separate and stays).
- Remove `onKeydown` from `onBeforeUnmount` (lines ~271+) and the `document.body.style.overflow = ''` reset (both now in `useDialog`); keep any other cleanup.
- Drop now-unused imports (`watch` if no longer used elsewhere — verify; `nextTick`/`onBeforeUnmount` likewise). Keep `computed`, `ref`.
> Read the whole file first. The swipe/drag carousel, preloading, and template are untouched. Only the dialog plumbing moves to `useDialog`.

- [ ] **Step 2: Parity browser test**

Check for an existing Lightbox browser test (`grep -ril lightbox tests`). If none, create `tests/Browser/LightboxDialogTest.php`: seed an entry with ≥2 photos (mirror `EventDetailSmokeTest.php`'s media seeding), visit the entry, open the lightbox (click a photo/thumbnail), assert the rendered dialog (`[role="dialog"]`) is visible, press `ArrowRight` and assert the shown image changed, press `Escape` and assert the dialog is gone. Assert rendered DOM, not props JSON.

- [ ] **Step 3: Build + test + commit**

Run `npm run build`, then the Lightbox test (+ `SettingsModalTest` to confirm no shared-primitive regression). All pass. Commit to `feature/shared-modal`.

---

### Task 3: Migrate `ContentToc` + `StoryToc` onto `useDialog`

**Context:** these two are near-identical mobile "contents sheet" dialogs (`open` ref, `sheetEl`, `focusableInSheet`, `onSheetKeydown` = Esc+Tab only, `watch(open)` scroll/focus/listener, plus a `window` scroll-spy that must stay). Same migration for both.

**Files:**
- Modify: `resources/js/Components/Ui/ContentToc.vue`
- Modify: `resources/js/Components/Story/StoryToc.vue`
- Test: `tests/Browser/ContentTocDialogTest.php` (create if none)

**Interfaces:**
- Consumes: `useDialog({ isOpen, onClose }) → { panelEl }` (no arrows here).

- [ ] **Step 1: Migrate `ContentToc.vue`**

- Add `import { useDialog } from '../../composables/useDialog';`.
- Remove `focusableInSheet()` and `onSheetKeydown()` entirely.
- Replace the `sheetEl` ref with `useDialog`:
```js
const { panelEl } = useDialog({ isOpen: () => open.value, onClose: () => { open.value = false; } });
```
  and change the template `ref="sheetEl"` (line ~233) to `ref="panelEl"`.
- Remove the dialog parts of `watch(open)` (lines ~82-99): scroll-lock, focus save/restore, `addEventListener`/remove of `onSheetKeydown`. If nothing else remains in that watch, remove it entirely.
- In `onBeforeUnmount` (lines ~177-181), remove the `document.removeEventListener('keydown', onSheetKeydown)` and `document.body.style.overflow = ''` lines; KEEP `window.removeEventListener('scroll', onScroll)` and any scroll-spy cleanup.
- Keep everything scroll-spy: `onMounted`, `onScroll`, the `window` scroll listener, the rail/pill template, and the `open.value = false` on link-click (line ~143).
- Drop now-unused imports (`nextTick` if unused; keep `ref`, `watch` if still used by scroll-spy, `onMounted`, `onBeforeUnmount`).

- [ ] **Step 2: Migrate `StoryToc.vue`**

Apply the identical change (its lines: `focusableInSheet`/`onSheetKeydown` removed; `sheetEl`→`panelEl` at ref line ~214; `watch(open)` dialog bits removed lines ~72-89; `onBeforeUnmount` keydown+overflow lines removed ~160-164; scroll-spy kept). Same `useDialog({ isOpen: () => open.value, onClose: () => { open.value = false; } })`.

- [ ] **Step 3: Parity browser test**

If no TOC browser test exists, create `tests/Browser/ContentTocDialogTest.php`: visit a page that renders `ContentToc` (an article/page with headings — check which pages use it), resize to a mobile width so the contents pill shows, open the sheet, assert `[role="dialog"]` visible, press `Escape`, assert it closes. Assert rendered DOM.
> If routing to a `ContentToc`-bearing page is impractical to seed, test `StoryToc` on a story page instead (whichever is simpler to seed), since both now share `useDialog` — one parity test covers the shared behaviour; note in the test which component it exercises.

- [ ] **Step 4: Build + test + commit**

Run `npm run build`, then the TOC test (+ `SettingsModalTest`). All pass. Commit to `feature/shared-modal`.

---

### Task 4: Migrate `CommandPalette` (partial: trap + scroll-lock + restore)

**Context:** the outlier. It mounts a GLOBAL `onGlobalKeydown` (⌘K to open, Escape, arrow list-nav, and the 404 snake game) that must stay. It adopts `useDialog` ONLY for the focus trap, body scroll-lock, and focus save/restore, with `closeOnEsc: false` (it owns Escape).

**Files:**
- Modify: `resources/js/Components/Overlays/CommandPalette.vue`
- Test: extend/confirm any existing CommandPalette test, or create `tests/Browser/CommandPaletteDialogTest.php`

**Interfaces:**
- Consumes: `useDialog({ isOpen, onClose, closeOnEsc: false }) → { panelEl }`.

- [ ] **Step 1: Adopt `useDialog` for the shared bits only**

Read the whole file first, then:
- Add `import { useDialog } from '../../composables/useDialog';`.
- Identify its close function and open-state (`isOpen`). Add:
```js
const { panelEl } = useDialog({ isOpen: () => isOpen.value, onClose: close, closeOnEsc: false });
```
  Bind `panelEl` to the dialog panel element (the `role="dialog"` div at line ~333). If that panel already has a `ref`, reconcile: use `panelEl` (rename the existing ref usages) OR keep both only if the existing ref is needed elsewhere — prefer a single `panelEl`.
- Remove the body scroll-lock from its `watch(isOpen)` (line ~231 `document.body.style.overflow = ...`) and the `document.body.style.overflow = ''` in its unmount (line ~315) — `useDialog` owns scroll-lock. KEEP the `scrollIntoView` active-item logic (line ~260) and everything else in that watch.
- If CommandPalette currently traps Tab itself (e.g. inside `onPanelKeydown`/`onInputKeydown`), remove ONLY the Tab-trap branch and let `useDialog` handle it; keep arrow-key/Enter/Escape handling. If it does not trap Tab, `useDialog` simply adds it.
- Do NOT touch `onGlobalKeydown` (⌘K/Esc/arrows/snake) or `onMounted`/`onUnmounted` for that global listener.
- Verify focus-on-open: `useDialog` focuses the first focusable, which is the search `input` — confirm the palette's search field still receives focus on open (it should, as the first focusable). If the palette also self-focuses the input, that's harmless; remove the redundant self-focus only if it's clearly duplicated.
> This is the riskiest migration — change only the scroll-lock + trap + restore, and verify the ⌘K open, Escape close, arrow navigation, Enter-to-select, and the snake game all still work.

- [ ] **Step 2: Parity browser test**

Check for an existing CommandPalette test. Ensure coverage (existing or new `tests/Browser/CommandPaletteDialogTest.php`): open the palette (press ⌘K / `Meta+k`, or click its trigger — whichever the app exposes), assert `[role="dialog"]` visible and the search input is focused, type a query and assert results render, press `Escape` and assert it closes. Assert rendered DOM.
> If simulating ⌘K is unreliable in the harness, open via the palette's visible trigger button instead. The test must genuinely open the palette and assert the rendered dialog, not skip.

- [ ] **Step 3: Build + full test + commit**

Run `npm run build`, then `php artisan test --compact` (full suite; expect all green except the pre-existing unrelated `ImportEventsTest`). Commit to `feature/shared-modal`.

---

## Self-Review

**Spec coverage:**
- `useDialog` composable owning trap/Esc/scroll/restore + optional `onKeydown` → Task 1 Step 1. ✓
- `Modal.vue` centered-card wrapper on `useDialog` → Task 1 Step 2. ✓
- `SettingsModal` → `Modal.vue` → Task 1 Step 3. ✓
- `Lightbox` → `useDialog` keeping arrows/swipe → Task 2. ✓
- `ContentToc` + `StoryToc` → `useDialog` keeping scroll-spy → Task 3. ✓
- `CommandPalette` partial (`closeOnEsc: false`, keep global ⌘K/snake/arrows) → Task 4. ✓
- Behaviour parity via per-overlay browser tests → each task's test step. ✓
- Input-inclusive focusable selector centralised → Task 1 Step 1 (`focusableWithin`). ✓

**Placeholder scan:** The migration steps reference line numbers as read on 2026-07-11 and say "read the whole file first" — the implementer removes named blocks (focus trap / keydown / scroll-lock) and adds the one `useDialog` call, which is concrete work, not a deferred TODO. The two new files carry complete code. "Create test if none exists" is a genuine per-file check with a concrete fallback, not a placeholder.

**Type/name consistency:** `useDialog({ isOpen, onClose, closeOnEsc, onKeydown }) → { panelEl }` is used identically in `Modal.vue`, Lightbox, both TOCs, and CommandPalette. `isOpen` is always a getter `() => x.value`. `panelEl` is the returned ref bound in every consumer's template. `Modal.vue`'s `update:open(false)` → SettingsModal's `onOpenChange` → `closeSettings()` is consistent. `closeOnEsc: false` only on CommandPalette.
