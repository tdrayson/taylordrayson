<script setup>
import { ref } from 'vue';
import { CONTROL, CONTROL_BORDER } from '../../lib/editor/control.js';
import Input from '../Ui/Input.vue';

/**
 * The entry's status, and the password a private entry is locked with. The
 * password is a sibling field, so it reaches the form through `fill`.
 */
defineProps({
    id: { type: String, required: true },
    modelValue: { type: String, default: 'published' },
    options: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue', 'fill']);

const password = ref('');

// Pushed on every keystroke so the form already holds it when Save is pressed.
function updatePassword(value) {
    password.value = value;
    emit('fill', { password: value });
}
</script>

<template>
    <div class="space-y-2">
        <select
            :id="id"
            :value="modelValue"
            :class="[CONTROL, CONTROL_BORDER, 'text-neutral-900']"
            @change="emit('update:modelValue', $event.target.value)"
        >
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>

        <Input
            v-if="modelValue === 'private'"
            id="password"
            type="password"
            placeholder="Password"
            :model-value="password"
            @update:model-value="updatePassword"
        />
    </div>
</template>
