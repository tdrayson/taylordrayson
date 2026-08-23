<script setup>
import { computed } from 'vue';

const props = defineProps({
    rows: { type: Array, default: () => [] },
    // 'plain' drops the rules, for a list whose group is already marked out by
    // a surface of its own.
    variant: { type: String, default: 'default' },
});

// Rows with nothing to show are dropped, so a caller can pass a sparse list.
// The whole list goes with them: an empty dl still draws its two borders, which
// lands as a stray 2px rule.
const filled = computed(() => props.rows.filter(
    (row) => row.value !== null && row.value !== undefined && row.value !== '',
));
</script>

<template>
    <dl
        v-if="filled.length"
        class="max-w-lg"
        :class="variant === 'plain' ? 'space-y-2' : 'divide-y divide-neutral-50 border-y border-neutral-50'"
    >
        <div
            v-for="row in filled"
            :key="row.label"
            class="flex items-baseline justify-between"
            :class="variant === 'plain' ? 'gap-3' : 'gap-6 py-3'"
        >
            <dt class="text-label uppercase text-neutral-500" :class="[{ 'pl-4 text-neutral-400': row.sub }, variant === 'plain' ? 'whitespace-nowrap' : '']">{{ row.label }}</dt>
            <dd class="text-right text-neutral-900 tnum" :class="variant === 'plain' ? 'text-caption' : 'text-meta'">{{ row.value }}</dd>
        </div>
    </dl>
</template>
