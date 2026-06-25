<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowDown01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';
import Button from '../Ui/Button.vue';

const open = ref(false);
const root = ref(null);

const now = new Date();
const pad = (value) => String(value).padStart(2, '0');
const year = now.getFullYear();
const month = pad(now.getMonth() + 1);
const day = pad(now.getDate());

const todayUrl = `/${year}/${month}/${day}`;

const items = [
    { label: 'This month', href: `/${year}/${month}` },
    { label: 'This year', href: `/${year}` },
    { label: 'On this day', href: '/on-this-day' },
    { label: "I'm feeling lucky", href: '/lucky' },
];

function close() {
    open.value = false;
}

function onDocumentClick(event) {
    if (root.value && !root.value.contains(event.target)) {
        close();
    }
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        close();
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div ref="root" class="relative">
        <div class="inline-flex">
            <Button :href="todayUrl" variant="secondary" size="sm" class="rounded-r-none border-r-0">
                Today
            </Button>
            <Button
                variant="secondary"
                size="sm"
                class="rounded-l-none px-2"
                :aria-label="open ? 'Close time navigation' : 'Open time navigation'"
                aria-haspopup="true"
                :aria-expanded="open"
                @click="open = !open"
            >
                <Icon :icon="ArrowDown01Icon" class="size-3.5 transition-transform" :class="open ? 'rotate-180' : ''" />
            </Button>
        </div>

        <div
            v-if="open"
            class="absolute right-0 z-50 mt-2 w-44 overflow-hidden rounded-md border border-neutral-100 bg-neutral-0 py-1 shadow-card"
        >
            <Link
                v-for="item in items"
                :key="item.label"
                :href="item.href"
                class="block px-4 py-2 text-sm text-neutral-700 transition-colors hover:bg-neutral-25 hover:text-neutral-900"
                @click="close"
            >
                {{ item.label }}
            </Link>
        </div>
    </div>
</template>
