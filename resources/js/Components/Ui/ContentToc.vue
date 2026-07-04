<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { Menu01Icon, Cancel01Icon, ArrowUp01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from './Icon.vue';

// Generalised from Story/StoryToc.vue: same scroll-spy/rail/sheet behaviour,
// but reads plain `{ id, label }` entries from any matching elements instead
// of story-specific chapter number/kicker data, so it can sit under any long
// document (currently articles; stories can adopt it later).
const props = defineProps({
    // Selector matching the headings to collect into the table of contents.
    selector: { type: String, default: '[data-toc]' },
    // Attribute each matched element carries its display label in.
    labelAttr: { type: String, default: 'data-toc-label' },
});

// Headings discovered in the document: [{ id, label }].
const items = ref([]);
// The id of the heading currently in view (drives the active highlight).
const activeId = ref(null);
// Whether the mobile contents sheet is open.
const open = ref(false);
// Whether the reader has scrolled far enough to reveal the mobile pill.
const scrolled = ref(false);
let observer = null;

// Lock background scrolling while the mobile contents sheet is open.
watch(open, (isOpen) => {
    document.body.style.overflow = isOpen ? 'hidden' : '';
});

/**
 * Read every heading matching `selector` from the rendered document and start
 * a scroll-spy that marks the one near the top of the viewport as active.
 *
 * @returns {void}
 */
function buildToc() {
    const headings = [...document.querySelectorAll(props.selector)];

    items.value = headings.map((el) => ({
        id: el.id,
        label: el.getAttribute(props.labelAttr) ?? '',
    }));

    observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    activeId.value = entry.target.id;
                }
            });
        },
        // Treat a heading as active once it reaches the upper third of the viewport.
        { rootMargin: '-15% 0px -70% 0px' },
    );

    headings.forEach((el) => observer.observe(el));
}

/**
 * Smooth-scroll to a heading and close the mobile sheet.
 *
 * @param {string} id The heading element id to scroll to.
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
 * Reveal the mobile pill only once the reader has scrolled some way down the
 * page, so it doesn't obscure the header on first load.
 *
 * @returns {void}
 */
function onScroll() {
    scrolled.value = window.scrollY > 400;
}

onMounted(() => {
    // The document's headings must already be rendered by the time this runs.
    // Vue flushes a component's mounted hook only after its whole subtree has
    // patched the DOM, and fires siblings in template order, so as long as
    // ContentToc is placed AFTER the rendered content in the host template
    // (see ArticleDetail.vue), querying the DOM here is safe: same ordering
    // StoryToc relies on by being the last chapter sibling.
    buildToc();
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
});

onBeforeUnmount(() => {
    observer?.disconnect();
    window.removeEventListener('scroll', onScroll);
    document.body.style.overflow = '';
});
</script>

<template>
    <!-- Desktop: a clean line-and-text list. It is placed in the gutter by the
         host's relative-positioned wrapper and sticks via CSS (no JS measuring),
         so its top starts level with the first heading. -->
    <nav
        v-if="items.length"
        class="hidden xl:absolute xl:left-full xl:top-0 xl:block xl:h-full xl:pl-10"
        aria-label="Table of contents"
    >
        <ul class="sticky top-24 flex w-48 flex-col border-l border-neutral-100">
            <li v-for="item in items" :key="item.id">
                <button
                    type="button"
                    class="-ml-px block w-full border-l-2 py-1.5 pl-4 text-left text-caption transition-colors focus-visible:text-neutral-900 focus-visible:outline-none"
                    :class="activeId === item.id ? 'border-neutral-900 font-medium text-neutral-900' : 'border-transparent text-neutral-400 hover:text-neutral-700'"
                    @click="goTo(item.id)"
                >{{ item.label }}</button>
            </li>
        </ul>
    </nav>

    <!-- Smaller screens: a floating glass pill (top + contents). It slides up
         into view once the reader has scrolled a way down, and back down at the top. -->
    <div class="fixed bottom-5 left-1/2 z-30 -translate-x-1/2 xl:hidden">
        <Transition name="pill">
            <div
                v-if="items.length && scrolled"
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
            <div v-if="open" class="fixed inset-0 z-50 flex flex-col justify-end">
                <div class="absolute inset-0 bg-neutral-900/50" @click="open = false" />
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
                                class="flex w-full items-center rounded-lg px-3 py-2.5 text-left transition-colors"
                                :class="activeId === item.id ? 'bg-accent-50' : 'hover:bg-neutral-25'"
                                @click="goTo(item.id)"
                            >
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
