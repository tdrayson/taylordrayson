<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
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

const stacked = computed(() => props.fields.filter((field) => stacks(field) && !field.isTitle && !field.isBody));
const chippable = computed(() => props.fields.filter((field) => !stacks(field)));
const primaryChips = computed(() => chippable.value.filter((field) => field.primary));
const extraChips = computed(() => chippable.value.filter((field) => !field.primary));

function filled(name) {
    const value = props.values[name];

    return Array.isArray(value)
        ? value.length > 0
        : value !== null && value !== undefined && value !== '' && value !== false;
}

/** Which chips are open, and which extras have been added to the row. */
const expanded = ref([]);
const added = ref(extraChips.value.filter((field) => filled(field.name)).map((field) => field.name));
const { isOpen: showExtras, root: extrasRoot, close: closeExtras, toggle: toggleExtras } = useDismissable();

// Declaration order, not primary-then-added: the fields class is where the
// order is decided, and a chip should not jump position because it was added
// from the + menu rather than shown by default.
const visibleChips = computed(() => {
    const shown = [
        ...primaryChips.value,
        ...extraChips.value.filter((field) => added.value.includes(field.name)),
    ];

    return props.fields.filter((field) => shown.includes(field));
});

const remainingExtras = computed(() => extraChips.value.filter((field) => !added.value.includes(field.name)));

function toggle(name) {
    expanded.value = expanded.value.includes(name)
        ? expanded.value.filter((item) => item !== name)
        : [...expanded.value, name];
}

function add(field) {
    added.value.push(field.name);
    expanded.value.push(field.name);
    closeExtras();
}

/** The value on the chip itself, so a field that is set reads at a glance. */
function summary(field) {
    const value = form[field.name];

    if (value === null || value === undefined || value === '' || value === false) {
        return null;
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

/**
 * The slug follows the title until it is edited by hand, and only while the
 * entry is unpublished: once something is public its URL is a promise, and
 * retitling it must not quietly move the page.
 */
const slugField = computed(() => props.fields.find((field) => field.type === 'slug') ?? null);
const slugEdited = ref(Boolean(props.values[slugField.value?.name]));

watch(() => (titleField.value ? form[titleField.value.name] : null), (title) => {
    if (! slugField.value || slugEdited.value || form.published === true) {
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

        const field = props.fields.find((candidate) => candidate.name === key);

        if (field && ! field.primary && ! added.value.includes(key) && ! stacks(field)) {
            added.value.push(key);
        }
    });
}

const status = computed(() => {
    if (form.processing) {
        return 'Posting...';
    }

    if (form.isDirty) {
        return 'Changes not posted';
    }

    return props.method === 'post' ? 'Not posted yet' : 'Posted';
});

function submit() {
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
                v-for="field in visibleChips"
                :key="field.name"
                type="button"
                class="inline-flex max-w-full items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-meta transition-colors"
                :class="[
                    // Three states, not two: open is the accent, set is a quiet
                    // fill, empty is an outline. Without the distinction a
                    // field with data looks identical to the one you are
                    // currently editing.
                    expanded.includes(field.name)
                        ? 'border-accent-500 bg-accent-50 text-accent-700'
                        : summary(field)
                            ? 'border-neutral-100 bg-neutral-25 text-neutral-900'
                            : 'border-neutral-100 text-neutral-700 hover:border-accent-500 hover:text-accent-700',
                ]"
                @click="toggle(field.name)"
            >
                <span :class="summary(field) ? 'text-neutral-500' : ''">{{ field.label }}</span>
                <span v-if="summary(field)" class="min-w-0 truncate font-medium">{{ summary(field) }}</span>
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
                <li v-for="field in remainingExtras" :key="field.name">
                    <button
                        type="button"
                        class="w-full px-3 py-2.5 text-left text-meta text-neutral-900 transition-colors hover:bg-accent-50 hover:text-accent-700 sm:py-1.5"
                        @click="add(field)"
                    >
                        {{ field.label }}
                    </button>
                </li>
            </ul>
        </div>

        <!-- An opened chip appears here, under the row, in the flow. -->
        <div v-if="expanded.length" class="mt-4 space-y-4 rounded-lg border border-neutral-50 bg-neutral-25 p-4">
            <FieldInput
                v-for="field in visibleChips.filter((f) => expanded.includes(f.name))"
                :key="field.name"
                :field="field"
                :model-value="form[field.name]"
                :relative-to-value="field.relativeTo ? String(form[field.relativeTo] ?? '') : null"
                @update:model-value="onFieldInput(field, $event)"
                @fill="applyFill"
            />
        </div>

        <div class="mt-8 flex flex-col-reverse items-stretch gap-3 border-t border-neutral-50 pt-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-center text-caption text-neutral-500 sm:text-left sm:text-meta">{{ status }}</p>

            <Button variant="primary" size="lg" class="w-full sm:w-auto" :disabled="form.processing" @click="submit">
                {{ submitLabel }}
            </Button>
        </div>
    </div>
</template>
