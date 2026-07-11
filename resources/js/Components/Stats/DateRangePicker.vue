<script setup>
import { ref, computed } from 'vue';
import Icon from '../Ui/Icon.vue';

const props = defineProps({
    // The range currently in effect, shown on the trigger.
    label: { type: String, required: true },
});

// Emits { from, to } as YYYY-MM-DD; the parent reloads the dashboard for that range.
const emit = defineEmits(['change']);

const open = ref(false);

const presets = ['Today', 'Yesterday', 'Last 7 days', 'Last 30 days', 'Last 90 days', 'This month', 'This year', 'All time'];

// --- calendar state ---------------------------------------------------------
const today = new Date();
const viewYear = ref(today.getFullYear());
const viewMonth = ref(today.getMonth());
const start = ref(null);
const end = ref(null);

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const MONTHS_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const WEEKDAYS = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

// Which picker is showing: day grid, month grid, or year grid. Clicking the
// header steps up (days -> months -> years) for quick jumps.
const view = ref('days');

const yearWindow = computed(() => {
    const start = Math.floor(viewYear.value / 12) * 12;

    return Array.from({ length: 12 }, (_, index) => start + index);
});

const headerLabel = computed(() => {
    if (view.value === 'days') {
        return `${MONTHS[viewMonth.value]} ${viewYear.value}`;
    }

    if (view.value === 'months') {
        return String(viewYear.value);
    }

    return `${yearWindow.value[0]} - ${yearWindow.value[11]}`;
});

function openHeader() {
    view.value = view.value === 'days' ? 'months' : view.value === 'months' ? 'years' : 'days';
}

// The prev/next arrows step by the unit of the current view.
function stepNav(delta) {
    if (view.value === 'days') {
        stepMonth(delta);
    } else if (view.value === 'months') {
        viewYear.value += delta;
    } else {
        viewYear.value += delta * 12;
    }
}

function pickMonth(index) {
    viewMonth.value = index;
    view.value = 'days';
}

function pickYear(year) {
    viewYear.value = year;
    view.value = 'months';
}

// The day cells for the visible month, padded with blanks so the 1st lands on
// its weekday column.
const cells = computed(() => {
    const firstDay = new Date(viewYear.value, viewMonth.value, 1).getDay();
    const daysInMonth = new Date(viewYear.value, viewMonth.value + 1, 0).getDate();
    const out = Array.from({ length: firstDay }, () => null);

    for (let day = 1; day <= daysInMonth; day++) {
        out.push(new Date(viewYear.value, viewMonth.value, day));
    }

    return out;
});

function stepMonth(delta) {
    const next = new Date(viewYear.value, viewMonth.value + delta, 1);
    viewYear.value = next.getFullYear();
    viewMonth.value = next.getMonth();
}

