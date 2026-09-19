import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { hiddenNames, required, revealed } from '../../resources/js/lib/editor/visibility.js';

const kind = { name: 'response_kind', showWhen: null };
const url = { name: 'response_url', showWhen: { response_kind: [] } };
const rsvp = { name: 'rsvp_value', showWhen: { response_kind: ['rsvp'] } };

describe('conditional field visibility', () => {
    it('always shows a field with no condition', () => {
        assert.equal(revealed(kind, {}), true);
    });

    it('shows an any-value field once the field it depends on is set', () => {
        assert.equal(revealed(url, { response_kind: null }), false);
        assert.equal(revealed(url, { response_kind: 'like' }), true);
    });

    // An empty string is a select's "nothing chosen", not a value.
    it('treats an empty string as unset', () => {
        assert.equal(revealed(url, { response_kind: '' }), false);
    });

    it('shows a listed-value field only for those values', () => {
        assert.equal(revealed(rsvp, { response_kind: 'like' }), false);
        assert.equal(revealed(rsvp, { response_kind: 'rsvp' }), true);
    });

    it('requires every condition, so two read as and', () => {
        const both = { name: 'x', showWhen: { a: ['1'], b: [] } };

        assert.equal(revealed(both, { a: '1', b: null }), false);
        assert.equal(revealed(both, { a: '2', b: 'set' }), false);
        assert.equal(revealed(both, { a: '1', b: 'set' }), true);
    });

    // What the editor clears. A field with no condition is never in this list,
    // however empty it is, or every optional field would be wiped on save.
    it('names only the conditional fields whose condition has stopped being met', () => {
        const fields = [kind, url, rsvp];

        assert.deepEqual(hiddenNames(fields, { response_kind: 'rsvp' }), []);
        assert.deepEqual(hiddenNames(fields, { response_kind: 'like' }), ['rsvp_value']);
        assert.deepEqual(hiddenNames(fields, { response_kind: null }), ['response_url', 'rsvp_value']);
    });
});

describe('conditional required', () => {
    const content = { name: 'content', required: true, requiredUnless: { response_kind: ['like', 'repost', 'rsvp'] } };

    it('holds a required field to it until its condition excuses it', () => {
        assert.equal(required(content, { response_kind: '' }), true);
        assert.equal(required(content, { response_kind: 'reply' }), true);
        assert.equal(required(content, { response_kind: 'like' }), false);
    });

    it('never requires a field that is not required', () => {
        assert.equal(required({ name: 'x', required: false }, {}), false);
        assert.equal(required({ name: 'x', required: true }, {}), true);
    });
});
