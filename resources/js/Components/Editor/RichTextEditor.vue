<script setup>
import { onBeforeUnmount, reactive, ref, watch } from 'vue';
import { EditorContent, useEditor, VueNodeViewRenderer } from '@tiptap/vue-3';
import Mention from '@tiptap/extension-mention';
import { Callout } from '../../lib/editor/nodes';
import { extensionsFor } from '../../lib/editor/profiles';
import { toProseMirror } from '../../lib/portable-text/toProseMirror';
import { fromProseMirror } from '../../lib/portable-text/fromProseMirror';
import MentionChip from './MentionChip.vue';
import SuggestionMenu from './SuggestionMenu.vue';
import SelectionToolbar from './SelectionToolbar.vue';
import CalloutBlock from './CalloutBlock.vue';
import { blocksFor } from '../../lib/editor/blocks';
import { suggestionKeys } from '../../lib/editor/suggestionKeys';
import { SlashCommands } from '../../lib/editor/slashCommands';

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
    // kind:id -> {title, url, exists}, so a loaded document can label its
    // mentions before anything is typed.
    resolved: { type: Object, default: () => ({}) },
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

const mention = Mention.extend({
    // Selectable so a click takes the whole chip: the default leaves it
    // unselectable, which is what lets a caret land beside its parts.
    selectable: true,

    addAttributes() {
        return {
            kind: { default: null },
            id: { default: null },
        };
    },
    addNodeView() {
        return VueNodeViewRenderer(MentionChip);
    },
}).configure({
    // Read by the chip to label a mention without storing the label.
    resolved: props.resolved,
    suggestion: {
        char: '@',
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

    // Label the chip immediately; the server resolves it properly on save.
    props.resolved[`${item.kind}:${item.id}`] = { title: item.label, url: null, exists: true };

    insert({ kind: item.kind, id: item.id });
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

const slash = SlashCommands.configure({
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

const editor = useEditor({
    content: toProseMirror(props.modelValue),
    extensions: extensionsFor(props.profile, { placeholder: props.placeholder, mention, slash, callout }),
    editorProps: {
        attributes: {
            class: 'prose-editor focus:outline-none min-h-32',
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
