<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import * as chrono from 'chrono-node';
import Input from '../Ui/Input.vue';
import { clock } from '../../lib/format.js';
import { useDismissable } from '../../lib/editor/dismissable.js';

/**
 * A date and time with an explicit timezone. The value is wall-clock text, never
 * an instant: parsing through Date and back would walk it by the browser's offset.
 */
const props = defineProps({
    // 'YYYY-MM-DD HH:mm:ss' or the T-separated form.
    modelValue: { type: String, default: '' },
    id: { type: String, default: null },
    // The timezone stored alongside, if the type keeps one.
    timezone: { type: String, default: null },
    timezoneLabel: { type: String, default: 'Timezone' },
    // The value this one is measured from, when the field declares a
    // relativeTo: an event's end is nearly always a few hours after its start.
    relativeToValue: { type: String, default: null },
    relativeToLabel: { type: String, default: 'start' },
});

const emit = defineEmits(['update:modelValue', 'update:timezone']);

const { isOpen: open, root, close, toggle } = useDismissable();
const typed = ref('');

const pad = (n) => String(n).padStart(2, '0');
const stamp = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:00`;

/** Read the stored wall clock literally rather than through Date. */
const parts = computed(() => {
    const match = String(props.modelValue ?? '').match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/);

    return match
        ? { date: `${match[1]}-${match[2]}-${match[3]}`, time: `${match[4]}:${match[5]}` }
        : { date: '', time: '' };
});

// Ticks so an unset field reads as the time it would actually be stamped with.
const tick = ref(new Date());
let ticker = null;

onMounted(() => {
    ticker = setInterval(() => {
        tick.value = new Date();
    }, 1000);
});

onBeforeUnmount(() => clearInterval(ticker));

// Frozen when the popover opens rather than read from the ticking clock, which
// would rewrite the time input from under a half-typed value.
const openedAt = ref(stamp(new Date()));

watch(open, (isOpen) => {
    if (isOpen) {
        openedAt.value = stamp(new Date());
    }
});

/**
 * What the date and time inputs show. An unset field would otherwise open on two
 * blanks, when what it will actually save is now; the value itself stays unset
 * until one of them is touched.
 */
const shown = computed(() => (parts.value.date
    ? parts.value
    : { date: openedAt.value.slice(0, 10), time: openedAt.value.slice(11, 16) }));

const label = computed(() => {
    if (! parts.value.date) {
        return `Now - ${clock(tick.value)}`;
    }

    const [y, m, d] = parts.value.date.split('-');

    return `${new Date(`${parts.value.date}T00:00:00`).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' })} ${y === String(new Date().getFullYear()) ? '' : y} ${parts.value.time}`.replace(/\s+/g, ' ').trim();
});

/** The shortcuts from a real calendar, computed rather than hardcoded. */
const shortcuts = computed(() => {
    const now = new Date();
    const at = (days, hours = now.getHours(), minutes = now.getMinutes()) => {
        const date = new Date(now);
        date.setDate(date.getDate() + days);
        date.setHours(hours, minutes, 0, 0);

        return date;
    };

    const daysToSaturday = (6 - now.getDay() + 7) % 7 || 7;

    return [
        { label: 'Now', date: now },
        { label: 'Yesterday', date: at(-1) },
        { label: 'This morning', date: at(0, 9, 0) },
        { label: 'Last night', date: at(-1, 21, 0) },
        { label: 'This weekend', date: at(daysToSaturday, 10, 0) },
    ];
});

function choose(date) {
    emit('update:modelValue', stamp(date));
    close();
    typed.value = '';
}

/**
 * Offsets from the field this one is measured from. Reading the start as wall
 * clock rather than through Date keeps the arithmetic in the same frame the
 * value is stored in.
 */
const relativeOptions = computed(() => {
    const match = String(props.relativeToValue ?? '').match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/);

    if (! match) {
        return [];
    }

    const [, y, mo, d, h, mi] = match.map(Number);
    const from = new Date(y, mo - 1, d, h, mi);

    return [
        { label: `1 hour after ${props.relativeToLabel}`, minutes: 60 },
        { label: `2 hours after ${props.relativeToLabel}`, minutes: 120 },
        { label: `3 hours after ${props.relativeToLabel}`, minutes: 180 },
        { label: `Next day`, minutes: 60 * 24 },
    ].map((option) => ({ ...option, date: new Date(from.getTime() + option.minutes * 60000) }));
});

/** "yesterday 9am", "3 aug 18:30", "last friday". */
function parseTyped() {
    const parsed = chrono.parseDate(typed.value, new Date(), { forwardDate: false });

    if (parsed) {
        choose(parsed);
    }
}

