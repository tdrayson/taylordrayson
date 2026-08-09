<script setup>
import { computed } from 'vue';
import { NodeViewWrapper } from '@tiptap/vue-3';

/**
 * How a mention is drawn while the editor is open, so it reads as a chip from
 * the moment it is picked rather than from the moment the field is left.
 *
 * The node stores kind and id only, so the label has to be resolved. The
 * suggestion menu hands one over when inserting; on a document loaded from the
 * server the resolved map does. Neither is stored, which is the point: a
 * renamed target shows its new name next time, not the name it had when it was
 * mentioned.
 */
const props = defineProps({
    node: { type: Object, required: true },
    extension: { type: Object, required: true },
});

const resolved = computed(() => {
    const { kind, id } = props.node.attrs;

    return props.extension.options.resolved?.[`${kind}:${id}`] ?? null;
});

// Falls back to the kind rather than the raw id: "@article" reads as a
// reference that has not loaded, where "@42" reads as a bug.
const label = computed(() => resolved.value?.title ?? `@${props.node.attrs.kind ?? 'unknown'}`);

const missing = computed(() => resolved.value !== null && resolved.value.exists === false);
</script>

<template>
    <NodeViewWrapper as="span" class="inline">
        <span
            :class="[
                'inline-flex items-center rounded px-1 py-0.5 text-meta font-medium',
                missing
                    ? 'bg-neutral-25 text-neutral-500 line-through'
                    : 'bg-accent-50 text-accent-700',
            ]"
            :title="missing ? 'This entry no longer exists' : null"
        >{{ label }}</span>
    </NodeViewWrapper>
</template>
