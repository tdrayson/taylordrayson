<script setup>
import { onMounted, ref } from 'vue';
import Combobox from '../Ui/Combobox.vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { type: [String, Number], default: '' },
    mode: { type: String, default: 'edit' },
    error: { type: String, default: null },
});

defineEmits(['update:modelValue']);

const options = ref([]);
const loading = ref(false);

const slug = usePage().props.resource?.slug;

async function load(term = '') {
    loading.value = true;
    try {
        const params = new URLSearchParams({ field: props.field.key, q: term });
        if (props.modelValue !== '' && props.modelValue !== null) {
            params.set('value', String(props.modelValue));
        }
        const response = await fetch(`/cp/${slug}/options?${params.toString()}`, {
            headers: { Accept: 'application/json' },
        });
        const data = await response.json();
        options.value = data.options ?? [];
    } catch {
        options.value = [];
    } finally {
        loading.value = false;
    }
}

let timer = null;
function search(term) {
    clearTimeout(timer);
    timer = setTimeout(() => load(term), 250);
}

onMounted(() => load());
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label class="text-label font-medium text-neutral-700">{{ field.label }}</label>
        <Combobox
            :model-value="modelValue ?? ''"
            :options="options"
            :loading="loading"
            :invalid="!!error"
            :placeholder="`Select ${field.label.toLowerCase()}`"
            @update:model-value="$emit('update:modelValue', $event)"
            @search="search"
        />
        <p v-if="error" class="text-caption text-red-600">{{ error }}</p>
    </div>
</template>
