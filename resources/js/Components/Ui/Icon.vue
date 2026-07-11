<script setup>
import { computed } from 'vue';
import { HugeiconsIcon } from '@hugeicons/vue';
import { icons } from '../../icons';

const props = defineProps({
    // Reference a registered icon by its export name (<Icon name="Settings01Icon" />)
    // so callers don't import from hugeicons themselves.
    name: { type: String, default: null },
    // Or pass a raw icon object, for dynamically-chosen icons (e.g. per entry type).
    icon: { type: [Array, Object], default: null },
    strokeWidth: { type: [Number, String], default: 1.7 },
});

// Prefer an explicit icon object; otherwise resolve the name from the registry.
const resolved = computed(() => props.icon ?? icons[props.name] ?? null);

if (import.meta.env.DEV && !props.icon && props.name && !icons[props.name]) {
    console.warn(`[Icon] "${props.name}" is not in the icon registry (resources/js/icons.js).`);
}
</script>

<template>
    <HugeiconsIcon v-if="resolved" :icon="resolved" :stroke-width="strokeWidth" color="currentColor" aria-hidden="true" />
</template>
