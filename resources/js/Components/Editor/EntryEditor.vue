<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { withMediaIds } from '../../lib/editor/media.js';
import { noteSlug, plainTextOf, slugify, slugifyInput } from '../../lib/editor/defaults.js';
import { stash } from '../../lib/editor/handoff.js';
import { shiftWallClock } from '../../lib/editor/wallClock.js';
import { DEFAULT_TIMEZONE } from '../../lib/time.js';
import Button from '../Ui/Button.vue';
import FieldGroup from './FieldGroup.vue';
import FieldInput from './FieldInput.vue';
import LengthNotice from './LengthNotice.vue';

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
    submitLabel: { type: String, default: 'Post' },
    // The type this one graduates into when its capped field overflows, e.g. a
    // note into an article. Null on every surface where that is not on offer,
    // which includes editing something already posted.
    convertTo: { type: String, default: null },
});

const form = useForm({ ...props.values });

const titleField = computed(() => props.fields.find((field) => field.isTitle) ?? null);
const page = usePage();

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

/** How far a relative field sits after the one it is measured from, by default. */
const RELATIVE_DEFAULT_MINUTES = 60;

/** Fields measured from another, e.g. an event's end from its start. */
const relativeFields = computed(() => props.fields.filter((field) => field.relativeTo));

// A relative field follows its source until it is set by hand: choosing an end
// yourself is the way to say you want that one. A value already present on load
// was chosen on a previous save, so it counts as set.
const relativeEdited = new Set(
    relativeFields.value.filter((field) => props.values[field.name]).map((field) => field.name),
);

watch(() => relativeFields.value.map((field) => form[field.relativeTo] ?? null), (sources) => {
    relativeFields.value.forEach((field, index) => {
        if (! relativeEdited.has(field.name)) {
            form[field.name] = shiftWallClock(sources[index], RELATIVE_DEFAULT_MINUTES);
        }
    });
});

function onFieldInput(field, value) {
    if (field.relativeTo) {
        relativeEdited.add(field.name);
    }

    if (slugField.value && field.name === slugField.value.name) {
        slugEdited.value = true;
        form[field.name] = slugifyInput(value);

        return;
    }

    form[field.name] = value;
}

/**
 * What the slug will be if the field is left empty. Only types that declare a
 * fallback derive one; elsewhere the slug follows the title and is never blank.
 */
const derivedSlug = computed(() => {
    if (! slugField.value?.fallback) {
        return '';
    }

    // A note's body is a Prose field, which isBody does not mark: that flag is
    // for the RichText one an article uses. Either way it is the entry's words.
    const words = props.fields.find((field) => field.isBody || field.type === 'prose');

    return noteSlug(words ? form[words.name] : null, slugField.value.fallback);
});

/**
 * The URL this entry will answer on, as the slug is typed. Dated types live
 * under their day; anything else sits at the site root.
 */
const slugPreview = computed(() => {
    const slug = form[slugField.value?.name] || derivedSlug.value;

    if (! slug) {
        return null;
    }

    const day = previewDay.value;

    return day ? `/${day.replaceAll('-', '/')}/${slug}` : `/${slug}`;
});

/**
 * The day the entry will sit under. A date field left empty is stamped at save
 * rather than on load (see defaultValueFor), so preview today rather than
 * dropping the date and showing a URL the entry will never have.
 */
const previewDay = computed(() => {
    const chosen = String(form.occurred_at ?? '').slice(0, 10);

    if (chosen) {
        return chosen;
    }

    const field = props.fields.find((one) => one.name === 'occurred_at');

    if (! field?.defaultsToNow) {
        return '';
    }

    const timezone = page.props.ambient?.location?.timezone ?? DEFAULT_TIMEZONE;

    // en-CA renders as YYYY-MM-DD, which is the shape the URL wants.
    return new Intl.DateTimeFormat('en-CA', {
        timeZone: timezone, year: 'numeric', month: '2-digit', day: '2-digit',
    }).format(new Date());
});

/** Apply the sibling values a lookup resolved: a book's author, a place's coordinates. */
function applyFill(values) {
    Object.entries(values).forEach(([key, value]) => {
        if (key in form) {
            form[key] = value;
        }
    });
}

/** The field carrying a character limit, if this type declares one. */
const cappedField = computed(() => props.fields.find((field) => field.max) ?? null);

/** Characters spent on it, measured on readable text the way the server measures. */
const usedCharacters = computed(() => (cappedField.value === null
    ? 0
    : plainTextOf(form[cappedField.value.name]).length));

const overBy = computed(() => (cappedField.value === null ? 0 : usedCharacters.value - cappedField.value.max));