// Local YYYY-MM-DD (no UTC shift), the wire format the controller parses.
function iso(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function daysAgo(count) {
    const date = new Date(today);
    date.setDate(date.getDate() - count);

    return date;
}

// Resolve a preset to a [from, to] pair of Dates.
function presetRange(preset) {
    const t = today;

    switch (preset) {
        case 'Today': return [t, t];
        case 'Yesterday': return [daysAgo(1), daysAgo(1)];
        case 'Last 7 days': return [daysAgo(6), t];
        case 'Last 30 days': return [daysAgo(29), t];
        case 'Last 90 days': return [daysAgo(89), t];
        case 'This month': return [new Date(t.getFullYear(), t.getMonth(), 1), t];
        case 'This year': return [new Date(t.getFullYear(), 0, 1), t];
        default: return [new Date(2016, 0, 1), t]; // All time
    }
}

function isSame(a, b) {
    return a && b && a.toDateString() === b.toDateString();
}

function inRange(date) {
    return start.value && end.value && date >= start.value && date <= end.value;
}

// First click sets the start (clearing any end); second sets the end, swapping
// if it lands before the start.
function pick(date) {
    if (!date) {
        return;
    }

    if (!start.value || end.value) {
        start.value = date;
        end.value = null;

        return;
    }

    if (date < start.value) {
        end.value = start.value;
        start.value = date;
    } else {
        end.value = date;
    }
}

function applyCustom() {
    if (!start.value) {
        return;
    }

    emit('change', { from: iso(start.value), to: iso(end.value ?? start.value) });
    open.value = false;
}

function choosePreset(preset) {
    const [from, to] = presetRange(preset);
    emit('change', { from: iso(from), to: iso(to) });
    open.value = false;
}
</script>

<template>
    <div class="relative">
        <button
            type="button"
            class="inline-flex items-center gap-2 rounded-lg border border-neutral-50 px-3 py-2 text-nav font-medium text-neutral-900 transition-colors hover:border-neutral-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
            @click="open = !open"
        >
            <Icon name="Calendar03Icon" class="size-4 text-neutral-500" />
            {{ label }}
            <Icon name="ArrowDown01Icon" class="size-4 text-neutral-500" />
        </button>

        <template v-if="open">
            <!-- Click-away backdrop. -->
            <div class="fixed inset-0 z-30" @click="open = false" />

            <div class="absolute right-0 z-40 mt-2 flex overflow-hidden rounded-lg border border-neutral-50 bg-neutral-0 shadow-card">
                <ul class="w-40 shrink-0 border-r border-neutral-50 p-2 text-nav">
                    <li v-for="preset in presets" :key="preset">
                        <button type="button" class="w-full rounded-md px-3 py-1.5 text-left text-neutral-700 transition-colors hover:bg-neutral-25 hover:text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500" @click="choosePreset(preset)">
                            {{ preset }}
                        </button>
                    </li>
                </ul>

                <div class="w-64 p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <button type="button" class="flex size-7 items-center justify-center rounded-md text-neutral-500 hover:text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500" aria-label="Previous" @click="stepNav(-1)">
                            <Icon name="ArrowLeft01Icon" class="size-4" />
                        </button>
                        <!-- Click to jump up a level: month -> months, year -> years. -->
                        <button type="button" class="rounded-md px-2 py-1 text-nav font-medium text-neutral-900 transition-colors hover:bg-neutral-25 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500" @click="openHeader">
                            {{ headerLabel }}
                        </button>
                        <button type="button" class="flex size-7 items-center justify-center rounded-md text-neutral-500 hover:text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500" aria-label="Next" @click="stepNav(1)">
                            <Icon name="ArrowRight01Icon" class="size-4" />
                        </button>
                    </div>

                    <!-- Day grid. -->
                    <div v-if="view === 'days'" class="grid grid-cols-7 gap-0.5 text-center">
                        <span v-for="weekday in WEEKDAYS" :key="weekday" class="py-1 text-[10px] uppercase text-neutral-400">{{ weekday }}</span>
                        <template v-for="(date, index) in cells" :key="index">
                            <span v-if="!date" />
                            <button
                                v-else
                                type="button"
                                class="aspect-square rounded-md text-meta transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                                :class="isSame(date, start) || isSame(date, end) ? 'bg-neutral-900 text-neutral-0' : inRange(date) ? 'bg-neutral-25 text-neutral-900' : 'text-neutral-700 hover:bg-neutral-25'"
                                @click="pick(date)"
                            >{{ date.getDate() }}</button>
                        </template>
                    </div>

                    <!-- Month grid. -->
                    <div v-else-if="view === 'months'" class="grid grid-cols-3 gap-1">
                        <button
                            v-for="(name, index) in MONTHS_SHORT"
                            :key="name"
                            type="button"
                            class="rounded-md py-2 text-meta transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                            :class="index === viewMonth ? 'bg-neutral-900 text-neutral-0' : 'text-neutral-700 hover:bg-neutral-25'"
                            @click="pickMonth(index)"
                        >{{ name }}</button>
                    </div>

                    <!-- Year grid. -->
                    <div v-else class="grid grid-cols-3 gap-1">
                        <button
                            v-for="year in yearWindow"
                            :key="year"
                            type="button"
                            class="rounded-md py-2 text-meta transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                            :class="year === viewYear ? 'bg-neutral-900 text-neutral-0' : 'text-neutral-700 hover:bg-neutral-25'"
                            @click="pickYear(year)"
                        >{{ year }}</button>
                    </div>

                    <button type="button" class="mt-3 w-full rounded-md bg-neutral-900 py-1.5 text-nav font-medium text-neutral-0 transition-opacity hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 disabled:opacity-40" :disabled="!start" @click="applyCustom">
                        Apply
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>
