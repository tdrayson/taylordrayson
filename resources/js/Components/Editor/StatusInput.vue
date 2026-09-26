<script setup>
import Select from '../Ui/Select.vue';
import PasswordInput from './PasswordInput.vue';

/**
 * The entry's status, and the password a private entry is locked with. The
 * password is a sibling field, so it reaches the form through `fill`.
 */
defineProps({
    id: { type: String, required: true },
    modelValue: { type: String, default: 'published' },
    password: { type: String, default: '' },
    options: { type: Array, default: () => [] },
    readonly: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'fill']);
</script>

<template>
    <div class="space-y-2">
        <Select :id="id" :model-value="modelValue" :options="options" :readonly="readonly" @update:model-value="emit('update:modelValue', $event)" />

        <PasswordInput
            v-if="modelValue === 'private'"
            :model-value="password"
            :readonly="readonly"
            @update:model-value="emit('fill', { password: $event })"
        />
    </div>
</template>
