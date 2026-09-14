<script setup>
import { inject, ref } from 'vue';
import Button from '../Ui/Button.vue';
import Icon from '../Ui/Icon.vue';
import Modal from '../Ui/Modal.vue';
import ImageField from './ImageField.vue';

/**
 * A book's cover: the standard image field, plus a dialog of the covers the book
 * has been printed with, searched by the title and author in the form right now.
 */
defineOptions({ inheritAttrs: false });

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);

// Provided by EntryEditor, so the search follows a title changed but not yet saved.
const form = inject('editorForm', null);

const open = ref(false);
const covers = ref([]);
const loading = ref(false);
const failed = ref(false);

// Bumped on every call so a slower earlier fetch, resolving after a newer one, is dropped instead of overwriting it.
let requestId = 0;

/** Open the dialog and fetch covers for whichever book the form names now. */
async function openCovers() {
    open.value = true;
    loading.value = true;
    failed.value = false;

    const query = [form?.title, form?.['meta.author']].filter(Boolean).join(' ');
    const id = ++requestId;

    try {
        const response = await fetch(`/lookup/book-covers?${new URLSearchParams({ q: query })}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (id !== requestId) {
            return;
        }

        failed.value = ! response.ok;
        covers.value = response.ok ? (await response.json()).data ?? [] : [];
    } catch {
        if (id !== requestId) {
            return;
        }

        failed.value = true;
        covers.value = [];
    } finally {
        if (id === requestId) {
            loading.value = false;
        }
    }
}

/** Whether a cover is the one already in the field. */
const isChosen = (url) => props.modelValue[0]?.url === url;

/** Put the chosen cover in the field; the server downloads it on save. */
function choose(url) {
    emit('update:modelValue', [{ id: `url:${url}`, name: 'Cover', url }]);
    open.value = false;
}
</script>

<template>
    <div>
        <ImageField v-bind="$attrs" :model-value="modelValue" @update:model-value="emit('update:modelValue', $event)">
            <template #actions>
                <Button size="sm" variant="secondary" :disabled="! form?.title" @click="openCovers">
                    <Icon name="Image01Icon" class="size-4" />
                    Choose from editions
                </Button>
            </template>
        </ImageField>

        <Modal v-model:open="open" title="Choose a cover" close-label="Close covers" panel-class="max-w-3xl">
            <p v-if="loading" class="text-meta text-neutral-500">Finding covers...</p>
            <p v-else-if="failed" class="text-meta text-red-600">Covers are unavailable right now, so upload one instead.</p>
            <p v-else-if="! covers.length" class="text-meta text-neutral-500">No covers found for this book.</p>

            <ul v-else class="grid grid-cols-3 gap-4 sm:grid-cols-5">
                <li v-for="(url, index) in covers" :key="url">
                    <button
                        type="button"
                        :aria-label="`Edition ${index + 1}`"
                        :aria-pressed="isChosen(url)"
                        class="group relative block w-full rounded-md focus-visible:outline-none"
                        @click="choose(url)"
                    >
                        <span
                            class="block aspect-2/3 overflow-hidden rounded-md bg-neutral-25 shadow-card ring-offset-2 ring-offset-neutral-0 transition group-hover:ring-2 group-focus-visible:ring-2"
                            :class="isChosen(url) ? 'ring-2 ring-accent-500' : 'group-hover:ring-neutral-200 group-focus-visible:ring-accent-500'"
                        >
                            <img :src="url" alt="" loading="lazy" class="size-full object-cover">
                        </span>

                        <span
                            v-if="isChosen(url)"
                            class="absolute -right-2 -top-2 flex size-6 items-center justify-center rounded-full bg-accent-500 text-neutral-0 ring-2 ring-neutral-0"
                        >
                            <Icon name="Tick02Icon" class="size-4" :stroke-width="2.5" />
                        </span>
                    </button>
                </li>
            </ul>
        </Modal>
    </div>
</template>
