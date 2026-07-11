<script setup>
import { ref, onBeforeUnmount } from 'vue';
import { Tick02Icon, Copy01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';
import { copyText } from '../../lib/clipboard';

const props = defineProps({
    label: { type: String, required: true },
    icon: { type: [Array, Object], default: null },
    url: { type: String, required: true },
});

const copied = ref(false);
let timer = null;

async function copy() {
    await copyText(props.url);

    copied.value = true;
    clearTimeout(timer);
    timer = setTimeout(() => (copied.value = false), 2000);
}

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <div>
        <div class="mb-2 flex items-center gap-2 text-meta font-semibold text-neutral-700">
            <Icon v-if="icon" :icon="icon" class="size-4 text-accent-500" />
            {{ label }}
        </div>
        <div class="flex items-stretch gap-2">
            <input
                :value="url"
                type="text"
                readonly
                :title="url"
                class="min-w-0 flex-1 rounded-md border border-neutral-50 bg-neutral-25 px-3 py-2.5 font-mono text-meta text-neutral-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                @focus="$event.target.select()"
            />
            <button
                type="button"
                class="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-neutral-100 px-3 text-meta font-semibold text-neutral-700 transition-colors hover:bg-neutral-25 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                :aria-label="copied ? 'Copied' : 'Copy feed URL'"
                @click="copy"
            >
                <Icon :icon="copied ? Tick02Icon : Copy01Icon" class="size-4" :class="copied ? 'text-accent-500' : ''" />
                <span class="hidden sm:inline">{{ copied ? 'Copied' : 'Copy' }}</span>
            </button>
        </div>
    </div>
</template>
