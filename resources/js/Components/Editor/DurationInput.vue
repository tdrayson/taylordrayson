<script setup>
import { computed, ref, watch } from 'vue';
import Input from '../Ui/Input.vue';

/**
 * Duration typed the way it is said: "2h 15m", "90m", "1:30".
 *
 * Stored as seconds, because that is what every consumer expects, but nobody
 * should ever be asked to do that arithmetic themselves.
 */
const props = defineProps({
    modelValue: { type: [Number, String], default: null },
    id: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const text = ref('');

/** Seconds -> "2h 15m", dropping the part that is zero. */
function format(seconds) {
    const total = Number(seconds);

    if (! total) {
        return '';
    }

    const hours = Math.floor(total / 3600);
    const minutes = Math.round((total % 3600) / 60);

    return [hours ? `${hours}h` : null, minutes ? `${minutes}m` : null].filter(Boolean).join(' ') || '0m';
}

/** "2h 15m" | "90m" | "1:30" | "90" -> seconds. */
function parse(value) {
    const input = String(value).trim().toLowerCase();

    if (input === '') {
        return null;
    }

    const clock = input.match(/^(\d+):([0-5]?\d)$/);

    if (clock) {
        return Number(clock[1]) * 3600 + Number(clock[2]) * 60;
    }

    const hours = input.match(/(\d+(?:\.\d+)?)\s*h/);
    const minutes = input.match(/(\d+(?:\.\d+)?)\s*m/);

    if (hours || minutes) {
        return Math.round((hours ? parseFloat(hours[1]) * 3600 : 0) + (minutes ? parseFloat(minutes[1]) * 60 : 0));
    }

    // A bare number is minutes: "90" means an hour and a half, not 90 seconds.
    const bare = parseFloat(input);

    return Number.isNaN(bare) ? null : Math.round(bare * 60);
}

watch(() => props.modelValue, (value) => {
    if (document.activeElement?.id !== props.id) {
        text.value = format(value);
    }
}, { immediate: true });

const hint = computed(() => {
    const seconds = parse(text.value);

    return seconds ? format(seconds) : null;
});
</script>

<template>
    <div>
        <Input
            :id="id"
            :model-value="text"
            placeholder="2h 15m"
            @update:model-value="text = $event; emit('update:modelValue', parse($event))"
        />
        <p v-if="hint && hint !== text" class="mt-1 text-caption text-neutral-500">{{ hint }}</p>
    </div>
</template>
