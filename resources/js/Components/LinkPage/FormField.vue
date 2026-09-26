<script setup>
import Input from '../Ui/Input.vue';
import Textarea from '../Ui/Textarea.vue';

const model = defineModel({ type: String, default: '' });

const props = defineProps({
    name: { type: String, required: true },
    label: { type: String, required: true },
    type: { type: String, default: 'text' },
    required: { type: Boolean, default: false },
    multiline: { type: Boolean, default: false },
    autocomplete: { type: String, default: null },
    // Native validation; the details form has no server to answer with errors.
    pattern: { type: String, default: null },
    placeholder: { type: String, default: '' },
    variant: { type: String, default: 'personal' },
});

const VARIANTS = {
    personal: { label: 'font-display', control: 'focus:border-accent-500' },
    business: { label: 'font-open-sans', control: 'focus:border-tinker-500' },
};

const CONTROL = 'rounded-lg px-4 text-base dark:bg-neutral-25';

const id = `details-${props.name}`;
</script>

<template>
    <div class="flex flex-col gap-2">
        <label :for="id" :class="VARIANTS[variant].label" class="font-semibold text-neutral-900">
            {{ label }}
            <span v-if="!required" class="font-normal text-neutral-500">(optional)</span>
        </label>
        <Textarea
            v-if="multiline"
            :id="id"
            v-model="model"
            :name="name"
            rows="3"
            :placeholder="placeholder"
            :class="[CONTROL, VARIANTS[variant].control, 'py-3']"
        />
        <Input
            v-else
            :id="id"
            v-model="model"
            :name="name"
            :type="type"
            :required="required"
            :autocomplete="autocomplete"
            :pattern="pattern"
            :placeholder="placeholder"
            :class="[CONTROL, VARIANTS[variant].control, 'min-h-12']"
        />
    </div>
</template>
