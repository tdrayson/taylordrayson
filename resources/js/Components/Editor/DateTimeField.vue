<script setup>
import { computed, ref } from 'vue';
import * as chrono from 'chrono-node';
import Input from '../Ui/Input.vue';

/**
 * A date and time, with the shortcuts you actually reach for and an explicit
 * timezone.
 *
 * The value is wall-clock text, never an instant: entries store the clock on
 * the wall where they happened, so parsing through Date and back would walk
 * the time by the browser's offset. chrono is already a dependency here, used
 * by the command palette, so "yesterday 9am" costs nothing extra.
 */
const props = defineProps({
    // 'YYYY-MM-DD HH:mm:ss' or the T-separated form.
    modelValue: { type: String, default: '' },
    id: { type: String, default: null },
    // The timezone stored alongside, if the type keeps one.
    timezone: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue', 'update:timezone']);

const open = ref(false);
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

const label = computed(() => {
    if (! parts.value.date) {
        return 'No date';
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
    open.value = false;
    typed.value = '';
}

/** "yesterday 9am", "3 aug 18:30", "last friday". */
function parseTyped() {
    const parsed = chrono.parseDate(typed.value, new Date(), { forwardDate: false });

    if (parsed) {
        choose(parsed);
    }
}

function setDatePart(value) {
    emit('update:modelValue', `${value} ${parts.value.time || '12:00'}:00`);
}

function setTimePart(value) {
    emit('update:modelValue', `${parts.value.date || stamp(new Date()).slice(0, 10)} ${value}:00`);
}
</script>

<template>
    <div class="relative">
        <button
            :id="id"
            type="button"
            class="w-full rounded-md border border-neutral-100 bg-neutral-0 px-3 py-2 text-left text-meta text-neutral-900 transition-colors hover:border-accent-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
            @click="open = ! open"
        >
            {{ label }}
        </button>

        <div
            v-if="open"
            class="absolute z-30 mt-1 w-80 rounded-lg border border-neutral-100 bg-neutral-0 p-3 shadow-lg"
        >
            <Input
                v-model="typed"
                placeholder="yesterday 9am, 3 Aug 18:30"
                class="mb-2"
                @keydown.enter.prevent="parseTyped"
            />

            <ul class="mb-2 border-b border-neutral-50 pb-2">
                <li v-for="shortcut in shortcuts" :key="shortcut.label">
                    <button
                        type="button"
                        class="flex w-full items-baseline justify-between gap-4 rounded px-2 py-1.5 text-left text-meta text-neutral-900 transition-colors hover:bg-accent-50 hover:text-accent-700"
                        @click="choose(shortcut.date)"
                    >
                        <span>{{ shortcut.label }}</span>
                        <span class="text-caption text-neutral-500">
                            {{ shortcut.date.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' }) }}
                        </span>
                    </button>
                </li>
            </ul>

            <div class="grid grid-cols-2 gap-2">
                <label class="text-label uppercase text-neutral-500">
                    Date
                    <input
                        type="date"
                        :value="parts.date"
                        class="mt-1 w-full rounded-md border border-neutral-100 px-2 py-1.5 text-meta text-neutral-900 focus:border-accent-500 focus:outline-none"
                        @input="setDatePart($event.target.value)"
                    >
                </label>

                <label class="text-label uppercase text-neutral-500">
                    Time
                    <input
                        type="time"
                        :value="parts.time"
                        class="mt-1 w-full rounded-md border border-neutral-100 px-2 py-1.5 text-meta text-neutral-900 focus:border-accent-500 focus:outline-none"
                        @input="setTimePart($event.target.value)"
                    >
                </label>
            </div>

            <label v-if="timezone !== null" class="mt-2 block text-label uppercase text-neutral-500">
                Timezone
                <input
                    type="text"
                    :value="timezone"
                    :placeholder="Intl.DateTimeFormat().resolvedOptions().timeZone"
                    class="mt-1 w-full rounded-md border border-neutral-100 px-2 py-1.5 text-meta text-neutral-900 focus:border-accent-500 focus:outline-none"
                    @input="emit('update:timezone', $event.target.value)"
                >
            </label>

            <button
                type="button"
                class="mt-2 w-full rounded-md bg-neutral-25 py-1.5 text-caption text-neutral-700 transition-colors hover:bg-accent-50 hover:text-accent-700"
                @click="open = false"
            >
                Done
            </button>
        </div>
    </div>
</template>
