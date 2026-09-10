import assert from 'node:assert/strict';
import { test } from 'node:test';
import { toProseMirror } from '../../resources/js/lib/portable-text/toProseMirror.js';
import { fromProseMirror, resetKeyCounter } from '../../resources/js/lib/portable-text/fromProseMirror.js';

test('a stored tag becomes a dynamicTag node, not literal text', () => {
    const doc = toProseMirror([{
        _type: 'block', _key: 'b1', style: 'normal', markDefs: [],
        children: [
            { _type: 'span', _key: 's1', text: 'Logged ', marks: [] },
            { _type: 'dynamicTag', _key: 't1', tag: 'entries.count', options: { type: 'note' } },
        ],
    }]);

    const node = doc.content[0].content[1];

    assert.equal(node.type, 'dynamicTag');
    assert.equal(node.attrs.tag, 'entries.count');
    assert.deepEqual(node.attrs.options, { type: 'note' });
    assert.equal(node.attrs._key, 't1');
});

test('a dynamicTag node serialises back to a stored tag node', () => {
    resetKeyCounter();

    const blocks = fromProseMirror({
        type: 'doc',
        content: [{
            type: 'paragraph',
            attrs: { _key: 'b1' },
            content: [
                { type: 'text', text: 'Logged ' },
                { type: 'dynamicTag', attrs: { tag: 'entries.count', options: { type: 'note' }, _key: 't1' } },
            ],
        }],
    });

    assert.deepEqual(blocks[0].children[1], {
        _type: 'dynamicTag', _key: 't1', tag: 'entries.count', options: { type: 'note' },
    });
});

test('a tag survives a full round trip unchanged', () => {
    resetKeyCounter();

    const original = [{
        _type: 'block', _key: 'b1', style: 'normal', markDefs: [],
        children: [{ _type: 'dynamicTag', _key: 't1', tag: 'ambient.rings.move.goal', options: {} }],
    }];

    assert.deepEqual(fromProseMirror(toProseMirror(original))[0].children[0], original[0].children[0]);
});
