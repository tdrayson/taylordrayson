<script setup>
import { nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';
import Icon from './Icon.vue';
import { useDismissable } from '../../composables/useDismissable.js';

defineProps({
    label: { type: String, required: true },
});

const { isOpen, root, toggle } = useDismissable();
const trigger = ref(null);
const panel = ref(null);
const panelId = useId();
const position = ref({ top: 0, left: 0 });

const GAP = 8;
const WIDTH = 256;
const EDGE = 12;

/** Place the panel under the trigger, or above it when it would run off the bottom. */
function place() {
    const rect = trigger.value.getBoundingClientRect();
    const height = panel.value?.offsetHeight ?? 0;
    const centred = rect.left + rect.width / 2 - WIDTH / 2;
    const fitsBelow = rect.bottom + GAP + height <= window.innerHeight - EDGE;

    position.value = {
        top: fitsBelow ? rect.bottom + GAP : rect.top - GAP - height,
        left: Math.min(Math.max(centred, EDGE), window.innerWidth - WIDTH - EDGE),
    };
}

// Fixed rather than teleported, so the panel escapes the modal's scroll clipping
// but stays inside its focus trap and the dismiss root.
watch(isOpen, (open) => {
    if (open) {
        place();
        nextTick(place);
        window.addEventListener('scroll', place, true);
        window.addEventListener('resize', place);
    } else {
        window.removeEventListener('scroll', place, true);
        window.removeEventListener('resize', place);
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', place, true);
    window.removeEventListener('resize', place);
});
</script>

<template>
    <span ref="root" class="inline-flex">
        <button
            ref="trigger"
            type="button"
            :aria-label="label"
            :aria-expanded="isOpen"
            :aria-controls="panelId"
            class="rounded-full text-neutral-500 transition-colors hover:text-neutral-900 aria-expanded:text-neutral-900"
            @click="toggle"
        >
            <Icon name="InformationCircleIcon" class="size-4" />
        </button>

        <Transition name="fade">
            <div
                v-if="isOpen"
                :id="panelId"
                ref="panel"
                class="fixed z-50 w-64 space-y-2 rounded-lg border border-neutral-50 bg-neutral-0 p-3 text-sm text-neutral-700 shadow-card"
                :style="{ top: `${position.top}px`, left: `${position.left}px` }"
            >
                <slot />
            </div>
        </Transition>
    </span>
</template>
