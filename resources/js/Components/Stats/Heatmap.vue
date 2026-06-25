<script setup>
const cells = Array.from({ length: 364 }, (_, i) => {
    const raw = (Math.sin((i + 1) * 43.13) * 4313.13) % 1;
    const value = raw < 0 ? raw + 1 : raw;

    return value < 0.18 ? 0 : value < 0.42 ? 1 : value < 0.68 ? 2 : value < 0.88 ? 3 : 4;
});

const ramp = ['var(--color-neutral-25)', 'var(--color-heat-1)', 'var(--color-heat-2)', 'var(--color-heat-3)', 'var(--color-heat-4)'];

function color(level) {
    return ramp[level];
}
</script>

<style scoped>
/* 364 cells flow column-major into 7 weekday rows → 52 week columns.
   1fr columns stretch the grid to the container width; aspect-ratio keeps cells square. */
.heatmap-grid {
    display: grid;
    grid-auto-flow: column;
    grid-template-rows: repeat(7, 1fr);
    grid-auto-columns: 1fr;
    gap: 2px;
}

.heatmap-grid > span {
    aspect-ratio: 1;
}
</style>

<template>
    <div>
        <div class="heatmap-grid">
            <span v-for="(level, index) in cells" :key="index" class="rounded" :style="{ background: color(level) }" />
        </div>
        <div class="mt-3 flex items-center gap-1.5 text-xs text-neutral-500">
            Quieter
            <span v-for="(swatch, index) in ramp" :key="index" class="inline-block size-3 rounded" :style="{ background: swatch }" />
            Busier
        </div>
    </div>
</template>
