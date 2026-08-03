<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Button from '../Ui/Button.vue';
import FieldInput from './FieldInput.vue';

/**
 * The editing surface for any type, drawn from its field definitions.
 *
 * Ghost's shape: the body gets the full width, the rest lives in a panel that
 * is summoned rather than parked, and the save state sits quietly in the header
 * beside Preview and Publish.
 */
const props = defineProps({
    // Field definitions from FieldRegistry.
    fields: { type: Array, required: true },
    // Current values, keyed by field name (dotted names allowed).
    values: { type: Object, required: true },
    action: { type: String, required: true },
    method: { type: String, default: 'patch' },
    resolved: { type: Object, default: () => ({}) },
    submitLabel: { type: String, default: 'Post' },
});

const emit = defineEmits(['preview']);

const form = useForm({ ...props.values });

// The body is whatever field the type nominates, and it is the only one that
// gets the full width. Everything else is a property.
const bodyField = computed(() => props.fields.find((field) => field.isBody) ?? null);
const primaryFields = computed(() => props.fields.filter((field) => field.primary && !field.isBody));
const optionalFields = computed(() => props.fields.filter((field) => !field.primary));

const showProperties = ref(false);
// Optional fields stay hidden until asked for, or until one already has a value.
const revealed = ref(optionalFields.value.filter((field) => {
    const value = props.values[field.name];

    return value !== null && value !== undefined && value !== '';
}).map((field) => field.name));

const hidden = computed(() => optionalFields.value.filter((field) => !revealed.value.includes(field.name)));

/**
 * Apply the sibling values a lookup resolved. Only keys the form already has
 * are written, so a source returning something this type does not store is
 * ignored rather than silently added to the payload.
 */
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
    <div>
        <div class="sticky top-0 z-10 mb-6 flex items-center justify-between gap-3 border-b border-neutral-50 bg-neutral-0/90 py-3 backdrop-blur">
            <p class="text-meta text-neutral-500">
                <span v-if="form.processing">Posting...</span>
                <span v-else-if="form.isDirty">Changes not posted</span>
                <!-- Nothing exists yet on a create, so claiming it is posted
                     would be a lie the first time anyone reads it. -->
                <span v-else-if="method === 'post'">Not posted yet</span>
                <span v-else>Posted</span>
            </p>

            <div class="flex items-center gap-2">
                <Button v-if="bodyField" size="sm" @click="emit('preview', form.data())">Preview</Button>
                <Button size="sm" @click="showProperties = !showProperties">Properties</Button>
                <Button size="sm" variant="primary" :disabled="form.processing" @click="submit">{{ submitLabel }}</Button>
            </div>
        </div>

        <!-- The body, full width. -->
        <FieldInput
            v-if="bodyField"
            :field="bodyField"
            :model-value="form[bodyField.name]"
            :resolved="resolved"
            @update:model-value="form[bodyField.name] = $event"
            @fill="applyFill"
        />

        <!-- Types with no body (fuel, books, appearances) are just a form. -->
        <div v-else class="max-w-xl space-y-4">
            <FieldInput
                v-for="field in primaryFields"
                :key="field.name"
                :field="field"
                :model-value="form[field.name]"
                @update:model-value="form[field.name] = $event"
                @fill="applyFill"
            />
        </div>

        <aside
            v-if="showProperties"
            class="fixed right-4 top-24 z-20 max-h-[70vh] w-80 space-y-4 overflow-y-auto rounded-lg border border-neutral-100 bg-neutral-0 p-4 shadow-lg"
        >
            <!-- With a body present the primary fields are properties too; without
                 one they are already the form above, so they are not repeated. -->
            <FieldInput
                v-for="field in (bodyField ? primaryFields : [])"
                :key="field.name"
                :field="field"
                :model-value="form[field.name]"
                @update:model-value="form[field.name] = $event"
                @fill="applyFill"
            />

            <FieldInput
                v-for="field in optionalFields.filter((f) => revealed.includes(f.name))"
                :key="field.name"
                :field="field"
                :model-value="form[field.name]"
                @update:model-value="form[field.name] = $event"
                @fill="applyFill"
            />

            <div v-if="hidden.length" class="border-t border-neutral-50 pt-3">
                <p class="mb-2 text-label uppercase text-neutral-500">Add field</p>
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="field in hidden"
                        :key="field.name"
                        type="button"
                        class="rounded-md bg-neutral-25 px-2 py-1 text-caption text-neutral-700 transition-colors hover:bg-accent-50 hover:text-accent-700"
                        @click="revealed.push(field.name)"
                    >
                        + {{ field.label }}
                    </button>
                </div>
            </div>
        </aside>
    </div>
</template>
