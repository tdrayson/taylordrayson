<script setup>
// Recursive renderer for Editor.js list blocks. Items are either plain strings
// (classic list tool) or { content, items } objects (nested list tool).
defineProps({
    items: { type: Array, default: () => [] },
    ordered: { type: Boolean, default: false },
});

const text = (item) => (typeof item === 'string' ? item : (item?.content ?? ''));
const children = (item) => (item && typeof item === 'object' && Array.isArray(item.items) ? item.items : []);
</script>

<template>
    <component
        :is="ordered ? 'ol' : 'ul'"
        class="ml-5 list-outside space-y-1.5"
        :class="ordered ? 'list-decimal' : 'list-disc'"
    >
        <li v-for="(item, i) in items" :key="i">
            <span v-html="text(item)"></span>
            <EditorList v-if="children(item).length" :items="children(item)" :ordered="ordered" class="mt-1.5" />
        </li>
    </component>
</template>
