<script setup>
import { computed } from 'vue';
import Icon from '../Ui/Icon.vue';

const props = defineProps({
    // A dateline shown under the lead; a string is one line, an array is one
    // line per item (e.g. covered range, then last updated).
    meta: { type: [String, Array], default: null },
    // [{ value, label }] shown as a divided strip beneath the hero.
    kpis: { type: Array, default: () => [] },
    // Optional data-type icon, shown oversized and faint as background texture.
    icon: { type: [Array, Object], default: null },
    // Data-type accent (CSS colour) for the KPI figures; defaults to fuel amber.
    accent: { type: String, default: 'var(--color-fuel)' },
});

/**
 * The dateline normalised to an array of lines.
 * @returns {string[]}
 */
const metaLines = computed(() => {
    if (!props.meta) {
        return [];
    }

    return Array.isArray(props.meta) ? props.meta : [props.meta];
});
</script>

<template>
    <!-- On mobile the hero bleeds flush to the top bar (cancels the page's pt-8);
         on desktop it keeps the gap and sits inset with rounded corners. -->
    <header data-story-hero class="relative -mt-8 full-width overflow-hidden bg-neutral-900 text-neutral-0 md:mt-0 md:px-8 md:full-width-inset md:rounded-xl">
        <Icon v-if="icon" :icon="icon" :stroke-width="1.2" class="pointer-events-none absolute -right-10 top-1/2 size-96 -translate-y-1/2 -rotate-12 scale-150 text-neutral-800/40 md:text-neutral-800/80" />
        <div class="relative mx-auto max-w-4xl px-5 py-16 sm:py-20 md:px-0">
            <h1 class="mt-5 max-w-2xl font-display text-display-xl text-neutral-0">
                <slot name="title" />
            </h1>
            <p class="mt-5 max-w-xl text-body text-neutral-300">
                <slot name="lead" />
            </p>
            <div v-if="metaLines.length" class="mt-6 space-y-0.5 text-meta text-neutral-400">
                <p v-for="(line, index) in metaLines" :key="index">{{ line }}</p>
            </div>
        </div>

        <div v-if="kpis.length" class="relative border-t border-neutral-800">
            <dl class="mx-auto grid max-w-4xl grid-cols-2 gap-px bg-neutral-800 lg:grid-cols-4">
                <div v-for="(kpi, index) in kpis" :key="index" class="flex flex-col-reverse bg-neutral-900 px-5 py-5 sm:px-10 lg:first:pl-0 lg:last:pr-0">
                    <dt class="mt-1 text-label uppercase text-neutral-400">{{ kpi.label }}</dt>
                    <dd class="font-display text-stat tnum" :style="{ color: accent }">{{ kpi.value }}</dd>
                </div>
            </dl>
        </div>
    </header>
</template>
