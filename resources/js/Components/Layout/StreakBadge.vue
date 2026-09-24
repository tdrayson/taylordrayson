<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Eyebrow from '../Ui/Eyebrow.vue';
import Heading from '../Ui/Heading.vue';
import Icon from '../Ui/Icon.vue';

const props = defineProps({
    // Falls back to the shared count, which every page carries; passing one
    // explicitly is for the design system, which has no real page behind it.
    count: { type: [Number, String], default: null },
    label: { type: String, default: 'days logged' },
});

const page = usePage();

// Same source as the sidebar everywhere else: shared by the middleware and
// recomputed the moment a new day is logged, so it is never a frozen number.
const days = computed(() => props.count ?? page.props.streakDays ?? 0);

const formatted = computed(() =>
    typeof days.value === 'number' ? days.value.toLocaleString() : days.value,
);
</script>

<template>
    <div>
        <Eyebrow class="text-neutral-500">Streak</Eyebrow>
        <div class="mt-1 flex items-baseline gap-2">
            <Icon name="FireIcon" class="size-4 self-center text-accent-500" />
            <Heading as="span" size="title" class="tabular-nums">{{ formatted }}</Heading>
            <span class="text-xs text-neutral-500">{{ label }}</span>
        </div>
    </div>
</template>
