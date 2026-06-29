<script setup>
import { computed, onUnmounted, ref, watch } from 'vue';
import { Calendar03Icon, ArrowLeft01Icon, ArrowRight01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from './Icon.vue';
import { cn } from '../../lib/cn.js';

const props = defineProps({
    modelValue: { type: String, default: '' },
    withTime: { type: Boolean, default: false },
    invalid: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const root = ref(null);

const WEEKDAYS = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

/** Split the stored value into its date and time halves. */
const datePart = computed(() => (props.modelValue || '').split('T')[0] || '');
const timePart = computed(() => (props.modelValue || '').split('T')[1] || '00:00');

/** The month currently shown in the grid; defaults to the selected date or today. */
const cursor = ref(initialCursor());

function initialCursor() {
    const base = datePart.value ? parseYmd(datePart.value) : new Date();

    return { year: base.getFullYear(), month: base.getMonth() };
}

function parseYmd(value) {
    const [y, m, d] = value.split('-').map(Number);

    return new Date(y, m - 1, d);
}

function pad(value) {
    return String(value).padStart(2, '0');
}

function ymd(year, month, day) {
    return `${year}-${pad(month + 1)}-${pad(day)}`;
}

const grid = computed(() => {
    const first = new Date(cursor.value.year, cursor.value.month, 1);
    const offset = (first.getDay() + 6) % 7; // Monday-first
    const daysInMonth = new Date(cursor.value.year, cursor.value.month + 1, 0).getDate();
    const cells = [];

    for (let i = 0; i < offset; i += 1) {
        cells.push(null);
    }
    for (let day = 1; day <= daysInMonth; day += 1) {
        cells.push(day);
    }

    return cells;
});

const display = computed(() => {
    if (!datePart.value) {
        return '';
    }
    const d = parseYmd(datePart.value);
    const label = `${WEEKDAYS[(d.getDay() + 6) % 7]} ${d.getDate()} ${MONTHS[d.getMonth()].slice(0, 3)} ${d.getFullYear()}`;

    return props.withTime ? `${label}, ${timePart.value}` : label;
});

function shiftMonth(step) {
    const next = new Date(cursor.value.year, cursor.value.month + step, 1);
    cursor.value = { year: next.getFullYear(), month: next.getMonth() };
}

function selectDay(day) {
    const date = ymd(cursor.value.year, cursor.value.month, day);
    emit('update:modelValue', props.withTime ? `${date}T${timePart.value}` : date);
    if (!props.withTime) {
        open.value = false;
    }
}

function setTime(value) {
    const date = datePart.value || ymd(cursor.value.year, cursor.value.month, 1);
    emit('update:modelValue', `${date}T${value || '00:00'}`);
}

function isSelected(day) {
    return datePart.value === ymd(cursor.value.year, cursor.value.month, day);
}

function onClickOutside(event) {
    if (root.value && !root.value.contains(event.target)) {
        open.value = false;
    }
}

watch(open, (value) => {
    if (value) {
        cursor.value = initialCursor();
        window.addEventListener('mousedown', onClickOutside);
    } else {
        window.removeEventListener('mousedown', onClickOutside);
    }
});

// The watcher only detaches on an open -> closed transition; unmounting while
// the popover is open would otherwise leak the window listener.
onUnmounted(() => window.removeEventListener('mousedown', onClickOutside));

const triggerClasses = computed(() =>
    cn(
        'flex w-full items-center justify-between gap-2 rounded-md border bg-neutral-0 px-3 py-2 text-left text-meta transition-colors focus:outline-none',
        props.invalid ? 'border-red-500' : 'border-neutral-100 focus:border-accent-500',
    ),
);
</script>

<template>
    <div ref="root" class="relative">
        <button type="button" :class="triggerClasses" @click="open = !open">
            <span :class="display ? 'text-neutral-900' : 'text-neutral-500'">{{ display || 'Select date' }}</span>
            <Icon :icon="Calendar03Icon" class="size-4 shrink-0 text-neutral-500" />
        </button>

        <div
            v-if="open"
            class="absolute z-20 mt-1 w-72 rounded-md border border-neutral-100 bg-neutral-0 p-3 shadow-card"
        >
            <div class="mb-2 flex items-center justify-between">
                <button type="button" class="rounded-md p-1 hover:bg-neutral-25" @click="shiftMonth(-1)">
                    <Icon :icon="ArrowLeft01Icon" class="size-4 text-neutral-700" />
                </button>
                <p class="text-label font-medium text-neutral-900">{{ MONTHS[cursor.month] }} {{ cursor.year }}</p>
                <button type="button" class="rounded-md p-1 hover:bg-neutral-25" @click="shiftMonth(1)">
                    <Icon :icon="ArrowRight01Icon" class="size-4 text-neutral-700" />
                </button>
            </div>

            <div class="grid grid-cols-7 gap-1 text-center text-eyebrow uppercase text-neutral-500">
                <span v-for="day in WEEKDAYS" :key="day" class="py-1">{{ day }}</span>
            </div>

            <div class="grid grid-cols-7 gap-1">
                <template v-for="(cell, index) in grid" :key="index">
                    <span v-if="cell === null" />
                    <button
                        v-else
                        type="button"
                        class="rounded-md py-1.5 text-meta transition-colors"
                        :class="isSelected(cell) ? 'bg-accent-500 text-neutral-0' : 'text-neutral-900 hover:bg-neutral-25'"
                        @click="selectDay(cell)"
                    >
                        {{ cell }}
                    </button>
                </template>
            </div>

            <div v-if="withTime" class="mt-3 border-t border-neutral-50 pt-3">
                <input
                    type="time"
                    :value="timePart"
                    class="w-full rounded-md border border-neutral-100 px-2.5 py-1.5 text-meta text-neutral-900 focus:border-accent-500 focus:outline-none"
                    @input="setTime($event.target.value)"
                >
            </div>
        </div>
    </div>
</template>
