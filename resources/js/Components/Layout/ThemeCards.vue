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
            class="group flex flex-col gap-2 rounded-lg p-1.5 text-center focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2"
            @click="setTheme(card.value)"
        >
            <!-- Stylised theme preview thumbnail. -->
            <span
                class="block overflow-hidden rounded-md border-2 transition"
                :class="theme === card.value ? 'border-accent-500' : 'border-neutral-100 group-hover:border-neutral-300'"
            >
                <span class="preview" :class="`preview-${card.value}`">
                    <span class="preview-bar" />
                    <span class="preview-line" />
                    <span class="preview-line short" />
                </span>
            </span>
            <span
                class="text-caption font-semibold"
                :class="theme === card.value ? 'text-neutral-900' : 'text-neutral-500'"
            >{{ card.label }}</span>
        </button>
    </div>
</template>

<style scoped>
.preview {
    display: block;
    aspect-ratio: 16 / 10;
    padding: 6px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.preview-bar {
    height: 6px;
    width: 40%;
    border-radius: 3px;
}

.preview-line {
    height: 4px;
    width: 85%;
    border-radius: 2px;
}

.preview-line.short {
    width: 60%;
}

/* Light thumbnail. */
.preview-light {
    background: #ffffff;
}
.preview-light .preview-bar { background: #3858e9; }
.preview-light .preview-line { background: #c9c9c9; }

/* Dark thumbnail. */
.preview-dark {
    background: #191919;
}
.preview-dark .preview-bar { background: #6c84f2; }
.preview-dark .preview-line { background: #3f3f3f; }

/* System thumbnail: diagonal split of light and dark. */
.preview-system {
    background: linear-gradient(135deg, #ffffff 0 50%, #191919 50% 100%);
}
.preview-system .preview-bar { background: #6c84f2; }
.preview-system .preview-line { background: #8c8c8c; }
</style>
