<script setup>
import { ref } from 'vue';
import LookupInput from './LookupInput.vue';

/**
 * A place: search by name, or ask the browser where you are.
 *
 * Geolocation is on a button and never automatic, so the permission prompt
 * only ever appears because it was asked for, and nothing is captured when you
 * are entering something from last week at a desk.
 */
defineProps({
    modelValue: { type: [String, Number], default: '' },
    source: { type: String, default: 'place' },
    placeholder: { type: String, default: '' },
    id: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue', 'fill']);

const locating = ref(false);
const error = ref(null);

function useMyLocation() {
    if (! navigator.geolocation) {
        error.value = 'This browser cannot report a location.';

        return;
    }

    locating.value = true;
    error.value = null;

    navigator.geolocation.getCurrentPosition(async (position) => {
        const { latitude, longitude } = position.coords;

        try {
            const response = await fetch(`/lookup-reverse?lat=${latitude}&lng=${longitude}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            const place = response.ok ? (await response.json()).data : null;

            // The coordinates are the useful part and are kept even when the
            // name cannot be resolved, since the map only needs the position.
            emit('fill', { latitude, longitude });

            if (place?.name) {
                emit('update:modelValue', place.name);
            }
        } catch {
            emit('fill', { latitude, longitude });
        } finally {
            locating.value = false;
        }
    }, (positionError) => {
        locating.value = false;
        error.value = positionError.code === 1
            ? 'Location permission was declined.'
            : 'Could not get a location.';
    }, { enableHighAccuracy: true, timeout: 10000 });
}
</script>

<template>
    <div>
        <div class="flex items-start gap-2">
            <LookupInput
                :id="id"
                :model-value="modelValue"
                :source="source"
                :placeholder="placeholder"
                class="flex-1"
                @update:model-value="emit('update:modelValue', $event)"
                @fill="emit('fill', $event)"
            />

            <button
                type="button"
                class="shrink-0 rounded-md border border-neutral-100 px-3 py-2 text-meta text-neutral-700 transition-colors hover:border-accent-500 hover:text-accent-700 disabled:opacity-40"
                :disabled="locating"
                @click="useMyLocation"
            >
                {{ locating ? 'Locating...' : 'Use my location' }}
            </button>
        </div>

        <p v-if="error" class="mt-1 text-caption text-red-600">{{ error }}</p>
    </div>
</template>
