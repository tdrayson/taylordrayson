<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    placement: { type: String, default: 'bottom' },
});

const isTop = computed(() => props.placement === 'top');
</script>

<template>
    <span class="group relative inline-flex">
        <slot />
        <span
            role="tooltip"
            aria-hidden="true"
            class="pointer-events-none absolute left-1/2 z-50 -translate-x-1/2 whitespace-nowrap rounded-md bg-neutral-900 px-3 py-1.5 text-xs font-medium text-white opacity-0 shadow-card transition-opacity duration-150 group-hover:opacity-100 group-focus-within:opacity-100"
            :class="isTop ? 'bottom-full mb-2' : 'top-full mt-2'"
        >
            {{ label }}
            <span class="absolute left-1/2 size-2 -translate-x-1/2 rotate-45 bg-neutral-900" :class="isTop ? '-bottom-1' : '-top-1'" />
        </span>
    </span>
</template>
