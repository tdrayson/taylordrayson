<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
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

// `preview` hands the loaded context up, so the editor can name the slug after it.
const emit = defineEmits(['update:modelValue', 'preview']);

// The input this previews from, owned by its own field.
const URL_INPUT_ID = 'response_url';

const preview = ref(null);

watch(preview, (value) => emit('preview', value));

const loading = ref(false);
const failed = ref(false);

// Whether the quote inside the preview is swapped for its input.
const editing = ref(false);

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
 * post arrives. The url and kind are captured up front, since either may move
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

    if (loadedFor.value !== null && loadedFor.value !== url) {
        emit('update:modelValue', '');
        editing.value = false;
    }

    loadedFor.value = url;

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

        preview.value = response.ok ? (await response.json()).data : null;
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
        editing.value = false;
    }

    load();
});

// The fetched excerpt: what publishes while the quote is left empty.
const excerpt = computed(() => preview.value?.cited?.quote ?? '');

const quoteInput = ref(null);

/**
 * Opens the quote for editing, starting from their words rather than an empty
 * box when nothing has been trimmed yet.
 */
async function editQuote() {
    if (! props.modelValue) {
        emit('update:modelValue', excerpt.value);
    }

    editing.value = true;
    await nextTick();
    quoteInput.value?.focus();
}

/** Closes the input. An untouched excerpt is stored as empty, so a later refresh still updates it. */
function doneEditing() {
    if (props.modelValue.trim() === excerpt.value) {
        emit('update:modelValue', '');
    }

    editing.value = false;
}

/** Puts the fetched excerpt back after it has been trimmed or replaced. */
function reset() {
    emit('update:modelValue', editing.value ? excerpt.value : '');
}

/**
 * The preview kept true to what will actually publish: the trimmed quote when
 * there is one, the fetched excerpt otherwise. `preview` itself is left alone,
 * since "Reset to excerpt" needs the original.
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

// Refits after every value change, typed or reset, once the DOM holds it.
watch(() => [props.modelValue, editing.value], fitQuote, { flush: 'post' });
</script>

<template>
    <div class="space-y-3">
        <div data-testid="citation-preview" :class="['transition-opacity', loading && 'opacity-50']">
            <ReplyContext v-if="displayPreview" :response="displayPreview">
                <!-- A gesture answers with no words of its own, so only a reply edits a quote. -->
                <template v-if="editing && responseKind === 'reply'" #quote>
                    <textarea
                        :id="id"
                        ref="quoteInput"
                        :value="modelValue"
                        rows="3"
                        maxlength="600"
                        :class="[CONTROL, CONTROL_BORDER, 'resize-none overflow-hidden text-neutral-900']"
                        @input="emit('update:modelValue', $event.target.value)"
                    />
                </template>
            </ReplyContext>
            <p v-else-if="failed" class="text-xs text-neutral-500">Could not read that page. It will be tried again when you save.</p>
        </div>

        <p v-if="preview || failed" class="flex gap-4 text-xs text-neutral-500">
            <template v-if="responseKind === 'reply'">
                <button v-if="! editing" type="button" class="rounded-sm hover:text-accent-700" @click="editQuote">{{ excerpt || modelValue ? 'Edit quote' : 'Add quote' }}</button>
                <button v-else type="button" class="rounded-sm hover:text-accent-700" @click="doneEditing">Done</button>
                <button v-if="editing || modelValue" type="button" class="rounded-sm hover:text-accent-700" @click="reset">Reset to excerpt</button>
            </template>
            <button type="button" class="rounded-sm hover:text-accent-700" @click="load(true)">Refresh</button>
        </p>
    </div>
</template>
