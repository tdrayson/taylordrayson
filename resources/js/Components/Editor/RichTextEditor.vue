<script setup>
import { onBeforeUnmount, reactive, ref, watch } from 'vue';
import { EditorContent, useEditor, VueNodeViewRenderer } from '@tiptap/vue-3';
import TiptapImage from '@tiptap/extension-image';
import CodeBlockLowlight from '@tiptap/extension-code-block-lowlight';
import { lowlight } from '../../lib/editor/lowlight';
import { Callout } from '../../lib/editor/nodes';
import { extensionsFor } from '../../lib/editor/profiles';
import { toProseMirror } from '../../lib/portable-text/toProseMirror';
import { fromProseMirror } from '../../lib/portable-text/fromProseMirror';
import SuggestionMenu from './SuggestionMenu.vue';
import SelectionToolbar from './SelectionToolbar.vue';
import CalloutBlock from './CalloutBlock.vue';
import ImageBlock from './ImageBlock.vue';
import CodeBlockView from './CodeBlockView.vue';
import { blocksFor } from '../../lib/editor/blocks';
import { suggestionKeys } from '../../lib/editor/suggestionKeys';
import { suggestionExtension } from '../../lib/editor/slashCommands';

/**
 * The writing surface. Speaks Portable Text on both sides: it takes the stored
 * document in and emits the stored document out, so nothing outside this
 * component ever sees a ProseMirror node.
 */
