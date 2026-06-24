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
        <div class="inline-flex items-center rounded-md border border-line">
            <Button :href="todayUrl" variant="plain">Today</Button>
            <button
                type="button"
                class="flex items-center border-l border-line px-2 py-1.5 text-ink-3 transition-colors hover:text-accent"
                :aria-label="open ? 'Close time navigation' : 'Open time navigation'"
                aria-haspopup="true"
                :aria-expanded="open"
                @click="open = !open"
            >
                <Icon :icon="ArrowDown01Icon" class="size-3.5 transition-transform" :class="open ? 'rotate-180' : ''" />
            </button>
        </div>

        <div
            v-if="open"
            class="absolute right-0 z-50 mt-2 w-44 overflow-hidden rounded-md border border-line bg-canvas py-1 shadow-card"
        >
            <Link
                v-for="item in items"
                :key="item.label"
                :href="item.href"
                class="block px-4 py-2 text-sm text-ink-2 transition-colors hover:bg-surface hover:text-ink"
                @click="close"
            >
                {{ item.label }}
            </Link>
        </div>
    </div>
</template>
