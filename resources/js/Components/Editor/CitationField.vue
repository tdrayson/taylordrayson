<script setup>
import { computed, ref, watch } from 'vue';
import { csrf } from '../../lib/csrf.js';
import { CONTROL, CONTROL_BORDER } from '../../lib/editor/control.js';
import ReplyContext from '../Entry/ReplyContext.vue';

const props = defineProps({
    id: { type: String, required: true },
    // The trimmed quote, empty when the fetched excerpt stands.
    modelValue: { type: String, default: '' },
    // The URL and kind from their own fields, which decide what to preview.
    responseUrl: { type: String, default: null },
    responseKind: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const preview = ref(null);
const loading = ref(false);
const failed = ref(false);

// The URL our last successful load resolved for, so a change can be told apart
// from the page's own first load of an existing reply.
const loadedFor = ref(null);

// Tags each load() call so a slower, older request cannot land after a newer
// one and overwrite it with a stale answer.
let latestRequestId = 0;

/**
 * Asks the server what the context will look like, through the same builder the
 * entry page uses. `refresh` refetches even when a copy is already stored.
 *
 * Changing the URL to a different post makes a quote taken from the old one
 * stale, so it is cleared here before the new excerpt arrives rather than left
 * to be saved against the wrong post. The url and kind are captured up front
 * and used throughout, rather than re-read off props after the await, since
 * either may have moved on again while this call was in flight.
 */
async function load(refresh = false) {
    const url = props.responseUrl;
    const kind = props.responseKind;

    if (! url || ! kind) {
        preview.value = null;
        loadedFor.value = null;
        latestRequestId += 1;

        return;
    }

    const changedUrl = loadedFor.value !== null && loadedFor.value !== url;

    if (changedUrl) {
        emit('update:modelValue', '');
    }

    const requestId = ++latestRequestId;
    loading.value = true;
    failed.value = false;

    try {
        const response = await fetch('/citations/preview', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': csrf() },
            credentials: 'same-origin',
            body: JSON.stringify({ url, kind, refresh }),
        });

        if (requestId !== latestRequestId) {
            return;
        }

        if (! response.ok) {
            throw new Error(response.status);
        }

        preview.value = (await response.json()).data;
        loadedFor.value = url;

        // Pre-fill the quote from the excerpt: the first time for this field, or
        // whenever the url just changed and the old quote was cleared above. A
        // gesture has nothing to pre-fill, whatever the excerpt says.
        if (kind === 'reply' && preview.value?.cited?.quote && (changedUrl || ! props.modelValue)) {
            emit('update:modelValue', preview.value.cited.quote);
        }
    } catch {
        if (requestId === latestRequestId) {
            failed.value = true;
        }
    } finally {
        if (requestId === latestRequestId) {
            loading.value = false;
        }
    }
}

// Debounced, so typing a URL character by character does not fetch each prefix.
let timer = null;
watch(() => [props.responseUrl, props.responseKind], () => {
    clearTimeout(timer);
    timer = setTimeout(() => load(), 400);
}, { immediate: true });

// Switching away from a reply drops the quote client-side too, so the value
// held here (and about to be submitted) agrees with what a gesture publishes.
watch(() => props.responseKind, (kind) => {
    if (kind !== 'reply') {
        emit('update:modelValue', '');
    }
});

/** Puts the fetched excerpt back after it has been trimmed or replaced. */
function reset() {
    emit('update:modelValue', preview.value?.cited?.quote ?? '');
}

/**
 * The preview kept true to what will actually publish: BuildResponseContext
 * quotes the trimmed response_quote when there is one, the fetched excerpt
 * otherwise, so this mirrors that rule rather than always showing the
 * excerpt untouched. `preview` itself is left alone, since "Reset to excerpt"
 * and the pre-fill above both need the original.
 */
const displayPreview = computed(() => {
    if (! preview.value) {
        return null;
    }

    if (props.responseKind !== 'reply' || ! preview.value.cited || ! props.modelValue) {
        return preview.value;
    }

    return {
        ...preview.value,
        cited: { ...preview.value.cited, quote: props.modelValue },
    };
});
</script>

<template>
    <div class="space-y-3">
        <div data-testid="citation-preview" :class="['transition-opacity', loading && 'opacity-50']">
            <ReplyContext v-if="displayPreview" :response="displayPreview" />
            <p v-else-if="failed" class="text-caption text-neutral-500">Could not read that page. It will be tried again when you save.</p>
        </div>

        <!-- A gesture answers with no words of its own, so it has nothing to quote. -->
        <textarea
            v-if="responseKind === 'reply'"
            :id="id"
            :value="modelValue"
            rows="3"
            maxlength="600"
            :class="[CONTROL, CONTROL_BORDER, 'text-neutral-900']"
            @input="emit('update:modelValue', $event.target.value)"
        />

        <p class="flex gap-4 text-caption text-neutral-500">
            <button v-if="responseKind === 'reply'" type="button" class="rounded-sm hover:text-accent-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500" @click="reset">Reset to excerpt</button>
            <button type="button" class="rounded-sm hover:text-accent-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500" @click="load(true)">Refresh</button>
        </p>
    </div>
</template>
