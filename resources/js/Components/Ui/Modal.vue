<script setup>
import { useId } from 'vue';
import Heading from './Heading.vue';
import Icon from './Icon.vue';
import { useDialog } from '../../composables/useDialog';
import { useMounted } from '../../composables/useMounted';
import { cn } from '../../lib/cn.js';

const mounted = useMounted();

const props = defineProps({
    open: { type: Boolean, default: false },
    // Visible dialog title; renders a labelled header + a close button.
    title: { type: String, default: null },
    // Accessible name when there is no visible title.
    ariaLabel: { type: String, default: null },
    closeOnBackdrop: { type: Boolean, default: true },
    // Size / positioning override for the panel.
    panelClass: { type: [String, Array, Object], default: '' },
    // Accessible name for the close button; callers with a specific context
    // (e.g. "Close settings") should override the generic default.
    closeLabel: { type: String, default: 'Close' },
});

const emit = defineEmits(['update:open']);

function close() {
    emit('update:open', false);
}

const { panelEl } = useDialog({ isOpen: () => props.open, onClose: close });

// Unique per mounted instance so multiple Modals never collide on id, unlike
// a hardcoded "modal-title".
const titleId = useId();
</script>

<template>
    <Teleport v-if="mounted" to="body">
        <Transition name="fade">
            <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <!-- Fixed black, not the neutral ramp: an intentional dark surface in both themes. -->
                <div class="absolute inset-0 bg-black/50" @click="closeOnBackdrop && close()" />

                <div
                    ref="panelEl"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="title ? null : ariaLabel"
                    :aria-labelledby="title ? titleId : null"
                    tabindex="-1"
                    :class="cn('relative z-10 flex max-h-full w-full max-w-md flex-col overflow-hidden rounded-lg border border-neutral-50 bg-neutral-0 shadow-card focus:outline-none', panelClass)"
                >
                    <div v-if="title || $slots.header" class="flex shrink-0 items-center justify-between border-b border-neutral-50 px-5 py-4">
                        <slot name="header">
                            <Heading :id="titleId" size="section">{{ title }}</Heading>
                        </slot>
                        <button
                            type="button"
                            :aria-label="closeLabel"
                            class="rounded-md p-1 text-neutral-500 transition-colors hover:text-neutral-900"
                            @click="close"
                        >
                            <Icon name="Cancel01Icon" class="size-5" />
                        </button>
                    </div>

                    <div class="min-h-0 overflow-y-auto p-5">
                        <slot />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
