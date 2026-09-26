<script setup>
defineProps({
    variant: { type: String, default: 'personal' },
});

const VARIANTS = {
    personal: 'font-display text-base font-semibold text-neutral-500',
    business: 'font-bebas text-2xl uppercase leading-none tracking-wide text-neutral-500',
};
</script>

<template>
    <h2 :class="VARIANTS[variant]" class="px-0.5">
        <slot />
    </h2>
</template>
