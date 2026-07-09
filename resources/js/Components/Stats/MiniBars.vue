<script setup>
import { computed } from 'vue';
import Tooltip from '../Ui/Tooltip.vue';

const props = defineProps({
    // [{ value, label, tick? }] where label is the tooltip and tick is the
    // optional axis caption under the bar.
    items: { type: Array, default: () => [] },
    accent: { type: String, default: '#2ea06b' },
});

const max = computed(() => Math.max(1, ...props.items.map((item) => item.value)));

// Bar height as a share of the max, with a floor so any activity is visible.
function height(value) {
    return value ? `${Math.max(6, (value / max.value) * 100)}%` : '0%';
}
</script>

<template>
    <div>
        <div class="flex gap-1 border-b border-neutral-100">
            <Tooltip
                v-for="(item, index) in items"
                :key="index"
                class="flex-1"
                placement="top"
                :label="item.label"
            >
                <div class="flex h-28 w-full flex-col justify-end">
                    <div class="w-full rounded-t transition-opacity hover:opacity-80" :style="{ height: height(item.value), backgroundColor: accent }" />
                </div>
            </Tooltip>
        </div>
        <div class="mt-1.5 flex gap-1">
            <span v-for="(item, index) in items" :key="index" class="flex-1 text-center text-[10px] uppercase text-neutral-400">{{ item.tick ?? '' }}</span>
        </div>
    </div>
</template>
