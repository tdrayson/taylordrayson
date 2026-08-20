<script setup>
import { computed, ref } from 'vue';
import { BubbleMenu } from '@tiptap/vue-3/menus';
import Icon from '../Ui/Icon.vue';

/**
 * The formatting bar over a selection, and the editor's only way to reach a
 * link: clicking one opens this rather than navigating, since following a link
 * out of a half-written draft is never what was meant.
 *
 * Marks only. Blocks are the slash menu's job, so this stays to what you reach
 * for mid-sentence.
 */
const props = defineProps({
    editor: { type: Object, required: true },
});

// Open only while editing the href, so the bar returns to its buttons after.
const editingLink = ref(false);
const href = ref('');

// A destination on another host. Relative paths and same-host URLs stay in the
// tab; everything else is opened in a new one by the renderer.
const isExternal = computed(() => /^https?:\/\//i.test(href.value.trim())
    && ! href.value.includes(window.location.host));

const BUTTONS = [
    { mark: 'bold', icon: 'TextBoldIcon', label: 'Bold' },
    { mark: 'italic', icon: 'TextItalicIcon', label: 'Italic' },
    { mark: 'strike', icon: 'TextStrikethroughIcon', label: 'Strikethrough' },
    { mark: 'code', icon: 'SourceCodeIcon', label: 'Code' },
];

function toggle(mark) {
    props.editor.chain().focus().toggleMark(mark).run();
}

/**
 * Over a selection, or with the caret merely resting inside a link: the second
 * is what makes clicking a link open the bar instead of following it.
 */
function shouldShow({ editor: instance, from, to }) {
    return from !== to || instance.isActive('link');
}

/** Open the href field, prefilled when the selection is already a link. */
function startLink() {
    href.value = props.editor.getAttributes('link').href ?? '';
    editingLink.value = true;
}

function applyLink() {
    const value = href.value.trim();
    const chain = props.editor.chain().focus().extendMarkRange('link');

    // An emptied field is how you remove a link, rather than a separate control.
    (value === '' ? chain.unsetLink() : chain.setLink({ href: value })).run();

    editingLink.value = false;
}

function cancelLink() {
    editingLink.value = false;
    props.editor.commands.focus();
}
</script>

<template>
    <BubbleMenu
        :editor="editor"
        :options="{ placement: 'top' }"
        :should-show="shouldShow"
        class="flex items-center gap-0.5 rounded-lg border border-neutral-100 bg-neutral-0 p-1 shadow-lg"
    >
        <template v-if="editingLink">
            <input
                v-model="href"
                type="url"
                placeholder="https://"
                class="w-56 rounded px-2 py-1 text-meta text-neutral-900 focus:outline-none"
                autofocus
                @keydown.enter.prevent="applyLink"
                @keydown.esc.prevent="cancelLink"
            >

            <!-- External links open in a new tab when rendered, decided by the
                 host rather than stored per link, so this reports it. -->
            <Icon
                v-if="isExternal"
                name="ArrowUpRight01Icon"
                class="size-4 shrink-0 text-neutral-500"
                title="Opens in a new tab"
            />

            <button
                type="button"
                class="rounded p-1.5 text-neutral-500 transition-colors hover:bg-accent-50 hover:text-accent-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                aria-label="Apply link"
                @click="applyLink"
            ><Icon name="Tick02Icon" class="size-4" /></button>
        </template>

        <template v-else>
            <button
                v-for="button in BUTTONS"
                :key="button.mark"
                type="button"
                class="rounded p-1.5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                :class="editor.isActive(button.mark)
                    ? 'bg-accent-50 text-accent-700'
                    : 'text-neutral-500 hover:bg-neutral-25 hover:text-neutral-900'"
                :aria-label="button.label"
                :aria-pressed="editor.isActive(button.mark)"
                @click="toggle(button.mark)"
            ><Icon :name="button.icon" class="size-4" /></button>

            <span class="mx-0.5 h-4 w-px bg-neutral-100" />

            <button
                type="button"
                class="rounded p-1.5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                :class="editor.isActive('link')
                    ? 'bg-accent-50 text-accent-700'
                    : 'text-neutral-500 hover:bg-neutral-25 hover:text-neutral-900'"
                :aria-label="editor.isActive('link') ? 'Edit link' : 'Add link'"
                @click="startLink"
            ><Icon name="Link02Icon" class="size-4" /></button>
        </template>
    </BubbleMenu>
</template>
