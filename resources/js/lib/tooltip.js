/**
 * Whether a tooltip should stay down for this trigger.
 *
 * A device with no hover synthesises mouseenter from a tap, so a tooltip on a
 * link fires at the same moment the link is followed, and on the way back the
 * bubble is still up with nothing left to dismiss it. A tap on anything else
 * has no other meaning, so there the tooltip is the whole point of the tap.
 *
 * Either direction counts: a tooltip may wrap a link, or sit inside one. Both
 * are a tap that navigates.
 *
 * @param {Element|null} trigger The tooltip's own root element.
 * @param {boolean} canHover Whether the pointer can hover, i.e. not a touch screen.
 * @returns {boolean}
 */
export function tooltipSuppressed(trigger, canHover) {
    if (canHover || ! trigger) {
        return false;
    }

    return Boolean(trigger.querySelector?.('a[href]') || trigger.closest?.('a[href]'));
}
