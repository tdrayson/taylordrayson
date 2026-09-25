<script setup>
import { nextTick, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import TinkerLayout from '../../Layouts/TinkerLayout.vue';
import Icon from '../../Components/Ui/Icon.vue';
import CardButton from '../../Components/LinkPage/CardButton.vue';
import DetailsForm from '../../Components/LinkPage/DetailsForm.vue';
import TinkerBanner from '../../Components/LinkPage/TinkerBanner.vue';

defineProps({
    backHref: { type: String, required: true },
    contactHref: { type: String, required: true },
});

const FIELDS = [
    { name: 'name', label: 'Your name', required: true, autocomplete: 'name', pattern: '.*\\S.*' },
    { name: 'phone', label: 'Phone', type: 'tel', required: true, autocomplete: 'tel', pattern: '\\+?[\\d\\s\\(\\)\\-]{7,}' },
    { name: 'email', label: 'Email', type: 'email', required: true, autocomplete: 'email' },
    { name: 'business', label: 'Business name', autocomplete: 'organization' },
    { name: 'met', label: 'Where did we meet?', multiline: true, placeholder: 'BNI, an event, a coffee shop…' },
];

const sent = ref(false);
const heading = ref(null);

// Swap between the form and its confirmation, moving focus to the new heading.
async function show(isSent) {
    sent.value = isSent;
    await nextTick();
    heading.value?.focus();
}
</script>

<template>
    <TinkerLayout title="Send me your details">
        <TinkerBanner compact>
            <nav class="relative mx-auto flex h-full max-w-md items-center px-6">
                <Link :href="backHref" class="flex items-center gap-2 text-lg font-semibold text-white hover:underline">
                    <Icon name="ArrowLeft01Icon" class="size-5" />
                    Back
                </Link>
            </nav>
        </TinkerBanner>

        <div class="mx-auto flex max-w-md flex-col px-6 py-10">
            <template v-if="!sent">
                <h1 ref="heading" tabindex="-1" class="font-montserrat text-3xl font-extrabold tracking-tight text-neutral-900">Send me your details</h1>
                <p class="mt-2 text-lg/relaxed text-neutral-600">I'll save your number and get in touch.</p>
                <DetailsForm :fields="FIELDS" variant="business" class="mt-8" @sent="show(true)" />
            </template>

            <div v-else class="mt-10 flex flex-col items-center text-center">
                <span class="flex size-16 items-center justify-center rounded-full bg-tinker-50 text-tinker-600" aria-hidden="true">
                    <Icon name="Tick02Icon" class="size-8" />
                </span>
                <h1 ref="heading" tabindex="-1" class="mt-6 font-montserrat text-3xl font-extrabold tracking-tight text-neutral-900">Thanks, got it</h1>
                <p class="mt-3 text-lg/relaxed text-neutral-600">I'll be in touch soon. In the meantime, save my details so you know it's me.</p>
                <CardButton :href="contactHref" variant="business" class="mt-8">Save my contact</CardButton>
                <button type="button" class="mt-6 text-lg font-semibold text-tinker-600 hover:underline" @click="show(false)">
                    Back to the form
                </button>
            </div>
        </div>
    </TinkerLayout>
</template>
