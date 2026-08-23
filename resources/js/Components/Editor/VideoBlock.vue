<script setup>
import { computed, ref } from 'vue';
import { NodeViewWrapper } from '@tiptap/vue-3';
import Icon from '../Ui/Icon.vue';
import { videoSource } from '../../lib/video';
import VideoEmbed from '../Ui/VideoEmbed.vue';

/**
 * A video inside a document, drawn as the embed it will publish as. There is no
 * dropzone: a video streams from wherever it is hosted rather than being
 * uploaded here, so the block only ever takes a URL.
 */
const props = defineProps({
    node: { type: Object, required: true },
    updateAttributes: { type: Function, required: true },
    deleteNode: { type: Function, required: true },
});

const typedUrl = ref('');

const url = computed(() => props.node.attrs.url);

// Recognised by videoSource means it will play; anything else stores fine and
// then renders as a dead player, which is worth saying before it is published.
const unsupported = computed(() => Boolean(url.value) && videoSource(url.value) === null);

function useTypedUrl() {
    const value = typedUrl.value.trim();

    if (value !== '') {
        props.updateAttributes({ url: value });
        typedUrl.value = '';
    }
}
</script>

<template>
    <NodeViewWrapper class="not-prose relative my-8 max-w-media">
        <!-- See CodeBlockView: the block places its own options panel. -->
        <div data-block-panel contenteditable="false" class="absolute bottom-full left-0 z-40 mb-2 w-full"></div>

        <!-- The published component, not a copy of it: the still an author sets
             is then the one they are looking at, and the editor stops loading a
             player for every video in the document. -->
        <div v-if="url" class="group relative" contenteditable="false">
            <VideoEmbed
                v-if="! unsupported"
                :url="url"
                :caption="node.attrs.caption"
                :poster="node.attrs.poster"
            />

            <p v-else class="flex items-center gap-2 rounded-lg border border-dashed border-neutral-100 p-4 text-meta text-neutral-500">
                <Icon name="Alert02Icon" class="size-4 shrink-0" />
                <span>Not a YouTube, Vimeo or video-file URL, so this will not play.</span>
            </p>

            <button
                type="button"
                class="absolute right-2 top-2 z-10 rounded-md bg-neutral-900/70 p-1.5 text-neutral-0 opacity-0 transition-opacity hover:bg-neutral-900 focus-visible:opacity-100 group-hover:opacity-100"
                aria-label="Remove video"
                @click="deleteNode()"
            ><Icon name="Delete02Icon" class="size-4" /></button>
        </div>

        <div v-else contenteditable="false" class="rounded-lg border border-dashed border-neutral-100 p-4">
            <div class="flex min-h-11 items-center gap-2">
                <Icon name="PlayIcon" class="size-4 shrink-0 text-neutral-500" />

                <input
                    v-model="typedUrl"
                    type="url"
                    placeholder="Paste a YouTube, Vimeo or video-file URL"
                    class="min-w-0 flex-1 bg-transparent text-meta text-neutral-900 placeholder:text-neutral-400 focus:outline-none"
                    @keydown.enter.prevent="useTypedUrl"
                >

                <button
                    type="button"
                    class="rounded px-2 py-1 text-caption text-neutral-500 transition-colors hover:bg-accent-50 hover:text-accent-700"
                    @click="useTypedUrl"
                >Use</button>
            </div>
        </div>
    </NodeViewWrapper>
</template>
