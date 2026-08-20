<script setup>
import { computed, ref } from 'vue';
import Icon from '../Ui/Icon.vue';
import { CONTROL_BORDER } from '../../lib/editor/control.js';

/**
 * Upload for an entry's images. Handles both a single cover and a gallery: the
 * only difference is how many it will hold, so one component covers both rather
 * than two that drift apart.
 *
 * The value is an ordered list of items. An item already attached carries its
 * media uuid; one uploaded while the form is open carries a `pending:` token,
 * which the server swaps for real media on save.
 */
const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    // 'image' holds one, 'gallery' holds many.
    multiple: { type: Boolean, default: false },
    id: { type: String, default: null },
    invalid: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const dragging = ref(false);
const uploading = ref(0);
const error = ref(null);

const items = computed(() => (Array.isArray(props.modelValue) ? props.modelValue : []));
const full = computed(() => ! props.multiple && items.value.length >= 1);

/**
 * Laravel ships the token as the XSRF-TOKEN cookie rather than a meta tag here,
 * and expects it back URL-decoded in X-XSRF-TOKEN.
 */
function csrf() {
    const cookie = document.cookie.split('; ').find((part) => part.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '';
}

// The long edge the server also caps at, so a file that is already small
// enough here is not re-encoded twice.
const MAX_DIMENSION = 1920;
const QUALITY = 0.82;

/**
 * Shrink and re-encode before sending. A phone photo is several megabytes of
 * something nothing ever serves at that size, and uploading it wastes the
 * author's connection as much as the server's disk.
 *
 * SVG is left alone, since rasterising a vector is a downgrade. HEIC is tried:
 * Safari decodes it and iPhone photos are the large ones worth shrinking before
 * they are sent, and anywhere that cannot decode it falls through to the server.
 *
 * Re-encoding drops EXIF, which loses the embedded GPS along with everything
 * else. That suits a public photo, but it is a real loss, not a free win.
 */
async function shrink(file) {
    if (! ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'].includes(file.type)) {
        return file;
    }

    try {
        // from-image so a portrait phone photo is not re-encoded on its side.
        const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
        const scale = Math.min(1, MAX_DIMENSION / Math.max(bitmap.width, bitmap.height));

        if (scale === 1 && file.type === 'image/webp') {
            return file;
        }

        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/webp', QUALITY));

        // A small PNG screenshot can come out bigger as WebP; keep the better one.
        if (! blob || blob.size >= file.size) {
            return file;
        }

        return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.webp', { type: 'image/webp' });
    } catch {
        // Anything the browser cannot decode goes up as it is, and the server
        // deals with it.
        return file;
    }
}

async function upload(original) {
    const file = await shrink(original);
    const body = new FormData();
    body.append('file', file);

    uploading.value++;

    try {
        const response = await fetch('/media/pending', {
            method: 'POST',
            body,
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': csrf() },
        });

        if (! response.ok) {
            const payload = await response.json().catch(() => null);

            error.value = payload?.message ?? 'That file could not be uploaded.';

            return;
        }

        const { data } = await response.json();

        // A single-image field replaces rather than appends: picking a second
        // cover means changing your mind, not keeping both.
        emit('update:modelValue', props.multiple ? [...items.value, data] : [data]);
        error.value = null;
    } catch {
        error.value = 'That file could not be uploaded.';
    } finally {
        uploading.value--;
    }
}

function add(files) {
    const chosen = props.multiple ? [...files] : [...files].slice(0, 1);

    chosen.filter((file) => file.type.startsWith('image/')).forEach(upload);
}

function onDrop(event) {
    dragging.value = false;
    add(event.dataTransfer?.files ?? []);
}

function onPick(event) {
    add(event.target.files ?? []);
    // Cleared so choosing the same file twice in a row still fires a change.
    event.target.value = '';
}

function remove(index) {
    emit('update:modelValue', items.value.filter((_, position) => position !== index));
}

/** Move one image one place along, so the running order is arrangeable. */
function move(index, by) {
    const next = [...items.value];
    const target = index + by;

    if (target < 0 || target >= next.length) {
        return;
    }

    [next[index], next[target]] = [next[target], next[index]];
    emit('update:modelValue', next);
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <ul v-if="items.length" class="flex flex-wrap gap-2">
            <li
                v-for="(item, index) in items"
                :key="item.id"
                class="group relative overflow-hidden rounded-md border border-neutral-100 bg-neutral-25"
            >
                <img :src="item.url" :alt="item.name" class="size-24 object-cover">

                <div class="absolute inset-x-0 bottom-0 flex justify-between gap-1 bg-neutral-900/70 px-1 py-0.5 opacity-0 transition-opacity group-hover:opacity-100 group-focus-within:opacity-100">
                    <div v-if="multiple" class="flex gap-0.5">
                        <button
                            type="button"
                            class="rounded p-0.5 text-neutral-0 hover:text-accent-300 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-neutral-0"
                            :aria-label="`Move ${item.name} earlier`"
                            :disabled="index === 0"
                            @click="move(index, -1)"
                        ><Icon name="ArrowLeft01Icon" class="size-4" /></button>

                        <button
                            type="button"
                            class="rounded p-0.5 text-neutral-0 hover:text-accent-300 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-neutral-0"
                            :aria-label="`Move ${item.name} later`"
                            :disabled="index === items.length - 1"
                            @click="move(index, 1)"
                        ><Icon name="ArrowRight01Icon" class="size-4" /></button>
                    </div>

                    <span v-else />

                    <button
                        type="button"
                        class="rounded p-0.5 text-neutral-0 hover:text-red-300 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-neutral-0"
                        :aria-label="`Remove ${item.name}`"
                        @click="remove(index)"
                    ><Icon name="Delete02Icon" class="size-4" /></button>
                </div>
            </li>
        </ul>

        <!-- A label rather than a div: the whole zone is then the click target
             natively, and the sr-only input keeps its own keyboard focus. -->
        <label
            v-if="! full"
            class="flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-md border border-dashed px-3 py-6 text-meta transition-colors focus-within:ring-2 focus-within:ring-accent-500"
            :class="[
                dragging ? 'border-accent-500 bg-accent-50 text-accent-700' : 'text-neutral-500 hover:border-accent-500 hover:text-accent-700',
                invalid ? 'border-red-500' : CONTROL_BORDER,
            ]"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <Icon name="Image01Icon" class="size-4 shrink-0" />

            <span v-if="uploading">Uploading...</span>
            <span v-else>Choose {{ multiple ? 'images' : 'an image' }} or drop {{ multiple ? 'them' : 'it' }} here</span>

            <input
                :id="id"
                type="file"
                accept="image/*"
                class="sr-only"
                :multiple="multiple"
                @change="onPick"
            >
        </label>

        <p v-if="error" class="text-caption text-red-600">{{ error }}</p>
    </div>
</template>
