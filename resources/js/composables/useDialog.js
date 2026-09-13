import { ref, watch, nextTick, onBeforeUnmount } from 'vue';

// Elements that can receive keyboard focus inside a dialog. Includes form
// controls so sr-only radios etc. stay in the trap (the a11y fix, centralised).
function focusableWithin(el) {
    if (!el) {
        return [];
    }
    const nodes = [...el.querySelectorAll('button, a[href], input, select, textarea, [tabindex]:not([tabindex="-1"])')].filter(
        (node) => !node.hasAttribute('disabled') && node.offsetParent !== null,
    );

    // Within a native radio group only ONE radio is a real tab stop: the checked
    // one, or the first when none is checked. Keep just that representative so the
    // trap's first/last match the actual tab sequence - otherwise, when the
    // focused radio isn't the DOM-last radio, Tab escapes the trap.
    return nodes.filter((node) => {
        if (node.tagName === 'INPUT' && node.type === 'radio' && node.name) {
            const group = [...el.querySelectorAll(`input[type="radio"][name="${CSS.escape(node.name)}"]`)];
            const representative = group.find((radio) => radio.checked) ?? group[0];

            return node === representative;
        }

        return true;
    });
}

/**
 * Shared dialog behaviour: focus trap, Escape-to-close, body scroll lock and
 * focus save/restore. Consumers own their open state and chrome.
 *
 * @param {object} options
 * @param {() => boolean} options.isOpen   Reactive getter for the open state.
 * @param {() => void} options.onClose     Called when the dialog requests close (Escape).
 * @param {boolean} [options.closeOnEsc=true]  Set false when the consumer owns Escape itself.
 * @param {(event: KeyboardEvent) => void} [options.onKeydown]  Extra key handling (arrows) on the same listener.
 * @param {boolean} [options.trapFocus=true]  Set false when the consumer owns its own Tab handling
 *   (e.g. CommandPalette, which deliberately keeps Tab on its search input instead of wrapping).
 * @returns {{ panelEl: import('vue').Ref }}  Bind panelEl to the dialog element.
 */
export function useDialog({ isOpen, onClose, closeOnEsc = true, onKeydown, trapFocus = true }) {
    const panelEl = ref(null);
    let lastFocused = null;
    // Set once this instance has actually opened. Guards the closed branch
    // below so a dialog that mounts already-closed (every consumer, every
    // time `immediate` runs its first call) does not run close-cleanup for an
    // open it never had - without it, mounting a closed dialog while a sibling
    // is genuinely open would clear that sibling's scroll lock and listener.
    let hasOpened = false;

    function handleKeydown(event) {
        if (closeOnEsc && event.key === 'Escape') {
            onClose();
            return;
        }

        if (trapFocus && event.key === 'Tab') {
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

    // Only listen for keydown when there's actually work to do: closing on
    // Escape, trapping Tab, or a consumer-supplied handler. A consumer that
    // owns its own keydown model (e.g. CommandPalette) passes none of these
    // and still gets scroll-lock and focus save/restore from the watcher below.
    const needsKeydownListener = closeOnEsc || Boolean(onKeydown) || trapFocus;

    // Immediate: a dialog that mounts already open (a popup opened by the same
    // action that creates it, rather than toggled on an always-mounted
    // instance) needs this to run on that first render, not just on a later
    // change - without it, the first watch call is the close that never
    // fired, and neither the Escape listener nor the initial focus ever land.
    watch(isOpen, (open) => {
        if (open) {
            hasOpened = true;
            document.body.style.overflow = 'hidden';
            lastFocused = document.activeElement;

            if (needsKeydownListener) {
                document.addEventListener('keydown', handleKeydown);
            }

            nextTick(() => {
                (focusableWithin(panelEl.value)[0] ?? panelEl.value)?.focus();
            });
        } else if (hasOpened) {
            document.body.style.overflow = '';

            if (needsKeydownListener) {
                document.removeEventListener('keydown', handleKeydown);
            }

            if (lastFocused && typeof lastFocused.focus === 'function') {
                lastFocused.focus();
            }

            lastFocused = null;
        }
    }, { immediate: true });

    // Same guard as the watcher above: a chip mounts a closed Modal, and
    // unmounting it must not clear the scroll lock a sibling dialog is
    // genuinely holding.
    onBeforeUnmount(() => {
        document.removeEventListener('keydown', handleKeydown);

        if (hasOpened) {
            document.body.style.overflow = '';
        }
    });

    return { panelEl };
}
