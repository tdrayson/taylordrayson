<script setup>
defineProps({
    rows: { type: Array, default: () => [] },
});
</script>

<template>
    <dl class="divide-y divide-line-2 border-y border-line-2">
        <div
            v-for="row in rows.filter((item) => item.value !== null && item.value !== undefined && item.value !== '')"
            :key="row.label"
            class="flex items-baseline justify-between gap-6 py-3"
        >
            <dt class="text-label uppercase text-ink-3">{{ row.label }}</dt>
            <dd class="text-right text-meta text-ink tnum">{{ row.value }}</dd>
        </div>
    </dl>
</template>
