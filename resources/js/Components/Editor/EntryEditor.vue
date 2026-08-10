<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { clock } from '../../lib/format.js';
import { slugify } from '../../lib/editor/defaults.js';
import { useDismissable } from '../../lib/editor/dismissable.js';
import Button from '../Ui/Button.vue';
import FieldInput from './FieldInput.vue';

/**
 * The editing surface for any type: one column, mobile first, nothing floating.
 *
 * Shaped after Quill and HQ's add-task sheet. A title, a body, then a quiet row
 * of chips for the rest, each opening in place. Nothing sits in a panel beside
 * the content, because on a phone there is no beside.
 */
const props = defineProps({
    fields: { type: Array, required: true },
    values: { type: Object, required: true },
    action: { type: String, required: true },
    method: { type: String, default: 'patch' },
    resolved: { type: Object, default: () => ({}) },
    submitLabel: { type: String, default: 'Post' },
    heading: { type: String, default: null },
});

const form = useForm({ ...props.values });

const titleField = computed(() => props.fields.find((field) => field.isTitle) ?? null);
const bodyField = computed(() => props.fields.find((field) => field.isBody) ?? null);

// Publish state is the save action, not a field: it lives in the footer beside
// the button so the button can say what it will actually do.
const publishField = computed(() => props.fields.find((field) => field.isPublished) ?? null);
const isPublished = computed(() => publishField.value !== null && form[publishField.value.name] === true);

/**
 * Whether a field stacks rather than collapsing to a chip. Chips suit short,
 * usually-empty values; rich text and location need room, and a required field
 * behind a chip is a trap.
 */
function stacks(field) {
    return field.isTitle
        || field.isBody
        || field.type === 'rich-text'
        || field.type === 'location'
        || field.required;
}

const offered = computed(() => props.fields.filter((field) => !field.hidden && !field.isPublished));
const stacked = computed(() => offered.value.filter((field) => stacks(field) && !field.isTitle && !field.isBody));

/**
 * Chips, with grouped fields collapsed into one item: an address is a single
 * thing to fill in, not five separate offers.
 */
const chips = computed(() => {
    const items = [];
    const groups = new Map();

    offered.value.filter((field) => !stacks(field)).forEach((field) => {
        if (!field.group) {
            items.push({ key: field.name, label: field.label, fields: [field], primary: field.primary });

            return;
        }

        const existing = groups.get(field.group);

        if (existing) {
            existing.fields.push(field);
            existing.primary = existing.primary || field.primary;

            return;
        }

        const item = { key: field.group, label: field.group, fields: [field], primary: field.primary };
        groups.set(field.group, item);
        items.push(item);
    });

    return items;
});

const primaryChips = computed(() => chips.value.filter((item) => item.primary));
const extraChips = computed(() => chips.value.filter((item) => !item.primary));

function filled(name) {
    const value = props.values[name];

    return Array.isArray(value)
        ? value.length > 0
        : value !== null && value !== undefined && value !== '' && value !== false;
}

/** Which chips are open, and which extras have been added to the row. */
const expanded = ref([]);
const added = ref(extraChips.value.filter((item) => item.fields.some((field) => filled(field.name))).map((item) => item.key));
const { isOpen: showExtras, root: extrasRoot, close: closeExtras, toggle: toggleExtras } = useDismissable();

// Declaration order, not primary-then-added: the fields class is where the
// order is decided, and a chip should not jump position because it was added
// from the + menu rather than shown by default.
const visibleChips = computed(() => chips.value.filter(
    (item) => item.primary || added.value.includes(item.key),
));

const remainingExtras = computed(() => extraChips.value.filter((item) => !added.value.includes(item.key)));

function toggle(key) {
    expanded.value = expanded.value.includes(key)
        ? expanded.value.filter((item) => item !== key)
        : [...expanded.value, key];
}

function add(item) {
    added.value.push(item.key);
    expanded.value.push(item.key);
    closeExtras();
}

const tick = ref(new Date());
let ticker = null;

onMounted(() => {
    ticker = setInterval(() => {
        tick.value = new Date();
    }, 1000);
});

onBeforeUnmount(() => clearInterval(ticker));

