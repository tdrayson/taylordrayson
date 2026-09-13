import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { getSchema } from '@tiptap/core';
import { extensionsFor } from '../../resources/js/lib/editor/profiles.js';

/**
 * `inclusive` is a mark-spec property, and Tiptap's Link derives it from
 * options.autolink. Passing it to configure() therefore does nothing, which is
 * a silent no-op rather than an error, so this asserts the resolved schema
 * rather than the configuration that produced it.
 */
describe('the editor link mark', () => {
    for (const profile of ['inline', 'document']) {
        it(`is not inclusive in the ${profile} profile`, () => {
            const schema = getSchema(extensionsFor(profile));

            assert.equal(
                schema.marks.link.spec.inclusive,
                false,
                'a caret after a link must sit outside it, or a space typed there is swallowed into the link',
            );
        });
    }

    it('degrades a malformed data-dynamic-options to no options rather than throwing', () => {
        const schema = getSchema(extensionsFor('inline'));
        const spanRule = schema.marks.link.spec.parseDOM.find((rule) => rule.tag === 'span[data-href]');

        const element = {
            getAttribute: (name) => ({
                'data-href': null,
                'data-target': null,
                'data-dynamic-tag': 'site.social',
                'data-dynamic-options': '{not valid json',
            })[name] ?? null,
        };

        assert.doesNotThrow(() => spanRule.getAttrs(element));
        assert.equal(spanRule.getAttrs(element).options, null);
    });
});
