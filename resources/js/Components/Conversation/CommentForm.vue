<script setup>
import { onMounted, ref, useId } from 'vue';
import { readCommenter, rememberCommenter } from '../../lib/commenter.js';
import { csrf } from '../../lib/csrf.js';
import Button from '../Ui/Button.vue';
import Checkbox from '../Ui/Checkbox.vue';
import Icon from '../Ui/Icon.vue';
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
const bodyId = useId();

// Who you are is asked for only once there is something to attribute. Opened
// on first focus and never closed again: collapsing it while somebody tabs
// towards the name field would take the field away as they reach for it.
const revealed = ref(false);

// Fetched when the form appears rather than baked into the page: only a
// fraction of readers ever comment, and the issue time is what lets the server
// tell a person typing from a bot posting instantly.
onMounted(async () => {
    const known = readCommenter();

    if (known) {
        name.value = known.name;
        email.value = known.email;
    }

    try {
        const response = await fetch('/comments/token', {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': csrf() },
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
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': csrf() },
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
            revealed.value = true;

            return;
        }

        if (! response.ok) {
            throw new Error(response.status);
        }

        rememberCommenter(name.value, email.value);

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
    <div v-if="done" class="rounded-lg bg-neutral-25 p-4 text-body text-neutral-700">
        <p v-if="done === 'approved'">Posted. Thanks for joining in.</p>
        <p v-else>Thanks. I read every first comment before it appears, so this one will show up shortly.</p>
    </div>

    <form v-else class="space-y-3" novalidate @submit.prevent="submit">
        <p v-if="replyingTo" class="text-meta text-neutral-500">
            Replying to {{ replyingTo }}.
            <button type="button" class="underline underline-offset-2 hover:text-accent-500" @click="emit('cancel')">
                Cancel
            </button>
        </p>

        <div>
            <!-- Named for a screen reader but not on screen: the field is the
                 only thing here until you use it, and a label above it would
                 be a title for a form that is trying not to look like one. -->
            <label :for="bodyId" class="sr-only">Comment</label>
            <Textarea
                :id="bodyId"
                v-model="body"
                rows="4"
                placeholder="Add a comment"
                :invalid="Boolean(errorFor('body'))"
                @focus="revealed = true"
            />
            <span v-if="errorFor('body') || errorFor('nonce')" class="mt-1 block text-meta text-red-600">
                {{ errorFor('body') ?? errorFor('nonce') }}
            </span>
        </div>

        <div v-if="revealed" class="grid gap-3 sm:grid-cols-2">
            <label class="block text-label uppercase text-neutral-500">
                Name
                <Input v-model="name" class="mt-1" :invalid="Boolean(errorFor('author_name'))" autocomplete="name" />
                <span v-if="errorFor('author_name')" class="mt-1 block normal-case text-meta text-red-600">
                    {{ errorFor('author_name') }}
                </span>
            </label>

            <label class="block text-label uppercase text-neutral-500">
                Email <span class="normal-case text-neutral-500">(optional)</span>
                <Input v-model="email" type="email" class="mt-1" :invalid="Boolean(errorFor('author_email'))" autocomplete="email" />
                <span v-if="errorFor('author_email')" class="mt-1 block normal-case text-meta text-red-600">
                    {{ errorFor('author_email') }}
                </span>
            </label>

            <!-- Only offered once there is an address to send to, so the tick
                 box never asks for something it cannot do. Never restored from
                 storage: an opt-in somebody did not just make is not one. -->
            <label v-if="email" class="flex items-center gap-2 text-body text-neutral-700 sm:col-span-2">
                <Checkbox v-model="notify" />
                Email me if somebody replies
            </label>
        </div>

        <!-- The honeypot: hidden from people and from screen readers, out of
             the tab order, and named for a field a form-filling bot expects.
             Filling it in drops the submission without saying why. -->
        <div class="hidden" aria-hidden="true">
            <label>
                Website
                <input v-model="website" type="text" tabindex="-1" autocomplete="off">
            </label>
        </div>

        <!-- The reassurance sits beside the button rather than under it: it
             answers a question you have while deciding to press it. Wraps
             underneath where there is no room for both. -->
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 pt-2">
            <Button type="submit" variant="primary" :disabled="sending">
                {{ sending ? 'Posting...' : 'Post comment' }}
            </Button>
            <p v-if="revealed" class="flex items-center gap-1.5 text-meta text-neutral-500">
                <Icon name="LockIcon" class="size-4 shrink-0 text-green-600" />
                Your email is never shown, and only used for replies.
            </p>
        </div>
    </form>
</template>
