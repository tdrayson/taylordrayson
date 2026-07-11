# Merge ContentToc + StoryToc → TableOfContents Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the two near-identical TOC components (`ContentToc.vue`, `StoryToc.vue`) with one `TableOfContents.vue` driven by config props, migrate all four consumers, and delete the originals.

**Architecture:** `TableOfContents.vue` owns the shared mechanics (scroll-spy via IntersectionObserver, `useDialog` mobile sheet, desktop rail, mobile pill, scroll-to-and-close). It collects `{ id, label, number, level }` from `selector`; `number` (story chapters) renders in the sheet only when `numberAttr` is set. Active items use a neutral surface tint for every TOC (no accent).

**Tech Stack:** Vue 3 (client-only, no SSR), Inertia v3, Tailwind v4, `useDialog` composable, Hugeicons via `Icon.vue`, Pest 4 browser plugin.

## Global Constraints

- Per-task commits on `feature/table-of-contents` only; no push/PR without asking. NO "Claude-Session" trailer/attribution. NO em dashes. Never stage `todo.md`, `.superpowers/`, `public/twemoji/`, `.svg`.
- No new dependencies. No arbitrary Tailwind bracket values. Single root... N/A (TOC renders multiple top-level nodes, same as the originals — keep as-is). Comment non-obvious logic.
- **Behaviour parity** with the two originals is the bar. The ONLY intended visual change: the active-item sheet tint is neutral (`bg-neutral-50`) for every TOC, replacing ContentToc's `bg-accent-50` and StoryToc's hardcoded `bg-fuel/10`.
- Browser tests assert the RENDERED DOM element, never text that also appears in the Inertia `data-page` props JSON.

---

### Task 1: Create `TableOfContents.vue`, migrate the 4 consumers, delete the originals

**Files:**
- Create: `resources/js/Components/Ui/TableOfContents.vue`
- Modify: `resources/js/Components/Entry/ArticleDetail.vue`
- Modify: `resources/js/Pages/Stories/Flights.vue`
- Modify: `resources/js/Pages/Stories/Food.vue`
- Modify: `resources/js/Pages/Stories/Fuel.vue`
- Delete: `resources/js/Components/Ui/ContentToc.vue`
- Delete: `resources/js/Components/Story/StoryToc.vue`
- Test: `tests/Browser/ContentTocDialogTest.php` (existing; must stay green)

**Interfaces:**
- Produces: `TableOfContents.vue` — props `selector` (`'[data-toc]'`), `labelAttr` (`'data-toc-label'`), `numberAttr` (`null`). Renders the rail + pill + sheet.

- [ ] **Step 1: Create `resources/js/Components/Ui/TableOfContents.vue`**

