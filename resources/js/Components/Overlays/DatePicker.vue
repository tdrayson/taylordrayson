<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Calendar03Icon, ArrowLeft01Icon, ArrowRight01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';

const props = defineProps({
    modelValue: { type: String, default: null }, // day: YYYY-MM-DD, month: YYYY-MM, year: YYYY
    mode: { type: String, default: 'day' }, // day | month | year
    placeholder: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const root = ref(null);
const weekdays = ['M', 'T', 'W', 'T', 'F', 'S', 'S'];
const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const monthsShort = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

const pad = (value) => String(value).padStart(2, '0');

/** Parse the bound value into { year, month, day } parts for the active mode. */
const selected = computed(() => {
    if (!props.modelValue) {
        return null;
    }

    const [year, month, day] = props.modelValue.split('-').map(Number);

    return { year, month: month ? month - 1 : null, day: day ?? null };
});

/** The human-readable label shown in the trigger. */
const display = computed(() => {
    if (!selected.value) {
        return null;
    }

    if (props.mode === 'year') {
        return `${selected.value.year}`;
    }

    if (props.mode === 'month') {
        return `${monthNames[selected.value.month]} ${selected.value.year}`;
    }

    return `${selected.value.day} ${monthNames[selected.value.month]} ${selected.value.year}`;
});

const defaultPlaceholder = computed(
    () => props.placeholder ?? { day: 'Pick a date', month: 'Pick a month', year: 'Pick a year' }[props.mode]
);

// The year/month the grid is centred on.
const initialView = selected.value ?? currentParts();
const view = ref({ year: initialView.year, month: initialView.month ?? 0 });

function currentParts() {
    const now = new Date();

    return { year: now.getFullYear(), month: now.getMonth() };
}

const pickerView = ref(props.mode === 'day' ? 'days' : props.mode === 'month' ? 'months' : 'years');

const days = computed(() => {
    const first = new Date(view.value.year, view.value.month, 1);
    const offset = (first.getDay() + 6) % 7;
    const count = new Date(view.value.year, view.value.month + 1, 0).getDate();
    const cells = Array.from({ length: offset }, () => null);

    for (let day = 1; day <= count; day++) {
        cells.push(day);
    }

    return cells;
});

const yearRange = computed(() => {
    const start = view.value.year - (view.value.year % 12);

    return Array.from({ length: 12 }, (_, index) => start + index);
});

function stepMonth(delta) {
    const date = new Date(view.value.year, view.value.month + delta, 1);
    view.value = { year: date.getFullYear(), month: date.getMonth() };
}

const stepYear = (delta) => (view.value = { ...view.value, year: view.value.year + delta });
const stepDecade = (delta) => (view.value = { ...view.value, year: view.value.year + delta * 12 });

function pickDay(day) {
    emit('update:modelValue', `${view.value.year}-${pad(view.value.month + 1)}-${pad(day)}`);
    open.value = false;
}

function pickMonth(month) {
    if (props.mode === 'month') {
        emit('update:modelValue', `${view.value.year}-${pad(month + 1)}`);
        open.value = false;

        return;
    }

    view.value = { ...view.value, month };
    pickerView.value = 'days';
}

function pickYear(year) {
    if (props.mode === 'year') {
        emit('update:modelValue', `${year}`);
        open.value = false;

        return;
    }

    view.value = { ...view.value, year };
    pickerView.value = 'months';
}

const todayDate = new Date();
const today = { year: todayDate.getFullYear(), month: todayDate.getMonth(), day: todayDate.getDate() };
const isToday = (day) => today.year === view.value.year && today.month === view.value.month && today.day === day;

const isSelectedDay = (day) =>
    selected.value?.year === view.value.year && selected.value?.month === view.value.month && selected.value?.day === day;
const isSelectedMonth = (month) => props.mode === 'month' && selected.value?.year === view.value.year && selected.value?.month === month;
const isSelectedYear = (year) => props.mode === 'year' && selected.value?.year === year;

function toggle() {
    open.value = !open.value;

    if (open.value) {
        const parts = selected.value ?? currentParts();
        view.value = { year: parts.year, month: parts.month ?? 0 };
        pickerView.value = props.mode === 'day' ? 'days' : props.mode === 'month' ? 'months' : 'years';
    }
}

function onDocumentClick(event) {
    if (root.value && !root.value.contains(event.target)) {
        open.value = false;
    }
}

onMounted(() => document.addEventListener('click', onDocumentClick));
onUnmounted(() => document.removeEventListener('click', onDocumentClick));
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="flex w-full items-center gap-2 rounded-md border border-line bg-canvas px-3 py-2.5 text-left text-meta transition-colors hover:border-accent"
            :class="display ? 'text-ink' : 'text-ink-3'"
            @click="toggle"
        >
            <Icon :icon="Calendar03Icon" class="size-4 shrink-0 text-ink-3" />
            <span class="flex-1 truncate">{{ display ?? defaultPlaceholder }}</span>
        </button>

        <div v-if="open" class="absolute left-0 z-50 mt-2 w-64 rounded-lg border border-line-2 bg-canvas p-3 shadow-card">
            <!-- Days view -->
            <template v-if="pickerView === 'days'">
                <div class="flex items-center justify-between">
                    <button type="button" class="rounded p-1 text-ink-3 transition-colors hover:text-accent" @click="stepMonth(-1)">
                        <Icon :icon="ArrowLeft01Icon" class="size-4" />
                    </button>
                    <button type="button" class="rounded px-2 py-0.5 text-meta font-semibold text-ink transition-colors hover:text-accent" @click="pickerView = 'months'">
                        {{ monthNames[view.month] }} {{ view.year }}
                    </button>
                    <button type="button" class="rounded p-1 text-ink-3 transition-colors hover:text-accent" @click="stepMonth(1)">
                        <Icon :icon="ArrowRight01Icon" class="size-4" />
                    </button>
                </div>
                <div class="mt-3 grid grid-cols-7 gap-1 text-center">
                    <span v-for="(weekday, index) in weekdays" :key="index" class="text-label text-ink-3">{{ weekday }}</span>
                    <template v-for="(cell, index) in days" :key="index">
                        <button
                            v-if="cell"
                            type="button"
                            class="rounded border-2 border-transparent py-1 text-caption tnum transition-colors focus-visible:border-accent focus-visible:outline-none"
                            :class="isSelectedDay(cell) ? 'bg-accent text-canvas' : isToday(cell) ? 'font-semibold text-accent hover:border-accent' : 'text-ink-2 hover:border-accent'"
                            @click="pickDay(cell)"
                        >
                            {{ cell }}
                        </button>
                        <span v-else />
                    </template>
                </div>
            </template>

            <!-- Months view -->
            <template v-else-if="pickerView === 'months'">
                <div class="flex items-center justify-between">
                    <button type="button" class="rounded p-1 text-ink-3 transition-colors hover:text-accent" @click="stepYear(-1)">
                        <Icon :icon="ArrowLeft01Icon" class="size-4" />
                    </button>
                    <button type="button" class="rounded px-2 py-0.5 text-meta font-semibold text-ink transition-colors hover:text-accent" @click="pickerView = 'years'">
                        {{ view.year }}
                    </button>
                    <button type="button" class="rounded p-1 text-ink-3 transition-colors hover:text-accent" @click="stepYear(1)">
                        <Icon :icon="ArrowRight01Icon" class="size-4" />
                    </button>
                </div>
                <div class="mt-3 grid grid-cols-3 gap-1.5 text-center">
                    <button
                        v-for="(month, index) in monthsShort"
                        :key="index"
                        type="button"
                        class="rounded border-2 border-transparent py-2 text-caption transition-colors focus-visible:border-accent focus-visible:outline-none"
                        :class="isSelectedMonth(index) ? 'bg-accent text-canvas' : 'text-ink-2 hover:border-accent'"
                        @click="pickMonth(index)"
                    >
                        {{ month }}
                    </button>
                </div>
            </template>

            <!-- Years view -->
            <template v-else>
                <div class="flex items-center justify-between">
                    <button type="button" class="rounded p-1 text-ink-3 transition-colors hover:text-accent" @click="stepDecade(-1)">
                        <Icon :icon="ArrowLeft01Icon" class="size-4" />
                    </button>
                    <span class="text-meta font-semibold text-ink tnum">{{ yearRange[0] }} – {{ yearRange[11] }}</span>
                    <button type="button" class="rounded p-1 text-ink-3 transition-colors hover:text-accent" @click="stepDecade(1)">
                        <Icon :icon="ArrowRight01Icon" class="size-4" />
                    </button>
                </div>
                <div class="mt-3 grid grid-cols-3 gap-1.5 text-center">
                    <button
                        v-for="year in yearRange"
                        :key="year"
                        type="button"
                        class="rounded border-2 border-transparent py-2 text-caption tnum transition-colors focus-visible:border-accent focus-visible:outline-none"
                        :class="isSelectedYear(year) ? 'bg-accent text-canvas' : 'text-ink-2 hover:border-accent'"
                        @click="pickYear(year)"
                    >
                        {{ year }}
                    </button>
                </div>
            </template>
        </div>
    </div>
</template>
