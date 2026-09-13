<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { CONTROL, CONTROL_BORDER } from '../../lib/editor/control.js';
import { cn } from '../../lib/cn.js';
import Button from '../Ui/Button.vue';
import Input from '../Ui/Input.vue';

/** The owner's status switch: pick a status, add a password when private needs one, save. */
const props = defineProps({
    // { action, status, options: [{ value, label }], hasPassword }
    control: { type: Object, required: true },
});

const form = useForm({ status: props.control.status, password: '' });

// A stored password carries over, so only a private entry without one asks for it.
const needsPassword = computed(() => form.status === 'private' && ! props.control.hasPassword);

// Stays on the page; the reload brings back the saved status and listings follow it.
function save() {
    form.patch(props.control.action, { preserveScroll: true, onSuccess: () => form.reset('password') });
}
</script>

<template>
    <form class="flex flex-wrap items-center gap-2" @submit.prevent="save">
        <label for="entry-status" class="sr-only">Status</label>
        <select id="entry-status" v-model="form.status" :class="cn(CONTROL, CONTROL_BORDER, 'w-auto text-neutral-900')">
            <option v-for="option in control.options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>

        <Input v-if="needsPassword" v-model="form.password" type="password" placeholder="Password" class="w-auto" aria-label="Password" />

        <Button type="submit" size="sm" :disabled="form.processing || ! form.isDirty">Save</Button>

        <p v-if="form.errors.status" class="w-full text-caption text-red-600">{{ form.errors.status }}</p>
    </form>
</template>
