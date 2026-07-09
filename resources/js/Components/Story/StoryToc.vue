<script setup>
import { ref, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { Menu01Icon, Cancel01Icon, ArrowUp01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';

// Chapters discovered from the rendered story: [{ id, number, kicker }].
const chapters = ref([]);
// The id of the chapter currently in view (drives the active highlight).
const activeId = ref(null);
// Whether the mobile contents sheet is open.
const open = ref(false);
// Whether the hero has scrolled away (gates the mobile pill so the full header
// is unobstructed on first load).
const scrolled = ref(false);
let observer = null;

// The sheet element, for focus trapping, and whichever element opened it, so
// focus can be restored on close (mirrors Lightbox.vue's dialog behaviour).
const sheetEl = ref(null);
let lastFocused = null;

function focusableInSheet() {
    if (!sheetEl.value) {
        return [];
    }

    return [...sheetEl.value.querySelectorAll('button, a[href], [tabindex]:not([tabindex="-1"])')].filter(
        (el) => !el.hasAttribute('disabled') && el.offsetParent !== null,
    );
}

/**
 * Escape closes the sheet; Tab is trapped inside it while open.
 *
 * @param {KeyboardEvent} event
 * @returns {void}
 */
function onSheetKeydown(event) {
    if (event.key === 'Escape') {
        open.value = false;

        return;
    }

    if (event.key !== 'Tab') {
        return;
    }

    const focusable = focusableInSheet();

    if (focusable.length === 0) {
        event.preventDefault();

        return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    const active = document.activeElement;

    if (event.shiftKey && (active === first || !sheetEl.value.contains(active))) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && active === last) {
        event.preventDefault();
        first.focus();
    }
}

// Lock background scrolling while the mobile contents sheet is open, move
// focus into the sheet, and restore it to the trigger on close.
watch(open, (isOpen) => {
    document.body.style.overflow = isOpen ? 'hidden' : '';

    if (isOpen) {
        lastFocused = document.activeElement;
        document.addEventListener('keydown', onSheetKeydown);
        nextTick(() => {
            const focusable = focusableInSheet();
            (focusable[0] ?? sheetEl.value)?.focus();
        });
    } else {
        document.removeEventListener('keydown', onSheetKeydown);

        if (lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus();
        }

        lastFocused = null;
    }
});

/**
 * Read every chapter section from the page and start a scroll-spy that marks
 * the one near the top of the viewport as active.
 *
 * @returns {void}
 */
function buildToc() {
    const sections = [...document.querySelectorAll('[data-story-chapter]')];

    chapters.value = sections.map((el) => ({
        id: el.id,
        number: el.dataset.number,
        kicker: el.dataset.kicker,
    }));

    observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    activeId.value = entry.target.id;
                }
            });
        },
        // Treat a chapter as active once it reaches the upper third of the viewport.
        { rootMargin: '-15% 0px -70% 0px' },
    );

    sections.forEach((el) => observer.observe(el));
}

/**
 * Smooth-scroll to a chapter and close the mobile sheet.
 *
 * @param {string} id The chapter element id to scroll to.
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
 * Reveal the mobile pill only once the hero has largely scrolled off the top,
 * so the reader sees the full header before it appears.
 *
 * @returns {void}
 */
function onScroll() {
    const hero = document.querySelector('[data-story-hero]');
    scrolled.value = hero ? hero.getBoundingClientRect().bottom < 120 : window.scrollY > 400;
}

onMounted(() => {
    buildToc();
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
});

onBeforeUnmount(() => {
    observer?.disconnect();
    window.removeEventListener('scroll', onScroll);
    document.removeEventListener('keydown', onSheetKeydown);
    document.body.style.overflow = '';
});
</script>

<template>
    <!-- Desktop: a clean line-and-text list. It is placed in the gutter by the
         article's flex layout and sticks via CSS (no JS measuring), so its top
         starts level with the first chapter's heading. -->
    <nav
        v-if="chapters.length"
        class="hidden xl:absolute xl:left-full xl:top-0 xl:block xl:h-full xl:pl-10"
        aria-label="Table of contents"
    >
        <ul class="toc-rail sticky top-10 flex w-48 flex-col border-l border-neutral-100">
            <li v-for="chapter in chapters" :key="chapter.id">
                <button
                    type="button"
                    class="-ml-px block w-full border-l-2 py-1.5 pl-4 text-left text-caption transition-colors focus-visible:text-neutral-900 focus-visible:outline-none"
                    :class="activeId === chapter.id ? 'border-neutral-900 font-medium text-neutral-900' : 'border-transparent text-neutral-400 hover:text-neutral-700'"
                    @click="goTo(chapter.id)"
                >{{ chapter.kicker }}</button>
            </li>
        </ul>
    </nav>

    <!-- Smaller screens: a floating glass pill (top + contents). It slides up
         into view once the hero has scrolled away, and back down at the top.
         Centering lives on the wrapper so the slide transform is conflict-free. -->
    <div class="fixed bottom-5 left-1/2 z-30 -translate-x-1/2 xl:hidden">
        <Transition name="pill">
            <div
                v-if="chapters.length && scrolled"
                class="flex items-stretch overflow-hidden rounded-full bg-neutral-900/60 text-neutral-0 shadow-card ring-1 ring-white/10 backdrop-blur-xl"
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

    <!-- The contents sheet that the pill opens. -->
    <Teleport to="body">
        <Transition name="sheet">
            <div
                v-if="open"
                ref="sheetEl"
                tabindex="-1"
                role="dialog"
                aria-modal="true"
                aria-label="Table of contents"
                class="fixed inset-0 z-50 flex flex-col justify-end focus:outline-none"
            >
                <div class="absolute inset-0 bg-neutral-900/50" @click="open = false" />
                <div class="relative max-h-svh overflow-y-auto rounded-t-2xl bg-neutral-0 p-5 pb-8">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-label uppercase text-neutral-500">Contents</h2>
                        <button type="button" class="text-neutral-500 transition-colors hover:text-neutral-900" aria-label="Close contents" @click="open = false">
                            <Icon :icon="Cancel01Icon" class="size-5" />
                        </button>
                    </div>
                    <ul class="flex flex-col">
                        <li v-for="chapter in chapters" :key="chapter.id">
                            <button
                                type="button"
                                class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left transition-colors"
                                :class="activeId === chapter.id ? 'bg-fuel/10' : 'hover:bg-neutral-25'"
                                @click="goTo(chapter.id)"
                            >
                                <span class="text-label tnum text-neutral-400">{{ chapter.number }}</span>
                                <span class="text-meta" :class="activeId === chapter.id ? 'font-semibold text-neutral-900' : 'text-neutral-700'">{{ chapter.kicker }}</span>
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
