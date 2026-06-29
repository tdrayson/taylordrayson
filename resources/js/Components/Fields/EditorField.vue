<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import EditorJS from '@editorjs/editorjs';
import Header from '@editorjs/header';
import List from '@editorjs/list';
import Checklist from '@editorjs/checklist';
import Quote from '@editorjs/quote';
import Code from '@editorjs/code';
import Delimiter from '@editorjs/delimiter';
import Marker from '@editorjs/marker';
import InlineCode from '@editorjs/inline-code';
import SimpleImage from '@editorjs/simple-image';

const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { type: [Object, Array, String], default: null },
    mode: { type: String, default: 'edit' },
    error: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const holder = ref(null);
let editor = null;

/** Editor.js wants a document object; tolerate a stored string or null. */
function toDocument(value) {
    if (typeof value === 'string') {
        try {
            return JSON.parse(value);
        } catch {
            return undefined;
        }
    }

    return value && typeof value === 'object' ? value : undefined;
}

onMounted(() => {
    editor = new EditorJS({
        holder: holder.value,
        readOnly: props.mode === 'display',
        minHeight: 120,
        data: toDocument(props.modelValue),
        tools: {
            header: { class: Header, inlineToolbar: true },
            list: { class: List, inlineToolbar: true },
            checklist: { class: Checklist, inlineToolbar: true },
            quote: { class: Quote, inlineToolbar: true },
            code: Code,
            delimiter: Delimiter,
            image: SimpleImage,
            marker: { class: Marker, shortcut: 'CMD+SHIFT+M' },
            inlineCode: { class: InlineCode, shortcut: 'CMD+SHIFT+C' },
        },
        async onChange(api) {
            emit('update:modelValue', await api.saver.save());
        },
    });
});

onBeforeUnmount(() => {
    editor?.destroy?.();
    editor = null;
});
</script>

<template>
    <div class="flex flex-col gap-2">
        <label class="text-caption font-medium text-neutral-600">{{ field.label }}</label>
        <div
            ref="holder"
            class="editor-holder rounded-md border bg-neutral-0 px-3 py-2"
            :class="error ? 'border-red-500' : 'border-neutral-100'"
        />
        <p v-if="error" class="text-caption text-red-600">{{ error }}</p>
    </div>
</template>

<style scoped>
.editor-holder :deep(.codex-editor__redactor) {
    padding-bottom: 0 !important;
}
</style>
