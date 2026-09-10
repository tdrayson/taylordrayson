<script setup>
import { computed } from 'vue';
import { NodeViewWrapper } from '@tiptap/vue-3';
import Icon from '../Ui/Icon.vue';
import { useDynamicTags } from '../../composables/useDynamicTags';

/**
 * A dynamic tag while the editor is open: a reference to live site data,
 * rendered as a quiet inline chip showing what it currently resolves to
 * rather than the raw `{tag options}` token.
 */
const props = defineProps({
    node: { type: Object, required: true },
    selected: { type: Boolean, default: false },
});

const { previewFor } = useDynamicTags();

const tag = computed(() => props.node.attrs.tag ?? '');
const label = computed(() => `Dynamic tag: ${tag.value}`);
// Falls back to the tag name while the registry is still loading, or once
// its options move away from the defaults `previewFor` knows a value for.
const display = computed(() => previewFor(tag.value, props.node.attrs.options ?? {}) ?? tag.value);
</script>

<template>
    <NodeViewWrapper
        as="span"
        contenteditable="false"
        :aria-label="label"
        :class="[
            'inline-flex items-center gap-1 rounded bg-neutral-25 px-1 py-0.5 align-baseline font-medium text-neutral-900',
            selected ? 'ring-2 ring-accent-500' : '',
        ]"
    ><Icon name="ChartColumnIcon" class="size-3.5 shrink-0" />{{ display }}</NodeViewWrapper>
</template>
