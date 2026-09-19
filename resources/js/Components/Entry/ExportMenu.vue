<script setup>
import { computed } from 'vue';
import Icon from '../Ui/Icon.vue';
import DropdownMenu from '../Ui/DropdownMenu.vue';

/**
 * The other formats this entry is available in. A quiet third line in the
 * footer, matching the weight of "Tagged" and "Via" above it.
 */
const props = defineProps({
    // [{ extension, type, label, purpose, url }] from the server.
    formats: { type: Array, default: () => [] },
});

// Each row names its extension and what it is for, so seven of them read as a
// list rather than as seven cryptic suffixes. External, because these are
// files: an Inertia visit would try to parse .json as a page response.
const items = computed(() =>
    props.formats.map((format) => ({
        label: `.${format.extension}`,
        description: format.purpose,
        href: format.url,
        external: true,
    })),
);
</script>

<template>
    <DropdownMenu v-if="items.length" :items="items" label="Other formats" width-class="w-64" align="left">
        <template #trigger="{ open, toggle }">
            <button
                type="button"
                class="inline-flex items-center gap-1 text-xs text-neutral-500 transition-colors hover:text-neutral-700 focus-visible:text-neutral-700 focus-visible:outline-none"
                :aria-label="open ? 'Close the format list' : 'Show other formats'"
                aria-haspopup="menu"
                :aria-expanded="open"
                @click="toggle"
            >
                Also as
                <Icon name="ArrowDown01Icon" class="size-3 transition-transform" :class="open ? 'rotate-180' : ''" />
            </button>
        </template>
    </DropdownMenu>
</template>
