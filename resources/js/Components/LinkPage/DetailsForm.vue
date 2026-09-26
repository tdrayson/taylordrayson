<script setup>
import { onMounted, reactive, ref } from 'vue';
import Icon from '../Ui/Icon.vue';
import CardButton from './CardButton.vue';
import FormField from './FormField.vue';

const props = defineProps({
    // [{ name, label, type?, required?, multiline?, autocomplete?, pattern?, placeholder? }]
    fields: { type: Array, required: true },
    variant: { type: String, default: 'personal' },
});

const emit = defineEmits(['sent']);

const values = reactive(Object.fromEntries(props.fields.map((field) => [field.name, ''])));

// The Contact Picker API only exists on Android Chrome, and never during SSR.
const canPickContact = ref(false);

onMounted(() => {
    canPickContact.value = 'contacts' in navigator && 'ContactsManager' in window;
});

/**
 * Prefill name, phone and email from one contact the visitor picks. An empty
 * selection (the picker dismissed) leaves the form as it was.
 */
async function pickContact() {
    const [contact] = await navigator.contacts.select(['name', 'tel', 'email'], { multiple: false });

    if (!contact) {
        return;
    }

    const picked = { name: contact.name?.[0], phone: contact.tel?.[0], email: contact.email?.[0] };

    Object.entries(picked).forEach(([name, value]) => {
        if (value && name in values) {
            values[name] = value;
        }
    });
}

// Only reached once native validation passes. Nothing is sent yet: the form is
// a stand-in until the Tinkr embed replaces it.
function submit() {
    emit('sent');
}

const DIVIDER_LINE = 'h-px flex-1 bg-neutral-100';
</script>

<template>
    <form class="flex flex-col gap-5" @submit.prevent="submit">
        <template v-if="canPickContact">
            <CardButton :variant="variant" outline @click="pickContact">
                <Icon name="ContactBookIcon" :class="variant === 'business' ? 'text-tinker-600' : 'text-accent-500'" class="size-6" />
                Choose from my contacts
            </CardButton>

            <p class="flex items-center gap-4 text-sm text-neutral-500">
                <span :class="DIVIDER_LINE" />
                or fill in below
                <span :class="DIVIDER_LINE" />
            </p>
        </template>

        <FormField
            v-for="field in fields"
            :key="field.name"
            v-model="values[field.name]"
            v-bind="field"
            :variant="variant"
        />

        <CardButton type="submit" :variant="variant" class="mt-3">Send details</CardButton>

        <p class="flex items-center justify-center gap-2 text-sm text-neutral-500">
            <Icon name="SquareLock02Icon" class="size-4 shrink-0" />
            Only I see these. No sharing, no mailing lists.
        </p>
    </form>
</template>
