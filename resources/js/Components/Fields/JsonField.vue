<script setup>
defineProps({
    field: { type: Object, required: true },
    modelValue: { type: [String, Object, Array], default: '' },
    mode: { type: String, default: 'edit' },
    error: { type: String, default: null },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label class="text-label font-medium text-neutral-700">{{ field.label }}</label>
        <pre v-if="mode === 'display'" class="overflow-x-auto rounded-md bg-neutral-25 p-3 text-caption text-neutral-700">{{ modelValue || 'Not set' }}</pre>
        <template v-else>
            <textarea
                :value="modelValue ?? ''"
                rows="6"
                class="w-full rounded-md border bg-neutral-0 px-3 py-2 font-mono text-caption text-neutral-900 transition-colors focus:outline-none"
                :class="error ? 'border-red-500 focus:border-red-500' : 'border-neutral-100 focus:border-accent-500'"
                @input="$emit('update:modelValue', $event.target.value)"
            />
            <p v-if="error" class="text-caption text-red-600">{{ error }}</p>
        </template>
    </div>
</template>
