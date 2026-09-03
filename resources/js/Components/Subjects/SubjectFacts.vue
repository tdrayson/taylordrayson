<script setup>
import { computed } from 'vue';
import { useFormat } from '../../composables/useFormat';

const props = defineProps({
    // list<{label, value}>, the subject's own typed-in facts.
    facts: { type: Array, default: () => [] },
    // list<{label, value?, distanceM?}>, derived from the feed. A row carrying
    // raw metres resolves through the visitor's mi/km setting here.
    rows: { type: Array, default: () => [] },
});

const { distanceParts } = useFormat();

const all = computed(() => [...props.facts, ...props.rows].map((row) => {
    if (row.distanceM === null || row.distanceM === undefined) {
        return row;
    }

    const parts = distanceParts(row.distanceM, 0);

    return { label: row.label, value: `${parts.value} ${parts.unit}` };
}));
</script>

<template>
    <!-- Hairline rows rather than a grid of uppercase captions: one fact reads
         as a line of the page, not as a stray label floating on its own, and a
         dozen still line their values up down the right. The counts sit here
         too, under the typed-in facts, rather than as a sentence alongside. -->
    <dl v-if="all.length" class="divide-y divide-neutral-50 border-y border-neutral-50">
        <div v-for="row in all" :key="row.label" class="flex items-baseline justify-between gap-6 py-2.5">
            <dt class="text-meta text-neutral-500">{{ row.label }}</dt>
            <dd class="text-right text-meta font-medium text-neutral-900 tnum">{{ row.value }}</dd>
        </div>
    </dl>
</template>
