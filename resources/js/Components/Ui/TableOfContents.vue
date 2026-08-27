<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import Icon from './Icon.vue';
import { useDialog } from '../../composables/useDialog';
import { useMounted } from '../../composables/useMounted';

const mounted = useMounted();

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
 * Smooth-scroll to an item, put its hash in the address bar and close the
 * mobile sheet.
 *
 * @param {string} id The element id to scroll to.
 * @returns {void}
 */
function goTo(id) {
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth' });
    // replaceState, not the default jump: the URL becomes copyable without
    // stacking a history entry per heading, and without the instant scroll that
    // following the link natively would do. The existing state is carried
    // through because Inertia keeps the page there, and a null state makes
    // popstate rewrite the entry instead of navigating to it (see #287).
    window.history.replaceState(window.history.state, '', `#${id}`);
    open.value = false;
}

/**
 * Handle a click on a contents link. A modified or middle click is left to the
 * browser, so opening a section in a new tab still works.
 *
 * @param {MouseEvent} event
 * @param {string} id The element id the link points at.
 * @returns {void}
 */
function onItemClick(event, id) {
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    event.preventDefault();
    goTo(id);
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
        class="hidden toc:absolute toc:left-full toc:top-0 toc:block toc:h-full toc:pl-10"
        aria-label="Table of contents"
    >
        <ul class="toc-rail sticky top-10 flex w-56 flex-col border-l border-neutral-100">
            <li v-for="item in items" :key="item.id">
                <a
                    :href="`#${item.id}`"
                    class="-ml-px block w-full border-l-2 py-1.5 text-left text-caption transition-colors focus-visible:text-neutral-900 focus-visible:outline-none"
                    :class="[
                        activeId === item.id ? 'border-neutral-900 font-medium text-neutral-900' : 'border-transparent text-neutral-400 hover:text-neutral-700',
                        item.level >= 3 ? 'pl-8' : 'pl-4',
                    ]"
                    @click="onItemClick($event, item.id)"
                >{{ item.label }}</a>
            </li>
        </ul>
    </nav>

    <!-- Smaller screens: a floating glass pill (top + contents) that slides up
         once the reader has scrolled a way down, and back down at the top. -->
    <div class="fixed bottom-5 left-1/2 z-30 -translate-x-1/2 toc:hidden">
        <Transition name="pill">
            <div
                v-if="items.length && scrolled"
                class="flex items-stretch overflow-hidden rounded-full bg-black/60 text-white shadow-card ring-1 ring-white/10 backdrop-blur-xl"
            >
                <button type="button" class="flex items-center gap-2 px-5 py-3 text-meta font-semibold transition-colors hover:bg-white/10" @click="toTop">
                    <Icon name="ArrowUp01Icon" class="size-4" /> Top
                </button>
                <span class="w-px bg-white/15" />
                <button type="button" class="flex items-center gap-2 px-5 py-3 text-meta font-semibold transition-colors hover:bg-white/10" @click="open = true">
                    <Icon name="Menu01Icon" class="size-4" /> Contents
                </button>
            </div>
        </Transition>
    </div>

    <Teleport v-if="mounted" to="body">
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
                <!-- Fixed black, not the neutral ramp: an intentional dark surface in both themes. -->
                <div class="absolute inset-0 bg-black/50" @click="open = false" />
                <div class="relative max-h-svh overflow-y-auto rounded-t-2xl bg-neutral-0 p-5 pb-8">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-label uppercase text-neutral-500">Contents</h2>
                        <button type="button" class="text-neutral-500 transition-colors hover:text-neutral-900" aria-label="Close contents" @click="open = false">
                            <Icon name="Cancel01Icon" class="size-5" />
                        </button>
                    </div>
                    <ul class="flex flex-col">
                        <li v-for="item in items" :key="item.id">
                            <a
                                :href="`#${item.id}`"
                                class="flex w-full items-center gap-3 rounded-lg py-2.5 pr-3 text-left transition-colors"
                                :class="[
                                    activeId === item.id ? 'bg-neutral-50' : 'hover:bg-neutral-25',
                                    item.level >= 3 ? 'pl-7' : 'pl-3',
                                ]"
                                @click="onItemClick($event, item.id)"
                            >
                                <span v-if="item.number" class="text-label tnum text-neutral-400">{{ item.number }}</span>
                                <span class="text-meta" :class="activeId === item.id ? 'font-semibold text-neutral-900' : 'text-neutral-700'">{{ item.label }}</span>
                            </a>
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
