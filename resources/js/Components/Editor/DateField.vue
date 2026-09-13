<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import * as chrono from 'chrono-node';
import Input from '../Ui/Input.vue';
import Icon from '../Ui/Icon.vue';
import DatePicker from '../Overlays/DatePicker.vue';
import { CONTROL, CONTROL_BORDER } from '../../lib/editor/control.js';
import { clock } from '../../lib/format.js';
import { useDismissable } from '../../lib/editor/dismissable.js';
import { stampWallClock, toWallClockDate, wallClockParts } from '../../lib/editor/wallClock.js';

/**
 * One date control for the editor: a publish date carrying a time, or a bare
 * day for a dynamic tag's date range. The value is wall-clock text, never an
 * instant: parsing through Date and back would walk it by the browser's
 * offset. A publish date's zone is a field of its own, alongside this one.
 */
const props = defineProps({
    // 'YYYY-MM-DD HH:mm:ss' (or T-separated) when withTime, else 'YYYY-MM-DD'.
    modelValue: { type: String, default: '' },
    id: { type: String, default: null },
    withTime: { type: Boolean, default: false },
    // The value this one is measured from, when the field declares a
    // relativeTo: an event's end is nearly always a few hours after its start.
    // Only meaningful alongside withTime.
    relativeToValue: { type: String, default: null },
    relativeToLabel: { type: String, default: 'start' },
    // What the trigger, typed input and aria-labels call this field when it
    // has no time of its own to read as ("From", "To"). Unused when withTime,
    // since a time-carrying field is already labelled by the row it sits in.
    label: { type: String, default: null },
    // Resolves typed text (and the date-only preset chips) to a date. Falls
    // back to chrono-node, client-side, when omitted: the publish date must
    // keep working offline and instantly. The dynamic tag range passes the
    // server's own grammar instead, so a preset and free text always agree.
    resolveTyped: { type: Function, default: null },
});

const emit = defineEmits(['update:modelValue']);

const { isOpen: open, root, close, toggle } = useDismissable();
const typed = ref('');
const resolving = ref(false);

/** Read the stored wall clock literally rather than through Date. */
const parts = computed(() => (props.withTime
    ? wallClockParts(props.modelValue)
    : { date: props.modelValue || '', time: '' }));

const hasValue = computed(() => (props.withTime ? Boolean(parts.value.date) : Boolean(props.modelValue)));

// Ticks so an unset time field reads as the time it would actually be
// stamped with. A date-only field never shows one, so it never needs this.
const tick = ref(new Date());
let ticker = null;

onMounted(() => {
    if (props.withTime) {
        ticker = setInterval(() => {
            tick.value = new Date();
        }, 1000);
    }
});

onBeforeUnmount(() => clearInterval(ticker));

/** Now as wall-clock parts, ticking, so an unset field reads as what it would be stamped with. */
const nowParts = computed(() => ({
    date: stampWallClock(tick.value).slice(0, 10),
    time: stampWallClock(tick.value).slice(11, 16),
}));

// Frozen when the popover opens rather than read from the ticking clock, which
// would rewrite the time input from under a half-typed value.
const openedAt = ref(stampWallClock(new Date()));

watch(open, (isOpen) => {
    if (isOpen && props.withTime) {
        openedAt.value = stampWallClock(new Date());
    }
});

/**
 * What the calendar and time inputs show. An unset time field would otherwise
 * open on two blanks, when what it will actually save is now; the value
 * itself stays unset until one of them is touched. A date-only field has no
 * such stand-in: unset just opens on today.
 */
const shown = computed(() => {
    if (parts.value.date) {
        return parts.value;
    }

    return props.withTime ? { date: openedAt.value.slice(0, 10), time: openedAt.value.slice(11, 16) } : { date: '', time: '' };
});

/**
 * The site's timestamp shape, matching LocalTime's "D j M Y, g:ia", with the
 * year dropped when it is this one. The zone is its own field, so it is not
 * repeated here.
 */
function readable({ date, time }) {
    const day = new Date(`${date}T00:00:00`).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' });
    const year = date.slice(0, 4) === String(tick.value.getFullYear()) ? '' : ` ${date.slice(0, 4)}`;

    return `${day}${year}, ${clock(new Date(`${date}T${time}`))}`;
}

/** A bare date, read literally rather than through Date's own zone handling. */
function dateOnlyToDate(value) {
    const [year, month, day] = value.split('-').map(Number);

    return new Date(year, month - 1, day);
}

/** A bare date as "11 Sep 2026", or null when unset. */
const display = computed(() => (props.modelValue ? dateOnlyToDate(props.modelValue).toLocaleDateString('en-GB', {
    day: 'numeric', month: 'short', year: 'numeric',
}) : null));

// Unset reads as the stamp it would be given for a time field, or the field's
// own name for a bare one; the muted colour is what says nothing is chosen yet.
const triggerLabel = computed(() => (props.withTime
    ? readable(parts.value.date ? parts.value : nowParts.value)
    : (display.value ?? props.label)));

