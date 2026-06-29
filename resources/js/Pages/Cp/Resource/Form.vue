<!-- resources/js/Pages/Cp/Resource/Form.vue (placeholder, replaced in Task 9) -->
<script setup>
import { setLayoutProps } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });
setLayoutProps({ mode: 'cp' });

defineProps({
    resource: Object,
    record: Object,
    values: Object,
});
</script>

<template>
    <div></div>
</template>
