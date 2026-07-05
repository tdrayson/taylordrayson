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
    <dl :class="cn('flex flex-wrap', big ? 'gap-x-14 gap-y-8' : 'gap-x-12 gap-y-6', props.class)">
        <!-- dt must precede its dd per the dl content model; flex-col-reverse
             keeps the big value visually on top with the label caption below,
             matching the original div order, while the DOM order stays term-first. -->
        <div v-for="(stat, index) in visible" :key="index" class="flex flex-col-reverse">
            <dt class="mt-1.5 text-label uppercase text-neutral-500">{{ stat.label }}</dt>
            <dd class="font-display font-extrabold leading-none tracking-tight tnum" :class="big ? 'text-stat-lg' : 'text-stat'">
                <Duration v-if="stat.seconds != null" :seconds="stat.seconds" />
                <template v-else>
                    {{ stat.value }}<span v-if="stat.unit" class="ml-1 font-semibold text-neutral-500" :class="big ? 'text-lg' : 'text-base'">{{ stat.unit }}</span>
                </template>
            </dd>
        </div>
    </dl>
</template>
