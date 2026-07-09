<script setup>
import { ref, onBeforeUnmount } from 'vue';
import { Link04Icon, Tick02Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from './Icon.vue';
import Tooltip from './Tooltip.vue';

const props = defineProps({
    // The id of the heading this button copies a link to.
    targetId: { type: String, required: true },
    // Plain-text heading label, used for the accessible name.
    label: { type: String, default: '' },
});

// Whether the link was just copied (drives the tick icon + label swap).
const copied = ref(false);
let timer = null;

/**
 * Copy the absolute URL for this heading's anchor to the clipboard, with the
 * same non-secure-context fallback as CodeBlock.vue, then show a transient
 * copied state.
 *
 * @returns {Promise<void>}
 */
async function copy() {
    const url = `${window.location.origin}${window.location.pathname}#${props.targetId}`;

    try {
        await navigator.clipboard.writeText(url);
    } catch {
        const scratch = document.createElement('textarea');
        scratch.value = url;
        document.body.appendChild(scratch);
        scratch.select();
        document.execCommand('copy');
        scratch.remove();
    }

    copied.value = true;
    clearTimeout(timer);
    timer = setTimeout(() => (copied.value = false), 2000);
}

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <!-- Revealed by the heading's group hover (see PortableTextBlocks.js) or
         by keyboard focus on the button itself. -->
    <Tooltip :label="copied ? 'Copied' : 'Copy link'" placement="top" class="ml-2 align-middle">
        <button
            type="button"
            class="inline-flex rounded text-neutral-400 opacity-0 transition-opacity hover:text-neutral-700 focus-visible:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 group-hover/heading:opacity-100"
            :class="copied ? 'opacity-100 text-accent-500 hover:text-accent-500' : ''"
            :aria-label="copied ? 'Link copied' : `Copy link to section: ${label || targetId}`"
            @click="copy"
        >
            <Icon :icon="copied ? Tick02Icon : Link04Icon" class="size-4" />
        </button>
    </Tooltip>
</template>
