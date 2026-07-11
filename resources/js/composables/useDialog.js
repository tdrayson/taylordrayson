import { ref, watch, nextTick, onBeforeUnmount } from 'vue';

// Elements that can receive keyboard focus inside a dialog. Includes form
// controls so sr-only radios etc. stay in the trap (the a11y fix, centralised).
function focusableWithin(el) {
    if (!el) {
        return [];
    }
    return [...el.querySelectorAll('button, a[href], input, select, textarea, [tabindex]:not([tabindex="-1"])')].filter(
        (node) => !node.hasAttribute('disabled') && node.offsetParent !== null,
    );
}

/**
 * Shared dialog behaviour: focus trap, Escape-to-close, body scroll lock, and
 * focus save/restore. Consumers own their open-state and chrome; this owns the
 * cross-cutting a11y that was previously copied into every overlay.
 *
 * @param {object} options
 * @param {() => boolean} options.isOpen   Reactive getter for the open state.
 * @param {() => void} options.onClose     Called when the dialog requests close (Escape).
 * @param {boolean} [options.closeOnEsc=true]  Set false when the consumer owns Escape itself.
 * @param {(event: KeyboardEvent) => void} [options.onKeydown]  Extra key handling (arrows) on the same listener.
 * @returns {{ panelEl: import('vue').Ref }}  Bind panelEl to the dialog element.
 */
export function useDialog({ isOpen, onClose, closeOnEsc = true, onKeydown }) {
    const panelEl = ref(null);
    let lastFocused = null;

    function handleKeydown(event) {
        if (closeOnEsc && event.key === 'Escape') {
            onClose();
            return;
        }

        if (event.key === 'Tab') {
            const focusable = focusableWithin(panelEl.value);

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
            return;
        }

        // Any other key: let the consumer handle it (arrow nav, etc.).
        onKeydown?.(event);
    }

    watch(isOpen, (open) => {
        document.body.style.overflow = open ? 'hidden' : '';

        if (open) {
            lastFocused = document.activeElement;
            document.addEventListener('keydown', handleKeydown);
            nextTick(() => {
                (focusableWithin(panelEl.value)[0] ?? panelEl.value)?.focus();
            });
        } else {
            document.removeEventListener('keydown', handleKeydown);

            if (lastFocused && typeof lastFocused.focus === 'function') {
                lastFocused.focus();
            }

            lastFocused = null;
        }
    });

    onBeforeUnmount(() => {
        document.removeEventListener('keydown', handleKeydown);
        document.body.style.overflow = '';
    });

    return { panelEl };
}
