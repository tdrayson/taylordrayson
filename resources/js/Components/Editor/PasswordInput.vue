<script setup>
import { ref } from 'vue';
import Icon from '../Ui/Icon.vue';
import Input from '../Ui/Input.vue';

/** The password a private entry is locked with, hidden until the owner reveals it. */
defineProps({
    id: { type: String, default: 'password' },
    modelValue: { type: String, default: '' },
    readonly: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);

// Hidden by default so it is not read over a shoulder; the owner can reveal it to share it.
const revealed = ref(false);
</script>

<template>
    <!-- new-password stops the browser filling in the owner's sign-in password. -->
    <Input
        :id="id"
        :type="revealed ? 'text' : 'password'"
        placeholder="Password"
        autocomplete="new-password"
        :model-value="modelValue"
        :readonly="readonly"
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <template #suffix>
            <button
                type="button"
                class="-mr-1.5 flex size-8 items-center justify-center rounded-md transition-colors hover:text-neutral-900"
                :aria-label="revealed ? 'Hide password' : 'Show password'"
                :aria-pressed="revealed"
                @click="revealed = ! revealed"
            >
                <Icon :name="revealed ? 'ViewOffSlashIcon' : 'ViewIcon'" class="size-4" />
            </button>
        </template>
    </Input>
</template>
