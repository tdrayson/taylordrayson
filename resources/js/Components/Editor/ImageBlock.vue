<script setup>
import { computed, ref } from 'vue';
import { NodeViewWrapper } from '@tiptap/vue-3';
import Icon from '../Ui/Icon.vue';
import DynamicTagOptions from './DynamicTagOptions.vue';
import { uploadPending } from '../../lib/editor/uploads';
import { defaultOptionsFor, useDynamicTags } from '../../composables/useDynamicTags';

/**
 * An image inside a document. Empty until it has a source, so inserting one
 * gives you the place to drop a file or paste a URL, or point it at a live
 * dynamic photo, rather than a prompt.
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

const { tags, previewFor } = useDynamicTags();

const url = computed(() => props.node.attrs.url);
const tagName = computed(() => props.node.attrs.tag);
/** Tags legal as an image source, today just entries.photo. */
const imageTags = computed(() => tags.value.filter((candidate) => candidate.supports.includes('image')));
/** The photo a tagged image currently resolves to; null until that settles. */
const tagPreviewUrl = computed(() => (tagName.value ? previewFor(tagName.value, props.node.attrs.options ?? {}, 'image') : null));

const pendingImageTag = ref(null);
const pendingImageOptions = ref({});
const imageTagOptionsOpen = ref(false);

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
        props.updateAttributes({ url: parked.url, alt: props.node.attrs.alt ?? '', tag: null, options: null });
    } catch {
        error.value = 'That image could not be uploaded.';
    } finally {
        uploading.value = false;
    }
}

function useTypedUrl() {
    const value = typedUrl.value.trim();

    if (value !== '') {
        props.updateAttributes({ url: value, tag: null, options: null });
    }
}

/** Picking a tag applies at once when it takes no options, otherwise opens its options form first. */
function pickImageTag(tag) {
    if (tag.options.length === 0) {
        props.updateAttributes({ tag: tag.name, options: {}, url: null });

        return;
    }

    pendingImageTag.value = tag;
    pendingImageOptions.value = defaultOptionsFor(tag);
    imageTagOptionsOpen.value = true;
}

function applyImageTag(options) {
    props.updateAttributes({ tag: pendingImageTag.value.name, options, url: null });
    pendingImageTag.value = null;
    imageTagOptionsOpen.value = false;
}
</script>

<template>
    <NodeViewWrapper class="not-prose relative my-8 max-w-media">
        <!-- See CodeBlockView: the block places its own options panel. -->
        <div data-block-panel contenteditable="false" class="absolute bottom-full left-0 z-40 mb-2 w-full"></div>

        <figure v-if="url || tagName" class="group relative" :data-dynamic-tag="tagName || null">
            <img
                v-if="tagName ? tagPreviewUrl : url"
                :src="tagName ? tagPreviewUrl : url"
                :alt="node.attrs.alt ?? ''"
                class="w-full rounded-lg"
                :class="node.attrs.ratio && node.attrs.ratio !== 'original' ? 'object-cover' : ''"
                :style="node.attrs.ratio && node.attrs.ratio !== 'original' ? { aspectRatio: node.attrs.ratio } : null"
            >

            <div v-else class="flex aspect-video items-center justify-center rounded-lg bg-neutral-25 text-caption text-neutral-500">
                Nothing to show yet
            </div>

            <span
                v-if="tagName"
                class="absolute left-2 top-2 rounded bg-neutral-900/70 px-1.5 py-0.5 text-caption text-neutral-0"
            >Live photo</span>

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

            <div v-if="imageTags.length" class="mt-2 flex flex-wrap items-center gap-2 border-t border-neutral-50 pt-2">
                <button
                    v-for="tag in imageTags"
                    :key="tag.name"
                    type="button"
                    class="flex items-center gap-1 rounded px-2 py-1 text-caption text-neutral-500 transition-colors hover:bg-neutral-25 hover:text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                    :aria-label="`Use ${tag.label} as this image`"
                    @click="pickImageTag(tag)"
                ><Icon name="ChartColumnIcon" class="size-3.5 shrink-0" />{{ tag.label }}</button>
            </div>

            <p v-if="error" class="mt-2 text-caption text-red-600">{{ error }}</p>
        </div>

        <DynamicTagOptions
            v-if="pendingImageTag"
            :open="imageTagOptionsOpen"
            :tag="pendingImageTag"
            :options="pendingImageOptions"
            placement="image"
            @apply="applyImageTag"
            @update:open="imageTagOptionsOpen = $event"
        />
    </NodeViewWrapper>
</template>