Write it exactly as below. It composes ContentToc's structure (rail width `w-56`, `level`-based indentation, the pill/toTop) with StoryToc's hero-aware pill gating and optional chapter number, and a neutral active tint. The `<style scoped>` block is identical in both originals — copy it verbatim.
```vue
<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Menu01Icon, Cancel01Icon, ArrowUp01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from './Icon.vue';
import { useDialog } from '../../composables/useDialog';

// One table of contents for any long document: a desktop rail plus a mobile
// pill and sheet with scroll-spy. Collects `{ id, label, number, level }` from
// the elements matching `selector`; `number` (story chapters) shows in the
// sheet only when `numberAttr` is given. Merged from the former ContentToc and
// StoryToc, which shared all mechanics and differed only in item shape/accent.
const props = defineProps({
    // Selector matching the headings/chapters to collect.
    selector: { type: String, default: '[data-toc]' },
    // Attribute each matched element carries its display label in.
    labelAttr: { type: String, default: 'data-toc-label' },
    // Attribute holding an optional leading number (story chapters); when null
    // the sheet shows the label alone.
    numberAttr: { type: String, default: null },
});

// Items discovered in the document: [{ id, label, number, level }].
const items = ref([]);
// The id of the item currently in view (drives the active highlight).
const activeId = ref(null);
// Whether the mobile contents sheet is open.
const open = ref(false);
// Whether the reader has scrolled far enough to reveal the mobile pill.
const scrolled = ref(false);
let observer = null;

// Shared dialog behaviour (focus trap, Escape-to-close, scroll lock, focus
// save/restore); panelEl binds to the sheet element in the template.
const { panelEl } = useDialog({ isOpen: () => open.value, onClose: () => { open.value = false; } });

/**
 * Read every element matching `selector` and start a scroll-spy that marks the
 * one near the top of the viewport as active.
 *
 * @returns {void}
 */
function buildToc() {
    const elements = [...document.querySelectorAll(props.selector)];

    items.value = elements.map((el) => ({
        id: el.id,
        label: el.getAttribute(props.labelAttr) ?? '',
        // Optional leading number for story chapters; null for plain headings.
        number: props.numberAttr ? el.getAttribute(props.numberAttr) : null,
        // Heading depth (2 = h2, 3 = h3) drives the indented hierarchy; anything
        // without the attribute (e.g. story chapters) sits at the base level.
        level: Number(el.getAttribute('data-toc-level') ?? 2),
    }));

    observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    activeId.value = entry.target.id;
                }
            });
        },
        // Treat an item as active once it reaches the upper third of the viewport.
        { rootMargin: '-15% 0px -70% 0px' },
    );

    elements.forEach((el) => observer.observe(el));
}

/**
 * Smooth-scroll to an item and close the mobile sheet.
 *
 * @param {string} id The element id to scroll to.
 * @returns {void}
 */
function goTo(id) {
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth' });
    open.value = false;
}

/**
 * Smooth-scroll back to the top of the page.
 *
 * @returns {void}
 */
function toTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/**
 * Reveal the mobile pill once the reader has scrolled down: past a story hero
 * if present, otherwise past a fixed offset, so it never obscures the header on
 * first load.
 *
 * @returns {void}
 */
function onScroll() {
    const hero = document.querySelector('[data-story-hero]');
    scrolled.value = hero ? hero.getBoundingClientRect().bottom < 120 : window.scrollY > 400;
}

onMounted(() => {
    // The headings/chapters must already be rendered when this runs; place
    // TableOfContents AFTER the content in the host template so the DOM query
    // here sees them (Vue fires mounted hooks in template order).
    buildToc();
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
});

onBeforeUnmount(() => {
    observer?.disconnect();
    window.removeEventListener('scroll', onScroll);
});
</script>

<template>
    <!-- Desktop: a clean line-and-text list, placed in the gutter by the host's
         relative-positioned wrapper and stuck via CSS (no JS measuring). -->
    <nav
        v-if="items.length"
        class="hidden xl:absolute xl:left-full xl:top-0 xl:block xl:h-full xl:pl-10"
        aria-label="Table of contents"
    >
        <ul class="toc-rail sticky top-10 flex w-56 flex-col border-l border-neutral-100">
            <li v-for="item in items" :key="item.id">
                <button
                    type="button"
                    class="-ml-px block w-full border-l-2 py-1.5 text-left text-caption transition-colors focus-visible:text-neutral-900 focus-visible:outline-none"
                    :class="[
                        activeId === item.id ? 'border-neutral-900 font-medium text-neutral-900' : 'border-transparent text-neutral-400 hover:text-neutral-700',
                        item.level >= 3 ? 'pl-8' : 'pl-4',
                    ]"
                    @click="goTo(item.id)"
                >{{ item.label }}</button>
            </li>
        </ul>
    </nav>

    <!-- Smaller screens: a floating glass pill (top + contents) that slides up
         once the reader has scrolled a way down, and back down at the top. -->
    <div class="fixed bottom-5 left-1/2 z-30 -translate-x-1/2 xl:hidden">
        <Transition name="pill">
            <div
                v-if="items.length && scrolled"
                class="flex items-stretch overflow-hidden rounded-full bg-black/60 text-white shadow-card ring-1 ring-white/10 backdrop-blur-xl"
            >
                <button type="button" class="flex items-center gap-2 px-5 py-3 text-meta font-semibold transition-colors hover:bg-white/10" @click="toTop">
                    <Icon :icon="ArrowUp01Icon" class="size-4" /> Top
                </button>
                <span class="w-px bg-white/15" />
                <button type="button" class="flex items-center gap-2 px-5 py-3 text-meta font-semibold transition-colors hover:bg-white/10" @click="open = true">
                    <Icon :icon="Menu01Icon" class="size-4" /> Contents
                </button>
            </div>
        </Transition>
    </div>

    <!-- The contents sheet the pill opens. -->
    <Teleport to="body">
        <Transition name="sheet">
            <div
                v-if="open"
                ref="panelEl"
                tabindex="-1"
                role="dialog"
                aria-modal="true"
                aria-label="Table of contents"
                class="fixed inset-0 z-50 flex flex-col justify-end focus:outline-none"
            >
                <!-- Fixed bg-black (not bg-neutral-900): the floating glass pill and
                     its sheet backdrop are intentional dark surfaces in both themes. -->
                <div class="absolute inset-0 bg-black/50" @click="open = false" />
                <div class="relative max-h-svh overflow-y-auto rounded-t-2xl bg-neutral-0 p-5 pb-8">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-label uppercase text-neutral-500">Contents</h2>
                        <button type="button" class="text-neutral-500 transition-colors hover:text-neutral-900" aria-label="Close contents" @click="open = false">
                            <Icon :icon="Cancel01Icon" class="size-5" />
                        </button>
                    </div>
                    <ul class="flex flex-col">
                        <li v-for="item in items" :key="item.id">
                            <button
                                type="button"
                                class="flex w-full items-center gap-3 rounded-lg py-2.5 pr-3 text-left transition-colors"
                                :class="[
                                    activeId === item.id ? 'bg-neutral-50' : 'hover:bg-neutral-25',
                                    item.level >= 3 ? 'pl-7' : 'pl-3',
                                ]"
                                @click="goTo(item.id)"
                            >
                                <span v-if="item.number" class="text-label tnum text-neutral-400">{{ item.number }}</span>
                                <span class="text-meta" :class="activeId === item.id ? 'font-semibold text-neutral-900' : 'text-neutral-700'">{{ item.label }}</span>
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
/* Long TOCs scroll inside the rail instead of running off short viewports:
   cap at the viewport minus the sticky top-10 offset plus a bottom gap. */
.toc-rail {
    max-height: calc(100svh - 3.5rem);
    overflow-y: auto;
    overscroll-behavior: contain;
}

.pill-enter-active,
.pill-leave-active {
    transition: opacity 0.25s ease, transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.pill-enter-from,
.pill-leave-to {
    opacity: 0;
    transform: translateY(1rem);
}

@media (prefers-reduced-motion: reduce) {
    .pill-enter-active,
    .pill-leave-active {
        transition: opacity 0.2s ease;
    }

    .pill-enter-from,
    .pill-leave-to {
        transform: none;
    }
}

.sheet-enter-active,
.sheet-leave-active {
    transition: opacity 0.2s ease;
}

.sheet-enter-active > div:last-child,
.sheet-leave-active > div:last-child {
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.sheet-enter-from,
.sheet-leave-to {
    opacity: 0;
}

.sheet-enter-from > div:last-child,
.sheet-leave-to > div:last-child {
    transform: translateY(100%);
}

@media (prefers-reduced-motion: reduce) {
    .sheet-enter-active > div:last-child,
    .sheet-leave-active > div:last-child {
        transition: none;
    }
}
</style>
```
> Note: the story rail was `w-48`; the merged component uses `w-56` (ContentToc's) for one consistent width — a 32px-wider story rail is the accepted minor visual change. Everything else is byte-for-byte parity.

- [ ] **Step 2: Migrate `ArticleDetail.vue`**

Change the import `import ContentToc from '../Ui/ContentToc.vue';` → `import TableOfContents from '../Ui/TableOfContents.vue';`, and the usage `<ContentToc v-if="headingCount >= 2" />` → `<TableOfContents v-if="headingCount >= 2" />`. Nothing else changes (defaults match ContentToc's `[data-toc]`/`data-toc-label`).

- [ ] **Step 3: Migrate the three story pages**

In `Flights.vue`, `Food.vue`, and `Fuel.vue`: change `import StoryToc from '../../Components/Story/StoryToc.vue';` → `import TableOfContents from '../../Components/Ui/TableOfContents.vue';`, and `<StoryToc />` → `<TableOfContents selector="[data-story-chapter]" label-attr="data-kicker" number-attr="data-number" />`. `StoryChapter.vue` (which stamps `data-story-chapter`/`data-number`/`data-kicker`) is unchanged.

- [ ] **Step 4: Delete the originals**

Confirm no remaining references: `grep -rn "ContentToc\|StoryToc" resources/js` should return nothing after Steps 2-3 (the `ContentToc` mention in the old comment goes with the file). Then delete `resources/js/Components/Ui/ContentToc.vue` and `resources/js/Components/Story/StoryToc.vue`.

- [ ] **Step 5: Build + tests**

Run: `npm run build`, then `php artisan test tests/Browser/ContentTocDialogTest.php tests/Browser/SettingsModalTest.php --compact`.
Expected: build succeeds (no dangling ContentToc/StoryToc imports); `ContentTocDialogTest` (opens the mobile sheet on `/stories/fuel`, now via `TableOfContents`, asserts the rendered `[role="dialog"]` + Escape close) stays green. (Ignore the unrelated pre-existing `ImportEventsTest`.)

- [ ] **Step 6: Commit** to `feature/table-of-contents` (TableOfContents.vue, ArticleDetail, the 3 story pages, deleted ContentToc + StoryToc).

---

## Self-Review

**Spec coverage:**
- One merged `TableOfContents.vue` owning shared mechanics + chrome → Step 1. ✓
- Config props `selector`/`labelAttr`/`numberAttr` → Step 1 defineProps. ✓
- Sheet number rendered only when `numberAttr` set → Step 1 (`<span v-if="item.number">`). ✓
- Neutral active tint for every TOC (drop accent/fuel) → Step 1 (`bg-neutral-50`). ✓
- Migrate ArticleDetail + 3 story pages; delete originals → Steps 2-4. ✓
- Behaviour parity (rail/pill/sheet/scroll-spy/useDialog) → Step 1 lifts verbatim; hero-aware `onScroll` covers both. ✓
- Browser test stays green → Step 5. ✓

**Placeholder scan:** The full component code and exact per-file edits are given; the only "verify" is the grep for dangling references (a concrete check), not deferred logic.

**Type/name consistency:** `items` shape `{ id, label, number, level }` used consistently in `buildToc`, the rail (`item.label`, `item.level`), and the sheet (`item.number`, `item.label`, `item.level`). Props `selector`/`labelAttr`/`numberAttr` match the consumer bindings in Steps 2-3 (`selector`, `label-attr`, `number-attr`). Story consumers pass `data-kicker` as `labelAttr` (so `getAttribute('data-kicker')` = the old `dataset.kicker`) and `data-number` as `numberAttr` (= old `dataset.number`).
