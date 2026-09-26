<script setup>
import { computed, nextTick, ref } from 'vue';
import { summariseValue } from '../../lib/editor/summary.js';
import Eyebrow from '../Ui/Eyebrow.vue';
import FieldInput from './FieldInput.vue';

/**
 * A timezone shown as its value and a Change link, since it is nearly always
 * right. Changing it reveals the usual lookup.
 */
const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { type: String, default: null },
    error: { type: String, default: null },
    id: { type: String, default: null },
    // Draws the field's label above the value, for a timezone on a row of its own.
    labelled: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
});

defineEmits(['update:modelValue', 'fill']);

const root = ref(null);
const changing = ref(false);

// A refused value cannot be fixed while only its summary shows.
const revealed = computed(() => changing.value || Boolean(props.error));

/** Swap the summary for the lookup and put the cursor in it. */
async function change() {
    changing.value = true;

    await nextTick();

    root.value?.querySelector('input')?.focus();
}
</script>

<template>
    <div ref="root">
        <FieldInput
            v-if="revealed"
            :id="id"
            :field="field"
            :model-value="modelValue"
            :error="error"
            :readonly="readonly"
            :class="labelled ? '' : 'mt-3'"
            @update:model-value="$emit('update:modelValue', $event)"
            @fill="(values, options) => $emit('fill', values, options)"
        />

        <template v-else>
            <Eyebrow v-if="labelled" as="p" class="text-neutral-500">{{ field.label }}</Eyebrow>

            <div class="flex items-center gap-2 text-sm">
                <span class="min-w-0 truncate text-neutral-500">{{ summariseValue(field, modelValue) || 'Not set' }}</span>

                <button
                    v-if="! readonly"
                    type="button"
                    class="min-h-11 shrink-0 text-accent-500 underline underline-offset-2 hover:text-accent-700"
                    :aria-label="`Change ${field.label.toLowerCase()}`"
                    @click="change"
                >
                    Change
                </button>
            </div>
        </template>
    </div>
</template>
