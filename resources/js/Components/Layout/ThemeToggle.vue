<script setup>
import { useTheme } from '../../useTheme';
import Icon from '../Ui/Icon.vue';
import { ComputerIcon, Sun03Icon, Moon02Icon } from '@hugeicons-pro/core-stroke-rounded';

const { theme, setTheme } = useTheme();

// Three explicit options; System defers to the OS setting.
const options = [
    { value: 'system', label: 'System', icon: ComputerIcon },
    { value: 'light', label: 'Light', icon: Sun03Icon },
    { value: 'dark', label: 'Dark', icon: Moon02Icon },
];
</script>

<template>
    <div
        role="radiogroup"
        aria-label="Colour theme"
        class="inline-flex items-center gap-1 rounded-lg border border-neutral-100 p-1"
    >
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            role="radio"
            :aria-checked="theme === option.value"
            :aria-label="option.label"
            class="rounded-md p-1.5 text-neutral-500 transition hover:bg-neutral-50 hover:text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
            :class="theme === option.value ? 'bg-neutral-50 text-neutral-900' : ''"
            @click="setTheme(option.value)"
        >
            <Icon :icon="option.icon" class="size-4" />
        </button>
    </div>
</template>
