<script setup>
import NumberStat from './NumberStat.vue';

defineProps({
    stats: { type: Array, required: true },
    big: { type: Boolean, default: false },
});
</script>

<template>
    <div class="flex flex-wrap" :class="big ? 'gap-x-14 gap-y-8' : 'gap-x-12 gap-y-6'">
        <NumberStat
            v-for="(stat, index) in stats"
            :key="index"
            :value="stat.value"
            :unit="stat.unit"
            :seconds="stat.seconds ?? null"
            :label="stat.label"
            :big="big"
        />
    </div>
</template>
