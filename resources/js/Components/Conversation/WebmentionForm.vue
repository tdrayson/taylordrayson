<script setup>
import { computed, ref } from 'vue';
import Button from '../Ui/Button.vue';
import Input from '../Ui/Input.vue';

const props = defineProps({
    // The page being responded to, which the endpoint needs as `target`.
    target: { type: String, required: true },
});

const source = ref('');
const sending = ref(false);
const error = ref(null);
const sent = ref(false);

// The endpoint checks this properly; the point here is to not make somebody
// wait on a round trip to be told they left the box empty.
const looksLikeUrl = computed(() => /^https?:\/\/\S+\.\S+/.test(source.value.trim()));

async function submit() {
    if (sending.value || ! looksLikeUrl.value) {
        return;
    }

    sending.value = true;
    error.value = null;

    try {
        const response = await fetch('/webmention', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' },
            body: new URLSearchParams({ source: source.value.trim(), target: props.target }),
        });

        if (! response.ok) {
            error.value = (await response.json()).error ?? 'That could not be accepted.';

            return;
        }

        sent.value = true;
    } catch {
        error.value = 'That did not send. Try again in a moment.';
    } finally {
        sending.value = false;
    }
}
</script>

<template>
    <div v-if="sent" class="rounded-lg border border-neutral-100 bg-neutral-25 p-4 text-meta text-neutral-700">
        Got it. I will fetch your post shortly, and it will appear here once I have read it.
    </div>

    <form v-else class="space-y-2" novalidate @submit.prevent="submit">
        <label class="block text-label uppercase text-neutral-500" for="webmention-source">
            Written a response of your own?
        </label>

        <p class="text-caption text-neutral-500">
            Paste its URL and I will pull in what you said. Your post needs to link back to this page.
        </p>

        <div class="flex flex-col gap-2 sm:flex-row">
            <Input
                id="webmention-source"
                v-model="source"
                type="url"
                placeholder="https://your-site.com/your-post"
                class="flex-1"
                :invalid="Boolean(error)"
            />
            <Button type="submit" :disabled="sending || ! looksLikeUrl">
                {{ sending ? 'Sending...' : 'Send webmention' }}
            </Button>
        </div>

        <p v-if="error" class="text-caption text-red-600">{{ error }}</p>
    </form>
</template>
