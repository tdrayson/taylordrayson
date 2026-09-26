<script setup>
import Button from '../Ui/Button.vue';
import Eyebrow from '../Ui/Eyebrow.vue';
import StatusInput from './StatusInput.vue';

/**
 * The publish block (status, save state, View and the primary button), then
 * the sidebar fields passed in the default slot. Beside the writing column on
 * desktop; below it on a phone, where the sticky bar does the saving.
 */
defineProps({
    // The status field's definition, or null for a type without one.
    statusField: { type: Object, default: null },
    password: { type: String, default: '' },
    // What the saved status means, or what is stopping the save.
    statusText: { type: String, required: true },
    // The entry's own page, for an entry that already exists.
    viewUrl: { type: String, default: null },
    submitLabel: { type: String, required: true },
    disabled: { type: Boolean, default: false },
});

const status = defineModel('status', { type: String, default: null });

defineEmits(['fill', 'submit']);
</script>

<template>
    <aside class="mt-12 border-t border-neutral-50 pt-8 lg:sticky lg:top-6 lg:mt-0 lg:w-72 lg:shrink-0 lg:self-start lg:border-t-0 lg:pt-0">
        <div class="space-y-3">
            <div v-if="statusField">
                <Eyebrow as="label" :for="statusField.name" class="mb-1 block text-neutral-500">{{ statusField.label }}</Eyebrow>

                <StatusInput
                    :id="statusField.name"
                    :model-value="status ?? 'published'"
                    :password="password"
                    :options="statusField.options ?? []"
                    :readonly="Boolean(statusField.readOnly)"
                    @update:model-value="status = $event"
                    @fill="$emit('fill', $event)"
                />
            </div>

            <p class="hidden text-sm text-neutral-500 lg:block">{{ statusText }}</p>

            <div class="hidden items-center gap-2 lg:flex">
                <Button v-if="viewUrl" :href="viewUrl" variant="secondary" size="lg" class="flex-1">View</Button>

                <Button variant="primary" size="lg" class="flex-1" :disabled="disabled" @click="$emit('submit')">
                    {{ submitLabel }}
                </Button>

                <slot name="menu" />
            </div>
        </div>

        <template v-if="$slots.default">
            <hr class="my-6 border-neutral-50" :class="statusField ? '' : 'hidden lg:block'">

            <slot />
        </template>
    </aside>
</template>
