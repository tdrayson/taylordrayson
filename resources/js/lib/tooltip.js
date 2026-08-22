/**
 * Whether a tooltip should stay down for this trigger.
 *
 * Without hover, a tap both follows the link and opens the bubble, leaving it
 * up with nothing to dismiss it.
 *
 * @param {Element|null} trigger The tooltip's own root element.
 * @param {boolean} canHover Whether the pointer can hover.
 * @returns {boolean}
 */
export function tooltipSuppressed(trigger, canHover) {
    if (canHover || ! trigger) {
        return false;
    }

    return Boolean(trigger.querySelector?.('a[href]') || trigger.closest?.('a[href]'));
}
