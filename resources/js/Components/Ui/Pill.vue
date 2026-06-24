<script setup>
defineProps({
    label: { type: [String, Number], required: true },
    accent: { type: Boolean, default: false },
});
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-1 text-label uppercase"
        :class="accent ? 'bg-accent-soft text-accent-active' : 'bg-surface text-ink-2'"
    >{{ label }}</span>
</template>
