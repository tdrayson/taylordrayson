<script setup>
import { computed } from 'vue';
import { cn } from '../../lib/cn.js';
import Duration from '../Timeline/Duration.vue';

const props = defineProps({
    stats: { type: Array, default: () => [] },
    size: { type: String, default: 'md' },
    class: { type: [String, Array, Object], default: '' },
});

// Drop blank stats so callers can pass a sparse list without gaps.
const visible = computed(() =>
    props.stats.filter((stat) => stat.seconds != null || (stat.value !== null && stat.value !== undefined && stat.value !== '')),
);

const big = computed(() => props.size === 'lg');
</script>

<template>
    <div :class="cn('flex flex-wrap', big ? 'gap-x-14 gap-y-8' : 'gap-x-12 gap-y-6', props.class)">
        <div v-for="(stat, index) in visible" :key="index">
            <div class="font-display font-extrabold leading-none tracking-tight tnum" :class="big ? 'text-stat-lg' : 'text-stat'">
                <Duration v-if="stat.seconds != null" :seconds="stat.seconds" />
                <template v-else>
                    {{ stat.value }}<span v-if="stat.unit" class="ml-1 font-semibold text-ink-3" :class="big ? 'text-lg' : 'text-base'">{{ stat.unit }}</span>
                </template>
            </div>
            <div class="mt-1.5 text-label uppercase text-ink-3">{{ stat.label }}</div>
        </div>
    </div>
</template>
