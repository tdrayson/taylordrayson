<!-- resources/js/Pages/Cp/Dashboard.vue (placeholder, replaced in Task 9) -->
<script setup>
import { setLayoutProps } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });
setLayoutProps({ mode: 'cp' });
</script>

<template>
    <h1 class="font-display text-display">Control panel</h1>
</template>
