<script setup>
import { useForm } from '@inertiajs/vue3';
import Card from '../../Components/Ui/Card.vue';
import Input from '../../Components/Ui/Input.vue';
import Button from '../../Components/Ui/Button.vue';
import AppHead from '../../Components/AppHead.vue';

const form = useForm({ email: '', password: '' });

function submit() {
    form.post('/cp/login');
}
</script>

<template>
    <AppHead :og="{ title: 'Sign in' }" />
    <div class="flex min-h-dvh items-center justify-center px-6">
        <Card variant="elevated" class="w-full max-w-sm">
            <h1 class="font-display text-section">Sign in</h1>
            <form class="mt-6 flex flex-col gap-4" @submit.prevent="submit">
                <div class="flex flex-col gap-1.5">
                    <label class="text-label font-medium text-neutral-700">Email</label>
                    <Input v-model="form.email" type="email" :invalid="!!form.errors.email" />
                    <p v-if="form.errors.email" class="text-caption text-red-600">{{ form.errors.email }}</p>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-label font-medium text-neutral-700">Password</label>
                    <Input v-model="form.password" type="password" :invalid="!!form.errors.password" />
                </div>
                <Button type="submit" variant="primary" :disabled="form.processing">Sign in</Button>
            </form>
        </Card>
    </div>
</template>
