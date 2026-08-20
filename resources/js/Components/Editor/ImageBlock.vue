<script setup>
import { computed, ref } from 'vue';
import { NodeViewWrapper } from '@tiptap/vue-3';
import Icon from '../Ui/Icon.vue';
import { uploadPending } from '../../lib/editor/uploads';

/**
 * An image inside a document. Empty until it has a source, so inserting one
 * gives you the place to drop a file or paste a URL rather than a prompt.
 */
const props = defineProps({
    node: { type: Object, required: true },
    updateAttributes: { type: Function, required: true },
    deleteNode: { type: Function, required: true },
});

const dragging = ref(false);
const uploading = ref(false);
const error = ref(null);
const typedUrl = ref('');

const url = computed(() => props.node.attrs.url);

async function upload(file) {
    if (! file?.type?.startsWith('image/')) {
        return;
    }

    uploading.value = true;
    error.value = null;

    try {
        const parked = await uploadPending(file);

        // The parked URL stands in until the entry saves, at which point the
        // server attaches the file and rewrites this to where it landed.
        props.updateAttributes({ url: parked.url, alt: props.node.attrs.alt ?? '' });
    } catch {
        error.value = 'That image could not be uploaded.';
    } finally {
        uploading.value = false;
    }
}

function useTypedUrl() {
    const value = typedUrl.value.trim();

    if (value !== '') {
        props.updateAttributes({ url: value });
    }
}
</script>

<template>
    <NodeViewWrapper class="not-prose relative my-8 max-w-media">
        <!-- See CodeBlockView: the block places its own options panel. -->
        <div data-block-panel contenteditable="false" class="absolute bottom-full left-0 z-40 mb-2 w-full"></div>

        <figure v-if="url" class="group relative">
            <img
                :src="url"
                :alt="node.attrs.alt ?? ''"
                class="w-full rounded-lg"
                :class="node.attrs.ratio && node.attrs.ratio !== 'original' ? 'object-cover' : ''"
                :style="node.attrs.ratio && node.attrs.ratio !== 'original' ? { aspectRatio: node.attrs.ratio } : null"
            >

            <button
                type="button"
                contenteditable="false"
                class="absolute right-2 top-2 rounded-md bg-neutral-900/70 p-1.5 text-neutral-0 opacity-0 transition-opacity hover:bg-neutral-900 focus-visible:opacity-100 group-hover:opacity-100"
                aria-label="Remove image"
                @click="deleteNode()"
            ><Icon name="Delete02Icon" class="size-4" /></button>

            <figcaption v-if="node.attrs.caption" contenteditable="false" class="mt-2 text-caption text-neutral-500">
                {{ node.attrs.caption }}
            </figcaption>
        </figure>

        <div
            v-else
            contenteditable="false"
            class="rounded-lg border border-dashed p-4 transition-colors"
            :class="dragging ? 'border-accent-500 bg-accent-50' : 'border-neutral-100'"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="dragging = false; upload($event.dataTransfer?.files?.[0])"
        >
            <label class="flex min-h-11 cursor-pointer items-center justify-center gap-2 text-meta text-neutral-500 transition-colors hover:text-accent-700">
                <Icon name="Image01Icon" class="size-4 shrink-0" />
                <span v-if="uploading">Uploading...</span>
                <span v-else>Drop an image here, or choose one</span>

                <input type="file" accept="image/*" class="sr-only" @change="upload($event.target.files?.[0])">
            </label>

            <div class="mt-3 flex items-center gap-2 border-t border-neutral-50 pt-3">
                <input
                    v-model="typedUrl"
                    type="url"
                    placeholder="or paste an image URL"
                    class="min-w-0 flex-1 bg-transparent text-meta text-neutral-900 placeholder:text-neutral-400 focus:outline-none"
                    @keydown.enter.prevent="useTypedUrl"
                >

                <button
                    type="button"
                    class="rounded px-2 py-1 text-caption text-neutral-500 transition-colors hover:bg-accent-50 hover:text-accent-700"
                    @click="useTypedUrl"
                >Use</button>
            </div>

            <p v-if="error" class="mt-2 text-caption text-red-600">{{ error }}</p>
        </div>
    </NodeViewWrapper>
</template>
