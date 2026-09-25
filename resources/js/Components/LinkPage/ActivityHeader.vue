<script setup>
import { computed } from 'vue';
import Avatar from '../Profile/Avatar.vue';
import ThemeToggle from './ThemeToggle.vue';
import { LEVELS } from './shared.js';

const props = defineProps({
    // Activity levels 0-3, oldest day first.
    levels: { type: Array, required: true },
    avatar: { type: String, required: true },
    name: { type: String, required: true },
});

// The avatar takes the first two columns of rows one and two, so row one has
// room for four days before the toggle claims its last column.
const beforeToggle = computed(() => props.levels.slice(0, 4));
const afterToggle = computed(() => props.levels.slice(4));
</script>

<template>
    <div class="grid grid-cols-7 gap-2.5">
        <Avatar :src="avatar" :alt="name" size="size-full" class="col-span-2 row-span-2 rounded-lg border border-accent-200 shadow-sm" />
        <span v-for="(level, day) in beforeToggle" :key="day" :class="LEVELS[level]" class="aspect-square rounded-md" aria-hidden="true" />
        <ThemeToggle class="aspect-square rounded-md bg-accent-600 text-neutral-0 hover:bg-accent-700" />
        <span v-for="(level, day) in afterToggle" :key="day + 4" :class="LEVELS[level]" class="aspect-square rounded-md" aria-hidden="true" />
    </div>
</template>
