<script setup>
import Card from './Card.vue';
import Pill from './Pill.vue';

defineProps({
    label: { type: String, required: true },
    description: { type: String, required: true },
    recommended: { type: Boolean, default: false },
    active: { type: Boolean, default: false },
});

defineEmits(['select']);
</script>

<template>
    <button
        type="button"
        class="block w-full rounded-lg text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
        @click="$emit('select')"
    >
        <Card
            variant="outline"
            :class="['h-full transition-colors', active ? 'border-accent-500 ring-1 ring-accent-500' : 'hover:border-accent-500']"
        >
            <div class="flex items-start justify-between gap-3">
                <p class="font-display text-item-title text-neutral-900">{{ label }}</p>
                <Pill v-if="recommended" variant="accent" label="Recommended" />
            </div>
            <p class="mt-1.5 text-meta text-neutral-500">{{ description }}</p>
        </Card>
    </button>
</template>