/** The value on the chip itself, so a field that is set reads at a glance. */
function fieldSummary(field) {
    const value = form[field.name];

    if (value === null || value === undefined || value === '' || value === false) {
        return field.defaultsToNow ? `Now - ${clock(tick.value)}` : null;
    }

    if (value === true) {
        return 'Yes';
    }

    if (Array.isArray(value)) {
        return value.length ? value.join(', ') : null;
    }

    if (field.type === 'select') {
        return field.options?.find((option) => option.value === value)?.label ?? String(value);
    }

    // A chip is a glance, so a stored timestamp reads as a date rather than as
    // the database value it happens to be.
    if (field.type === 'datetime') {
        const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/);

        if (match) {
            const [, y, mo, d, h, mi] = match;
            const date = new Date(Number(y), Number(mo) - 1, Number(d));

            return `${date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })} ${h}:${mi}`;
        }
    }

    if (field.type === 'duration') {
        const total = Number(value);
        const hours = Math.floor(total / 3600);
        const minutes = Math.round((total % 3600) / 60);

        return [hours ? `${hours}h` : null, minutes ? `${minutes}m` : null].filter(Boolean).join(' ');
    }

    if (field.type === 'distance') {
        return `${Math.round((Number(value) / 1609.344) * 10) / 10} mi`;
    }

    return String(value).slice(0, 24);
}

function summary(item) {
    const parts = item.fields.map((field) => fieldSummary(field)).filter(Boolean);

    return parts.length ? parts.join(', ').slice(0, 40) : null;
}

/**
 * The slug follows the title until it is edited by hand, and only while the
 * entry is unpublished: once something is public its URL is a promise, and
 * retitling it must not quietly move the page.
 */
const slugField = computed(() => props.fields.find((field) => field.type === 'slug') ?? null);
const slugEdited = ref(Boolean(props.values[slugField.value?.name]));

watch(() => (titleField.value ? form[titleField.value.name] : null), (title) => {
    if (! slugField.value || slugEdited.value || isPublished.value) {
        return;
    }

    form[slugField.value.name] = slugify(title);
});

function onFieldInput(field, value) {
    if (slugField.value && field.name === slugField.value.name) {
        slugEdited.value = true;
    }

    form[field.name] = value;
}

/**
 * Apply the sibling values a lookup resolved, and surface the ones that were
 * hidden. A pick that quietly fills City and Country behind a + menu looks
 * like it did nothing.
 */
function applyFill(values) {
    Object.entries(values).forEach(([key, value]) => {
        if (! (key in form)) {
            return;
        }

        form[key] = value;

        const item = chips.value.find((candidate) => candidate.fields.some((field) => field.name === key));

        if (item && ! item.primary && ! added.value.includes(item.key)) {
            added.value.push(item.key);
        }
    });
}

const status = computed(() => {
    if (form.processing) {
        return publishField.value ? 'Saving...' : 'Posting...';
    }

    if (form.isDirty) {
        return 'Unsaved changes';
    }

    if (publishField.value) {
        return isPublished.value ? 'Published, live to everyone' : 'Draft, only you can see this';
    }

    return props.method === 'post' ? 'Not posted yet' : 'Posted';
});

/** Save, optionally flipping publish state in the same request. */
function submit(published = null) {
    if (published !== null && publishField.value) {
        form[publishField.value.name] = published;
    }

    form[props.method](props.action, { preserveScroll: true });
}
</script>

