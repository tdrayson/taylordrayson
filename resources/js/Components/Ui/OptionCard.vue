<script setup>
import Card from './Card.vue';
import Heading from './Heading.vue';
import Pill from './Pill.vue';

const props = defineProps({
    label: { type: String, required: true },
    description: { type: String, required: true },
    recommended: { type: Boolean, default: false },
    active: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
});

const emit = defineEmits(['select']);

function onClick() {
    if (props.readonly) {
        return;
    }

    emit('select');
}
</script>

<template>
    <button
        type="button"
        class="block w-full rounded-lg text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
        :class="readonly && 'cursor-default'"
        :aria-readonly="readonly || undefined"
        @click="onClick"
    >
        <Card
            variant="outline"
            :class="[
                'h-full transition-colors',
                readonly ? 'bg-neutral-50' : (active ? 'border-accent-500 ring-1 ring-accent-500' : 'hover:border-accent-500'),
            ]"
        >
            <div class="flex items-start justify-between gap-3">
                <Heading as="p" size="title" class="text-neutral-900">{{ label }}</Heading>
                <Pill v-if="recommended" variant="accent" label="Recommended" />
            </div>
            <p class="mt-1.5 text-sm text-neutral-500">{{ description }}</p>
        </Card>
    </button>
</template>
