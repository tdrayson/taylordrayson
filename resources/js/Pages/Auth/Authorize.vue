<script setup>
import AppHead from '../../Components/AppHead.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Button from '../../Components/Ui/Button.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

defineProps({
    // The client's own name, as it registered itself. Untrusted text.
    client: { type: String, required: true },
    scopes: { type: Array, default: () => [] },
    authToken: { type: String, required: true },
    csrf: { type: String, required: true },
});
</script>

<template>
    <AppHead :og="{ title: 'Authorise' }" />

    <div class="mx-auto w-full max-w-sm py-12">
        <h1 class="font-display text-section text-neutral-900">Authorise</h1>

        <p class="mt-1 text-meta text-neutral-500">
            <span class="font-semibold text-neutral-900">{{ client }}</span>
            is asking to connect to your site.
        </p>

        <ul v-if="scopes.length" class="mt-6 space-y-2 border-y border-neutral-50 py-4">
            <li v-for="scope in scopes" :key="scope.id" class="text-meta text-neutral-700">
                {{ scope.description }}
            </li>
        </ul>

        <p class="mt-4 text-caption text-neutral-500">
            This grants read-only access. Nothing behind it can change your data.
        </p>

        <!--
            Plain forms rather than Inertia: approving completes the OAuth
            request with a redirect to the client, which the browser has to
            follow itself. An XHR visit would leave the flow half-finished.
        -->
        <div class="mt-6 flex gap-2">
            <form method="POST" action="/oauth/authorize" class="flex-1">
                <input type="hidden" name="_token" :value="csrf">
                <input type="hidden" name="auth_token" :value="authToken">
                <Button type="submit" variant="primary" class="w-full">Approve</Button>
            </form>

            <form method="POST" action="/oauth/authorize" class="flex-1">
                <input type="hidden" name="_token" :value="csrf">
                <input type="hidden" name="_method" value="DELETE">
                <input type="hidden" name="auth_token" :value="authToken">
                <Button type="submit" class="w-full">Deny</Button>
            </form>
        </div>
    </div>
</template>
