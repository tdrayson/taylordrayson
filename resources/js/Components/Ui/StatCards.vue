<script setup>
import { computed } from 'vue';
import { unitTitle } from '../../lib/units.js';

const props = defineProps({
    // Each: { value, label, unit?, tone? } where tone is 'default', 'accent',
    // 'fuel', 'food', 'flight' or 'activity'.
    stats: { type: Array, default: () => [] },
});

const TONES = {
    default: 'bg-neutral-25 text-neutral-900',
    accent: 'bg-accent-50 text-accent-700',
    fuel: 'bg-fuel/10 text-neutral-900',
    food: 'bg-food/10 text-neutral-900',
    flight: 'bg-flight/10 text-neutral-900',
    activity: 'bg-activity/10 text-neutral-900',
};

// Match the column count to the number of stats so there's never an empty cell.
const COLUMNS = {
    1: 'grid-cols-1',
    2: 'grid-cols-2',
    3: 'grid-cols-1 sm:grid-cols-3',
    4: 'grid-cols-2 sm:grid-cols-4',
};

/**
 * The grid-columns classes for the current number of stats.
 * @returns {string}
 */
const columns = computed(() => COLUMNS[props.stats.length] ?? 'grid-cols-2 sm:grid-cols-4');
</script>

<template>
    <dl class="my-7 grid gap-px overflow-hidden rounded-lg border border-neutral-50 bg-neutral-50" :class="columns">
        <!-- dt must precede its dd per the dl content model, so flex-col-reverse
             puts the value on top while the DOM order stays term-first. -->
        <div v-for="(stat, index) in stats" :key="index" class="flex flex-col-reverse px-4 py-4" :class="TONES[stat.tone] ?? TONES.default">
            <dt class="mt-1.5 text-label uppercase text-neutral-500">{{ stat.label }}</dt>
            <dd class="font-display text-stat leading-none tnum">{{ stat.value }}<abbr v-if="stat.unit" :title="unitTitle(stat.unit)" class="ml-1 text-base font-semibold text-neutral-500 no-underline">{{ stat.unit }}</abbr></dd>
        </div>
    </dl>
</template>
