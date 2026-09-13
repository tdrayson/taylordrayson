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
    <form data-password-prompt class="flex max-w-sm flex-col gap-3" @submit.prevent="unlock">
        <label for="entry-password" class="text-label uppercase text-neutral-500">Password</label>
        <Input id="entry-password" v-model="form.password" type="password" :invalid="Boolean(form.errors.password)" />
        <p v-if="form.errors.password" class="text-caption text-red-600">{{ form.errors.password }}</p>
        <Button type="submit" variant="primary" :disabled="form.processing || ! form.password">Unlock</Button>
    </form>
</template>
