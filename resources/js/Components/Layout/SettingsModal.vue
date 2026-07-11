<script setup>
import { ref, watch, nextTick, onBeforeUnmount } from 'vue';
import { Cancel01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';
import ThemeCards from './ThemeCards.vue';
import { useSettings } from '../../useSettings';

const { settingsOpen, closeSettings } = useSettings();

// The dialog panel, for the Tab focus trap; the trigger element, so focus can
// return to it once the dialog closes (mirrors Lightbox.vue / ContentToc.vue).
const panelEl = ref(null);
let lastFocused = null;

function focusableInPanel() {
    if (!panelEl.value) {
        return [];
    }

    return [...panelEl.value.querySelectorAll('button, a[href], [tabindex]:not([tabindex="-1"])')].filter(
        (el) => !el.hasAttribute('disabled') && el.offsetParent !== null,
    );
}

// Esc closes; Tab is trapped inside the dialog while it's open.
function onKeydown(event) {
    if (event.key === 'Escape') {
        closeSettings();

        return;
    }

    if (event.key !== 'Tab') {
        return;
    }

    const focusable = focusableInPanel();

    if (focusable.length === 0) {
        return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    const active = document.activeElement;

    if (event.shiftKey && (active === first || !panelEl.value.contains(active))) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && active === last) {
        event.preventDefault();
        first.focus();
    }
}

// Lock background scroll while open, move focus into the dialog, and restore
// it to whichever gear button opened the modal on close.
watch(settingsOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';

    if (open) {
        lastFocused = document.activeElement;
        document.addEventListener('keydown', onKeydown);
        nextTick(() => {
            (focusableInPanel()[0] ?? panelEl.value)?.focus();
        });
    } else {
        document.removeEventListener('keydown', onKeydown);

        if (lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus();
        }

        lastFocused = null;
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <Transition name="settings-modal">
            <div v-if="settingsOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <!-- Fixed bg-black (not bg-neutral-900): the backdrop is an intentional
                     dim scrim in both themes, so it must not invert with the neutral ramp. -->
                <div class="absolute inset-0 bg-black/50" @click="closeSettings" />

                <div
                    ref="panelEl"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="settings-title"
                    tabindex="-1"
                    class="relative z-10 w-full max-w-md rounded-lg border border-neutral-50 bg-neutral-0 shadow-card focus:outline-none"
                >
                    <div class="flex items-center justify-between border-b border-neutral-50 px-5 py-4">
                        <h2 id="settings-title" class="font-display text-section">Settings</h2>
                        <button
                            type="button"
                            aria-label="Close settings"
                            class="rounded-md p-1 text-neutral-500 transition-colors hover:text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                            @click="closeSettings"
                        >
                            <Icon :icon="Cancel01Icon" class="size-5" />
                        </button>
                    </div>

                    <div class="space-y-6 p-5">
                        <section class="space-y-3">
                            <h3 class="text-label uppercase tracking-wide text-neutral-500">Appearance</h3>
                            <div class="space-y-2">
                                <span class="text-body text-neutral-900">Theme</span>
                                <ThemeCards />
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.settings-modal-enter-active,
.settings-modal-leave-active {
    transition: opacity 0.15s ease;
}

.settings-modal-enter-from,
.settings-modal-leave-to {
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .settings-modal-enter-active,
    .settings-modal-leave-active {
        transition: none;
    }
}
</style>
