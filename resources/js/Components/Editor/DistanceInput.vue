<script setup>
import { ref, watch } from 'vue';
import Input from '../Ui/Input.vue';

/**
 * Distance in miles, stored in metres.
 *
 * Miles because that is the site's default unit and what a British reader
 * would say out loud; metres because that is the canonical storage every
 * distance uses.
 */
const props = defineProps({
    modelValue: { type: [Number, String], default: null },
    id: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const METRES_PER_MILE = 1609.344;
const text = ref('');

watch(() => props.modelValue, (value) => {
    if (document.activeElement?.id === props.id) {
        return;
    }

    const metres = Number(value);
    text.value = metres ? String(Math.round((metres / METRES_PER_MILE) * 10) / 10) : '';
}, { immediate: true });

function onInput(value) {
    text.value = value;
    const miles = parseFloat(value);

    emit('update:modelValue', Number.isNaN(miles) ? null : Math.round(miles * METRES_PER_MILE));
}
</script>

<template>
    <div class="flex items-center gap-2">
        <Input :id="id" :model-value="text" type="number" placeholder="0" class="flex-1" @update:model-value="onInput" />
        <span class="shrink-0 text-meta text-neutral-500">miles</span>
    </div>
</template>
