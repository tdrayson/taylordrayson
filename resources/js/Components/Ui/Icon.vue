<script setup>
import { computed } from 'vue';
import { HugeiconsIcon } from '@hugeicons/vue';
import { icons } from '../../icons';

const props = defineProps({
    // Reference a registered icon by its export name (<Icon name="Settings01Icon" />)
    // so callers don't import from hugeicons themselves.
    name: { type: String, default: null },
    // A raw icon object, or a registry-name string (for data-driven icons whose
    // value comes from a map, e.g. per entry type). Strings resolve via the registry.
    icon: { type: [Array, Object, String], default: null },
    strokeWidth: { type: [Number, String], default: 1.7 },
});

// A string in either prop is a registry name; a non-string `icon` is a raw icon.
const resolved = computed(() => {
    const source = props.icon ?? props.name;

    return typeof source === 'string' ? (icons[source] ?? null) : (source ?? null);
});

if (import.meta.env.DEV) {
    const source = props.icon ?? props.name;

    if (typeof source === 'string' && !icons[source]) {
        console.warn(`[Icon] "${source}" is not in the icon registry (resources/js/icons.js).`);
    }
}
</script>

<template>
    <HugeiconsIcon v-if="resolved" :icon="resolved" :stroke-width="strokeWidth" color="currentColor" aria-hidden="true" />
</template>
