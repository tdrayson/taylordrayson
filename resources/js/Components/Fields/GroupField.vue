<script setup>
import { computed, defineAsyncComponent } from 'vue';

const FieldRenderer = defineAsyncComponent(() => import('./FieldRenderer.vue'));

const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { type: Object, default: () => ({}) },
    mode: { type: String, default: 'edit' },
    error: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const value = computed(() => props.modelValue ?? {});

function update(key, next) {
    emit('update:modelValue', { ...value.value, [key]: next });
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <FieldRenderer
            v-for="sub in field.fields"
            :key="sub.key"
            :field="sub"
            :mode="mode"
            :model-value="value[sub.key] ?? ''"
            @update:model-value="update(sub.key, $event)"
        />
    </div>
</template>
