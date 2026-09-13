<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
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
    // The URL the editor opened with: loading that one never pre-fills the quote.
    openedResponseUrl: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

// The input this previews from, owned by its own field.
const URL_INPUT_ID = 'response_url';

const preview = ref(null);
const loading = ref(false);
const failed = ref(false);

// The last URL a load was started for, so a change can be told apart from a repeat.
const loadedFor = ref(null);

// Tags each load() call so a slower, older request cannot land after a newer
// one and overwrite it with a stale answer.
let latestRequestId = 0;

/**
 * Asks the server what the context will look like, through the same builder the
 * entry page uses. `refresh` refetches even when a copy is already stored.
 *
 * A quote taken from a different post is stale, so it is cleared before the new
 * excerpt arrives. The url and kind are captured up front, since either may move
 * on again while this call is in flight.
 */
async function load(refresh = false) {
    const url = props.responseUrl;
    const kind = props.responseKind;

    if (! url || ! kind) {
        preview.value = null;
        failed.value = false;
        loadedFor.value = null;
        latestRequestId += 1;

        return;
    }

    const changedUrl = loadedFor.value !== null && loadedFor.value !== url;
    loadedFor.value = url;

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

        // Only an unreachable server is worth a message: anything else, such as a
        // half-typed URL failing validation, just has nothing to preview.
        if (response.status >= 500) {
            throw new Error(response.status);
        }

        if (! response.ok) {
            preview.value = null;

            return;
        }

        preview.value = (await response.json()).data;

        // Pre-fill the quote from the excerpt for a post the editor did not open
        // with, when it is empty or was just cleared for a new URL. An empty quote
        // already publishes the excerpt, so an existing reply is left alone.
        const prefill = kind === 'reply'
            && preview.value?.cited?.quote
            && url !== props.openedResponseUrl
            && (changedUrl || ! props.modelValue);

        if (prefill) {
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

/** Loads only when the URL field holds something new since the last load. */
function loadIfChanged() {
    if ((props.responseUrl || null) !== loadedFor.value) {
        load();
    }
}

/** A paste lands in the input after the event, so the load waits a tick for it. */
function onPaste(event) {
    if (event.target?.id === URL_INPUT_ID) {
        setTimeout(loadIfChanged, 0);
    }
}

/** Leaving the URL input is when a typed URL is taken as finished. */
function onFocusOut(event) {
    if (event.target?.id === URL_INPUT_ID) {
        loadIfChanged();
    }
}

onMounted(() => {
    document.addEventListener('paste', onPaste);
    document.addEventListener('focusout', onFocusOut);

    if (props.responseUrl) {
        load();
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('paste', onPaste);
    document.removeEventListener('focusout', onFocusOut);
});

// A new kind can change what the preview says. Switching away from a reply also
// drops the quote client-side, so the value submitted agrees with what a gesture publishes.
watch(() => props.responseKind, (kind) => {
    if (kind !== 'reply') {
        emit('update:modelValue', '');
    }

    load();
});

/** Puts the fetched excerpt back after it has been trimmed or replaced. */
function reset() {
    emit('update:modelValue', preview.value?.cited?.quote ?? '');
}

/**
 * The preview kept true to what will actually publish: the trimmed quote when
 * there is one, the fetched excerpt otherwise. `preview` itself is left alone,
 * since "Reset to excerpt" and the pre-fill both need the original.
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

// Shown greyed in an empty quote: what publishes when it is left blank.
const excerpt = computed(() => preview.value?.cited?.quote ?? '');

const quoteInput = ref(null);

/**
 * Grows the quote box to its text rather than scrolling it. The quote is capped
 * at 600 characters, so the box can never get unreasonably tall.
 */
function fitQuote() {
    const el = quoteInput.value;

    if (! el) {
        return;
    }

    el.style.height = 'auto';
    // scrollHeight excludes the border, which border-box sizing counts.
    el.style.height = `${el.scrollHeight + el.offsetHeight - el.clientHeight}px`;
}

// Refits after every value change, typed, pre-filled or reset, once the DOM holds it.
watch(() => [props.modelValue, props.responseKind], fitQuote, { flush: 'post' });
onMounted(fitQuote);
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
            ref="quoteInput"
            :value="modelValue"
            :placeholder="excerpt"
            rows="3"
            maxlength="600"
            :class="[CONTROL, CONTROL_BORDER, 'resize-none overflow-hidden text-neutral-900']"
            @input="emit('update:modelValue', $event.target.value)"
        />

        <p class="flex gap-4 text-caption text-neutral-500">
            <button v-if="responseKind === 'reply'" type="button" class="rounded-sm hover:text-accent-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500" @click="reset">Reset to excerpt</button>
            <button type="button" class="rounded-sm hover:text-accent-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500" @click="load(true)">Refresh</button>
        </p>
    </div>
</template>
