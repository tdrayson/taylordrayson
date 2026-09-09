<script setup>
import { defineAsyncComponent, ref } from 'vue';
import Icon from '../Ui/Icon.vue';

// Only pulled down if somebody actually has a response to send, which on most
// entries is nobody.
const WebmentionForm = defineAsyncComponent(() => import('./WebmentionForm.vue'));

defineProps({
    // The page being responded to, which the endpoint needs as `target`.
    target: { type: String, required: true },
});

const open = ref(false);
</script>

<template>
    <div>
        <!-- Sits with the entry's other metadata rather than as a bar of its
             own: it is a fact about the post, like its tags or its source. -->
        <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-sm text-caption text-neutral-500 transition-colors hover:text-accent-500 focus-visible:text-accent-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2"
            :aria-expanded="open"
            @click="open = ! open"
        >
            <Icon name="Link02Icon" class="size-3.5" />
            Written a response? Add its URL
        </button>

        <WebmentionForm v-if="open" :target="target" class="mt-3" />
    </div>
</template>
