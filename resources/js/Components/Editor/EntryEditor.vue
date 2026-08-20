<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { slugify } from '../../lib/editor/defaults.js';
import Button from '../Ui/Button.vue';
import FieldGroup from './FieldGroup.vue';
import FieldInput from './FieldInput.vue';

/**
 * The editing surface for any type: one column, mobile first, nothing floating.
 *
 * A title, a body, then every offered field stacked beneath. Nothing sits in a
 * panel beside the content, because on a phone there is no beside, and nothing
 * hides behind a menu: a field you cannot see is a field you forget exists.
 */
const props = defineProps({
    fields: { type: Array, required: true },
    values: { type: Object, required: true },
    action: { type: String, required: true },
    method: { type: String, default: 'patch' },
    resolved: { type: Object, default: () => ({}) },
    submitLabel: { type: String, default: 'Post' },
});

const form = useForm({ ...props.values });

const titleField = computed(() => props.fields.find((field) => field.isTitle) ?? null);
const bodyField = computed(() => props.fields.find((field) => field.isBody) ?? null);

// Publish state is the save action, not a field: it lives in the footer beside
// the button so the button can say what it will actually do.
const publishField = computed(() => props.fields.find((field) => field.isPublished) ?? null);
const isPublished = computed(() => publishField.value !== null && form[publishField.value.name] === true);

const offered = computed(() => props.fields.filter((field) => !field.hidden && !field.isPublished));

// Title and body are drawn above the stack, so neither appears in it.
const rest = computed(() => offered.value.filter((field) => !field.isTitle && !field.isBody));

/**
 * The stack in declaration order, a group standing where its first field was
 * declared. Drawing every group after every loose field instead put an address
 * a whole form away from the venue lookup that fills it.
 */
const rows = computed(() => {
    const seen = new Set();

    return rest.value.flatMap((field) => {
        if (! field.group) {
            return [{ kind: 'field', key: field.name, field }];
        }

        if (seen.has(field.group)) {
            return [];
        }

        seen.add(field.group);

        return [{
            kind: 'group',
            key: field.group,
            label: field.group,
            fields: rest.value.filter((candidate) => candidate.group === field.group),
        }];
    });
});

/** The group's set values on one line, so it reads without being opened. */
function groupSummary(item) {
    return item.fields
        .map((field) => form[field.name])
        .filter((value) => value !== null && value !== undefined && value !== '')
        .join(', ');
}

const slugField = computed(() => props.fields.find((field) => field.type === 'slug') ?? null);

/**
 * Publishing settles the URL: it can be linked, bookmarked or in a feed from
 * that moment, so the slug stops following the title and stops being editable.
 * A draft has none of that, and keeps tracking its title however often it is
 * saved. A type with no publish state goes live at its first save, which is
 * where its slug settles instead.
 */
const slugLocked = computed(() => (publishField.value ? isPublished.value : props.method !== 'post'));

/**
 * Until then it follows the title, unless it has been typed by hand: writing a
 * slug yourself is the way to say you want that one.
 *
 * A reloaded draft has to work that out from the values alone. A slug matching
 * its title is one this generated, so it carries on generating; anything else
 * was chosen, and is left alone.
 */
const slugEdited = ref((() => {
    const slug = props.values[slugField.value?.name];

    return Boolean(slug) && slug !== slugify(props.values[titleField.value?.name]);
})());

watch(() => (titleField.value ? form[titleField.value.name] : null), (title) => {
    if (! slugField.value || slugEdited.value || slugLocked.value) {
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

/** Apply the sibling values a lookup resolved: a book's author, a place's coordinates. */
function applyFill(values) {
    Object.entries(values).forEach(([key, value]) => {
        if (key in form) {
            form[key] = value;
        }
    });
}

const errorCount = computed(() => Object.keys(form.errors).length);

const status = computed(() => {
    if (form.processing) {
        return publishField.value ? 'Saving...' : 'Posting...';
    }

    // Ahead of the dirty check: a rejected save leaves the form dirty, and
    // "Unsaved changes" would read as nothing having gone wrong.
    if (errorCount.value) {
        return errorCount.value === 1 ? 'Not saved, one field needs fixing' : `Not saved, ${errorCount.value} fields need fixing`;
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

        <p v-if="titleField && form.errors[titleField.name]" class="mt-1 text-caption text-red-600">{{ form.errors[titleField.name] }}</p>

        <FieldInput
            v-if="bodyField"
            :field="bodyField"
            :model-value="form[bodyField.name]"
            :resolved="resolved"
            :error="form.errors[bodyField.name]"
            hide-label
            :class="titleField ? 'mt-4' : ''"
            @update:model-value="form[bodyField.name] = $event"
            @fill="applyFill"
        />

        <div v-if="rows.length" class="mt-6 space-y-4">
            <template v-for="row in rows" :key="row.key">
                <FieldInput
                    v-if="row.kind === 'field'"
                    :field="row.field"
                    :model-value="form[row.field.name]"
                    :resolved="resolved"
                    :relative-to-value="row.field.relativeTo ? String(form[row.field.relativeTo] ?? '') : null"
                    :latitude="form.latitude ?? null"
                    :longitude="form.longitude ?? null"
                    :error="form.errors[row.field.name]"
                    :readonly="row.field.type === 'slug' && slugLocked"
                    @update:model-value="onFieldInput(row.field, $event)"
                    @fill="applyFill"
                />

                <FieldGroup
                    v-else
                    :label="row.label"
                    :summary="groupSummary(row)"
                    :invalid="row.fields.some((field) => form.errors[field.name])"
                >
                    <FieldInput
                        v-for="field in row.fields"
                        :key="field.name"
                        :field="field"
                        :model-value="form[field.name]"
                        :error="form.errors[field.name]"
                        @update:model-value="onFieldInput(field, $event)"
                        @fill="applyFill"
                    />
                </FieldGroup>
            </template>
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