const props = defineProps({
    // Portable Text blocks.
    modelValue: { type: Array, default: () => [] },
    profile: { type: String, default: 'document' },
    placeholder: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

/**
 * The menu state lives here, not in the suggestion factory: TipTap builds that
 * once and hands `onKeyDown` only the event, never the current items or the
 * insert command.
 */
const menu = reactive({ open: false, items: [], active: 0, rect: null });
let insert = null;

// The block menu keeps its own state: both triggers can never be open at once,
// but sharing one object would leak the mention menu's items into the block
// list on a fast "/" after an unfinished "@".
const blockMenu = reactive({ open: false, items: [], active: 0, rect: null });
let insertBlock = null;

/** Guards against the editor's own update echoing back in as a prop change. */
const emitting = ref(false);

/** Replace the typed trigger with a link to what was picked. */
function insertEntryLink(instance, range, picked) {
    instance
        .chain()
        .focus()
        .deleteRange(range)
        .insertContent([{ type: 'text', text: picked.label, marks: [{ type: 'link', attrs: { href: picked.url } }] }])
        // Off the link mark, or the words typed next join the link.
        .unsetMark('link')
        .insertContent(' ')
        .run();
}

async function fetchCandidates(query) {
    try {
        const response = await fetch(`/mentions/search?q=${encodeURIComponent(query ?? '')}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (! response.ok) {
            return [];
        }

        return (await response.json()).data ?? [];
    } catch {
        // A failed lookup shows an empty menu rather than breaking the keystroke.
        return [];
    }
}

const mention = suggestionExtension('entryLinks').configure({
    suggestion: {
        char: '@',
        // A picked entry becomes an ordinary link, so it renders as the same
        // chip a pasted internal URL does and survives a later rename the same
        // way. Nothing bespoke is stored.
        command: ({ editor: instance, range, props: entry }) => insertEntryLink(instance, range, entry),
        items: ({ query }) => fetchCandidates(query),
        render: () => ({
            onStart(p) {
                insert = p.command;
                menu.items = p.items;
                menu.active = 0;
                menu.rect = p.clientRect?.() ?? null;
                menu.open = true;
            },
            onUpdate(p) {
                insert = p.command;
                menu.items = p.items;
                menu.active = 0;
                menu.rect = p.clientRect?.() ?? null;
            },
            onKeyDown: ({ event }) => mentionKeys(event),
            onExit() {
                menu.open = false;
                menu.items = [];
            },
        }),
    },
});

const mentionKeys = suggestionKeys(menu, (item) => pick(item));

function pick(item) {
    if (! item || ! insert) {
        return;
    }

    insert(item);
    menu.open = false;
}

function pickBlock(item) {
    if (! item || ! insertBlock) {
        return;
    }

    insertBlock(item);
    blockMenu.open = false;
}

const blockKeys = suggestionKeys(blockMenu, pickBlock);

const slash = suggestionExtension('blockMenu').configure({
    suggestion: {
        char: '/',
        allowSpaces: false,
        command: ({ editor: instance, range, props: block }) => block.run(instance, range),
        items: ({ editor: instance, query }) => blocksFor(instance, query),
        render: () => ({
            onStart(p) {
                insertBlock = p.command;
                blockMenu.items = p.items;
                blockMenu.active = 0;
                blockMenu.rect = p.clientRect?.() ?? null;
                blockMenu.open = true;
            },
            onUpdate(p) {
                insertBlock = p.command;
                blockMenu.items = p.items;
                blockMenu.active = 0;
                blockMenu.rect = p.clientRect?.() ?? null;
            },
            onKeyDown: ({ event }) => blockKeys(event),
            onExit() {
                blockMenu.open = false;
                blockMenu.items = [];
            },
        }),
    },
});

// The panel is drawn as it will be published, and its label doubles as the
// control that changes which kind it is.
const callout = Callout.extend({
    addNodeView() {
        return VueNodeViewRenderer(CalloutBlock);
    },
});

// Highlighted as it is typed, wrapped in the chrome the published page shows.
const codeBlock = CodeBlockLowlight.extend({
    /**
     * `language` comes from the parent. `filename` and `lineNumbers` do not
     * exist there, and an undeclared attribute is dropped on load, so an article
     * would silently lose both from every code block it contains.
     */
    addAttributes() {
        return {
            ...this.parent?.(),
            filename: { default: null, rendered: false },
            // On by default: a code block without them is the exception.
            lineNumbers: { default: true, rendered: false },
        };
    },

    addNodeView() {
        return VueNodeViewRenderer(CodeBlockView);
    },
}).configure({ lowlight });

// Drawn as the figure it will become, with its own dropzone while it is empty.
const image = TiptapImage.extend({
    addNodeView() {
        return VueNodeViewRenderer(ImageBlock);
    },
});

const editor = useEditor({
    content: toProseMirror(props.modelValue),
    extensions: extensionsFor(props.profile, { placeholder: props.placeholder, mention, slash, callout, image, codeBlock }),
    editorProps: {
        attributes: {
            class: 'prose-editor focus:outline-none min-h-32',
        },
        /**
         * Never leave the draft by clicking a link in it. `openOnClick: false`
         * stops TipTap opening one, but a target="_blank" anchor is followed by
         * the browser itself even inside a contenteditable, so the click has to
         * be swallowed here.
         */
        handleClick(view, position, event) {
            const link = event.target?.closest?.('a');

            if (! link) {
                return false;
            }

            event.preventDefault();

            return false;
        },
    },
    onUpdate: ({ editor: instance }) => {
        const document = fromProseMirror(instance.getJSON());

        lastEmitted = JSON.stringify(document);
        emitting.value = true;
        emit('update:modelValue', document);
        emitting.value = false;
    },
});

/**
 * The document exactly as it was last handed out, so an update can be
 * recognised as our own coming back.
 *
 * Re-converting to compare does not work: keys are minted fresh for any node or
 * link that has none, and a span references its link by that key, so no two
 * conversions of the same state ever match. Comparing against what was actually
 * emitted sidesteps the whole problem.
 */
let lastEmitted = null;

/**
 * Only reload when the change came from outside.
 *
 * The guard cannot be a flag alone: the parent's update arrives a tick later,
 * by which time the flag is already down. Without this, every keystroke resets
 * the content, which takes a new empty line with it.
 */
watch(() => props.modelValue, (value) => {
    if (emitting.value || ! editor.value) {
        return;
    }

    if (JSON.stringify(value) === lastEmitted) {
        return;
    }

    editor.value.commands.setContent(toProseMirror(value), false);
});

onBeforeUnmount(() => editor.value?.destroy());
</script>

<template>
    <div>
        <EditorContent :editor="editor" />

        <SelectionToolbar v-if="editor" :editor="editor" />

        <SuggestionMenu
            v-if="menu.open"
            :items="menu.items"
            :active="menu.active"
            :rect="menu.rect"
            empty-label="Nothing to mention"
            @pick="pick"
        />

        <SuggestionMenu
            v-if="blockMenu.open"
            :items="blockMenu.items"
            :active="blockMenu.active"
            :rect="blockMenu.rect"
            empty-label="No matching block"
            @pick="pickBlock"
        />
    </div>
</template>
