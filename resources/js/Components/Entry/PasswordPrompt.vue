<script setup>
import { useForm } from '@inertiajs/vue3';
import Button from '../Ui/Button.vue';
import Input from '../Ui/Input.vue';

/** The password form a private entry or page shows in place of its body. */
const props = defineProps({
    action: { type: String, required: true },
});

const form = useForm({ password: '' });

// Posted back to this page; on success the reload brings the body in.
function unlock() {
    form.post(props.action, { preserveScroll: true, onFinish: () => form.reset('password') });
}
</script>

<template>
    <form data-password-prompt class="max-w-md" @submit.prevent="unlock">
        <h2 class="text-lg font-bold leading-tight tracking-tight text-neutral-900">Password protected</h2>
        <p class="mt-1 text-sm text-neutral-500">This one's private. If you've been given the password, pop it in below.</p>

        <div class="mt-4 flex flex-col gap-2 sm:flex-row">
            <label for="entry-password" class="sr-only">Password</label>
            <Input id="entry-password" v-model="form.password" type="password" placeholder="Password" :invalid="Boolean(form.errors.password)" class="sm:flex-1" />
            <Button type="submit" variant="primary" class="shrink-0" :disabled="form.processing || ! form.password">Unlock</Button>
        </div>

        <p v-if="form.errors.password" class="mt-2 text-xs text-red-600">{{ form.errors.password }}</p>
    </form>
</template>
