<script setup>
defineProps({
    bars: { type: Array, required: true },
});
</script>

<template>
    <div class="flex flex-col gap-3">
        <div v-for="(bar, index) in bars" :key="index" class="flex items-center gap-3">
            <span class="w-16 flex-none text-xs text-ink-2">{{ bar.label }}</span>
            <div class="h-3 flex-1 overflow-hidden rounded bg-surface">
                <div class="h-full rounded" :class="bar.accent ? 'bg-accent' : 'bg-ink'" :style="{ width: bar.width + '%' }" />
            </div>
            <span class="w-12 flex-none text-right text-xs text-ink-2 tnum">{{ bar.value }}</span>
        </div>
    </div>
</template>
