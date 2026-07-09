<script setup>
import { computed } from 'vue';

const props = defineProps({
    // [{ label, value }], already sorted descending.
    items: { type: Array, default: () => [] },
    accent: { type: String, default: 'currentColor' },
});

const max = computed(() => Math.max(1, ...props.items.map((item) => item.value)));
const total = computed(() => props.items.reduce((sum, item) => sum + item.value, 0));

// Bar width as a share of the largest type, so the leader fills the track.
function width(value) {
    return `${Math.max(2, (value / max.value) * 100)}%`;
}

function share(value) {
    return total.value ? Math.round((value / total.value) * 100) : 0;
}
</script>

<template>
    <ul class="flex flex-col gap-3">
        <li v-for="item in items" :key="item.label" class="grid grid-cols-[7rem_1fr_auto] items-center gap-3">
            <span class="truncate text-meta text-neutral-700">{{ item.label }}</span>
            <span class="h-2.5 overflow-hidden rounded-full bg-neutral-25">
                <span class="block h-full rounded-full" :style="{ width: width(item.value), backgroundColor: accent }" />
            </span>
            <span class="text-meta text-neutral-500 tnum">{{ item.value }} <span class="text-neutral-400">({{ share(item.value) }}%)</span></span>
        </li>
    </ul>
</template>