/** Quick date-times when a time is on offer, quick dates otherwise. */
const presets = computed(() => {
    if (! props.withTime) {
        return ['Today', 'Yesterday', 'Last week', 'Last month'].map((label) => ({ label }));
    }

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
    emit('update:modelValue', props.withTime ? stampWallClock(date) : stampWallClock(date).slice(0, 10));
    close();
    typed.value = '';
}

/** A preset chip: already a Date to pick directly, or a label to resolve like typed text. */
function choosePreset(preset) {
    if (preset.date) {
        choose(preset.date);

        return;
    }

    typed.value = preset.label;
    parseTyped();
}

/**
 * Offsets from the field this one is measured from. Reading the start as wall
 * clock rather than through Date keeps the arithmetic in the same frame the
 * value is stored in.
 */
const relativeOptions = computed(() => {
    const from = toWallClockDate(props.relativeToValue);

    if (from === null) {
        return [];
    }

    return [
        { label: `1 hour after ${props.relativeToLabel}`, minutes: 60 },
        { label: `2 hours after ${props.relativeToLabel}`, minutes: 120 },
        { label: `3 hours after ${props.relativeToLabel}`, minutes: 180 },
        { label: `Next day`, minutes: 60 * 24 },
    ].map((option) => ({ ...option, date: new Date(from.getTime() + option.minutes * 60000) }));
});

/**
 * "yesterday 9am", "3 aug 18:30", "last friday" through chrono, client-side;
 * or, when `resolveTyped` is given, the server's own grammar instead, so a
 * dynamic tag's preset chips and free text can never disagree with each other.
 */
async function parseTyped() {
    const value = typed.value.trim();

    if (value === '') {
        return;
    }

    if (props.resolveTyped) {
        resolving.value = true;
        const resolved = await props.resolveTyped(value);
        resolving.value = false;

        if (resolved) {
            choose(dateOnlyToDate(resolved));
        }

        return;
    }

    const parsed = chrono.parseDate(value, new Date(), { forwardDate: false });

    if (parsed) {
        choose(parsed);
    }
}

/** A day picked from the calendar. A time field keeps its current time; a bare one closes straight away. */
function onPickDay(value) {
    if (props.withTime) {
        emit('update:modelValue', `${value} ${shown.value.time}:00`);

        return;
    }

    emit('update:modelValue', value);
    close();
    typed.value = '';
}

function clear() {
    emit('update:modelValue', props.withTime ? null : '');
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
            :aria-label="withTime ? null : `${label} date`"
            :class="[
                CONTROL,
                'flex items-center gap-2 border-neutral-100 text-left hover:border-accent-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500',
                hasValue ? 'text-neutral-900' : 'text-neutral-500',
            ]"
            @click="toggle"
        >
            <Icon v-if="! withTime" name="Calendar03Icon" class="size-4 shrink-0" />
            <span class="flex-1 truncate">{{ triggerLabel }}</span>
        </button>

        <div
            v-if="open"
            class="absolute inset-x-0 z-30 mt-1 rounded-lg border border-neutral-100 bg-neutral-0 p-3 shadow-lg sm:right-auto"
            :class="withTime ? 'sm:w-80' : 'sm:w-72'"
        >
            <Input
                v-model="typed"
                :placeholder="withTime ? 'yesterday 9am, 3 Aug 18:30' : '1 March, last tuesday'"
                :aria-label="withTime ? null : `${label} date, typed`"
                :disabled="resolving"
                class="mb-2"
                @keydown.enter.prevent="parseTyped"
            />

            <ul v-if="relativeOptions.length" class="mb-2 border-b border-neutral-50 pb-2">
                <li v-for="option in relativeOptions" :key="option.label">
                    <button
                        type="button"
                        class="flex min-h-11 w-full items-center justify-between gap-4 rounded px-2 text-left text-meta text-neutral-900 transition-colors hover:bg-accent-50 hover:text-accent-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
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

            <ul class="mb-2 flex flex-wrap gap-1.5">
                <li v-for="preset in presets" :key="preset.label">
                    <button
                        type="button"
                        class="rounded-full bg-neutral-25 px-2.5 py-1 text-caption text-neutral-700 transition-colors hover:bg-accent-50 hover:text-accent-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                        @click="choosePreset(preset)"
                    >
                        {{ preset.label }}
                    </button>
                </li>
            </ul>

            <DatePicker :model-value="shown.date" mode="day" inline @update:model-value="onPickDay" />

            <label v-if="withTime" class="mt-2 block text-label uppercase text-neutral-500">
                Time
                <input
                    type="time"
                    :value="shown.time"
                    :class="[CONTROL, CONTROL_BORDER, 'mt-1 min-w-0 max-w-full appearance-none px-2 text-neutral-900']"
                    @input="setTimePart($event.target.value)"
                >
            </label>

            <div class="mt-2 flex gap-2">
                <button
                    v-if="hasValue"
                    type="button"
                    class="flex-1 rounded-md bg-neutral-25 py-2 text-caption text-neutral-700 transition-colors hover:bg-accent-50 hover:text-accent-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                    @click="clear"
                >
                    Clear
                </button>

                <button
                    v-if="withTime"
                    type="button"
                    class="flex-1 rounded-md bg-neutral-25 py-2 text-caption text-neutral-700 transition-colors hover:bg-accent-50 hover:text-accent-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                    @click="close"
                >
                    Done
                </button>
            </div>
        </div>
    </div>
</template>
