import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { tooltipSuppressed } from '../../resources/js/lib/tooltip.js';

/** The bits of an element the function actually looks at. */
const trigger = ({ inner = null, ancestor = null } = {}) => ({
    querySelector: (selector) => (selector === 'a[href]' ? inner : null),
    closest: (selector) => (selector === 'a[href]' ? ancestor : null),
});

describe('tooltipSuppressed', () => {
    it('never suppresses where the pointer can hover', () => {
        assert.equal(tooltipSuppressed(trigger({ inner: {} }), true), false);
        assert.equal(tooltipSuppressed(trigger(), true), false);
    });

    it('suppresses a tooltip wrapping a link when the pointer cannot hover', () => {
        // The tap follows the link, and the bubble would be left up behind it.
        assert.equal(tooltipSuppressed(trigger({ inner: {} }), false), true);
    });

    it('suppresses a tooltip sitting inside a link', () => {
        // Same tap, same navigation; only the nesting is the other way round.
        assert.equal(tooltipSuppressed(trigger({ ancestor: {} }), false), true);
    });

    it('still shows a tooltip on anything that is not a link', () => {
        // Here the tap has no other meaning, so the tooltip is what it is for.
        assert.equal(tooltipSuppressed(trigger(), false), false);
    });

    it('copes with a trigger that is not mounted yet', () => {
        assert.equal(tooltipSuppressed(null, false), false);
    });
});
