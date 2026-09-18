<script setup>
defineProps({
    fill: { type: Boolean, default: false },
    title: { type: String, required: true },
    author: { type: String, required: true },
    cover: { type: String, default: null },
    // Whole-number percent, already floored server-side; null for a finished book.
    percent: { type: Number, default: null },
    finished: { type: Boolean, default: false },
});

const onCoverError = (event) => {
    event.target.style.display = 'none';
};
</script>

<template>
    <div class="@container relative overflow-hidden rounded-3xl bg-neutral-0 shadow-card" :class="{ 'aspect-2/1': !fill }">
        <div class="absolute left-1/20 -bottom-1/10 z-2 aspect-2/3 w-3/10 origin-bottom -rotate-5 animate-book-in rounded-sm shadow-2xl motion-reduce:animate-none">
            <div class="absolute inset-0 flex items-center justify-center rounded-sm bg-linear-150 from-neutral-100 to-neutral-50 p-2 text-center">
                <span class="text-eyebrow uppercase text-neutral-400">Cover</span>
            </div>
            <img v-if="cover" class="absolute inset-0 z-1 size-full rounded-sm object-cover" :src="cover" alt="" @error="onCoverError" />
        </div>

        <div class="absolute inset-y-0 right-1/20 left-9/20 z-3 flex flex-col justify-center">
            <div class="text-eyebrow tracking-wide text-neutral-500 @md:text-xs @xl:text-sm">
                {{ finished ? 'Last read' : 'Currently reading' }}
            </div>
            <h2 class="mt-1 line-clamp-2 text-base leading-tight font-extrabold tracking-tight text-neutral-900 @sm:text-xl @md:mt-2 @md:line-clamp-3 @md:text-2xl @xl:mt-2.5 @xl:text-3xl">
                {{ title }}
            </h2>
            <div class="mt-1.5 text-xs font-medium text-neutral-500 @sm:text-sm @md:mt-2.5 @md:text-base @xl:mt-3 @xl:text-xl">{{ author }}</div>
            <div v-if="percent !== null" class="mt-1 text-xs font-bold text-neutral-700 @sm:text-sm @md:mt-1.5 @md:text-base @xl:mt-2 @xl:text-xl">
                {{ percent }}% read
            </div>
        </div>
    </div>
</template>
