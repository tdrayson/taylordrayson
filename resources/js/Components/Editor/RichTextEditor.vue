<script setup>
import { onBeforeUnmount, reactive, ref, watch } from 'vue';
import { EditorContent, useEditor, VueNodeViewRenderer } from '@tiptap/vue-3';
import Mention from '@tiptap/extension-mention';
import { extensionsFor } from '../../lib/editor/profiles';
import { toProseMirror } from '../../lib/portable-text/toProseMirror';
import { fromProseMirror } from '../../lib/portable-text/fromProseMirror';
import MentionChip from './MentionChip.vue';
import SuggestionMenu from './SuggestionMenu.vue';

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
            onKeyDown({ event }) {
                if (! menu.open) {
                    return false;
                }

                if (event.key === 'ArrowDown') {
                    menu.active = (menu.active + 1) % Math.max(menu.items.length, 1);

                    return true;
                }

                if (event.key === 'ArrowUp') {
                    menu.active = (menu.active - 1 + menu.items.length) % Math.max(menu.items.length, 1);

                    return true;
                }

                if (event.key === 'Enter' || event.key === 'Tab') {
                    pick(menu.items[menu.active]);

                    return true;
                }

                // The plugin exits on Escape whatever this returns. Claiming
                // the key is what stops it bubbling to the surrounding form,
                // where one Escape would both close the menu and cancel the
                // edit behind it.
                if (event.key === 'Escape') {
                    menu.open = false;

                    return true;
                }

                return false;
            },
            onExit() {
                menu.open = false;
                menu.items = [];
            },
        }),
    },
});

function pick(item) {
    if (! item || ! insert) {
        return;
    }

    // Label the chip immediately; the server resolves it properly on save.
    props.resolved[`${item.kind}:${item.id}`] = { title: item.label, url: null, exists: true };

    insert({ kind: item.kind, id: item.id });
    menu.open = false;
}

const editor = useEditor({
    content: toProseMirror(props.modelValue),
    extensions: extensionsFor(props.profile, { placeholder: props.placeholder, mention }),
    editorProps: {
        attributes: {
            class: 'prose-editor focus:outline-none min-h-[8rem]',
        },
    },
    onUpdate: ({ editor: instance }) => {
        emitting.value = true;
        emit('update:modelValue', fromProseMirror(instance.getJSON()));
        emitting.value = false;
    },
});

// Only reload when the change came from outside: re-setting content on our own
// emit would move the caret to the start on every keystroke.
watch(() => props.modelValue, (value) => {
    if (emitting.value || ! editor.value) {
        return;
    }

    editor.value.commands.setContent(toProseMirror(value), false);
});

onBeforeUnmount(() => editor.value?.destroy());
</script>

<template>
    <div>
        <EditorContent :editor="editor" />

        <SuggestionMenu
            v-if="menu.open"
            :items="menu.items"
            :active="menu.active"
            :rect="menu.rect"
            @pick="pick"
        />
    </div>
</template>
