<script setup>
defineProps({
    rows: { type: Array, default: () => [] },
});
</script>

<template>
    <dl class="divide-y divide-neutral-50 border-y border-neutral-50">
        <div
            v-for="row in rows.filter((item) => item.value !== null && item.value !== undefined && item.value !== '')"
            :key="row.label"
            class="flex items-baseline justify-between gap-6 py-3"
        >
            <dt class="text-label uppercase text-neutral-500">{{ row.label }}</dt>
            <dd class="text-right text-meta text-neutral-900 tnum">{{ row.value }}</dd>
        </div>
    </dl>
</template>
