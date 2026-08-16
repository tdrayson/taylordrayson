<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { slugify } from '../../lib/editor/defaults.js';
import { useDismissable } from '../../lib/editor/dismissable.js';
import Button from '../Ui/Button.vue';
import FieldGroup from './FieldGroup.vue';
import FieldInput from './FieldInput.vue';

/**
 * The editing surface for any type: one column, mobile first, nothing floating.
 *
 * A title, a body, then every offered field stacked beneath. Nothing sits in a
 * panel beside the content, because on a phone there is no beside.
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

const offered = computed(() => props.fields.filter((field) => !field.hidden && !field.isPublished));

// Title and body are drawn above the stack; a paired field is drawn inside the
// control it pairs with. None of them appear in the stack.
const rest = computed(() => offered.value.filter((field) => !field.isTitle && !field.isBody && !field.pairsWith));

/** The field drawn inside this one's control, e.g. a timezone inside its date. */
function pairedWith(name) {
    return props.fields.find((field) => field.pairsWith === name) ?? null;
}

function filled(name) {
    const value = props.values[name];

    return Array.isArray(value)
        ? value.length > 0
        : value !== null && value !== undefined && value !== '' && value !== false;
}

// An optional field already carrying a value is shown without being asked for.
const added = ref(rest.value.filter((field) => !field.primary && filled(field.name)).map((field) => field.name));
const { isOpen: showExtras, root: extrasRoot, close: closeExtras, toggle: toggleExtras } = useDismissable();

// A short form is drawn whole: hiding four fields behind a menu costs more
// reading than it saves, and the menu itself is one more thing to notice.
const SHOW_ALL_UP_TO = 6;

/** Fields as the + menu counts them, with a group counting once. */
const itemCount = computed(() => new Set(rest.value.map((field) => field.group ?? field.name)).size);

const showAll = computed(() => itemCount.value <= SHOW_ALL_UP_TO);

// Declaration order, not primary-then-added: the fields class decides the order,
// and a field should not jump position because it came from the + menu.
const visible = computed(() => rest.value.filter((field) => showAll.value || field.primary || added.value.includes(field.name)));

// Grouped fields are drawn inside their group block, not loose in the stack.
const stack = computed(() => visible.value.filter((field) => ! field.group));

const groups = computed(() => {
    const items = new Map();

    visible.value.filter((field) => field.group).forEach((field) => {
        const existing = items.get(field.group);

        existing
            ? existing.fields.push(field)
            : items.set(field.group, { key: field.group, label: field.group, fields: [field] });
    });

    return [...items.values()];
});

/** The group's set values on one line, so it reads without being opened. */
function groupSummary(item) {
    return item.fields
        .map((field) => form[field.name])
        .filter((value) => value !== null && value !== undefined && value !== '')
        .join(', ');
}

/** Unadded optionals, with a group offered as one item rather than five. */
const remainingItems = computed(() => {
    if (showAll.value) {
        return [];
    }

    const items = [];
    const seen = new Set();

    rest.value
        .filter((field) => !field.primary && !added.value.includes(field.name))
        .forEach((field) => {
            const key = field.group ?? field.name;

            if (seen.has(key)) {
                return;
            }

            seen.add(key);
            items.push({
                key,
                label: field.group ?? field.label,
                fields: rest.value.filter((candidate) => (candidate.group ?? candidate.name) === key),
            });
        });

    return items;
});

function add(item) {
    item.fields.forEach((field) => added.value.push(field.name));
    closeExtras();
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

        const field = rest.value.find((candidate) => candidate.name === key);

        if (field && ! field.primary && ! added.value.includes(key)) {
            added.value.push(key);
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

        <div v-if="stack.length" class="mt-6 space-y-4">
            <FieldInput
                v-for="field in stack"
                :key="field.name"
                :field="field"
                :model-value="form[field.name]"
                :resolved="resolved"
                :relative-to-value="field.relativeTo ? String(form[field.relativeTo] ?? '') : null"
                :latitude="form.latitude ?? null"
                :longitude="form.longitude ?? null"
                :paired="pairedWith(field.name)"
                :paired-value="pairedWith(field.name) ? form[pairedWith(field.name).name] : null"
                @update:model-value="onFieldInput(field, $event)"
                @update:paired="form[pairedWith(field.name).name] = $event"
                @fill="applyFill"
            />
        </div>

        <div v-if="groups.length" class="mt-4 space-y-4">
            <FieldGroup
                v-for="item in groups"
                :key="item.key"
                :label="item.label"
                :summary="groupSummary(item)"
            >
                <FieldInput
                    v-for="field in item.fields"
                    :key="field.name"
                    :field="field"
                    :model-value="form[field.name]"
                    @update:model-value="onFieldInput(field, $event)"
                    @fill="applyFill"
                />
            </FieldGroup>
        </div>

        <div v-if="remainingItems.length" ref="extrasRoot" class="relative mt-6">
            <button
                type="button"
                class="inline-flex min-h-11 items-center gap-2 rounded-md border border-neutral-100 px-3 text-meta text-neutral-700 transition-colors hover:border-accent-500 hover:text-accent-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                :aria-expanded="showExtras"
                @click="toggleExtras"
            >
                <span aria-hidden="true">+</span>
                Add field
            </button>

            <ul
                v-if="showExtras"
                class="absolute left-0 right-0 top-full z-20 mt-1 max-h-64 overflow-y-auto rounded-lg border border-neutral-100 bg-neutral-0 py-1 shadow-lg sm:right-auto sm:w-52"
            >
                <li v-for="item in remainingItems" :key="item.key">
                    <button
                        type="button"
                        class="flex min-h-11 w-full items-center px-3 text-left text-meta text-neutral-900 transition-colors hover:bg-accent-50 hover:text-accent-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 sm:min-h-0 sm:py-1.5"
                        @click="add(item)"
                    >
                        {{ item.label }}
                    </button>
                </li>
            </ul>
        </div>

        <!-- Sticky rather than fixed, so it needs no bottom padding on the form
             and settles at the end of the page on desktop. -->
        <div class="sticky bottom-0 z-10 mt-8 flex items-center justify-between gap-3 border-t border-neutral-50 bg-neutral-0 py-3 sm:static sm:py-0 sm:pt-4">
            <p class="text-caption text-neutral-500 sm:text-meta">{{ status }}</p>

            <div v-if="publishField" class="flex shrink-0 items-center gap-2">
                <Button
                    :variant="isPublished ? 'ghost' : 'secondary'"
                    size="lg"
                    :disabled="form.processing"
                    @click="submit(isPublished ? false : null)"
                >
                    {{ isPublished ? 'Unpublish' : 'Save draft' }}
                </Button>

                <Button
                    variant="primary"
                    size="lg"
                    :disabled="form.processing"
                    @click="submit(isPublished ? null : true)"
                >
                    {{ isPublished ? 'Update' : 'Publish' }}
                </Button>
            </div>

            <Button v-else variant="primary" size="lg" class="shrink-0" :disabled="form.processing" @click="submit">
                {{ submitLabel }}
            </Button>
        </div>
    </div>
</template>
