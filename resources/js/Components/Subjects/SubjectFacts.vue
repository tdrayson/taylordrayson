<script setup>
defineProps({
    // list<{label, value}>, from Subject.meta.
    facts: { type: Array, default: () => [] },
});
</script>

<template>
    <dl v-if="facts.length" class="grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-3">
        <div v-for="fact in facts" :key="fact.label">
            <dt class="text-label uppercase text-neutral-500">{{ fact.label }}</dt>
            <dd class="mt-1 text-meta font-medium text-neutral-900">{{ fact.value }}</dd>
        </div>
    </dl>
</template>
