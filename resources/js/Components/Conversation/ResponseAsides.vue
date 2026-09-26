<script setup>
import { computed, defineAsyncComponent, nextTick, ref } from 'vue';
import { copyText } from '../../lib/clipboard.js';
import { useOgCard } from '../../composables/useOgCard.js';
import Accordion from '../Ui/Accordion.vue';
import SharePreview from '../Ui/SharePreview.vue';

const props = defineProps({
    // The canonical, absolute URL of the thing being responded to.
    url: { type: String, required: true },
    // The page's Open Graph payload, the same one AppHead publishes.
    og: { type: Object, default: () => ({}) },
});

// Only the handful of readers who open this panel need the form's chunk.
const WebmentionForm = defineAsyncComponent(() => import('./WebmentionForm.vue'));

// The card a scraper would fetch, resolved the same way as the og:image tag.
const cardUrl = useOgCard(() => props.og);

const copied = ref(false);
const sendingLink = ref(false);
const webmention = ref(null);

/**
 * Open the webmention panel and put it on screen, for the invitation further up
 * the page. Closed first so a second click reopens one the reader had shut:
 * the panel follows this prop's changes, and setting true over true is not one.
 */
async function openWebmention() {
    sendingLink.value = false;
    await nextTick();
    sendingLink.value = true;
    await nextTick();
    webmention.value?.$el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

defineExpose({ openWebmention });

/** The bare domain, the way a share card labels its source. */
const host = computed(() => {
    try {
        return new URL(props.url).hostname.replace(/^www\./, '');
    } catch {
        return null;
    }
});

async function copy() {
    await copyText(props.url);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}
</script>

<template>
    <!-- Three side doors, all at the same weight, so none of them competes
         with the comment box above or with the entry itself. -->
    <div class="mt-10">
        <Accordion id="send-a-link" ref="webmention" variant="quiet" title="Written about this on your own site?" :open="sendingLink">
            <template #default="{ expanded }">
                <p class="mb-3 text-sm text-neutral-500">
                    Send me the link and your
                    <a
                        href="https://indieweb.org/responses"
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="response, opens in a new tab"
                        class="rounded-sm underline decoration-neutral-100 underline-offset-2 transition-colors hover:text-accent-500 focus-visible:text-accent-500"
                    >response</a>
                    will show up on this page.
                </p>
                <WebmentionForm v-if="expanded" :target="url" />
            </template>
        </Accordion>

        <Accordion variant="quiet" title="Reference this post">
            <p class="mb-3 text-sm text-neutral-500">
                Link back to this page if you write about it.
            </p>

            <div class="flex flex-col gap-2 sm:flex-row">
                <!-- Readonly rather than plain text: the point is to select and
                     copy it, and an input is the thing browsers already let you
                     do that with. -->
                <label class="sr-only" for="reference-url">URL for this post</label>
                <input
                    id="reference-url"
                    :value="url"
                    type="text"
                    readonly
                    class="min-w-0 flex-1 rounded-md border border-neutral-100 bg-neutral-25 px-3 py-2 text-sm text-neutral-700 focus:border-accent-500 focus-visible:-outline-offset-1"
                    @focus="$event.target.select()"
                >
                <button
                    type="button"
                    class="shrink-0 rounded-md border border-neutral-100 px-4 py-2 text-sm font-medium text-neutral-700 transition-colors hover:text-accent-500"
                    @click="copy"
                >
                    {{ copied ? 'Copied' : 'Copy' }}
                </button>
            </div>
        </Accordion>

        <Accordion variant="quiet" title="Sharing this?">
            <p class="mb-3 text-sm text-neutral-500">
                This is what shows up when you post the link somewhere.
            </p>

            <SharePreview :image="cardUrl" :title="og.title" :description="og.description" :host="host" />

            <p class="mt-3 text-sm text-neutral-500">
                Want to see how it is made?
                <a
                    :href="cardUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="the card on its own, opens in a new tab"
                    class="rounded-sm underline decoration-neutral-100 underline-offset-2 transition-colors hover:text-accent-500 focus-visible:text-accent-500"
                >Here is the card on its own</a>, drawn from this page's own title and description.
            </p>
        </Accordion>
    </div>
</template>