function setDatePart(value) {
    emit('update:modelValue', `${value} ${shown.value.time}:00`);
}

function clear() {
    emit('update:modelValue', null);
    typed.value = '';
    close();
}

function setTimePart(value) {
    emit('update:modelValue', `${shown.value.date} ${value}:00`);
}
</script>

<template>
    <div ref="root" class="relative">
        <button
            :id="id"
            type="button"
            class="w-full rounded-md border border-neutral-100 bg-neutral-0 px-3 py-3 text-left text-meta transition-colors hover:border-accent-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
            :class="parts.date ? 'text-neutral-900' : 'text-neutral-500'"
            @click="toggle"
        >
            {{ label }}
        </button>

        <div
            v-if="open"
            class="absolute inset-x-0 z-30 mt-1 rounded-lg border border-neutral-100 bg-neutral-0 p-3 shadow-lg sm:right-auto sm:w-80"
        >
            <Input
                v-model="typed"
                placeholder="yesterday 9am, 3 Aug 18:30"
                class="mb-2"
                @keydown.enter.prevent="parseTyped"
            />

            <ul v-if="relativeOptions.length" class="mb-2 border-b border-neutral-50 pb-2">
                <li v-for="option in relativeOptions" :key="option.label">
                    <button
                        type="button"
                        class="flex min-h-11 w-full items-center justify-between gap-4 rounded px-2 text-left text-meta text-neutral-900 transition-colors hover:bg-accent-50 hover:text-accent-700"
                        @click="choose(option.date)"
                    >
                        <span>{{ option.label }}</span>
                        <span class="text-caption text-neutral-500">
                            {{ option.date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) }}
                            {{ String(option.date.getHours()).padStart(2, '0') }}:{{ String(option.date.getMinutes()).padStart(2, '0') }}
                        </span>
                    </button>
                </li>
            </ul>

            <ul class="mb-2 border-b border-neutral-50 pb-2">
                <li v-for="shortcut in shortcuts" :key="shortcut.label">
                    <button
                        type="button"
                        class="flex min-h-11 w-full items-center justify-between gap-4 rounded px-2 text-left text-meta text-neutral-900 transition-colors hover:bg-accent-50 hover:text-accent-700"
                        @click="choose(shortcut.date)"
                    >
                        <span>{{ shortcut.label }}</span>
                        <span class="text-caption text-neutral-500">
                            {{ shortcut.date.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' }) }}
                        </span>
                    </button>
                </li>
            </ul>

            <div class="grid grid-cols-1 gap-2 sm:grid-cols-5">
                <label class="min-w-0 text-label uppercase text-neutral-500 sm:col-span-3">
                    Date
                    <input
                        type="date"
                        :value="shown.date"
                        class="mt-1 w-full min-w-0 max-w-full appearance-none rounded-md border border-neutral-100 px-2 py-2 text-meta text-neutral-900 focus:border-accent-500 focus:outline-none"
                        @input="setDatePart($event.target.value)"
                    >
                </label>

                <label class="min-w-0 text-label uppercase text-neutral-500 sm:col-span-2">
                    Time
                    <input
                        type="time"
                        :value="shown.time"
                        class="mt-1 w-full min-w-0 max-w-full appearance-none rounded-md border border-neutral-100 px-2 py-2 text-meta text-neutral-900 focus:border-accent-500 focus:outline-none"
                        @input="setTimePart($event.target.value)"
                    >
                </label>
            </div>

            <label v-if="timezone !== null" class="mt-2 block text-label uppercase text-neutral-500">
                {{ timezoneLabel }}
                <input
                    type="text"
                    :value="timezone"
                    :placeholder="Intl.DateTimeFormat().resolvedOptions().timeZone"
                    class="mt-1 w-full min-w-0 max-w-full appearance-none rounded-md border border-neutral-100 px-2 py-2 text-meta text-neutral-900 focus:border-accent-500 focus:outline-none"
                    @input="emit('update:timezone', $event.target.value)"
                >
            </label>

            <div class="mt-2 flex gap-2">
                <button
                    v-if="parts.date"
                    type="button"
                    class="flex-1 rounded-md bg-neutral-25 py-2 text-caption text-neutral-700 transition-colors hover:bg-accent-50 hover:text-accent-700"
                    @click="clear"
                >
                    Clear
                </button>

                <button
                    type="button"
                    class="flex-1 rounded-md bg-neutral-25 py-2 text-caption text-neutral-700 transition-colors hover:bg-accent-50 hover:text-accent-700"
                    @click="close"
                >
                    Done
                </button>
            </div>
        </div>
    </div>
</template>
