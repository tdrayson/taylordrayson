<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from './Icon.vue';
import { entryType } from '../entryTypes.js';

const sleepIcon = entryType('sleep').icon;
const foodIcon = entryType('calorie').icon;

const props = defineProps({
    year: { type: Number, required: true },
    month: { type: Number, required: true },
    days: { type: Object, default: () => ({}) },
});

const ICON_LIMIT = 5;
const weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const pad = (value) => String(value).padStart(2, '0');

const today = new Date();
const isCurrentMonth = computed(() => today.getFullYear() === props.year && today.getMonth() + 1 === props.month);

const cells = computed(() => {
    const first = new Date(props.year, props.month - 1, 1);
    const offset = (first.getDay() + 6) % 7;
    const daysInMonth = new Date(props.year, props.month, 0).getDate();
    const result = [];

    for (let i = 0; i < offset; i++) {
        result.push(null);
    }

    for (let day = 1; day <= daysInMonth; day++) {
        result.push(day);
    }

    return result;
});

function sleepHours(seconds) {
    return `${(seconds / 3600).toFixed(1)}h`;
}

function dayUrl(day) {
    return `/${props.year}/${pad(props.month)}/${pad(day)}`;
}
</script>

<template>
    <div class="mt-6 grid grid-cols-7 gap-2">
        <div v-for="weekday in weekdays" :key="weekday" class="px-1 pb-1 text-label uppercase text-ink-3">
            {{ weekday }}
        </div>

        <template v-for="(cell, index) in cells" :key="index">
            <Link
                v-if="cell"
                :href="dayUrl(cell)"
                class="flex min-h-24 flex-col rounded-md border-2 border-transparent bg-surface p-2 transition-colors hover:border-accent focus-visible:border-accent focus-visible:outline-none"
                :class="isCurrentMonth && cell === today.getDate() ? 'outline outline-2 outline-accent' : ''"
            >
                <span class="text-sm font-semibold" :class="isCurrentMonth && cell === today.getDate() ? 'text-accent' : 'text-ink'">{{ cell }}</span>
                <div v-if="days[cell]?.sleep || days[cell]?.calories" class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-ink-3 tnum">
                    <span v-if="days[cell]?.sleep" class="inline-flex items-center gap-1">
                        <Icon :icon="sleepIcon" class="size-3" />{{ sleepHours(days[cell].sleep) }}
                    </span>
                    <span v-if="days[cell]?.calories" class="inline-flex items-center gap-1">
                        <Icon :icon="foodIcon" class="size-3" />{{ days[cell].calories.toLocaleString() }}
                    </span>
                </div>

                <div v-if="days[cell]?.types?.length" class="mt-auto flex flex-wrap items-center gap-1 pt-1.5 text-ink-3">
                    <Icon
                        v-for="(type, i) in days[cell].types.slice(0, ICON_LIMIT)"
                        :key="i"
                        :icon="entryType(type).icon"
                        class="size-3.5"
                    />
                    <span v-if="days[cell].types.length > ICON_LIMIT" class="text-xs font-medium tnum">
                        +{{ days[cell].types.length - ICON_LIMIT }}
                    </span>
                </div>
            </Link>
            <div v-else />
        </template>
    </div>
</template>
