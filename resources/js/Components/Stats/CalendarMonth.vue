<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '../Ui/Icon.vue';
import { entryType } from '../../entryTypes.js';

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

const DOT_LIMIT = 3;

// One dot per distinct type for the narrow layout, where a cell is around 40px
// wide and an icon is unreadable. Deduplicated because five identical dots say
// nothing that one doesn't; colour is the only channel left at this size.
function dayDots(day) {
    return [...new Set(props.days[day]?.types ?? [])].slice(0, DOT_LIMIT);
}

function dayUrl(day) {
    return `/${props.year}/${pad(props.month)}/${pad(day)}`;
}
</script>

<template>
    <div class="mt-6 grid grid-cols-7 gap-2">
        <div v-for="weekday in weekdays" :key="weekday" class="px-1 pb-1 text-label uppercase text-neutral-500">
            {{ weekday }}
        </div>

        <template v-for="(cell, index) in cells" :key="index">
            <Link
                v-if="cell"
                :href="dayUrl(cell)"
                class="flex min-h-14 flex-col items-center rounded-md border-2 border-transparent bg-neutral-25 p-1 transition-colors hover:border-accent-500 focus-visible:border-accent-500 focus-visible:outline-none sm:aspect-square sm:min-h-24 sm:items-stretch sm:p-2"
                :class="isCurrentMonth && cell === today.getDate() ? 'outline outline-2 outline-accent-500' : ''"
            >
                <span class="text-sm font-semibold" :class="isCurrentMonth && cell === today.getDate() ? 'text-accent-500' : 'text-neutral-900'">{{ cell }}</span>
                <!-- Sleep and food stack on their own lines so every cell reads consistently.
                     Both are dropped below sm, where the cell is too narrow to hold "2,302". -->
                <div v-if="days[cell]?.sleep || days[cell]?.calories" class="mt-0.5 hidden flex-col gap-0.5 text-xs text-neutral-500 tnum sm:flex">
                    <span v-if="days[cell]?.sleep" class="inline-flex items-center gap-1">
                        <Icon :icon="sleepIcon" class="size-3" />{{ sleepHours(days[cell].sleep) }}
                    </span>
                    <span v-if="days[cell]?.calories" class="inline-flex items-center gap-1">
                        <Icon :icon="foodIcon" class="size-3" />{{ days[cell].calories.toLocaleString() }}
                    </span>
                </div>

                <div v-if="days[cell]?.types?.length" class="mt-1 flex items-center gap-1 sm:hidden">
                    <span
                        v-for="type in dayDots(cell)"
                        :key="type"
                        class="size-1.5 rounded-full"
                        :style="{ background: `var(--color-${entryType(type).accent})` }"
                    />
                </div>

                <div v-if="days[cell]?.types?.length" class="mt-auto hidden flex-wrap items-center gap-1 pt-1.5 text-neutral-500 sm:flex">
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
