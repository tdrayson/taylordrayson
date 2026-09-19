<script setup>
import { useSettings } from '../../useSettings';

const { theme, setTheme } = useSettings();

// System is shown as a split light/dark thumbnail; light/dark are solid.
const cards = [
    { value: 'system', label: 'System' },
    { value: 'light', label: 'Light' },
    { value: 'dark', label: 'Dark' },
];
</script>

<template>
    <div role="radiogroup" aria-label="Colour theme" class="grid grid-cols-3 gap-3">
        <button
            v-for="card in cards"
            :key="card.value"
            type="button"
            role="radio"
            :aria-checked="theme === card.value"
            :aria-label="card.label"
            class="group flex flex-col gap-2 rounded-lg p-1.5 text-center focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
            @click="setTheme(card.value)"
        >
            <span
                class="block overflow-hidden rounded-md border-2 transition"
                :class="theme === card.value ? 'border-accent-500' : 'border-neutral-100 group-hover:border-neutral-300'"
            >
                <span class="flex aspect-16/10 flex-col gap-1 p-1.5" :class="`preview-${card.value}`">
                    <span class="preview-bar h-1.5 w-2/5 rounded-full" />
                    <span class="preview-line h-1 w-17/20 rounded-full" />
                    <span class="preview-line h-1 w-3/5 rounded-full" />
                </span>
            </span>
            <span
                class="text-xs font-semibold"
                :class="theme === card.value ? 'text-neutral-900' : 'text-neutral-500'"
            >{{ card.label }}</span>
        </button>
    </div>
</template>

<style scoped>
/* Each thumbnail shows its theme regardless of the active one, so it can't read the tokens, which flip under .dark. */
.preview-light {
    background: #ffffff;
}
.preview-light .preview-bar { background: #3858e9; }
.preview-light .preview-line { background: #c9c9c9; }

/* Dark thumbnail. */
.preview-dark {
    background: #141a23;
}
.preview-dark .preview-bar { background: #6c84f2; }
.preview-dark .preview-line { background: #3a434e; }

/* System thumbnail: diagonal split of light and dark. */
.preview-system {
    background: linear-gradient(135deg, #ffffff 0 50%, #141a23 50% 100%);
}
.preview-system .preview-bar { background: #6c84f2; }
.preview-system .preview-line { background: #8c8c8c; }
</style>
