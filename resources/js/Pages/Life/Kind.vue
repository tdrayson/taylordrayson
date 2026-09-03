<script setup>
import { computed, ref, watch } from 'vue';
import { setLayoutProps, Link } from '@inertiajs/vue3';
import AppHead from '../../Components/AppHead.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import SubjectImage from '../../Components/Subjects/SubjectImage.vue';
import { cn } from '../../lib/cn.js';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    kind: { type: String, required: true },
    kindLabel: { type: String, required: true },
    segment: { type: String, required: true },
    categories: { type: Array, default: () => [] },
    category: { type: String, default: null },
    hasCategories: { type: Boolean, default: false },
    subjects: { type: Array, default: () => [] },
    og: { type: Object, default: () => ({}) },
});

setLayoutProps({
    breadcrumb: [{ label: 'Life', href: '/life' }, { label: props.kind }],
});

// Tile size follows how much of the site a subject occupies, in three bands
// off the busiest one rather than off a fixed number, so a kind with only a
// handful of subjects still gets a mix rather than a wall of small squares.
//
// The jitter is seeded from the slug, so a subject keeps its size between
// visits: a grid that reshuffles on every load reads as broken, not playful.
//
// Spans start at 3 against a fine 12-column track, not at 1 against a coarse
// one: the smallest tile clears 180px and the steps between bands are a third
// larger each time rather than double and treble.
const SPANS = ['col-span-3 row-span-3', 'col-span-4 row-span-4', 'col-span-5 row-span-5'];

function seed(slug) {
    let hash = 0;

    for (let index = 0; index < slug.length; index++) {
        hash = (hash * 31 + slug.charCodeAt(index)) % 1000;
    }

    return hash / 1000;
}

// Sized off the whole kind, not the visible slice, so a tile keeps its size
// when a facet is applied and only its position moves.
const sized = computed(() => {
    const busiest = Math.max(1, ...props.subjects.map((subject) => subject.weight ?? 0));

    return props.subjects.map((subject) => {
        const share = (subject.weight ?? 0) / busiest;
        // Nudge by up to a third of a band either way, so the grid reads as a
        // collage rather than a ranking.
        const nudged = share + (seed(subject.slug) - 0.5) * 0.3;
        const band = nudged > 0.62 ? 2 : nudged > 0.28 ? 1 : 0;

        return { ...subject, span: SPANS[band] };
    });
});

const active = ref(props.category);

// A back or forward step lands on a new page object; follow it rather than
// leaving the chips out of step with the URL.
watch(() => props.category, (value) => { active.value = value; });

const visible = computed(() => (
    active.value === null ? sized.value : sized.value.filter((subject) => subject.categoryValue === active.value)
));

// The facet is a browser-side filter, so the URL is rewritten rather than
// visited: a reload keeps the state without the grid flashing through a
// server render it would have no chance to animate out of.
function select(value) {
    active.value = value;

    const url = value === null ? `/life/${props.segment}` : `/life/${props.segment}?category=${value}`;

    window.history.replaceState(window.history.state, '', url);
}

function facetClasses(isActive) {
    return cn(
        'rounded-full px-3 py-1.5 text-meta font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500',
        isActive ? 'bg-accent-500 text-neutral-0' : 'bg-neutral-25 text-neutral-700 hover:bg-accent-50 hover:text-accent-700',
    );
}
</script>

<template>
    <AppHead :og="og" />

    <header>
        <h1 class="font-display text-display">{{ kind }}</h1>
    </header>

    <div v-if="hasCategories" class="mt-6 flex flex-wrap gap-2">
        <button type="button" :class="facetClasses(active === null)" @click="select(null)">All</button>
        <button
            v-for="option in categories"
            :key="option.value"
            type="button"
            :class="facetClasses(active === option.value)"
            @click="select(option.value)"
        >
            {{ option.label }}
        </button>
    </div>

    <!-- Squares at three sizes, packed dense so the bigger tiles leave no
         holes. Uniform squares turned the page into a contact sheet; sizing
         them by how much of the site a subject occupies gives it a shape.
         TransitionGroup animates the survivors into their new places, which
         is why this is a grid and not CSS columns. -->
    <TransitionGroup
        v-if="sized.length"
        tag="div"
        name="tile"
        class="mt-10 grid auto-rows-fr grid-cols-6 gap-3 sm:grid-cols-9 lg:grid-cols-12"
        style="grid-auto-flow: dense"
    >
        <Link
            v-for="subject in visible"
            :key="subject.slug"
            :href="subject.url"
            class="group relative block aspect-square overflow-hidden rounded-lg border border-neutral-50 bg-neutral-25 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
            :class="subject.span"
        >
            <img
                v-if="subject.cover"
                :src="subject.cover.src"
                :srcset="subject.cover.srcset || undefined"
                sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 75vw"
                :alt="subject.name"
                loading="lazy"
                class="size-full object-cover"
            >
            <SubjectImage v-else :cover="null" :name="subject.name" :kind="kindLabel" class="size-full border-0 bg-transparent" />

            <!-- Fixed black, not the neutral ramp: an intentional dark surface in both themes. -->
            <span class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/75 to-transparent p-3 pt-8 opacity-0 transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100">
                <span class="block truncate text-meta font-medium text-white">{{ subject.name }}</span>
            </span>
        </Link>
    </TransitionGroup>

    <p v-else class="mt-10 text-meta text-neutral-500">Nothing here yet.</p>
</template>

<style scoped>
/* A filtered-out tile shrinks away rather than blinking out, and the ones
   that stay slide to their new places. Leaving tiles are taken out of flow so
   the survivors can start moving immediately. */
.tile-enter-active,
.tile-leave-active {
    transition: opacity 0.25s ease, transform 0.25s ease;
}

.tile-move {
    transition: transform 0.35s ease;
}

.tile-enter-from,
.tile-leave-to {
    opacity: 0;
    transform: scale(0.85);
}

.tile-leave-active {
    position: absolute;
}

@media (prefers-reduced-motion: reduce) {
    .tile-enter-active,
    .tile-leave-active,
    .tile-move {
        transition: none;
    }
}
</style>
