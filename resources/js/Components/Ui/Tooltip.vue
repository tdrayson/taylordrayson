<script setup>
defineProps({
    label: { type: String, required: true },
});
</script>

<template>
    <span class="group relative inline-flex">
        <slot />
        <span
            role="tooltip"
            aria-hidden="true"
            class="pointer-events-none absolute left-1/2 top-full z-50 mt-2 -translate-x-1/2 whitespace-nowrap rounded-md bg-ink px-3 py-1.5 text-xs font-medium text-white opacity-0 shadow-card transition-opacity duration-150 group-hover:opacity-100"
        >
            {{ label }}
            <span class="absolute -top-1 left-1/2 size-2 -translate-x-1/2 rotate-45 bg-ink" />
        </span>
    </span>
</template>
