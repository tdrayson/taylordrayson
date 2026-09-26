<script setup>
import { computed } from 'vue';
import Icon from '../Ui/Icon.vue';
import { useSettings } from '../../useSettings';
import { cn } from '../../lib/cn.js';

const props = defineProps({
    class: { type: [String, Array, Object], default: '' },
});

const { theme, setTheme } = useSettings();

// Each press moves to the next theme, wrapping from dark back to system.
const THEMES = {
    system: { icon: 'ComputerIcon', label: 'System', next: 'light' },
    light: { icon: 'Sun03Icon', label: 'Light', next: 'dark' },
    dark: { icon: 'Moon02Icon', label: 'Dark', next: 'system' },
};

const current = computed(() => THEMES[theme.value] ?? THEMES.system);
</script>

<template>
    <button
        type="button"
        :aria-label="`Theme: ${current.label}. Switch to ${THEMES[current.next].label.toLowerCase()}`"
        :title="`Theme: ${current.label}`"
        :class="cn('flex items-center justify-center transition-colors', props.class)"
        @click="setTheme(current.next)"
    >
        <Icon :name="current.icon" class="size-5" />
    </button>
</template>
