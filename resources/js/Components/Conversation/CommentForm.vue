<script setup>
import { onMounted, ref } from 'vue';
import Button from '../Ui/Button.vue';
import Checkbox from '../Ui/Checkbox.vue';
import Input from '../Ui/Input.vue';
import Textarea from '../Ui/Textarea.vue';

const props = defineProps({
    type: { type: String, required: true },
    id: { type: Number, required: true },
    // Set when replying to somebody rather than to the entry.
    parentId: { type: Number, default: null },
    replyingTo: { type: String, default: null },
});

const emit = defineEmits(['posted', 'cancel']);

const name = ref('');
const email = ref('');
const notify = ref(false);
const body = ref('');
// The honeypot. Named for something a form-filling bot expects to see, kept
// out of the tab order and out of the accessibility tree.
const website = ref('');

const nonce = ref(null);
const sending = ref(false);
const errors = ref({});
const done = ref(null);

// Fetched when the form appears rather than baked into the page: only a
// fraction of readers ever comment, and the issue time is what lets the server
// tell a person typing from a bot posting instantly.
onMounted(async () => {
    try {
        const response = await fetch('/comments/token', {
            method: 'POST',
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        nonce.value = response.ok ? (await response.json()).nonce : null;
    } catch {
        nonce.value = null;
    }
});

async function submit() {
    if (sending.value) {
        return;
    }

    sending.value = true;
    errors.value = {};

    try {
        const response = await fetch(`/comments/${props.type}/${props.id}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                author_name: name.value,
                author_email: email.value || null,
                notify_replies: notify.value,
                body: body.value,
                parent_id: props.parentId,
                nonce: nonce.value,
                website: website.value,
            }),
        });

        if (response.status === 422) {
            errors.value = (await response.json()).errors ?? {};

            return;
        }

        if (! response.ok) {
            throw new Error(response.status);
        }

        // Held and approved read the same to the sender, so a bot learns
        // nothing from the answer.
        done.value = (await response.json()).status;
        emit('posted', done.value);
    } catch {
        errors.value = { body: ['That did not send. Try again in a moment.'] };
    } finally {
        sending.value = false;
    }
}

/** The first message for a field, since only one is ever worth showing. */
const errorFor = (field) => errors.value[field]?.[0] ?? null;
</script>

<template>
    <div v-if="done" class="rounded-lg border border-neutral-100 bg-neutral-25 p-4 text-meta text-neutral-700">
        <p v-if="done === 'approved'">Posted. Thanks for joining in.</p>
        <p v-else>Thanks. I read every first comment before it appears, so this one will show up shortly.</p>
    </div>

    <form v-else class="space-y-3" novalidate @submit.prevent="submit">
        <p v-if="replyingTo" class="text-caption text-neutral-500">
            Replying to {{ replyingTo }}.
            <button type="button" class="underline underline-offset-2 hover:text-accent-500" @click="emit('cancel')">
                Cancel
            </button>
        </p>

        <div class="grid gap-3 sm:grid-cols-2">
            <label class="block text-label uppercase text-neutral-500">
                Name
                <Input v-model="name" class="mt-1" :invalid="Boolean(errorFor('author_name'))" autocomplete="name" />
                <span v-if="errorFor('author_name')" class="mt-1 block normal-case text-caption text-red-600">
                    {{ errorFor('author_name') }}
                </span>
            </label>

            <label class="block text-label uppercase text-neutral-500">
                Email <span class="normal-case text-neutral-500">(optional)</span>
                <Input v-model="email" type="email" class="mt-1" :invalid="Boolean(errorFor('author_email'))" autocomplete="email" />
                <span v-if="errorFor('author_email')" class="mt-1 block normal-case text-caption text-red-600">
                    {{ errorFor('author_email') }}
                </span>
            </label>
        </div>

        <!-- Only offered once there is an address to send to, so the tick box
             never asks for something it cannot do. -->
        <label v-if="email" class="flex items-center gap-2 text-meta text-neutral-700">
            <Checkbox v-model="notify" />
            Email me if somebody replies
        </label>

        <label class="block text-label uppercase text-neutral-500">
            Comment
            <Textarea v-model="body" rows="4" class="mt-1" :invalid="Boolean(errorFor('body'))" />
            <span v-if="errorFor('body') || errorFor('nonce')" class="mt-1 block normal-case text-caption text-red-600">
                {{ errorFor('body') ?? errorFor('nonce') }}
            </span>
        </label>

        <!-- The honeypot: hidden from people and from screen readers, out of
             the tab order, and named for a field a form-filling bot expects.
             Filling it in drops the submission without saying why. -->
        <div class="hidden" aria-hidden="true">
            <label>
                Website
                <input v-model="website" type="text" tabindex="-1" autocomplete="off">
            </label>
        </div>

        <div class="flex items-center gap-3">
            <Button type="submit" variant="primary" :disabled="sending">
                {{ sending ? 'Posting...' : 'Post comment' }}
            </Button>
            <p class="text-caption text-neutral-500">Your email is never shown, and only used for replies.</p>
        </div>
    </form>
</template>
