<script setup>
import Eyebrow from './Eyebrow.vue';
import Heading from './Heading.vue';

defineProps({
    // Optional citation shown beneath the quote.
    cite: { type: String, default: null },
    // Accent for the left rule: 'default', 'accent', 'fuel', 'food' or 'flight'.
    tone: { type: String, default: 'default' },
});

// Left-rule colour per tone.
const TONES = {
    default: 'border-neutral-200',
    accent: 'border-accent-500',
    fuel: 'border-fuel',
    food: 'border-food',
    flight: 'border-flight',
};
</script>

<template>
    <figure class="my-8 border-l-2 pl-5" :class="TONES[tone] ?? TONES.default">
        <Heading as="blockquote" size="title" class="max-w-2xl text-neutral-900">
            <slot />
        </Heading>
        <Eyebrow v-if="cite" as="figcaption" class="mt-3 text-neutral-500">{{ cite }}</Eyebrow>
    </figure>
</template>
