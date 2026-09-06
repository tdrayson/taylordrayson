<script setup>
import { computed, defineAsyncComponent, ref } from 'vue';
import { copyText } from '../../lib/clipboard.js';
import Accordion from '../Ui/Accordion.vue';

const props = defineProps({
    // The canonical, absolute URL of the thing being responded to.
    url: { type: String, required: true },
    // The page's Open Graph payload: { title, description, image }.
    og: { type: Object, default: () => ({}) },
});

// Only the handful of readers who open this panel need the form's chunk.
const WebmentionForm = defineAsyncComponent(() => import('./WebmentionForm.vue'));

const copied = ref(false);

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
        <Accordion variant="quiet" title="Written about this on your own site?">
            <template #default="{ expanded }">
                <p class="mb-3 text-meta text-neutral-500">
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
            <p class="mb-3 text-meta text-neutral-500">
                Link back to this page if you write about it. I am Taylor Drayson, and the site is taylordrayson.com.
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
                    class="min-w-0 flex-1 rounded-md border border-neutral-100 bg-neutral-25 px-3 py-2 text-meta text-neutral-700 focus:border-accent-500 focus:outline-none"
                    @focus="$event.target.select()"
                >
                <button
                    type="button"
                    class="shrink-0 rounded-md border border-neutral-100 px-4 py-2 text-meta font-medium text-neutral-700 transition-colors hover:text-accent-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                    @click="copy"
                >
                    {{ copied ? 'Copied' : 'Copy' }}
                </button>
            </div>
        </Accordion>

        <Accordion v-if="og.image" variant="quiet" title="Sharing this?">
            <p class="mb-3 text-meta text-neutral-500">
                This is what shows up when you post the link somewhere.
            </p>

            <!-- Built as the embed itself rather than as a bare image: the
                 title and description are what a reader actually judges the
                 link on, and they come from the same tags the card does. -->
            <figure class="max-w-2xl overflow-hidden rounded-xl border border-neutral-50 bg-neutral-25">
                <!-- The box is reserved at the card's own ratio so opening
                     this panel does not jump when the image arrives. -->
                <img
                    :src="og.image"
                    alt=""
                    loading="lazy"
                    class="block aspect-og w-full border-b border-neutral-50 bg-neutral-50 object-cover"
                >
                <figcaption class="space-y-1 p-4">
                    <p v-if="host" class="text-label uppercase text-neutral-500">{{ host }}</p>
                    <p v-if="og.title" class="text-body font-semibold text-neutral-900">{{ og.title }}</p>
                    <p v-if="og.description" class="text-meta text-neutral-500">{{ og.description }}</p>
                </figcaption>
            </figure>

            <p class="mt-3 text-meta text-neutral-500">
                Want to see how it is made?
                <a
                    :href="og.image"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="the card on its own, opens in a new tab"
                    class="rounded-sm underline decoration-neutral-100 underline-offset-2 transition-colors hover:text-accent-500 focus-visible:text-accent-500"
                >Here is the card on its own</a>, drawn from this page's own title and description.
            </p>
        </Accordion>
    </div>
</template>
