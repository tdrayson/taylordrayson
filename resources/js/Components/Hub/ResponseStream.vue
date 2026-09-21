<script setup>
import { computed } from 'vue';
import Eyebrow from '../Ui/Eyebrow.vue';
import ResponseRow from './ResponseRow.vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
});

// A labelled break rather than a mark on every row: "new" has to say what it
// means without a key, and a rule between two groups does that on its own.
const fresh = computed(() => props.items.filter((item) => item.isNew));
const earlier = computed(() => props.items.filter((item) => ! item.isNew));
</script>

<template>
    <div>
        <ul v-if="fresh.length" class="space-y-px">
            <ResponseRow v-for="item in fresh" :key="item.id" :item="item" />
        </ul>

        <div v-if="fresh.length && earlier.length" class="my-3 flex items-center gap-3 px-3">
            <Eyebrow as="h3" class="text-neutral-400">Earlier</Eyebrow>
            <span class="h-px flex-1 bg-neutral-50" />
        </div>

        <ul class="space-y-px">
            <ResponseRow v-for="item in earlier" :key="item.id" :item="item" />
        </ul>
    </div>
</template>
