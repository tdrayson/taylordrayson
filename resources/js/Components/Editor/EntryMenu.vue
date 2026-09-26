<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { copyText } from '../../lib/clipboard.js';
import Button from '../Ui/Button.vue';
import DropdownMenu from '../Ui/DropdownMenu.vue';
import Icon from '../Ui/Icon.vue';
import Modal from '../Ui/Modal.vue';

/**
 * The editor's "More actions" menu for a saved entry: view, copy its link,
 * duplicate it into a new one, or delete it after a confirmation.
 */
const props = defineProps({
    // The entry's own page, relative to the site.
    viewUrl: { type: String, required: true },
    // Where a confirmed delete is sent; null where the type cannot be deleted here.
    deleteUrl: { type: String, default: null },
    // Offer Duplicate, which the editor carries out since it holds the values.
    canDuplicate: { type: Boolean, default: false },
    // What the delete confirmation calls the entry, e.g. its title; null for a title-less note.
    name: { type: String, default: null },
    // Open the panel above the trigger, for a trigger at the foot of the screen.
    above: { type: Boolean, default: false },
});

const emit = defineEmits(['duplicate']);

const page = usePage();
const copied = ref(false);
const confirming = ref(false);
const deleting = ref(false);

/** Copy the absolute permalink, then say so briefly. */
async function copyLink() {
    await copyText(new URL(props.viewUrl, page.props.appUrl).href);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

function destroy() {
    router.delete(props.deleteUrl, {
        onStart: () => (deleting.value = true),
        onFinish: () => (deleting.value = false),
    });
}

const items = computed(() => [
    { label: 'View on site', href: props.viewUrl },
    { label: 'Copy link', action: copyLink },
    props.canDuplicate ? { label: 'Duplicate', action: () => emit('duplicate') } : null,
    props.deleteUrl ? { label: 'Delete', action: () => (confirming.value = true), danger: true } : null,
].filter(Boolean));
</script>

<template>
    <div class="relative shrink-0">
        <DropdownMenu :items="items" label="More actions" align="right" :panel-class="above ? 'bottom-full mb-2' : 'mt-2'">
            <template #trigger="{ open, toggle }">
                <Button
                    variant="secondary"
                    size="icon"
                    class="size-11"
                    aria-label="More actions"
                    aria-haspopup="menu"
                    :aria-expanded="open"
                    @click="toggle"
                >
                    <Icon name="MoreHorizontalIcon" class="size-5" />
                </Button>
            </template>
        </DropdownMenu>

        <!-- Kept in the DOM so the live region is announced when its text changes. -->
        <span role="status" class="sr-only">{{ copied ? 'Link copied' : '' }}</span>

        <span
            v-if="copied"
            aria-hidden="true"
            class="pointer-events-none absolute right-0 z-50 whitespace-nowrap rounded-md bg-black px-3 py-1.5 text-xs font-medium text-white shadow-card"
            :class="above ? 'bottom-full mb-2' : 'top-full mt-2'"
        >
            Link copied
        </span>

        <Modal v-model:open="confirming" title="Delete this entry?">
            <p class="text-sm text-neutral-700">{{ name ? `“${name}” will be deleted.` : 'This entry will be deleted.' }} This can't be undone.</p>

            <div class="mt-6 flex justify-end gap-2">
                <Button variant="secondary" @click="confirming = false">Cancel</Button>
                <Button variant="destructive" :disabled="deleting" @click="destroy">Delete</Button>
            </div>
        </Modal>
    </div>
</template>
