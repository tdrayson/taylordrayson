<script setup>
import Button from '../Ui/Button.vue';
import Eyebrow from '../Ui/Eyebrow.vue';
import StatusInput from './StatusInput.vue';

/**
 * The publish block (status, save state, View and the primary button), then
 * the sidebar fields passed in the default slot. Beside the writing column on
 * desktop; on a phone only the fields show, under it, and the sticky bar publishes.
 */
defineProps({
    // The status field's definition, or null for a type without one.
    statusField: { type: Object, default: null },
    password: { type: String, default: '' },
    // The server's validation message for the status, if the last save was refused.
    statusError: { type: String, default: null },
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
    <!-- Capped to the viewport so the lower fields scroll into reach. The -m-2/p-2
         pair (so w-76 keeps a w-72 column) stops the scroll box clipping focus rings. -->
    <aside
        class="lg:sticky lg:top-4 lg:-m-2 lg:max-h-screen lg:w-76 lg:shrink-0 lg:self-start lg:overflow-y-auto lg:p-2 lg:pb-12"
        :class="$slots.default ? 'mt-12' : ''"
    >
        <div class="space-y-3">
            <div v-if="statusField" class="hidden lg:block">
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

                <p v-if="statusError" class="mt-1 text-xs text-red-600">{{ statusError }}</p>
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
            <hr class="my-6 hidden border-neutral-50 lg:block">

            <slot />
        </template>
    </aside>
</template>
