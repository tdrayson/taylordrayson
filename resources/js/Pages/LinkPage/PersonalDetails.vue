<script setup>
import { nextTick, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import LinkPageLayout from '../../Layouts/LinkPageLayout.vue';
import Icon from '../../Components/Ui/Icon.vue';
import CardButton from '../../Components/LinkPage/CardButton.vue';
import DetailsForm from '../../Components/LinkPage/DetailsForm.vue';
import LevelSquares from '../../Components/LinkPage/LevelSquares.vue';
import { LEVELS } from '../../Components/LinkPage/shared.js';

defineProps({
    backHref: { type: String, required: true },
    contactHref: { type: String, required: true },
});

const FIELDS = [
    { name: 'name', label: 'Your name', required: true, autocomplete: 'name', pattern: '.*\\S.*', placeholder: 'Marty McFly' },
    { name: 'phone', label: 'Phone', type: 'tel', required: true, autocomplete: 'tel', pattern: '\\+?[\\d\\s\\(\\)\\-]{7,}', placeholder: '+44 7121 881 955' },
    { name: 'email', label: 'Email', type: 'email', autocomplete: 'email', placeholder: 'marty@hillvalley.com' },
    { name: 'met', label: 'Where did we meet?', multiline: true, placeholder: 'Padel, a coffee shop, the Enchantment Under the Sea dance…' },
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
    <LinkPageLayout title="Swap details">
        <div class="mx-auto flex max-w-sm flex-col px-6 py-8">
            <nav class="flex items-center justify-between">
                <Link :href="backHref" class="flex items-center gap-2 font-display text-lg font-semibold text-neutral-900 transition-colors hover:text-accent-500">
                    <Icon name="ArrowLeft01Icon" class="size-5" />
                    Back
                </Link>
                <LevelSquares size="size-4" />
            </nav>

            <template v-if="!sent">
                <h1 ref="heading" tabindex="-1" class="mt-12 font-display text-4xl font-extrabold text-neutral-900">Swap details</h1>
                <p class="mt-3 text-lg/relaxed text-neutral-500">Pop your details in and I'll save your number too.</p>
                <DetailsForm :fields="FIELDS" class="mt-8" @sent="show(true)" />
            </template>

            <div v-else class="mt-16 flex flex-col">
                <div class="flex gap-2.5" aria-hidden="true">
                    <span v-for="level in [1, 2, 3]" :key="level" :class="LEVELS[level]" class="size-8 rounded-md" />
                    <span class="flex size-8 items-center justify-center rounded-md bg-accent-500 text-white">
                        <Icon name="Tick02Icon" class="size-5" />
                    </span>
                </div>
                <h1 ref="heading" tabindex="-1" class="mt-8 font-display text-4xl font-extrabold text-neutral-900">Logged.</h1>
                <p class="mt-3 text-lg/relaxed text-neutral-500">Thanks, I've got your details. Save mine too so you know it's me when I message.</p>
                <CardButton :href="contactHref" class="mt-8">Save my contact</CardButton>
                <button type="button" class="mt-6 font-display text-lg font-semibold text-accent-600 hover:underline" @click="show(false)">
                    Back to the form
                </button>
            </div>
        </div>
    </LinkPageLayout>
</template>
