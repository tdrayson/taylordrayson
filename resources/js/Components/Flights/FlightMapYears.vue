<script setup>
import { computed } from 'vue';
import Pill from '../Ui/Pill.vue';

/**
 * Year filter for the flight globe map: a button group of Pills, since
 * Pill.vue itself is never interactive.
 * @param {Array<number>} years Years present in the data, most recent first.
 * @param {number|null} modelValue Selected year, or null for all time.
 */
const props = defineProps({
    years: { type: Array, default: () => [] },
    modelValue: { type: Number, default: null },
});

const emit = defineEmits(['update:modelValue']);

// "All time" always leads so there is a way back to the unfiltered view.
const options = computed(() => [
    { label: 'All time', value: null },
    ...props.years.map((year) => ({ label: String(year), value: year })),
]);
</script>

<template>
    <!-- One line that scrolls, not a wrapping block: two decades of flying would
         otherwise push the stats and the list off a laptop screen. The negative
         margin lets a scrolled chip be cut by the panel edge rather than floating
         inside a gap, while the matching padding keeps the first chip aligned with
         everything else in the panel. No scroll-snap: it lands on the first chip
         and scrolls that padding straight back off. -->
    <div
        role="group"
        aria-label="Filter flights by year"
        class="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4"
    >
        <button
            v-for="option in options"
            :key="option.value ?? 'all'"
            type="button"
            class="group shrink-0 rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
            :aria-pressed="modelValue === option.value"
            @click="emit('update:modelValue', option.value)"
        >
            <!-- White ground, not the Pill default: that tint is the header
                 band's own colour, so an unselected chip would vanish into it. -->
            <Pill
                :label="option.label"
                :variant="modelValue === option.value ? 'accent' : 'default'"
                :class="modelValue === option.value ? '' : 'bg-neutral-0 transition-colors group-hover:bg-neutral-50 group-focus-visible:bg-neutral-50'"
            />
        </button>
    </div>
</template>

<style scoped>
/* The chips themselves show there is more to the right; a bar over them in a
   panel this small costs more than it tells you. */
.no-scrollbar {
    scrollbar-width: none;
}

.no-scrollbar::-webkit-scrollbar {
    display: none;
}
</style>
