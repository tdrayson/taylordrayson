<script setup>
import StarterKit from '@tiptap/starter-kit';
import TiptapLink from '@tiptap/extension-link';
import { Placeholder } from '@tiptap/extensions';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { nextTick, ref } from 'vue';
import { fromProseMirror } from '../../lib/portable-text/fromProseMirror.js';
import { cn } from '../../lib/cn.js';
import Icon from '../Ui/Icon.vue';

/**
 * The comment box, once somebody starts writing in it.
 *
 * Loaded on demand and deliberately smaller than the site's own editor: the
 * marks here are exactly the ones ContributedDocument accepts on the server, so
 * the editor cannot compose a comment the endpoint will refuse. Lists,
 * headings, blockquotes and code blocks are off for that reason, not for taste.
 */
const props = defineProps({
    // Text already typed into the plain textarea this replaces.
    initialText: { type: String, default: '' },
    placeholder: { type: String, default: 'Add a comment' },
    // The id of the visible-to-screen-readers label, since a contenteditable
    // is not a form control and `<label for>` does not name one.
    labelledBy: { type: String, required: true },
    invalid: { type: Boolean, default: false },
});

const emit = defineEmits(['update:document']);

const linking = ref(false);
const href = ref('');
const hrefField = ref(null);

const editor = useEditor({
    content: props.initialText ? `<p>${props.initialText.replace(/[<>&]/g, '')}</p>` : '',
    autofocus: 'end',
    extensions: [
        StarterKit.configure({
            heading: false,
            blockquote: false,
            bulletList: false,
            orderedList: false,
            listItem: false,
            horizontalRule: false,
            codeBlock: false,
            strike: false,
            link: false,
        }),
        TiptapLink.configure({ openOnClick: false, linkOnPaste: true, autolink: true }),
        Placeholder.configure({ placeholder: () => props.placeholder }),
    ],
    editorProps: {
        attributes: {
            'aria-labelledby': props.labelledBy,
            role: 'textbox',
            'aria-multiline': 'true',
            class: 'prose-comment min-h-24 w-full px-3 py-2 text-meta text-neutral-900 focus:outline-none',
        },
    },
    onUpdate: ({ editor: instance }) => emit('update:document', fromProseMirror(instance.getJSON())),
});

/** Toolbar buttons, so the row is a list rather than four near-identical blocks. */
const MARKS = [
    { name: 'bold', icon: 'TextBoldIcon', label: 'Bold' },
    { name: 'italic', icon: 'TextItalicIcon', label: 'Italic' },
    { name: 'underline', icon: 'TextUnderlineIcon', label: 'Underline' },
    { name: 'code', icon: 'SourceCodeIcon', label: 'Code' },
];

const toggle = (name) => editor.value?.chain().focus().toggleMark(name).run();

/**
 * Open the address field for the current selection. Linking nothing would put
 * the mark where the caret is and leave it attached to the next thing typed.
 */
async function startLink() {
    if (editor.value?.state.selection.empty) {
        return;
    }

    href.value = editor.value.getAttributes('link').href ?? '';
    linking.value = true;
    await nextTick();
    hrefField.value?.focus();
}

function applyLink() {
    const url = href.value.trim();
    const chain = editor.value?.chain().focus();

    // Only http(s), matching the server: anything else is refused there, so
    // offering it here would build a comment that cannot be posted.
    if (/^https?:\/\/\S+$/.test(url)) {
        chain.setLink({ href: url }).run();
    } else if (url === '') {
        chain.unsetLink().run();
    }

    linking.value = false;
    href.value = '';
}
</script>

<template>
    <div :class="cn('rounded-md border bg-neutral-0 transition-colors', invalid ? 'border-red-500' : 'border-neutral-100 focus-within:border-accent-500')">
        <div class="flex items-center gap-1 border-b border-neutral-50 px-2 py-1.5">
            <button
                v-for="mark in MARKS"
                :key="mark.name"
                type="button"
                :aria-label="mark.label"
                :title="mark.label"
                :aria-pressed="editor?.isActive(mark.name) ?? false"
                :class="cn(
                    'rounded p-1.5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500',
                    editor?.isActive(mark.name) ? 'bg-neutral-25 text-accent-700' : 'text-neutral-500 hover:text-neutral-900',
                )"
                @click="toggle(mark.name)"
            >
                <Icon :name="mark.icon" class="size-4" />
            </button>

            <button
                type="button"
                aria-label="Add a link"
                title="Add a link"
                :disabled="editor?.state.selection.empty ?? true"
                :aria-pressed="editor?.isActive('link') ?? false"
                :class="cn(
                    'rounded p-1.5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500',
                    editor?.isActive('link') ? 'bg-neutral-25 text-accent-700' : 'text-neutral-500 hover:text-neutral-900',
                    (editor?.state.selection.empty ?? true) && 'cursor-not-allowed opacity-40',
                )"
                @click="startLink"
            >
                <Icon name="Link02Icon" class="size-4" />
            </button>
        </div>

        <div v-if="linking" class="flex gap-2 border-b border-neutral-50 px-2 py-2">
            <label class="sr-only" for="comment-link-href">Address for the selected text</label>
            <input
                id="comment-link-href"
                ref="hrefField"
                v-model="href"
                type="url"
                placeholder="https://example.com"
                class="min-w-0 flex-1 rounded border border-neutral-100 bg-neutral-0 px-2 py-1 text-caption text-neutral-900 focus:border-accent-500 focus:outline-none"
                @keydown.enter.prevent="applyLink"
                @keydown.escape="linking = false"
            >
            <button type="button" class="shrink-0 rounded px-2 text-caption text-neutral-700 hover:text-accent-500" @click="applyLink">
                Apply
            </button>
        </div>

        <EditorContent :editor="editor" />
    </div>
</template>

<style scoped>
/* The editor's own paragraphs, which sit outside the prose plugin's reach. */
.prose-comment :deep(p) {
    margin: 0;
}

.prose-comment :deep(p + p) {
    margin-top: 0.5rem;
}

.prose-comment :deep(a) {
    text-decoration: underline;
    text-underline-offset: 2px;
}

/* Tiptap's placeholder, which only renders on the first empty paragraph. */
.prose-comment :deep(p.is-editor-empty:first-child::before) {
    content: attr(data-placeholder);
    color: var(--color-neutral-500);
    float: left;
    height: 0;
    pointer-events: none;
}
</style>
