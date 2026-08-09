<script setup>
import { ref } from 'vue';
import LookupInput from './LookupInput.vue';
import Icon from '../Ui/Icon.vue';

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
// The full address of whatever was picked, shown as confirmation. The name
// stays in the field itself, since that is what a card shows as the title.
const resolvedAddress = ref(null);

/**
 * Resolve a position into the parts an entry stores. A search result carries
 * only coordinates, because asking for the components of every row in a list
 * would cost a request per row nobody may choose.
 */
async function resolveParts(latitude, longitude) {
    try {
        const response = await fetch(`/lookup-reverse?lat=${latitude}&lng=${longitude}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        return response.ok ? (await response.json()).data : null;
    } catch {
        return null;
    }
}

async function onFill(values) {
    emit('fill', values);

    if (values.address) {
        resolvedAddress.value = values.address;
    }

    // A station lookup already returns its parts; a place search does not.
    if (values.latitude && ! values.postcode) {
        const place = await resolveParts(values.latitude, values.longitude);

        if (place) {
            resolvedAddress.value = place.formatted ?? place.address ?? resolvedAddress.value;
            emit('fill', Object.fromEntries(
                Object.entries({
                    address: place.address,
                    postcode: place.postcode,
                    city: place.city,
                    country: place.country,
                }).filter(([, value]) => value),
            ));
        }
    }
}

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
            onFill({
                latitude,
                longitude,
                ...(place?.address ? { address: place.address } : {}),
                ...(place?.city ? { city: place.city } : {}),
                ...(place?.postcode ? { postcode: place.postcode } : {}),
                ...(place?.country ? { country: place.country } : {}),
            });

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
                @fill="onFill"
            />

            <button
                type="button"
                class="shrink-0 rounded-md border border-neutral-100 p-2 text-neutral-700 transition-colors hover:border-accent-500 hover:text-accent-700 disabled:opacity-40"
                :disabled="locating"
                :aria-label="locating ? 'Getting your location' : 'Use my location'"
                :title="locating ? 'Getting your location' : 'Use my location'"
                @click="useMyLocation"
            >
                <Icon name="CenterFocusIcon" :class="['size-5', locating && 'animate-pulse']" />
            </button>
        </div>

        <p v-if="error" class="mt-1 text-caption text-red-600">{{ error }}</p>
        <p v-else-if="resolvedAddress" class="mt-1 text-caption text-neutral-500">{{ resolvedAddress }}</p>
    </div>
</template>
