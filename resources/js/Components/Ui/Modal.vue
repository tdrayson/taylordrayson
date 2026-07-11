<script setup>
import { Cancel01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from './Icon.vue';
import { useDialog } from '../../composables/useDialog';

const props = defineProps({
    open: { type: Boolean, default: false },
    // Visible dialog title; renders a labelled header + a close button.
    title: { type: String, default: null },
    // Accessible name when there is no visible title.
    ariaLabel: { type: String, default: null },
    closeOnBackdrop: { type: Boolean, default: true },
    // Size / positioning override for the panel.
    panelClass: { type: [String, Array, Object], default: '' },
});

const emit = defineEmits(['update:open']);

function close() {
    emit('update:open', false);
}

const { panelEl } = useDialog({ isOpen: () => props.open, onClose: close });
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <!-- Fixed bg-black (not bg-neutral-900): the backdrop is an intentional
                     dim scrim in both themes, so it must not invert with the neutral ramp. -->
                <div class="absolute inset-0 bg-black/50" @click="closeOnBackdrop && close()" />

                <div
                    ref="panelEl"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="title ? null : ariaLabel"
                    :aria-labelledby="title ? 'modal-title' : null"
                    tabindex="-1"
                    :class="['relative z-10 w-full max-w-md rounded-lg border border-neutral-50 bg-neutral-0 shadow-card focus:outline-none', panelClass]"
                >
                    <div v-if="title || $slots.header" class="flex items-center justify-between border-b border-neutral-50 px-5 py-4">
                        <slot name="header">
                            <h2 id="modal-title" class="font-display text-section">{{ title }}</h2>
                        </slot>
                        <button
                            type="button"
                            aria-label="Close"
                            class="rounded-md p-1 text-neutral-500 transition-colors hover:text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                            @click="close"
                        >
                            <Icon :icon="Cancel01Icon" class="size-5" />
                        </button>
                    </div>

                    <div class="p-5">
                        <slot />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.15s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .fade-enter-active,
    .fade-leave-active {
        transition: none;
    }
}
</style>
