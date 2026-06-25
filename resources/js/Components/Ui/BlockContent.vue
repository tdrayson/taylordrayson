<script setup>
import { computed } from 'vue';
import EditorList from './EditorList.vue';

// Read-only renderer for an Editor.js document. Accepts the parsed document
// object (content is cast to an array server-side) or a raw JSON string.
const props = defineProps({
    document: { type: [Object, Array, String], default: null },
});

const blocks = computed(() => {
    let doc = props.document;

    if (typeof doc === 'string') {
        try {
            doc = JSON.parse(doc);
        } catch {
            return [];
        }
    }

    return Array.isArray(doc?.blocks) ? doc.blocks : [];
});

const headingTag = (level) => `h${Math.min(Math.max(Number(level) || 2, 1), 6)}`;
const imageUrl = (data) => data?.file?.url ?? data?.url ?? null;
</script>

<template>
    <div v-if="blocks.length" class="block-content space-y-5 text-body text-neutral-900">
        <template v-for="(block, i) in blocks" :key="i">
            <component
                :is="headingTag(block.data.level)"
                v-if="block.type === 'header'"
                class="font-display text-neutral-900"
                :class="(Number(block.data.level) || 2) <= 2 ? 'text-item-title' : 'text-lg font-semibold'"
                v-html="block.data.text"
            ></component>

            <p v-else-if="block.type === 'paragraph'" v-html="block.data.text"></p>

            <EditorList
                v-else-if="block.type === 'list' || block.type === 'nestedlist'"
                :items="block.data.items"
                :ordered="block.data.style === 'ordered'"
            />

            <ul v-else-if="block.type === 'checklist'" class="space-y-1.5">
                <li v-for="(item, j) in block.data.items" :key="j" class="flex items-start gap-2">
                    <span
                        class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded border"
                        :class="item.checked ? 'border-accent-500 bg-accent-500 text-neutral-0' : 'border-neutral-50'"
                    >
                        <svg v-if="item.checked" viewBox="0 0 16 16" class="size-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8l3.5 3.5L13 5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </span>
                    <span :class="item.checked ? 'text-neutral-500 line-through' : ''" v-html="item.text"></span>
                </li>
            </ul>

            <blockquote v-else-if="block.type === 'quote'" class="border-l-2 border-accent-200 pl-4 text-neutral-700">
                <span v-html="block.data.text"></span>
                <cite v-if="block.data.caption" class="mt-1 block text-meta not-italic text-neutral-500" v-html="block.data.caption"></cite>
            </blockquote>

            <pre v-else-if="block.type === 'code'" class="overflow-x-auto rounded-lg bg-neutral-25 p-4 text-meta"><code>{{ block.data.code }}</code></pre>

            <hr v-else-if="block.type === 'delimiter'" class="border-neutral-50" />

            <figure v-else-if="block.type === 'image' && imageUrl(block.data)">
                <img :src="imageUrl(block.data)" :alt="block.data.caption || ''" class="w-full rounded-lg border border-neutral-50">
                <figcaption v-if="block.data.caption" class="mt-2 text-center text-meta text-neutral-500" v-html="block.data.caption"></figcaption>
            </figure>
        </template>
    </div>
</template>

<style scoped>
.block-content :deep(a) {
    color: var(--color-accent-500);
    text-decoration: underline;
    text-underline-offset: 2px;
}

.block-content :deep(a:hover) {
    color: var(--color-accent-700);
}

.block-content :deep(code) {
    border-radius: 4px;
    background: var(--color-neutral-25);
    padding: 0.1em 0.35em;
    font-size: 0.9em;
}

.block-content :deep(mark) {
    background: var(--color-accent-200);
    border-radius: 2px;
}
</style>
