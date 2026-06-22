<script setup>
import { computed } from 'vue';
import NumberStat from './NumberStat.vue';

const props = defineProps({
    stats: { type: Array, default: () => [] },
});

const visible = computed(() =>
    props.stats.filter((stat) => stat.seconds != null || (stat.value !== null && stat.value !== undefined && stat.value !== '')),
);
</script>

<template>
    <div class="flex flex-wrap gap-x-10 gap-y-6">
        <NumberStat
            v-for="stat in visible"
            :key="stat.label"
            :value="stat.value"
            :unit="stat.unit || ''"
            :seconds="stat.seconds ?? null"
            :label="stat.label"
        />
    </div>
</template>