/**
 * Past the cap the save is refused, so the button is stopped here rather than
 * letting it round-trip to a validation error. The words are never truncated:
 * the way out is the bigger type, not a sentence cut in half.
 */
const overLimit = computed(() => cappedField.value !== null && overBy.value > 0);

/** Where the offer to graduate is drawn, when this type has one to make. */
const noticeAfter = computed(() => (props.convertTo === null ? null : cappedField.value?.name ?? null));

/**
 * Open the bigger type's editor holding what has been written so far.
 *
 * Both types name their body `content`, so the document crosses as it is: a
 * note's blocks are a subset of what an article allows.
 */
function convert() {
    stash(props.convertTo, {
        content: form[cappedField.value.name],
        tags: form.tags ?? [],
    });

    router.visit(`/new/${props.convertTo}`);
}

const errorCount = computed(() => Object.keys(form.errors).length);

const status = computed(() => {
    if (form.processing) {
        return publishField.value ? 'Saving...' : 'Posting...';
    }

    // Ahead of the dirty check for the same reason the error count is: the form
    // being dirty is not the news when the save button will not fire.
    if (overLimit.value) {
        return `Too long to post, by ${overBy.value.toLocaleString()} ${overBy.value === 1 ? 'character' : 'characters'}`;
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

    // A media field holds { id, name, url } so the picker can draw a thumbnail,
    // but the server takes the ids alone. Reduced here rather than in the field
    // component, which would then have nothing left to render.
    form.transform((data) => withMediaIds(props.fields, data));

    form[props.method](props.action, { preserveScroll: true });
}
</script>

<template>
    <!-- Left-aligned in the content column, not centred inside it: every other
         page on the site starts at the same left edge, and centring made the
         editor jump 112px right of the page you arrived from. -->
    <div class="w-full max-w-2xl">
        <!-- The page still needs exactly one h1 for the outline, and the title
             here is an input rather than a heading. Same fallback Entry.vue
             uses for the types that show no headline. -->
        <h1 class="sr-only">{{ (titleField ? form[titleField.name] : '') || 'Untitled' }}</h1>

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
            :error="form.errors[bodyField.name]"
            hide-label
            :class="titleField ? 'mt-4' : ''"
            @update:model-value="form[bodyField.name] = $event"
            @fill="applyFill"
        />

        <LengthNotice
            v-if="noticeAfter && bodyField?.name === noticeAfter"
            :used="usedCharacters"
            :max="cappedField.max"
            :convert-to="convertTo"
            @convert="convert"
        />

        <!-- Ruled off from the writing surface: what follows is metadata about
             the entry rather than more of the entry. -->
        <div
            v-if="rows.length"
            class="space-y-4"
            :class="bodyField ? 'mt-12 border-t border-neutral-50 pt-8' : 'mt-6'"
        >
            <template v-for="row in rows" :key="row.key">
                <!-- Wrapped so the notice hangs off its own field rather than
                     becoming another row in the stack's spacing. -->
                <div v-if="row.kind === 'field'">
                    <FieldInput
                        :field="row.field"
                        :model-value="form[row.field.name]"
                        :relative-to-value="row.field.relativeTo ? String(form[row.field.relativeTo] ?? '') : null"
                        :latitude="form.latitude ?? null"
                        :longitude="form.longitude ?? null"
                        :error="form.errors[row.field.name]"
                        :readonly="row.field.type === 'slug' && slugLocked"
                        :placeholder="row.field.type === 'slug' ? derivedSlug : ''"
                        :hint="row.field.type === 'slug' ? slugPreview : null"
                        @update:model-value="onFieldInput(row.field, $event)"
                        @fill="applyFill"
                    />

                    <LengthNotice
                        v-if="row.field.name === noticeAfter"
                        :used="usedCharacters"
                        :max="cappedField.max"
                        :convert-to="convertTo"
                        @convert="convert"
                    />
                </div>

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
                    :disabled="form.processing || overLimit"
                    @click="submit(isPublished ? false : null)"
                >
                    {{ isPublished ? 'Unpublish' : 'Save draft' }}
                </Button>

                <Button
                    variant="primary"
                    size="lg"
                    :disabled="form.processing || overLimit"
                    @click="submit(isPublished ? null : true)"
                >
                    {{ isPublished ? 'Update' : 'Publish' }}
                </Button>
            </div>

            <Button v-else variant="primary" size="lg" class="shrink-0" :disabled="form.processing || overLimit" @click="submit">
                {{ submitLabel }}
            </Button>
        </div>
    </div>
</template>
