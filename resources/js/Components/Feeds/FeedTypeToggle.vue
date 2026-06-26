<script setup>
import { computed } from 'vue';
import Icon from '../Ui/Icon.vue';
import { entryType } from '../../entryTypes.js';

const props = defineProps({
    typeKey: { type: String, required: true },
    label: { type: String, required: true },
    active: { type: Boolean, default: false },
});

defineEmits(['toggle']);

const meta = computed(() => entryType(props.typeKey));
const accent = computed(() => `var(--color-${meta.value.accent})`);
</script>

<template>
    <button
        type="button"
        :aria-pressed="active"
        class="feed-toggle inline-flex items-center gap-2 rounded-full border px-3.5 py-2 text-meta font-semibold transition-colors"
        :class="active ? 'feed-toggle--active text-neutral-0' : 'border-neutral-100 text-neutral-700 hover:border-accent-500'"
        :style="{ '--accent': accent }"
        @click="$emit('toggle', typeKey)"
    >
        <Icon :icon="meta.icon" class="size-4" />
        {{ label }}
    </button>
</template>

<style scoped>
.feed-toggle--active {
    background: var(--accent);
    border-color: var(--accent);
}
</style>
