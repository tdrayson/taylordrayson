<script setup>
import { ref } from 'vue';
import { DragHandle } from '@tiptap/extension-drag-handle-vue-3';
import { Add01Icon, DragDropVerticalIcon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';

/**
 * The pair of controls in the left margin, following whichever block is hovered:
 * a plus that starts a new one and a grip that drags this one somewhere else.
 *
 * Positioning, hit detection and the drag itself all belong to TipTap's drag
 * handle extension. What is here is the chrome and what the plus does.
 */
const props = defineProps({
    editor: { type: Object, required: true },
});

// Where the hovered block starts, which is what both buttons act on.
const position = ref(null);

// Roughly the control's own height: two 16px icons in 4px of padding.
const CONTROL = 24;

/**
 * How far down to push the controls so they sit on the block's first line
 * rather than at the top of its box. A heading's line box is much taller than
 * the controls, and a block may lead with padding, so aligning the two boxes
 * leaves the controls floating above the text they belong to.
 */
const offset = ref(0);

function trackNode({ editor, pos }) {
    position.value = pos ?? null;

    const element = pos === null || pos === undefined ? null : editor.view.nodeDOM(pos);

    if (! (element instanceof HTMLElement)) {
        offset.value = 0;

        return;
    }

    const style = getComputedStyle(element);
    const line = parseFloat(style.lineHeight) || parseFloat(style.fontSize) * 1.5;

    offset.value = Math.max(0, parseFloat(style.paddingTop || '0') + (line - CONTROL) / 2);
}

/**
 * Open a fresh block below this one and offer the block list, which is the
 * whole point of the button: it saves reaching for the end of the line first.
 */
function insertBelow() {
    if (position.value === null) {
        return;
    }

    const node = props.editor.state.doc.nodeAt(position.value);

    if (! node) {
        return;
    }

    const end = position.value + node.nodeSize;

    props.editor
        .chain()
        .focus()
        .insertContentAt(end, { type: 'paragraph' })
        // Inside the new paragraph, past its opening token.
        .setTextSelection(end + 1)
        .insertContent('/')
        .run();
}

/**
 * Select the block the grip belongs to. Dragging works without this; it is for
 * the click, which otherwise lands on a control that appears to do nothing.
 */
function selectNode() {
    if (position.value !== null) {
        props.editor.commands.setNodeSelection(position.value);
    }
}
</script>

<template>
    <!-- Desktop only: the controls appear on hover, and below `lg` there is no
         margin beside the column to put them in. -->
    <DragHandle
        :editor="editor"
        :on-node-change="trackNode"
        :compute-position-config="{ placement: 'left-start' }"
        class="hidden items-start gap-0.5 pr-2 lg:flex"
        :style="{ paddingTop: `${offset}px` }"
    >
        <button
            type="button"
            draggable="false"
            aria-label="Insert a block below"
            class="rounded p-1 text-neutral-300 transition-colors hover:bg-neutral-25 hover:text-neutral-700 focus-visible:bg-neutral-25 focus-visible:text-neutral-700 focus-visible:outline-none"
            @click="insertBelow"
        >
            <Icon :icon="Add01Icon" class="size-4" />
        </button>

        <button
            type="button"
            draggable="false"
            aria-label="Select this block, or drag it to move it"
            class="cursor-grab rounded p-1 text-neutral-300 transition-colors hover:bg-neutral-25 hover:text-neutral-700 focus-visible:bg-neutral-25 focus-visible:text-neutral-700 focus-visible:outline-none active:cursor-grabbing"
            @click="selectNode"
        >
            <Icon :icon="DragDropVerticalIcon" class="size-4" />
        </button>
    </DragHandle>
</template>
