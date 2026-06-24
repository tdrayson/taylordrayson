<script setup>
import { FireIcon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';

defineProps({
    count: { type: [Number, String], default: '2,145' },
    label: { type: String, default: 'days logged' },
});
</script>

<template>
    <div>
        <div class="text-eyebrow uppercase text-ink-3">Streak</div>
        <div class="mt-1 flex items-baseline gap-2">
            <Icon :icon="FireIcon" class="size-4 self-center text-accent" />
            <span class="font-display text-name tnum">{{ count }}</span>
            <span class="text-caption text-ink-3">{{ label }}</span>
        </div>
    </div>
</template>
