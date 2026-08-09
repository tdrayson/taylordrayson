<script setup>
import Icon from '../Ui/Icon.vue';

defineProps({
    count: { type: [Number, String], default: '2,145' },
    label: { type: String, default: 'days logged' },
});
</script>

<template>
    <div>
        <div class="text-eyebrow uppercase text-neutral-500">Streak</div>
        <div class="mt-1 flex items-baseline gap-2">
            <Icon name="FireIcon" class="size-4 self-center text-accent-500" />
            <span class="font-display text-name tnum">{{ count }}</span>
            <span class="text-caption text-neutral-500">{{ label }}</span>
        </div>
    </div>
</template>
