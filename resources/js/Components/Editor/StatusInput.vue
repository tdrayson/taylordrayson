<script setup>
import { ref } from 'vue';
import Input from '../Ui/Input.vue';
import Select from '../Ui/Select.vue';

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
        <Select :id="id" :model-value="modelValue" :options="options" class="min-h-11" @update:model-value="emit('update:modelValue', $event)" />

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
