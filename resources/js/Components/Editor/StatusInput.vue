<script setup>
import { ref } from 'vue';
import Icon from '../Ui/Icon.vue';
import Input from '../Ui/Input.vue';
import Select from '../Ui/Select.vue';

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

// Hidden by default so it is not read over a shoulder; the owner can reveal it to share it.
const revealed = ref(false);
</script>

<template>
    <div class="space-y-2">
        <Select :id="id" :model-value="modelValue" :options="options" :readonly="readonly" @update:model-value="emit('update:modelValue', $event)" />

        <!-- new-password stops the browser filling in the owner's sign-in password. -->
        <Input
            v-if="modelValue === 'private'"
            id="password"
            :type="revealed ? 'text' : 'password'"
            placeholder="Password"
            autocomplete="new-password"
            :model-value="password"
            :readonly="readonly"
            @update:model-value="emit('fill', { password: $event })"
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
    </div>
</template>
