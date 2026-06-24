<script setup>
import { Airplane01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';

defineProps({
    origin: { type: Object, required: true }, // { iata, city }
    destination: { type: Object, required: true }, // { iata, city }
    departTime: { type: String, default: null },
    arriveTime: { type: String, default: null },
    duration: { type: String, default: null },
    note: { type: String, default: null },
    compact: { type: Boolean, default: false },
});
</script>

<template>
    <div class="flex items-center justify-between gap-4">
        <div class="shrink-0">
            <div v-if="departTime" class="font-display tnum" :class="compact ? 'text-section' : 'text-stat'">{{ departTime }}</div>
            <div class="font-display" :class="compact ? 'text-meta' : 'text-section'">
                <abbr v-if="origin.name" :title="origin.name" class="cursor-help no-underline">{{ origin.iata }}</abbr>
                <template v-else>{{ origin.iata }}</template>
            </div>
            <div v-if="!compact && origin.city" class="text-caption text-ink-3">{{ origin.city }}</div>
        </div>

        <div class="flex flex-1 flex-col items-center gap-1">
            <div v-if="duration" class="text-label uppercase text-ink-3 tnum">{{ duration }}</div>
            <div class="relative flex w-full items-center justify-center">
                <span class="absolute inset-x-0 top-1/2 h-px -translate-y-1/2 bg-line" />
                <span class="relative bg-canvas px-2 text-ink-3">
                    <Icon :icon="Airplane01Icon" class="size-4" />
                </span>
            </div>
            <div v-if="note" class="text-label uppercase text-ink-3 tnum">{{ note }}</div>
        </div>

        <div class="shrink-0 text-right">
            <div v-if="arriveTime" class="font-display tnum" :class="compact ? 'text-section' : 'text-stat'">{{ arriveTime }}</div>
            <div class="font-display" :class="compact ? 'text-meta' : 'text-section'">
                <abbr v-if="destination.name" :title="destination.name" class="cursor-help no-underline">{{ destination.iata }}</abbr>
                <template v-else>{{ destination.iata }}</template>
            </div>
            <div v-if="!compact && destination.city" class="text-caption text-ink-3">{{ destination.city }}</div>
        </div>
    </div>
</template>
