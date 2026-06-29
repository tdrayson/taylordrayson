<script setup>
import { router, usePage } from '@inertiajs/vue3';
import Button from '../Ui/Button.vue';

const page = usePage();

function signOut() {
    router.post('/cp/logout');
}
</script>

<template>
    <div class="flex items-center gap-3">
        <span class="text-meta font-medium text-neutral-700">{{ page.props.auth?.user?.name }}</span>
        <Button variant="secondary" size="sm" @click="signOut">Sign out</Button>
    </div>
</template>
