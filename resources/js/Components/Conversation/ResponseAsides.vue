<script setup>
import { defineAsyncComponent, ref } from 'vue';
import { copyText } from '../../lib/clipboard.js';
import Accordion from '../Ui/Accordion.vue';

const props = defineProps({
    // The canonical, absolute URL of the thing being responded to.
    url: { type: String, required: true },
    // The share card this page produces, when it has one.
    ogImage: { type: String, default: null },
});

// Only the handful of readers who open this panel need the form's chunk.
const WebmentionForm = defineAsyncComponent(() => import('./WebmentionForm.vue'));

const copied = ref(false);

async function copy() {
    await copyText(props.url);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}
</script>

<template>
    <!-- Three side doors, all at the same weight, so none of them competes
         with the comment box above or with the entry itself. -->
    <div class="mt-8">
        <Accordion variant="quiet" title="Written about this on your own site?">
            <template #default="{ expanded }">
                <p class="mb-3 text-caption text-neutral-500">
                    Send me the link and your
                    <a
                        href="https://indieweb.org/responses"
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="response, opens in a new tab"
                        class="rounded-sm underline decoration-neutral-100 underline-offset-2 transition-colors hover:text-accent-500 focus-visible:text-accent-500"
                    >response</a>
                    will show up below.
                </p>
                <WebmentionForm v-if="expanded" :target="url" />
            </template>
        </Accordion>

        <Accordion variant="quiet" title="Reference this post">
            <p class="mb-3 text-caption text-neutral-500">
                Link back to this page if you write about it. I am Taylor Drayson, and the site is taylordrayson.com.
            </p>

            <div class="flex max-w-md gap-2">
                <!-- Readonly rather than plain text: the point is to select and
                     copy it, and an input is the thing browsers already let you
                     do that with. -->
                <label class="sr-only" for="reference-url">URL for this post</label>
                <input
                    id="reference-url"
                    :value="url"
                    type="text"
                    readonly
                    class="w-full rounded-md border border-neutral-100 bg-neutral-25 px-3 py-2 text-caption text-neutral-700 focus:border-accent-500 focus:outline-none"
                    @focus="$event.target.select()"
                >
                <button
                    type="button"
                    class="shrink-0 rounded-md border border-neutral-100 px-3 text-caption text-neutral-700 transition-colors hover:text-accent-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                    @click="copy"
                >
                    {{ copied ? 'Copied' : 'Copy' }}
                </button>
            </div>
        </Accordion>

        <Accordion v-if="ogImage" variant="quiet" title="Sharing this?">
            <p class="mb-3 text-caption text-neutral-500">
                This is what shows up when you post the link somewhere.
            </p>

            <a
                :href="ogImage"
                target="_blank"
                rel="noopener noreferrer"
                aria-label="The full share card, opens in a new tab"
                class="block max-w-md rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
            >
                <img
                    :src="ogImage"
                    alt="The share card for this page"
                    loading="lazy"
                    class="w-full rounded-lg border border-neutral-50"
                >
            </a>
        </Accordion>
    </div>
</template>
