<script setup>
import { computed } from 'vue';
import Icon from '../Ui/Icon.vue';
import { useSettings } from '../../useSettings';
import { cn } from '../../lib/cn.js';

const props = defineProps({
    class: { type: [String, Array, Object], default: '' },
});

const { resolved, setTheme } = useSettings();

const isDark = computed(() => resolved.value === 'dark');

// Swap to the opposite of what is showing, as an explicit choice rather than 'system'.
function toggle() {
    setTheme(isDark.value ? 'light' : 'dark');
}
</script>

<template>
    <button
        type="button"
        :aria-label="isDark ? 'Switch to light theme' : 'Switch to dark theme'"
        :class="cn('flex items-center justify-center transition-colors', props.class)"
        @click="toggle"
    >
        <Icon :name="isDark ? 'Moon02Icon' : 'Sun03Icon'" class="size-5" />
    </button>
</template>
