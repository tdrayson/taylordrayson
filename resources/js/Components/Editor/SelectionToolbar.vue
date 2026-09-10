<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { BubbleMenu } from '@tiptap/vue-3/menus';
import Icon from '../Ui/Icon.vue';
import BlockOptions from './BlockOptions.vue';
import DynamicTagOptions from './DynamicTagOptions.vue';
import { blockOptionsFor } from '../../lib/editor/blockOptions';
import { useMounted } from '../../composables/useMounted';
import { defaultOptionsFor, useDynamicTags } from '../../composables/useDynamicTags';

const mounted = useMounted();
const { tags: dynamicTagList, previewFor } = useDynamicTags();

/** Tags legal as a link target, today just site.social. */
const hrefTags = computed(() => dynamicTagList.value.filter((tag) => tag.supports.includes('href')));

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
const blank = ref(false);

// null while editing a typed URL; a tag name once the author points the link
// at a dynamic tag instead.
const linkTagName = ref(null);
const linkTagOptions = ref({});
const linkTag = computed(() => hrefTags.value.find((candidate) => candidate.name === linkTagName.value) ?? null);
const linkTagPreview = computed(() => (linkTag.value ? (previewFor(linkTag.value.name, linkTagOptions.value, 'href') ?? linkTag.value.label) : linkTagName.value));
const tagOptionsOpen = ref(false);

const BUTTONS = [
    { mark: 'bold', icon: 'TextBoldIcon', label: 'Bold' },
    { mark: 'italic', icon: 'TextItalicIcon', label: 'Italic' },
    { mark: 'underline', icon: 'TextUnderlineIcon', label: 'Underline' },
    { mark: 'strike', icon: 'TextStrikethroughIcon', label: 'Strikethrough' },
    { mark: 'code', icon: 'SourceCodeIcon', label: 'Code' },
];

function toggle(mark) {
    props.editor.chain().focus().toggleMark(mark).run();
}

/**
 * Over a selection, or with the caret resting inside a link, which is what makes
 * clicking a link open the bar instead of following it.
 *
 * Block options are deliberately not here: they belong to the block, not to
 * where the caret happens to be, so they get their own panel below.
 */
function shouldShow({ editor: instance, from, to }) {
    return from !== to || onLink(instance);
}

/**
 * Whether the caret rests on a link, tolerating its left edge.
 *
 * The mark is not inclusive, so a caret sitting before the first character does
 * not carry it and `isActive('link')` is false there. That edge is exactly
 * where clicking a link lands, so asking what follows the caret too is what
 * makes a clicked link register as one.
 */
function onLink(instance) {
    if (instance.isActive('link')) {
        return true;
    }

    const type = instance.schema.marks.link;
    const after = instance.state.selection.$from.nodeAfter;

    return !! type && !! after && type.isInSet(after.marks) !== undefined;
}

// Tracked rather than computed: the caret moving is an editor event, not a
// reactive dependency Vue can see on its own.
const block = ref(null);
const panel = ref(null);

// Set by a click outside, cleared the moment the editor is used again. A flag
// rather than a focus check: focus moves into the panel's own fields, and a
// blur fires before the new element is current, so neither says what is meant.
let dismissed = false;

/**
 * The configurable block the caret is inside, and the slot inside that block to
 * put its panel in. Every block renders one, so the panel needs no coordinates:
 * it lands where the block already says it should go.
 */
function trackBlock() {
    const definition = dismissed ? null : blockOptionsFor(props.editor);

    if (! definition) {
        block.value = null;

        return;
    }

    const { selection } = props.editor.state;
    const { $from } = selection;
    let position = null;

    // A leaf like an image is selected, never entered, so there is no ancestor
    // to walk up to: the selection itself is the node.
    if (selection.node?.type.name === definition.type) {
        position = $from.pos;
    } else {
        for (let depth = $from.depth; depth > 0; depth--) {
            if ($from.node(depth).type.name === definition.type) {
                position = $from.before(depth);
                break;
            }
        }
    }

    const element = position === null ? null : props.editor.view.nodeDOM(position);
    const anchor = element?.querySelector?.('[data-block-panel]') ?? null;

    block.value = anchor ? { definition, anchor } : null;
}

/**
 * Anything outside the block and its panel puts it away. The caret leaving is
 * already handled by trackBlock, but clicking elsewhere on the page leaves the
 * selection where it was, so the panel would otherwise stay up over a block
 * nobody is editing.
 */
function onDocumentPointerDown(event) {
    const inPanel = panel.value?.contains(event.target);
    const inEditor = props.editor.view.dom.contains(event.target);

    dismissed = ! inPanel && ! inEditor;

    if (dismissed) {
        block.value = null;
    }
}

onMounted(() => {
    props.editor.on('selectionUpdate', trackBlock);
    props.editor.on('transaction', trackBlock);

    document.addEventListener('pointerdown', onDocumentPointerDown, true);
});

onBeforeUnmount(() => {
    props.editor.off('selectionUpdate', trackBlock);
    props.editor.off('transaction', trackBlock);

    document.removeEventListener('pointerdown', onDocumentPointerDown, true);
});

/** A destination on another site, which is what defaults to a new tab. */
function isExternal(value) {
    try {
        return new URL(String(value ?? '').trim(), window.location.href).host !== window.location.host;
    } catch {
        return false;
    }
}

