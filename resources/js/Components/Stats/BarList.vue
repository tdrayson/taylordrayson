<script setup>
defineProps({
    bars: { type: Array, required: true },
});
</script>

<template>
    <div class="flex flex-col gap-3">
        <div v-for="(bar, index) in bars" :key="index" class="flex items-center gap-3">
            <span class="w-16 flex-none text-xs text-neutral-700">{{ bar.label }}</span>
            <div class="h-3 flex-1 overflow-hidden rounded bg-neutral-25">
                <div class="h-full rounded" :class="bar.accent ? 'bg-accent-500' : 'bg-neutral-900'" :style="{ width: bar.width + '%' }" />
            </div>
            <span class="w-12 flex-none text-right text-xs text-neutral-700 tnum">{{ bar.value }}</span>
        </div>
    </div>
</template>
