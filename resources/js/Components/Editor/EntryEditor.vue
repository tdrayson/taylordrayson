<script setup>
import { computed, provide, ref, toRef, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useEditorTabs } from '../../composables/useEditorTabs.js';
import { useSlugField } from '../../composables/useSlugField.js';
import { isMedia, withMediaIds } from '../../lib/editor/media.js';
import { plainTextOf, slugifyInput } from '../../lib/editor/defaults.js';
import { stash } from '../../lib/editor/handoff.js';
import { shiftWallClock } from '../../lib/editor/wallClock.js';
import { hiddenNames, required, revealed } from '../../lib/editor/visibility.js';
import { MAIN_TAB, SOCIAL_TAB } from '../../lib/editor/placement.js';
import Button from '../Ui/Button.vue';
import Eyebrow from '../Ui/Eyebrow.vue';
import Select from '../Ui/Select.vue';
import ZoomSwitcher from '../Layout/ZoomSwitcher.vue';
import EditorFields from './EditorFields.vue';
import EditorHeader from './EditorHeader.vue';
import EditorShareTab from './EditorShareTab.vue';
import EditorSidebar from './EditorSidebar.vue';
import EditorSidebarRows from './EditorSidebarRows.vue';
import EntryMenu from './EntryMenu.vue';
import FieldInput from './FieldInput.vue';
import LengthNotice from './LengthNotice.vue';
import PasswordInput from './PasswordInput.vue';

/**
 * The editing surface for any type: a header over the tabbed writing column,
 * with the publish block and sidebar fields beside it on desktop.
 */
const props = defineProps({
    // The entry's type key, e.g. 'article', for the header's label and accent.
    type: { type: String, required: true },
    fields: { type: Array, required: true },
    values: { type: Object, required: true },
    action: { type: String, required: true },
    method: { type: String, default: 'patch' },
    submitLabel: { type: String, default: 'Post' },
    // The type this one graduates into when its capped field overflows, e.g. a
    // note into an article. Null on every surface where that is not on offer,
    // which includes editing something already posted.
    convertTo: { type: String, default: null },
    // Shown in the header for a type without a title field, e.g. the card title or "New Fuel".
    heading: { type: String, default: null },
    // The entry's date as the page formats it, for the header's date line.
    date: { type: String, default: null },
    // The entry's own page, for an entry that already exists.
    viewUrl: { type: String, default: null },
    // The saved entry's Open Graph payload, for the Social tab's opening card.
    og: { type: Object, default: null },
    // A saved hand-written entry's id; null when new or synced, which cannot be duplicated or deleted here.
    entryId: { type: Number, default: null },
    // The authoring key the /entries and /new routes resolve by, which the card type need not match; null for a synced type.
    authoringType: { type: String, default: null },
});

const form = useForm({ ...props.values });

// Lets a field read its siblings, such as a book cover searching by the title, without new props.
provide('editorForm', form);

const titleField = computed(() => props.fields.find((field) => field.isTitle) ?? null);

const offered = computed(() => props.fields.filter((field) => !field.hidden && revealed(field, form)));

/**
 * A field that stops being shown gives up its value.
 *
 * Choosing "RSVP", filling in the reply, then switching to "Like" would
 * otherwise save the RSVP nobody can see any more, and a stray property is
 * what post type discovery reads a post's whole type from.
 */
const hiddenByCondition = computed(() => hiddenNames(props.fields, form));

// Keyed on the names rather than the array, which is rebuilt on every keystroke
// and would otherwise fire this on all of them.
watch(
    () => hiddenByCondition.value.join(','),
    () => hiddenByCondition.value.forEach((name) => {
        if (form[name] !== null && form[name] !== undefined && form[name] !== '') {
            form[name] = null;
        }
    }),
);

// Drawn by the publish block, never as a field row.
const statusField = computed(() => props.fields.find((field) => field.type === 'status') ?? null);

const { placed, mainBody, mainRest, tabs, activeTab, fieldTabs } = useEditorTabs(toRef(props, 'fields'), offered, form);

/** The response context CitationField last loaded, which a response's slug is named from. */
const responsePreview = ref(null);