/** Open the href field, prefilled when the selection is already a link. */
function startLink() {
    // At the mark's left edge the caret carries nothing, so getAttributes() and
    // the extendMarkRange() in applyLink() would both miss the link that is
    // plainly under the pointer. Step one character in and they behave.
    if (! props.editor.isActive('link') && onLink(props.editor)) {
        props.editor.commands.setTextSelection(props.editor.state.selection.from + 1);
    }

    const link = props.editor.getAttributes('link');

    linkTagName.value = link.tag ?? null;
    linkTagOptions.value = link.options ?? {};
    href.value = link.href ?? '';
    // A new link to another host defaults to opening away, which is what is
    // wanted almost every time; the toggle is for the exceptions.
    // Unset means "decide by host", which for a new external link is a new tab.
    blank.value = link.target ? link.target === '_blank' : isExternal(href.value);
    editingLink.value = true;
}

function applyLink() {
    const chain = props.editor.chain().focus().extendMarkRange('link');

    if (linkTagName.value) {
        // href/target explicitly nulled so a mode switched away from a typed
        // URL doesn't leave its old value merged onto the mark.
        chain.setLink({ tag: linkTagName.value, options: linkTagOptions.value, href: null, target: null }).run();
    } else {
        const value = href.value.trim();

        // An emptied field is how you remove a link, rather than a separate control.
        (value === ''
            ? chain.unsetLink()
            : chain.setLink({ href: value, target: blank.value ? '_blank' : '_self', tag: null, options: null })
        ).run();
    }

    editingLink.value = false;
}

function cancelLink() {
    editingLink.value = false;
    props.editor.commands.focus();
}

/** Switches the href editor to a dynamic tag, opening its options form immediately when it takes any. */
function selectLinkTag(name) {
    linkTagName.value = name === '' ? null : name;

    if (! linkTagName.value) {
        return;
    }

    const tag = hrefTags.value.find((candidate) => candidate.name === linkTagName.value);

    linkTagOptions.value = tag ? defaultOptionsFor(tag) : {};

    if (tag?.options.length) {
        tagOptionsOpen.value = true;
    }
}

function applyLinkTagOptions(options) {
    linkTagOptions.value = options;
    tagOptionsOpen.value = false;
}
</script>

<template>
    <div>
    <BubbleMenu
        :editor="editor"
        :options="{ placement: 'top' }"
        :should-show="shouldShow"
        class="flex items-center gap-0.5"
    >
        <div class="flex items-center gap-0.5 rounded-lg border border-neutral-100 bg-neutral-0 p-1 shadow-lg">
        <template v-if="editingLink">
            <template v-if="linkTagName">
                <span class="inline-flex max-w-40 items-center gap-1 truncate rounded px-2 py-1 text-meta text-neutral-500">
                    <Icon name="ChartColumnIcon" class="size-3.5 shrink-0" />{{ linkTagPreview }}
                </span>

                <button
                    v-if="linkTag?.options.length"
                    type="button"
                    class="rounded p-1.5 text-neutral-500 transition-colors hover:bg-neutral-25 hover:text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                    :aria-label="`Edit ${linkTag.label} options`"
                    @click="tagOptionsOpen = true"
                ><Icon name="Settings01Icon" class="size-4" /></button>
            </template>

            <input
                v-else
                v-model="href"
                type="url"
                placeholder="https://"
                class="w-56 rounded px-2 py-1 text-meta text-neutral-900 focus:outline-none"
                autofocus
                @keydown.enter.prevent="applyLink"
                @keydown.esc.prevent="cancelLink"
            >

            <select
                v-if="hrefTags.length"
                :value="linkTagName ?? ''"
                aria-label="Link target"
                class="rounded px-1 py-1 text-meta text-neutral-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                @change="selectLinkTag($event.target.value)"
                @keydown.esc.prevent="cancelLink"
            >
                <option value="">Custom URL</option>
                <option v-for="tag in hrefTags" :key="tag.name" :value="tag.name">{{ tag.label }}</option>
            </select>

            <button
                v-if="! linkTagName"
                type="button"
                class="rounded p-1.5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                :class="blank ? 'bg-accent-50 text-accent-700' : 'text-neutral-500 hover:bg-neutral-25 hover:text-neutral-900'"
                :aria-label="blank ? 'Opens in a new tab' : 'Opens in the same tab'"
                :aria-pressed="blank"
                @click="blank = ! blank"
            ><Icon name="ArrowUpRight01Icon" class="size-4" /></button>

            <button
                type="button"
                class="rounded p-1.5 text-neutral-500 transition-colors hover:bg-accent-50 hover:text-accent-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                aria-label="Apply link"
                @click="applyLink"
            ><Icon name="Tick02Icon" class="size-4" /></button>

            <DynamicTagOptions
                v-if="linkTag"
                :open="tagOptionsOpen"
                :tag="linkTag"
                :options="linkTagOptions"
                placement="href"
                @apply="applyLinkTagOptions"
                @update:open="tagOptionsOpen = $event"
            />
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
                :class="onLink(editor)
                    ? 'bg-accent-50 text-accent-700'
                    : 'text-neutral-500 hover:bg-neutral-25 hover:text-neutral-900'"
                :aria-label="onLink(editor) ? 'Edit link' : 'Add link'"
                @click="startLink"
            ><Icon name="Link02Icon" class="size-4" /></button>
        </template>
        </div>
    </BubbleMenu>

    <!-- Rendered into the block itself, so it scrolls and moves with it and
         sits where the block puts it rather than at a computed offset. -->
    <Teleport v-if="mounted && block" :to="block.anchor">
        <div ref="panel">
            <BlockOptions :editor="editor" :definition="block.definition" />
        </div>
    </Teleport>
    </div>
</template>