<template>
    <!-- Left-aligned in the content column, not centred inside it: every other
         page on the site starts at the same left edge, and centring made the
         editor jump 112px right of the page you arrived from. -->
    <div class="w-full max-w-2xl">
        <p v-if="heading && titleField" class="mb-2 text-eyebrow uppercase text-neutral-500">{{ heading }}</p>
        <h1 v-else-if="heading" class="mb-6 font-display text-display text-neutral-900">{{ heading }}</h1>

        <!-- The heading: an input that reads as the title it will become, not a
             form field with a label above it. -->
        <input
            v-if="titleField"
            :id="titleField.name"
            v-model="form[titleField.name]"
            :placeholder="titleField.label"
            data-text-size
            class="w-full border-none bg-transparent p-0 font-display text-display text-neutral-900 placeholder:text-neutral-200 focus:outline-none"
        >

        <FieldInput
            v-if="bodyField"
            :field="bodyField"
            :model-value="form[bodyField.name]"
            :resolved="resolved"
            hide-label
            :class="titleField ? 'mt-4' : ''"
            @update:model-value="form[bodyField.name] = $event"
            @fill="applyFill"
        />

        <!-- Fields that have to be seen to be filled. -->
        <div v-if="stacked.length" class="mt-6 space-y-4">
            <FieldInput
                v-for="field in stacked"
                :key="field.name"
                :field="field"
                :model-value="form[field.name]"
                :resolved="resolved"
                :relative-to-value="field.relativeTo ? String(form[field.relativeTo] ?? '') : null"
                @update:model-value="onFieldInput(field, $event)"
                @fill="applyFill"
            />
        </div>

        <div v-if="visibleChips.length || remainingExtras.length" ref="extrasRoot" class="relative mt-6 flex flex-wrap items-center gap-1.5">
            <button
                v-for="item in visibleChips"
                :key="item.key"
                type="button"
                class="inline-flex max-w-full items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-meta transition-colors"
                :class="[
                    // Three states, not two: open is the accent, set is a quiet
                    // fill, empty is an outline. Without the distinction a
                    // field with data looks identical to the one you are
                    // currently editing.
                    expanded.includes(item.key)
                        ? 'border-accent-500 bg-accent-50 text-accent-700'
                        : summary(item)
                            ? 'border-neutral-100 bg-neutral-25 text-neutral-900'
                            : 'border-neutral-100 text-neutral-700 hover:border-accent-500 hover:text-accent-700',
                ]"
                @click="toggle(item.key)"
            >
                <span :class="summary(item) ? 'text-neutral-500' : ''">{{ item.label }}</span>
                <span v-if="summary(item)" class="min-w-0 truncate font-medium">{{ summary(item) }}</span>
            </button>

            <button
                v-if="remainingExtras.length"
                type="button"
                class="grid size-8 shrink-0 place-items-center rounded-md border border-neutral-100 text-meta text-neutral-700 transition-colors hover:border-accent-500 hover:text-accent-700"
                aria-label="Add another field"
                :aria-expanded="showExtras"
                @click="toggleExtras"
            >
                +
            </button>

            <ul
                v-if="showExtras"
                class="absolute left-0 right-0 top-full z-20 mt-1 max-h-64 overflow-y-auto rounded-lg border border-neutral-100 bg-neutral-0 py-1 shadow-lg sm:right-auto sm:w-52"
            >
                <li v-for="item in remainingExtras" :key="item.key">
                    <button
                        type="button"
                        class="w-full px-3 py-2.5 text-left text-meta text-neutral-900 transition-colors hover:bg-accent-50 hover:text-accent-700 sm:py-1.5"
                        @click="add(item)"
                    >
                        {{ item.label }}
                    </button>
                </li>
            </ul>
        </div>

        <!-- An opened chip appears here, under the row, in the flow. -->
        <div v-if="expanded.length" class="mt-4 space-y-4 rounded-lg border border-neutral-50 bg-neutral-25 p-4">
            <template v-for="item in visibleChips.filter((chip) => expanded.includes(chip.key))" :key="item.key">
                <FieldInput
                    v-for="field in item.fields"
                    :key="field.name"
                    :field="field"
                    :model-value="form[field.name]"
                    :relative-to-value="field.relativeTo ? String(form[field.relativeTo] ?? '') : null"
                    @update:model-value="onFieldInput(field, $event)"
                    @fill="applyFill"
                />
            </template>
        </div>

        <div class="mt-8 flex flex-col-reverse items-stretch gap-3 border-t border-neutral-50 pt-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-center text-caption text-neutral-500 sm:text-left sm:text-meta">{{ status }}</p>

            <div v-if="publishField" class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center">
                <Button
                    :variant="isPublished ? 'ghost' : 'secondary'"
                    size="lg"
                    class="w-full sm:w-auto"
                    :disabled="form.processing"
                    @click="submit(isPublished ? false : null)"
                >
                    {{ isPublished ? 'Unpublish' : 'Save draft' }}
                </Button>

                <Button
                    variant="primary"
                    size="lg"
                    class="w-full sm:w-auto"
                    :disabled="form.processing"
                    @click="submit(isPublished ? null : true)"
                >
                    {{ isPublished ? 'Update' : 'Publish' }}
                </Button>
            </div>

            <Button v-else variant="primary" size="lg" class="w-full sm:w-auto" :disabled="form.processing" @click="submit">
                {{ submitLabel }}
            </Button>
        </div>
    </div>
</template>