const { slugField, slugLocked, slugUnlocked, slugEdited, derivedSlug, slugPreview } = useSlugField(props, form, titleField, statusField, responsePreview);

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

// What each fill last wrote, so a later fill can tell a value typed since from its own.
const filled = {};

/**
 * Apply the sibling values a lookup resolved: a book's author, a place's coordinates.
 * A key in `keepEdits` is only replaced while blank or still holding what a fill put there.
 */
function applyFill(values, { keepEdits = [] } = {}) {
    Object.entries(values).forEach(([key, value]) => {
        if (! (key in form)) {
            return;
        }

        const edited = keepEdits.includes(key)
            && String(form[key] ?? '').trim() !== ''
            && form[key] !== filled[key];

        if (! edited) {
            form[key] = value;
            filled[key] = value;
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

/** A settled slug, until unlocked by hand for this session. */
const slugReadonly = computed(() => slugLocked.value && ! slugUnlocked.value);

const UNLOCK_SLUG = { label: 'Unlock', ariaLabel: 'Unlock the slug', action: () => (slugUnlocked.value = true) };

/** FieldInput props that depend on the rest of the form, e.g. a slug's preview and lock. */
function fieldBindings(field) {
    const lockedSlug = field.type === 'slug' && ! field.readOnly && slugReadonly.value;

    return {
        relativeToValue: field.relativeTo ? String(form[field.relativeTo] ?? '') : null,
        password: form.password ?? '',
        latitude: form.latitude ?? null,
        longitude: form.longitude ?? null,
        responseUrl: form.response_url ?? null,
        responseKind: form.response_kind ?? null,
        readonly: Boolean(field.readOnly) || (field.type === 'slug' && slugReadonly.value),
        labelAction: lockedSlug ? UNLOCK_SLUG : null,
        placeholder: field.type === 'slug' ? derivedSlug.value : '',
        hint: field.type === 'slug' ? slugPreview.value : null,
        excused: field.required && ! required(field, form),
    };
}

const errorCount = computed(() => Object.keys(form.errors).length);

const isPrivate = computed(() => statusField.value !== null && form[statusField.value.name] === 'private');

const statusError = computed(() => (statusField.value ? form.errors[statusField.value.name] ?? null : null));

/** A private entry cannot be saved with the password box empty. */
const needsPassword = computed(() => isPrivate.value && ! form.password);

/** What the saved status means for who can see the entry. */
const STATUS_NOTES = {
    draft: 'Draft, only you can see this',
    published: 'Published, live to everyone',
    unlisted: 'Unlisted, only people with the link',
    private: 'Private, locked behind a password',
};

const status = computed(() => {
    if (form.processing) {
        return props.method === 'post' ? 'Posting...' : 'Saving...';
    }

    // Ahead of the dirty check for the same reason the error count is: the form
    // being dirty is not the news when the save button will not fire.
    if (overLimit.value) {
        return `Too long to post, by ${overBy.value.toLocaleString()} ${overBy.value === 1 ? 'character' : 'characters'}`;
    }

    if (needsPassword.value) {
        return 'Add a password to make this private';
    }

    // Ahead of the dirty check: a rejected save leaves the form dirty, and
    // "Unsaved changes" would read as nothing having gone wrong.
    if (errorCount.value) {
        return errorCount.value === 1 ? 'Not saved, one field needs fixing' : `Not saved, ${errorCount.value} fields need fixing`;
    }

    if (form.isDirty) {
        return 'Unsaved changes';
    }

    if (statusField.value && props.method !== 'post') {
        return STATUS_NOTES[props.values[statusField.value.name]] ?? 'Posted';
    }

    return props.method === 'post' ? 'Not posted yet' : 'Posted';
});

const saveLabel = computed(() => (props.method === 'post' ? props.submitLabel : 'Save'));

const saveDisabled = computed(() => form.processing || overLimit.value || needsPassword.value);

/** Where the Social tab's Refresh posts; synced types have no preview. */
const previewUrl = computed(() => (props.authoringType !== null && (props.method === 'post' || props.entryId !== null)
    ? `/entries/${props.authoringType}/share-preview`
    : null));

/** The values a save would send, plus the id so the preview fills the saved entry. */
function sharePayload() {
    return { ...withMediaIds(props.fields, form.data()), ...(props.entryId !== null ? { id: props.entryId } : {}) };
}

const deleteUrl = computed(() => (props.entryId !== null && props.authoringType !== null
    ? `/entries/${props.authoringType}/${props.entryId}`
    : null));

/** What the delete confirmation names: the saved title, or the heading. */
const entryName = computed(() => (titleField.value ? props.values[titleField.value.name] : null) || props.heading || null);

/**
 * Open a new entry of this type holding these values. What makes this entry
 * this one (slug, status, password, media, dates stamped at save, synced values) stays behind.
 */
function duplicate() {
    const dropped = new Set(['password']);

    props.fields
        .filter((field) => ['slug', 'status'].includes(field.type) || isMedia(field) || field.defaultsToNow || field.readOnly)
        .forEach((field) => dropped.add(field.name));

    // An end measured from a dropped start would sit before the new one.
    props.fields
        .filter((field) => field.relativeTo && dropped.has(field.relativeTo))
        .forEach((field) => dropped.add(field.name));

    stash(props.authoringType, Object.fromEntries(props.fields
        .filter((field) => ! dropped.has(field.name))
        .map((field) => [field.name, form[field.name]])));

    router.visit(`/new/${props.authoringType}`);
}

/** Save the form as it stands; the status travels with every other field. */
function submit() {
    // A media field holds { id, name, url } so the picker can draw a thumbnail,
    // but the server takes the ids alone. Reduced here rather than in the field
    // component, which would then have nothing left to render.
    form.transform((data) => withMediaIds(props.fields, data));

    form[props.method](props.action, { preserveScroll: true });
}
</script>

<template>
    <!-- Breakout on desktop for the writing column plus the sidebar, the pair
         (max-w-5xl: 2xl + gap-16 + w-72) centred as one unit. -->
    <div class="lg:breakout">
        <div class="mx-auto w-full lg:max-w-5xl">
            <EditorHeader
                :type="type"
                :title-field="titleField"
                :title="titleField ? form[titleField.name] : ''"
                :title-error="titleField ? form.errors[titleField.name] : null"
                :heading="heading"
                :date="date"
                @update:title="form[titleField.name] = $event"
            />

            <div class="mt-8 lg:flex lg:gap-16">
                <div class="min-w-0 lg:flex-1">
                    <ZoomSwitcher v-if="tabs.length > 1" v-model="activeTab" :options="tabs" class="mb-8" />

                    <!-- v-show rather than v-if, so the body editor keeps its state across tabs. -->
                    <div v-show="activeTab === MAIN_TAB">
                        <FieldInput
                            v-if="mainBody"
                            :field="mainBody"
                            :model-value="form[mainBody.name]"
                            :error="form.errors[mainBody.name]"
                            hide-label
                            @update:model-value="form[mainBody.name] = $event"
                            @fill="applyFill"
                        />

                        <LengthNotice
                            v-if="mainBody && mainBody.name === noticeAfter"
                            :used="usedCharacters"
                            :max="cappedField.max"
                            :convert-to="convertTo"
                            @convert="convert"
                        />

                        <!-- Ruled off from the writing surface: what follows is
                             metadata about the entry rather than more of it. -->
                        <EditorFields
                            v-if="mainRest.length"
                            :fields="mainRest"
                            :form="form"
                            :bindings="fieldBindings"
                            :class="mainBody ? 'mt-12 border-t border-neutral-50 pt-8' : ''"
                            @update="onFieldInput"
                            @fill="applyFill"
                            @preview="responsePreview = $event"
                        >
                            <template #after-field="{ field }">
                                <LengthNotice
                                    v-if="field.name === noticeAfter"
                                    :used="usedCharacters"
                                    :max="cappedField.max"
                                    :convert-to="convertTo"
                                    @convert="convert"
                                />
                            </template>
                        </EditorFields>
                    </div>

                    <div v-for="tab in fieldTabs" v-show="activeTab === tab.value" :key="tab.value">
                        <EditorFields
                            :fields="placed.tabs[tab.value] ?? []"
                            :form="form"
                            :bindings="fieldBindings"
                            @update="onFieldInput"
                            @fill="applyFill"
                            @preview="responsePreview = $event"
                        />
                    </div>

                    <div v-show="activeTab === SOCIAL_TAB">
                        <EditorShareTab :og="og" :preview-url="previewUrl" :payload="sharePayload" />
                    </div>
                </div>

                <EditorSidebar
                    :status="statusField ? form[statusField.name] : null"
                    :status-field="statusField"
                    :status-error="statusError"
                    :password="form.password ?? ''"
                    :status-text="status"
                    :view-url="viewUrl"
                    :submit-label="saveLabel"
                    :disabled="saveDisabled"
                    @update:status="form[statusField.name] = $event"
                    @fill="applyFill"
                    @submit="submit"
                >
                    <template v-if="viewUrl" #menu>
                        <EntryMenu
                            :view-url="viewUrl"
                            :delete-url="deleteUrl"
                            :can-duplicate="entryId !== null && authoringType !== null"
                            :name="entryName"
                            @duplicate="duplicate"
                        />
                    </template>

                    <!-- Both drawn, one per breakpoint: the phone rows only mount an open
                         row's inputs, and suffix their ids so none repeats the list's. -->
                    <template v-if="placed.sidebar.length" #default>
                        <EditorFields
                            :fields="placed.sidebar"
                            :form="form"
                            :bindings="fieldBindings"
                            compact-zones
                            class="hidden lg:block"
                            @update="onFieldInput"
                            @fill="applyFill"
                            @preview="responsePreview = $event"
                        />

                        <EditorSidebarRows
                            :fields="placed.sidebar"
                            :form="form"
                            :bindings="fieldBindings"
                            class="lg:hidden"
                            @update="onFieldInput"
                            @fill="applyFill"
                            @preview="responsePreview = $event"
                        />
                    </template>
                </EditorSidebar>
            </div>

            <!-- Phone only: kept out of the sticky bar so the bar stays one line. -->
            <div v-if="isPrivate || statusError" class="mt-8 lg:hidden">
                <template v-if="isPrivate">
                    <Eyebrow as="label" for="password-bar" class="mb-1 block text-neutral-500">Password</Eyebrow>

                    <PasswordInput
                        id="password-bar"
                        :model-value="form.password ?? ''"
                        :readonly="Boolean(statusField.readOnly)"
                        @update:model-value="applyFill({ password: $event })"
                    />
                </template>

                <p v-if="statusError" class="mt-1 text-xs text-red-600">{{ statusError }}</p>
            </div>

            <!-- Phone only: the publish block moves here. Sticky rather than
                 fixed, so the form needs no bottom padding. -->
            <div class="sticky bottom-0 z-10 mt-8 border-t border-neutral-50 bg-neutral-0 py-3 sm:static sm:py-0 sm:pt-4 lg:hidden">
                <div class="flex items-center gap-3">
                    <div v-if="statusField" class="min-w-0 flex-1">
                        <Select
                            :model-value="form[statusField.name] ?? 'published'"
                            :options="statusField.options ?? []"
                            :readonly="Boolean(statusField.readOnly)"
                            :invalid="Boolean(statusError)"
                            :aria-label="statusField.label"
                            @update:model-value="form[statusField.name] = $event"
                        />
                    </div>

                    <p v-else class="min-w-0 flex-1 text-xs text-neutral-500 sm:text-sm">{{ status }}</p>

                    <Button variant="primary" size="lg" class="shrink-0" :disabled="saveDisabled" @click="submit">
                        {{ saveLabel }}
                    </Button>

                    <!-- The sidebar's menu is desktop only; this is the same one for a phone. -->
                    <EntryMenu
                        v-if="viewUrl"
                        :view-url="viewUrl"
                        :delete-url="deleteUrl"
                        :can-duplicate="entryId !== null && authoringType !== null"
                        :name="entryName"
                        above
                        @duplicate="duplicate"
                    />
                </div>

                <p v-if="statusField" class="mt-1 text-xs text-neutral-500">{{ status }}</p>
            </div>
        </div>
    </div>
</template>
