<script setup>
import { computed, ref } from 'vue';
import { NodeViewContent, NodeViewWrapper } from '@tiptap/vue-3';
import { CALLOUT_VARIANTS } from '../../lib/editor/callouts';
import { useDismissable } from '../../lib/editor/dismissable';

/**
 * A callout while the editor is open. Draws the panel the reader will see, and
 * makes its label the control that changes which kind it is.
 */
const props = defineProps({
    node: { type: Object, required: true },
    updateAttributes: { type: Function, required: true },
});

const { isOpen: open, root, close, toggle } = useDismissable();

const variant = computed(() => CALLOUT_VARIANTS[props.node.attrs.variant] ?? CALLOUT_VARIANTS.note);

const options = Object.entries(CALLOUT_VARIANTS).map(([value, config]) => ({ value, ...config }));

function choose(value) {
    props.updateAttributes({ variant: value });
    close();
}
</script>

<template>
    <NodeViewWrapper class="not-prose my-8 max-w-media pt-3">
        <div
            ref="root"
            :class="['callout-panel relative rounded-2xl px-6 pb-5 pt-7', variant.panel]"
            :style="{ '--callout-code': variant.code }"
        >
            <button
                type="button"
                contenteditable="false"
                :class="[
                    'absolute -top-3 left-6 inline-block -rotate-2 rounded-md px-3 py-1 font-display text-xs font-bold uppercase tracking-widest shadow-card transition-transform hover:rotate-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500',
                    variant.chip,
                ]"
                aria-label="Change callout kind"
                @click="toggle"
            >{{ variant.label }}</button>

            <ul
                v-if="open"
                contenteditable="false"
                class="absolute -top-1 left-6 z-20 w-40 list-none overflow-hidden rounded-lg border border-neutral-100 bg-neutral-0 py-1 shadow-lg"
            >
                <li v-for="option in options" :key="option.value">
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-meta text-neutral-900 transition-colors hover:bg-accent-50 hover:text-accent-700"
                        @click="choose(option.value)"
                    >
                        <span :class="['size-2.5 shrink-0 rounded-full', option.chip]" />
                        {{ option.label }}
                    </button>
                </li>
            </ul>

            <NodeViewContent />
        </div>
    </NodeViewWrapper>
</template>
