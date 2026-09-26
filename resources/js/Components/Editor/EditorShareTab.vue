<script setup>
import { computed, ref } from 'vue';
import { usePage, useHttp } from '@inertiajs/vue3';
import { useOgCard } from '../../composables/useOgCard.js';
import Button from '../Ui/Button.vue';
import SharePreview from '../Ui/SharePreview.vue';

/**
 * The Social tab: the share card a link to this entry shows, starting from the
 * saved one and redrawn from the unsaved values on Refresh.
 */
const props = defineProps({
    // The entry page's Open Graph payload, or null for an entry not yet saved.
    og: { type: Object, default: null },
    // Where Refresh posts the values; null where the type has no preview (synced types).
    previewUrl: { type: String, default: null },
    // Returns the form's current values, shaped as a save would send them.
    payload: { type: Function, required: true },
});

/** Past these a platform tends to truncate the text. */
const TITLE_LIMIT = 70;
const DESCRIPTION_LIMIT = 160;

const page = usePage();
const savedImage = useOgCard(() => props.og ?? {});

/** The last card Refresh drew, which replaces the saved one. */
const drawn = ref(null);
const error = ref(null);
const http = useHttp({});

/** The card on show: the latest drawn, else the saved one, else none yet. */
const card = computed(() => {
    if (drawn.value) {
        return drawn.value;
    }

    return savedImage.value
        ? { image: savedImage.value, title: props.og?.title ?? null, description: props.og?.description ?? null }
        : null;
});

/** The bare domain, as a share card labels its source. */
const host = computed(() => {
    try {
        return new URL(page.props.appUrl).hostname.replace(/^www\./, '');
    } catch {
        return null;
    }
});

/** "Title 64 characters. Description 171 characters, may be cut short." */
const lengthLine = computed(() => {
    const part = (label, text, limit) => `${label} ${text.length} characters${text.length > limit ? ', may be cut short' : ''}.`;
    const title = card.value?.title ?? '';
    const description = card.value?.description ?? '';

    return [part('Title', title, TITLE_LIMIT), description ? part('Description', description, DESCRIPTION_LIMIT) : null]
        .filter(Boolean)
        .join(' ');
});

/** The server's reason for a refused preview, or a generic one. */
function messageFrom(exception) {
    try {
        return JSON.parse(exception?.response?.data ?? '')?.message || 'Could not draw the card.';
    } catch {
        return 'Could not draw the card.';
    }
}

/** Draw the card from the form as it stands; nothing is saved. */
async function refresh() {
    error.value = null;

    try {
        drawn.value = await http.transform(() => props.payload()).post(props.previewUrl);
    } catch (exception) {
        error.value = messageFrom(exception);
    }
}
</script>

<template>
    <div>
        <template v-if="card">
            <SharePreview :image="card.image" :title="card.title" :description="card.description" :host="host" />

            <p class="mt-3 text-sm text-neutral-500">{{ lengthLine }}</p>
        </template>

        <p v-else-if="previewUrl" class="text-sm text-neutral-500">Refresh to draw the card.</p>

        <div v-if="previewUrl" class="mt-6">
            <Button variant="secondary" :disabled="http.processing" @click="refresh">
                {{ http.processing ? 'Drawing...' : 'Refresh' }}
            </Button>

            <p v-if="error" class="mt-2 text-sm text-red-600">{{ error }}</p>
        </div>
    </div>
</template>
