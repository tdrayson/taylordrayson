# Shared modal primitive (useDialog + Modal.vue) — design

**Date:** 2026-07-11
**Branch:** `feature/shared-modal` (off master, which now has the merged settings work / PR #9)
**Status:** Approved, pending implementation plan

## Context

Five overlays each hand-roll the same dialog logic: `SettingsModal`, `Lightbox`, `ContentToc`, `StoryToc`, `CommandPalette`. Four of them (`SettingsModal`, `Lightbox`, `ContentToc`, `StoryToc`) carry a near-identical block — `lastFocused` save/restore, a `focusableInX()` Tab trap, an Escape/Tab `onKeydown`, a `watch(open)` that locks body scroll + binds the listener + moves focus in, and `onBeforeUnmount` cleanup. This duplication already caused a real a11y bug: the focus-trap selector had to be fixed (to include form `input`s) in `SettingsModal` alone, and the same latent gap sits in the copies.

This feature extracts the shared behaviour into one place so the a11y lives in a single tested unit, and cuts the per-component boilerplate.

## Decisions

| Decision | Choice |
| --- | --- |
| Shape | A `useDialog()` **composable** (the shared logic; all 5 use it) + a thin `Modal.vue` **wrapper** (centered-card chrome, built on the composable) |
| Migration | All 5 overlays. `SettingsModal` → `Modal.vue`; `Lightbox`/`ContentToc`/`StoryToc` → `useDialog` (keep bespoke chrome); `CommandPalette` → `useDialog` for trap+scroll+restore only |
| `Modal.vue` now | Built now even though only `SettingsModal` consumes it initially — it is the canonical centered dialog and de-boilerplates the most common case |
| Non-goal | No visual/behavioural redesign of any overlay; behaviour parity is the whole job. Not merging `ContentToc`/`StoryToc` (separate dedup) |

## Architecture

### 1. `composables/useDialog.js`

The single owner of the duplicated dialog behaviour. Signature:

```
useDialog({ isOpen, onClose, closeOnEsc = true, onKeydown }) -> { panelEl }
```

- `isOpen` — a getter/ref returning the reactive open boolean (the consumer's own state, e.g. `settingsOpen`, a computed `index >= 0`, `open`).
- `onClose` — called when the dialog requests close (Escape, if `closeOnEsc`).
- `closeOnEsc` — default `true`; `false` for consumers that own Escape themselves (CommandPalette).
- `onKeydown(event)` — optional extra handler invoked for keys the composable does not own, so consumers can add Arrow-key handling (Lightbox image nav, and any consumer that wants it) without a second document listener.
- Returns `{ panelEl }` — a `ref` the consumer binds to its dialog element (`ref="panelEl"` or aliased). The trap and focus-in read this.

Owned behaviour (lifted verbatim from `SettingsModal`'s current, corrected implementation):
- **Focus trap:** `focusableInPanel()` querying `button, a[href], input, select, textarea, [tabindex]:not([tabindex="-1"])`, filtered by `!disabled && offsetParent !== null`; Tab / Shift+Tab wrap first↔last, including the "active element not in panel" edge.
- **Escape:** when `closeOnEsc`, Escape calls `onClose`.
- **Extra keys:** always calls `onKeydown?.(event)` so consumers handle arrows etc.
- **Body scroll lock:** `document.body.style.overflow = 'hidden'` while open, restored on close.
- **Focus management:** on open, save `document.activeElement`, move focus to the first focusable (or the panel); on close, restore focus to the saved trigger.
- **Listener lifecycle:** bind/unbind the single `keydown` listener on open/close; `onBeforeUnmount` removes it and clears the scroll lock.

It is a composable (not a component) because the five templates diverge completely (centered card, full-screen viewer, mobile bottom-sheet, command palette) — only the logic is shared.

### 2. `Components/Ui/Modal.vue`

A thin presentational wrapper for the **centered-card** case, built on `useDialog`.

- **Props:** `open: Boolean` (v-model), `title: String` (optional — renders a labelled `<h2 id>` header + a close button), `ariaLabel: String` (optional, when there is no visible title), `closeOnBackdrop: Boolean = true`, `panelClass: [String, Array, Object]` (size/positioning override).
- **Emits:** `update:open` (false on backdrop click, Escape, or close button).
- **Template:** `Teleport` to body, a fade `Transition`, a backdrop (`bg-neutral-900/50`, click → close when `closeOnBackdrop`), and the panel (`role="dialog"`, `aria-modal="true"`, `aria-labelledby` the title or `aria-label`, `tabindex="-1"`, `ref` bound to `useDialog().panelEl`). Slots: default (body); the header is rendered from `title` + a close button, or replaced via an optional `#header` slot.
- Uses `useDialog({ isOpen: () => props.open, onClose: () => emit('update:open', false) })`.

### 3. Migration mapping

| Overlay | Migration |
| --- | --- |
| `SettingsModal` | Replace all dialog plumbing with `<Modal :open="settingsOpen" @update:open="v => v || closeSettings()" title="Settings">`; keep the Appearance + Formatting section content. Drop `focusableInPanel`/`onKeydown`/`watch`/`onBeforeUnmount`/`panelEl`. |
| `Lightbox` | Use `const { panelEl } = useDialog({ isOpen: () => isOpen.value, onClose: close, onKeydown: onArrowKeys })`; bind `panelEl` to the viewer; keep image nav (`onArrowKeys`), preloading, and full-screen chrome. Remove its copied trap/scroll/focus block. |
| `ContentToc` | `useDialog({ isOpen: () => open.value, onClose: () => open.value = false })` for the mobile sheet; keep scroll-spy/rail/pill chrome. Remove copied block. |
| `StoryToc` | Same as `ContentToc`. |
| `CommandPalette` | `useDialog({ isOpen: () => isOpen.value, onClose: close, closeOnEsc: false })` for **focus trap + scroll lock + focus restore only**; keep its global ⌘K/Escape listener, arrow-key list nav, and 404-snake logic. Remove only its duplicated scroll-lock/focus bits. |

## Testing

Behaviour parity is the acceptance bar. There is no JS unit runner, so `useDialog` is proven through the overlays' Pest browser tests. Per overlay, assert the rendered behaviour (rendered DOM, never props JSON):
- **SettingsModal** (existing `SettingsModalTest`/`FormattingSettingsTest` already cover open + keyboard reachability): keep them green through the `Modal.vue` migration; they are the parity guard.
- **Lightbox / ContentToc / StoryToc / CommandPalette:** add/confirm focused browser tests that open the overlay, assert Escape closes it (except CommandPalette, which owns Escape — assert its existing close path still works), and that focus is trapped (Tab from the last focusable returns into the dialog) and restored to the trigger on close. Arrow-key behaviour (Lightbox next/prev, CommandPalette list nav) must still work.

Each overlay migrates as its own task with its own parity test, so a regression is caught per-component.

## Out of scope

- Any visual or interaction redesign of the overlays.
- Merging `ContentToc` and `StoryToc` into one component (they are near-duplicates — a worthwhile but separate dedup).
- Non-dialog overlays that surfaced in the survey but aren't modals (`Tooltip`, `MediaPlayer`, `MobileNav`, `ProfileCard`, `TimeJump`) — leave them alone.

## Notes

- The composable centralises the focus-trap selector, so the input-inclusion a11y fix can never again be present in one dialog and missing in another.
- `Modal.vue` and `useDialog` live beside the existing `Components/Ui/` and `composables/` conventions.
