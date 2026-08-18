<script setup>
import { useForm } from '@inertiajs/vue3';
import AppHead from '../../Components/AppHead.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Button from '../../Components/Ui/Button.vue';
import Checkbox from '../../Components/Ui/Checkbox.vue';
import Input from '../../Components/Ui/Input.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

defineProps({
    // One-off flash message, e.g. after being bounced here by the auth middleware.
    status: { type: String, default: null },
});

// useForm rather than the <Form> component: Input.vue is a v-model primitive,
// and <Form> reads uncontrolled fields by name.
const form = useForm({
    email: '',
    password: '',
    remember: false,
});

// Never leave the password in memory after an attempt, successful or not.
function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <AppHead :og="{ title: 'Sign in' }" />

    <div class="mx-auto w-full max-w-sm py-12">
        <h1 class="font-display text-section text-neutral-900">Sign in</h1>
        <p class="mt-1 text-meta text-neutral-500">
            Signing in turns on editing. There is nothing else behind it.
        </p>

        <p v-if="status" class="mt-4 text-meta text-accent-700">{{ status }}</p>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <div>
                <label for="email" class="mb-1 block text-label uppercase text-neutral-500">Email</label>
                <Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    required
                    autofocus
                    :invalid="Boolean(form.errors.email)"
                />
            </div>

            <div>
                <label for="password" class="mb-1 block text-label uppercase text-neutral-500">Password</label>
                <Input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    required
                    :invalid="Boolean(form.errors.email)"
                />
            </div>

            <!-- Failures always come back on `email`, whether the address was
                 wrong, the password was wrong, or the attempt was rate-limited,
                 so the form never reveals which. -->
            <p v-if="form.errors.email" class="text-meta text-red-600">{{ form.errors.email }}</p>

            <label class="flex min-h-11 cursor-pointer items-center gap-2 text-meta text-neutral-700">
                <Checkbox v-model="form.remember" />
                Stay signed in
            </label>

            <Button type="submit" variant="primary" size="lg" :disabled="form.processing" class="w-full">
                {{ form.processing ? 'Signing in...' : 'Sign in' }}
            </Button>
        </form>
    </div>
</template>
