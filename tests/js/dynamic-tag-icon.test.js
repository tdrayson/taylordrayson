import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { dynamicTagIcon } from '../../resources/js/lib/dynamicTagIcon.js';

describe('dynamicTagIcon', () => {
    it('shows nothing when the icon option is off', () => {
        assert.equal(dynamicTagIcon('ambient.weather.condition', 'clear', null), null);
        assert.equal(dynamicTagIcon('ambient.weather.condition', 'clear', false), null);
    });

    it('looks up the weather glyph straight from the resolved condition slug', () => {
        const descriptor = dynamicTagIcon('ambient.weather.condition', 'partly-cloudy', true);

        assert.equal(descriptor.kind, 'icon');
    });

    it('turns a battery percent and its extra state into a live battery descriptor', () => {
        const descriptor = dynamicTagIcon('ambient.battery.percent', 18, { charging: true, lowPower: false });

        assert.deepEqual(descriptor, { kind: 'battery', level: 0.18, charging: true, lowPower: false });
    });

    it('shows a typed entries icon only when a type was actually chosen', () => {
        assert.equal(dynamicTagIcon('entries.count', 3, { type: null }), null);
        assert.equal(dynamicTagIcon('entries.count', 3, { type: 'activity' }).kind, 'icon');
    });

    it('falls back to the flame for an untyped streak, not to nothing', () => {
        const untyped = dynamicTagIcon('streak.current', 4, { type: null });
        const typed = dynamicTagIcon('streak.current', 4, { type: 'sleep' });

        assert.equal(untyped.icon, 'FireIcon');
        assert.notEqual(typed.icon, 'FireIcon');
    });

    it('prefers a stored favicon for a social link, falling back to a globe', () => {
        const withFavicon = dynamicTagIcon('site.social', 'https://social.example/tdrayson', true, { 'social.example': '/favicons/social-example.png' });
        const withoutFavicon = dynamicTagIcon('site.social', 'https://social.example/tdrayson', true, {});

        assert.deepEqual(withFavicon, { kind: 'favicon', src: '/favicons/social-example.png' });
        assert.equal(withoutFavicon.kind, 'icon');
        assert.equal(withoutFavicon.icon, 'Globe02Icon');
    });
});
