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

test('a dynamicHref markDef becomes a link mark carrying its tag', () => {
    const doc = toProseMirror([{
        _type: 'block', _key: 'b1', style: 'normal',
        markDefs: [{ _key: 'd1', _type: 'dynamicHref', tag: 'site.social', options: { network: 'github' } }],
        children: [{ _type: 'span', _key: 's1', text: 'my GitHub', marks: ['d1'] }],
    }]);

    const mark = doc.content[0].content[0].marks[0];

    assert.equal(mark.type, 'link');
    assert.deepEqual(mark.attrs, { _key: 'd1', tag: 'site.social', options: { network: 'github' } });
});

test('a dynamic link mark serialises to a dynamicHref markDef', () => {
    const blocks = fromProseMirror({
        type: 'doc',
        content: [{
            type: 'paragraph',
            attrs: { _key: 'b1' },
            content: [{
                type: 'text',
                text: 'my GitHub',
                marks: [{ type: 'link', attrs: { _key: 'd1', tag: 'site.social', options: { network: 'github' } } }],
            }],
        }],
    });

    assert.deepEqual(blocks[0].markDefs[0], {
        _key: 'd1', _type: 'dynamicHref', tag: 'site.social', options: { network: 'github' },
    });
    assert.deepEqual(blocks[0].children[0].marks, ['d1']);
});

test('an ordinary link keeps its own markDef alongside a dynamic one', () => {
    resetKeyCounter();

    const blocks = fromProseMirror({
        type: 'doc',
        content: [{
            type: 'paragraph',
            attrs: { _key: 'b1' },
            content: [
                { type: 'text', text: 'plain', marks: [{ type: 'link', attrs: { _key: 'l1', href: 'https://example.com' } }] },
                { type: 'text', text: ' and ' },
                { type: 'text', text: 'dynamic', marks: [{ type: 'link', attrs: { _key: 'd1', tag: 'site.social', options: {} } }] },
            ],
        }],
    });

    assert.deepEqual(blocks[0].markDefs, [
        { _key: 'l1', _type: 'link', href: 'https://example.com' },
        { _key: 'd1', _type: 'dynamicHref', tag: 'site.social', options: {} },
    ]);
    assert.deepEqual(blocks[0].children[0].marks, ['l1']);
    assert.deepEqual(blocks[0].children[2].marks, ['d1']);
});

test('a dynamic link survives a full round trip unchanged', () => {
    resetKeyCounter();

    const original = [{
        _type: 'block', _key: 'b1', style: 'normal',
        markDefs: [{ _key: 'd1', _type: 'dynamicHref', tag: 'site.social', options: { network: 'github' } }],
        children: [{ _type: 'span', _key: 's1', text: 'my GitHub', marks: ['d1'] }],
    }];

    const back = fromProseMirror(toProseMirror(original));

    assert.deepEqual(back[0].markDefs, original[0].markDefs);
    assert.deepEqual(back[0].children[0].marks, original[0].children[0].marks);
});

test('an image carrying a tag round-trips without a url', () => {
    const original = [{ _type: 'image', _key: 'i1', tag: 'entries.photo', options: {} }];
    const back = fromProseMirror(toProseMirror(original));

    assert.equal(back[0]._type, 'image');
    assert.equal(back[0].tag, 'entries.photo');
    assert.ok(! back[0].url, 'a tagged image must not carry a url');
});

test('a plain image keeps its url and carries no tag', () => {
    const original = [{ _type: 'image', _key: 'i1', url: '/storage/1/a.jpg', alt: null, ratio: null, caption: null, width: null, height: null }];
    const back = fromProseMirror(toProseMirror(original));

    assert.equal(back[0].url, '/storage/1/a.jpg');
    assert.ok(! back[0].tag, 'an ordinary image must not carry a tag');
});
