<script setup>
import ZoomButton from '../Ui/ZoomButton.vue';
import EntryMap from '../Maps/EntryMap.vue';

defineProps({
    polyline: { type: String, default: null },
    photos: { type: Array, default: () => [] },
    color: { type: String, default: 'var(--color-activity)' },
});

defineEmits(['open']);
</script>

<template>
    <div v-if="polyline || photos.length" class="space-y-2.5">
        <EntryMap
            v-if="polyline"
            :polyline="polyline"
            :photos="photos"
            :color="color"
            @open-photo="$emit('open', $event)"
        />

        <div v-if="photos.length" class="grid grid-cols-2 gap-2.5 sm:grid-cols-3">
            <button
                v-for="(photo, index) in photos"
                :key="index"
                type="button"
                :aria-label="`View photo ${index + 1}`"
                class="group/zoom relative aspect-square overflow-hidden rounded-lg border border-neutral-50 bg-neutral-25 transition-opacity hover:opacity-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                @click="$emit('open', index)"
            >
                <img :src="photo.src" :srcset="photo.srcset || undefined" sizes="(min-width: 768px) 33vw, 50vw" alt="" class="size-full object-cover">
                <span class="pointer-events-none absolute right-2 top-2 opacity-0 transition-opacity group-hover/zoom:opacity-100 group-focus-within/zoom:opacity-100">
                    <ZoomButton />
                </span>
            </button>
        </div>
    </div>
</template>
