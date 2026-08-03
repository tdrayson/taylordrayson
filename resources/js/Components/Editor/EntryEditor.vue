<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
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
});

const form = useForm({ ...props.values });

const titleField = computed(() => props.fields.find((field) => field.isTitle) ?? null);
const bodyField = computed(() => props.fields.find((field) => field.isBody) ?? null);

/**
 * A chip suits a short value that is usually empty and quick to set: a date, a
 * tag list, a toggle. It does not suit a field you have to see to fill in.
 *
 * Rich text needs room, a location needs its search box and its button, and a
 * required field behind a chip is a trap: the post is refused and the reason is
 * a tap away. Those stack instead.
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
const showExtras = ref(false);

const visibleChips = computed(() => [
    ...primaryChips.value,
    ...extraChips.value.filter((field) => added.value.includes(field.name)),
]);

const remainingExtras = computed(() => extraChips.value.filter((field) => !added.value.includes(field.name)));

function toggle(name) {
    expanded.value = expanded.value.includes(name)
        ? expanded.value.filter((item) => item !== name)
        : [...expanded.value, name];
}

function add(field) {
    added.value.push(field.name);
    expanded.value.push(field.name);
    showExtras.value = false;
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

    return String(value).slice(0, 24);
}

function applyFill(values) {
    Object.entries(values).forEach(([key, value]) => {
        if (key in form) {
            form[key] = value;
        }
    });
}

function submit() {
    form[props.method](props.action, { preserveScroll: true });
}
</script>

<template>
    <div class="mx-auto w-full max-w-2xl">
        <!-- The heading: an input that reads as the title it will become, not a
             form field with a label above it. -->
        <input
            v-if="titleField"
            :id="titleField.name"
            v-model="form[titleField.name]"
            :placeholder="titleField.label"
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
                @update:model-value="form[field.name] = $event"
                @fill="applyFill"
            />
        </div>

        <!-- Everything else, as chips that open in place. -->
        <div v-if="visibleChips.length || remainingExtras.length" class="mt-6 flex flex-wrap items-center gap-1.5">
            <button
                v-for="field in visibleChips"
                :key="field.name"
                type="button"
                class="inline-flex max-w-full items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-meta transition-colors"
                :class="expanded.includes(field.name) || summary(field)
                    ? 'border-accent-500 bg-accent-50 text-accent-700'
                    : 'border-neutral-100 text-neutral-700 hover:border-accent-500 hover:text-accent-700'"
                @click="toggle(field.name)"
            >
                {{ field.label }}
                <span v-if="summary(field)" class="min-w-0 truncate font-medium">{{ summary(field) }}</span>
            </button>

            <div v-if="remainingExtras.length" class="relative">
                <button
                    type="button"
                    class="rounded-md border border-neutral-100 px-2.5 py-1.5 text-meta text-neutral-700 transition-colors hover:border-accent-500 hover:text-accent-700"
                    aria-label="Add another field"
                    @click="showExtras = ! showExtras"
                >
                    +
                </button>

                <ul
                    v-if="showExtras"
                    class="absolute left-0 z-20 mt-1 w-52 rounded-lg border border-neutral-100 bg-neutral-0 py-1 shadow-lg"
                >
                    <li v-for="field in remainingExtras" :key="field.name">
                        <button
                            type="button"
                            class="w-full px-3 py-1.5 text-left text-meta text-neutral-900 transition-colors hover:bg-accent-50 hover:text-accent-700"
                            @click="add(field)"
                        >
                            {{ field.label }}
                        </button>
                    </li>
                </ul>
            </div>
        </div>

        <!-- An opened chip appears here, under the row, in the flow. -->
        <div v-if="expanded.length" class="mt-4 space-y-4 rounded-lg border border-neutral-50 bg-neutral-25 p-4">
            <FieldInput
                v-for="field in visibleChips.filter((f) => expanded.includes(f.name))"
                :key="field.name"
                :field="field"
                :model-value="form[field.name]"
                @update:model-value="form[field.name] = $event"
                @fill="applyFill"
            />
        </div>

        <div class="mt-8 flex items-center justify-between gap-3 border-t border-neutral-50 pt-4">
            <p class="text-meta text-neutral-500">
                <span v-if="form.processing">Posting...</span>
                <span v-else-if="form.isDirty">Changes not posted</span>
                <span v-else-if="method === 'post'">Not posted yet</span>
                <span v-else>Posted</span>
            </p>

            <Button variant="primary" :disabled="form.processing" @click="submit">{{ submitLabel }}</Button>
        </div>
    </div>
</template>
