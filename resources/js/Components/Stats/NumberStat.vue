<script setup>
import Duration from '../Timeline/Duration.vue';

defineProps({
    value: { type: [String, Number], default: null },
    unit: { type: String, default: '' },
    label: { type: String, required: true },
    big: { type: Boolean, default: false },
    seconds: { type: Number, default: null },
});
</script>

<template>
    <div>
        <div class="font-display font-extrabold leading-none tracking-tight tnum" :class="big ? 'text-stat-lg' : 'text-stat'">
            <Duration v-if="seconds !== null" :seconds="seconds" />
            <template v-else>{{ value }}<span v-if="unit" class="ml-1 font-semibold text-ink-3" :class="big ? 'text-lg' : 'text-base'">{{ unit }}</span></template>
        </div>
        <div class="mt-1.5 text-label uppercase text-ink-3">{{ label }}</div>
    </div>
</template>
