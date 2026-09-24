<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Tooltip from '../Ui/Tooltip.vue';
import { formatDate } from '../../lib/dateFormat.js';

const props = defineProps({
    label: { type: String, default: 'entries' },
    // Four Mon-Sun weeks of { date: 'yyyy-mm-dd', count }, oldest first; days after today have a null count.
    days: { type: Array, required: true },
});

const WEEKDAYS = ['M', 'T', 'W', 'T', 'F', 'S', 'S'];

// Empty + three accent steps.
const COLORS = ['bg-neutral-100', 'bg-accent-200', 'bg-accent-400', 'bg-accent-600'];
const level = (c) => (c === 0 ? 0 : c <= 2 ? 1 : c <= 4 ? 2 : 3);

const total = computed(() => props.days.reduce((sum, day) => sum + (day.count ?? 0), 0));

function fmtCount(c) {
    if (c === 0) {
        return 'No entries';
    }
    return c === 1 ? '1 entry' : `${c} entries`;
}

const cells = computed(() => props.days.map(({ date, count }) => {
    if (count === null) {
        return { key: date, future: true };
    }

    const [year, month, day] = date.split('-');
    const label = formatDate(date, { year: false });

    return {
        key: date,
        href: `/${year}/${month}/${day}`,
        color: COLORS[level(count)],
        title: `${label}, ${fmtCount(count)}`,
    };
}));
</script>

<template>
    <div class="@container aspect-square rounded-3xl bg-neutral-0 text-neutral-900 shadow-card">
        <div class="flex h-full flex-col p-3.25 @5xs:p-4 @4xs:p-5 @xs:p-6.75">
            <div class="flex items-baseline justify-between font-extrabold">
                <h2 class="text-2xs @5xs:text-xs @4xs:text-sm @xs:text-xl">Last 4 weeks</h2>
                <span class="text-2xs text-accent-500 @5xs:text-xs @4xs:text-base @xs:text-xl">{{ total }}</span>
            </div>

            <div class="mt-2.25 mb-2 grid min-h-0 flex-1 grid-cols-7 content-center gap-1 @5xs:mt-2.75 @5xs:mb-2.5 @5xs:gap-1.25 @4xs:mt-3.25 @4xs:mb-3 @4xs:gap-1.5 @xs:mt-4.5 @xs:mb-4 @xs:gap-2">
                <span v-for="(weekday, i) in WEEKDAYS" :key="`weekday-${i}`" class="text-center text-3xs leading-none font-semibold text-neutral-400 @4xs:text-2xs @xs:text-xs" aria-hidden="true">{{ weekday }}</span>
                <template v-for="cell in cells" :key="cell.key">
                    <span v-if="cell.future" class="flex aspect-square w-full rounded-xs inset-ring inset-ring-neutral-100 @5xs:rounded @xs:rounded-sm" />
                    <Tooltip v-else :label="cell.title" placement="top" class="aspect-square w-full">
                        <Link
                            :href="cell.href"
                            class="flex-1 rounded-xs transition-transform duration-120 hover:z-1 focus-visible:z-1 motion-safe:hover:scale-118 @5xs:rounded @xs:rounded-sm"
                            :class="cell.color"
                            :aria-label="cell.title"
                        />
                    </Tooltip>
                </template>
            </div>

            <div class="flex items-center justify-between">
                <span class="text-3xs font-semibold text-neutral-500 @5xs:text-2xs @4xs:text-xs @xs:text-base">{{ label }}</span>
                <span class="flex items-center gap-0.5 @5xs:gap-0.75 @xs:gap-1.25">
                    <i v-for="color in COLORS" :key="color" class="size-1.5 rounded-xs @5xs:size-1.75 @4xs:size-2.25 @xs:size-3 @xs:rounded" :class="color" />
                </span>
            </div>
        </div>
    </div>
</template>
