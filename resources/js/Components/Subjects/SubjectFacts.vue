<script setup>
defineProps({
    // list<{label, value}>, from Subject.meta.
    facts: { type: Array, default: () => [] },
});
</script>

<template>
    <!-- Hairline rows rather than a grid of uppercase captions: one fact reads
         as a line of the page, not as a stray label floating on its own, and a
         dozen still line their values up down the right. -->
    <dl v-if="facts.length" class="divide-y divide-neutral-50 border-y border-neutral-50">
        <div v-for="fact in facts" :key="fact.label" class="flex items-baseline justify-between gap-6 py-2.5">
            <dt class="text-meta text-neutral-500">{{ fact.label }}</dt>
            <dd class="text-right text-meta font-medium text-neutral-900">{{ fact.value }}</dd>
        </div>
    </dl>
</template>
