<script setup>
import { computed } from 'vue';
import { NodeViewWrapper } from '@tiptap/vue-3';
import Icon from '../Ui/Icon.vue';

/**
 * A dynamic tag while the editor is open: a reference to live site data,
 * rendered as a quiet inline chip rather than the raw `{tag options}` token.
 *
 * There is no value yet to show, so it displays the tag name; a later store
 * of resolved previews will make the chip show what it actually publishes as.
 */
const props = defineProps({
    node: { type: Object, required: true },
    selected: { type: Boolean, default: false },
});

const tag = computed(() => props.node.attrs.tag ?? '');
const label = computed(() => `Dynamic tag: ${tag.value}`);
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
    ><Icon name="ChartColumnIcon" class="size-3.5 shrink-0" />{{ tag }}</NodeViewWrapper>
</template>
