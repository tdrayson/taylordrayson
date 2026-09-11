<script setup>
import { computed, ref } from 'vue';
import { NodeViewWrapper } from '@tiptap/vue-3';
import Icon from '../Ui/Icon.vue';
import FileCard from '../Ui/FileCard.vue';
import { uploadPending } from '../../lib/editor/uploads';

/**
 * A downloadable file inside a document, drawn as the card it will publish as.
 *
 * Empty until it has something to offer, and there are two ways to give it one:
 * upload a file, or name a GitHub repository and the asset to take from its
 * latest release.
 */
const props = defineProps({
    node: { type: Object, required: true },
    updateAttributes: { type: Function, required: true },
    deleteNode: { type: Function, required: true },
});

// Mirrors the server's upload rule. Checked here only so a wrong file says so
// immediately rather than after a round trip.
const ACCEPTED = [
    'zip', 'gz', 'tar',
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'rtf',
    'txt', 'md', 'csv', 'json', 'xml',
];

const ACCEPT = ACCEPTED.map((extension) => `.${extension}`).join(',');

const dragging = ref(false);
const uploading = ref(false);
const error = ref(null);
const repo = ref('');
const asset = ref('');

const attrs = computed(() => props.node.attrs);

// A release is ready as soon as it is named; an upload needs the file itself.
const ready = computed(() => (attrs.value.source === 'github'
    ? Boolean(attrs.value.repo && attrs.value.asset)
    : Boolean(attrs.value.url)));

async function upload(file) {
    if (! file) {
        return;
    }

    if (! ACCEPTED.includes(file.name.split('.').pop()?.toLowerCase())) {
        error.value = `Accepted files: ${ACCEPTED.join(', ')}.`;

        return;
    }

    uploading.value = true;
    error.value = null;

    try {
        const parked = await uploadPending(file);

        // Name, type and size come off the file so the card reads correctly
        // while the entry is still a draft. The server stamps its own copies
        // from the attachment when the parked upload is claimed on save.
        props.updateAttributes({
            source: 'upload',
            url: parked.url,
            name: file.name,
            mime: file.type || null,
            size: file.size,
        });
    } catch {
        error.value = 'That file could not be uploaded.';
    } finally {
        uploading.value = false;
    }
}

function useRelease() {
    const owner = repo.value.trim();
    const name = asset.value.trim();

    if (owner !== '' && name !== '') {
        props.updateAttributes({ source: 'github', repo: owner, asset: name });
    }
}
</script>

<template>
    <NodeViewWrapper class="not-prose relative my-8 max-w-media">
        <!-- See CodeBlockView: the block places its own options panel. -->
        <div data-block-panel contenteditable="false" class="absolute bottom-full left-0 z-40 mb-2 w-full"></div>

        <!-- The published card, not a copy of it, so what you are looking at is
             what publishes. A release shows no version here: resolving it is the
             server's job and the editor has not asked. -->
        <div v-if="ready" class="group relative" contenteditable="false">
            <FileCard
                :source="attrs.source"
                :url="attrs.url"
                :name="attrs.name"
                :mime="attrs.mime"
                :size="attrs.size"
                :repo="attrs.repo"
                :asset="attrs.asset"
                :poster="attrs.poster"
                :title="attrs.title"
            />

            <div class="absolute right-2 top-2 z-10 flex gap-1 opacity-0 transition-opacity focus-within:opacity-100 group-hover:opacity-100">
                <!-- Swapping the file in place rather than deleting the block
                     and starting again, which is what bumping a version is.
                     The old attachment goes when the document stops naming it. -->
                <label
                    v-if="attrs.source !== 'github'"
                    class="cursor-pointer rounded-md bg-neutral-900/70 p-1.5 text-neutral-0 transition-colors hover:bg-neutral-900 focus-within:bg-neutral-900"
                    :aria-label="uploading ? 'Replacing file' : 'Replace file'"
                >
                    <Icon :name="uploading ? 'RefreshIcon' : 'Download01Icon'" class="size-4 rotate-180" />
                    <input type="file" :accept="ACCEPT" class="sr-only" @change="upload($event.target.files?.[0])">
                </label>

                <button
                    type="button"
                    class="rounded-md bg-neutral-900/70 p-1.5 text-neutral-0 transition-colors hover:bg-neutral-900 focus-visible:bg-neutral-900"
                    aria-label="Remove file"
                    @click="deleteNode()"
                ><Icon name="Delete02Icon" class="size-4" /></button>
            </div>

            <p v-if="error" class="mt-2 text-caption text-red-600">{{ error }}</p>
        </div>

        <div
            v-else
            contenteditable="false"
            class="rounded-lg border border-dashed p-4 transition-colors"
            :class="dragging ? 'border-accent-500 bg-accent-50' : 'border-neutral-100'"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="dragging = false; upload($event.dataTransfer?.files?.[0])"
        >
            <label class="flex min-h-11 cursor-pointer items-center justify-center gap-2 text-meta text-neutral-500 transition-colors hover:text-accent-700 focus-within:text-accent-700">
                <Icon name="File01Icon" class="size-4 shrink-0" />
                <span v-if="uploading">Uploading...</span>
                <span v-else>Drop a file here, or choose one</span>

                <input type="file" :accept="ACCEPT" class="sr-only" @change="upload($event.target.files?.[0])">
            </label>

            <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-neutral-50 pt-3">
                <Icon name="GithubIcon" class="size-4 shrink-0 text-neutral-500" />

                <input
                    v-model="repo"
                    type="text"
                    placeholder="owner/repository"
                    class="min-w-0 flex-1 bg-transparent text-meta text-neutral-900 placeholder:text-neutral-400 focus:outline-none"
                    @keydown.enter.prevent="useRelease"
                >

                <input
                    v-model="asset"
                    type="text"
                    placeholder="asset.zip"
                    class="min-w-0 flex-1 bg-transparent text-meta text-neutral-900 placeholder:text-neutral-400 focus:outline-none"
                    @keydown.enter.prevent="useRelease"
                >

                <button
                    type="button"
                    class="rounded px-2 py-1 text-caption text-neutral-500 transition-colors hover:bg-accent-50 hover:text-accent-700 focus-visible:bg-accent-50 focus-visible:text-accent-700 focus-visible:outline-none"
                    @click="useRelease"
                >Use</button>
            </div>

            <p v-if="error" class="mt-2 text-caption text-red-600">{{ error }}</p>
        </div>
    </NodeViewWrapper>
</template>
