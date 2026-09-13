import assert from 'node:assert/strict';
import { describe, it, beforeEach } from 'node:test';
import { toProseMirror } from '../../resources/js/lib/portable-text/toProseMirror.js';
import { fromProseMirror, resetKeyCounter } from '../../resources/js/lib/portable-text/fromProseMirror.js';

/**
 * Span keys are internal identifiers with no meaning outside a document, and
 * ProseMirror text nodes cannot carry attributes, so they are regenerated on
 * every conversion. Block and markDef keys ARE preserved, and those are the
 * ones that matter: they keep a save from looking like a full rewrite.
 */
function stripSpanKeys(blocks) {
    return blocks.map((block) => {
        if (block._type !== 'block') {
            if (block._type === 'callout') {
                return { ...block, children: stripSpanKeys(block.children ?? []) };
            }

            return block;
        }

        return {
            ...block,
            // Mentions keep their key: it identifies a real reference, not a
            // run of text, and survives as a node attribute.
            children: (block.children ?? []).map((child) => child._type === 'mention'
                ? child
                : (({ _key, ...span }) => span)(child)),
        };
    });
}

/** Portable Text -> ProseMirror -> Portable Text. */
function roundTrip(blocks) {
    resetKeyCounter();

    return fromProseMirror(toProseMirror(blocks));
}

function survives(name, blocks) {
    it(name, () => {
        assert.deepEqual(stripSpanKeys(roundTrip(blocks)), stripSpanKeys(blocks));
    });
}

const span = (text, marks = []) => ({ _type: 'span', _key: 's1', text, marks });

describe('portable text round trip', () => {
    beforeEach(() => resetKeyCounter());

    survives('a plain paragraph', [
        { _type: 'block', _key: 'b1', style: 'normal', children: [span('Hello.')] },
    ]);

    survives('every heading level the renderer supports', [
        { _type: 'block', _key: 'h2', style: 'h2', children: [span('Two')] },
        { _type: 'block', _key: 'h3', style: 'h3', children: [span('Three')] },
        { _type: 'block', _key: 'h4', style: 'h4', children: [span('Four')] },
        { _type: 'block', _key: 'h5', style: 'h5', children: [span('Five')] },
        { _type: 'block', _key: 'h6', style: 'h6', children: [span('Six')] },
    ]);

    survives('a blockquote', [
        { _type: 'block', _key: 'q1', style: 'blockquote', children: [span('Quoted.')] },
    ]);

    survives('decorator marks', [
        {
            _type: 'block',
            _key: 'b1',
            style: 'normal',
            children: [
                span('plain '),
                span('bold', ['strong']),
                span(' and '),
                span('italic', ['em']),
                span(' and '),
                span('code', ['code']),
            ],
        },
    ]);

    survives('a link, with its markDef', [
        {
            _type: 'block',
            _key: 'b1',
            style: 'normal',
            markDefs: [{ _key: 'l1', _type: 'link', href: 'https://example.com' }],
            children: [span('a link', ['l1'])],
        },
    ]);

    survives('a bulleted list', [
        { _type: 'block', _key: 'i1', style: 'normal', listItem: 'bullet', level: 1, children: [span('One')] },
        { _type: 'block', _key: 'i2', style: 'normal', listItem: 'bullet', level: 1, children: [span('Two')] },
    ]);

    survives('a numbered list', [
        { _type: 'block', _key: 'i1', style: 'normal', listItem: 'number', level: 1, children: [span('First')] },
        { _type: 'block', _key: 'i2', style: 'normal', listItem: 'number', level: 1, children: [span('Second')] },
    ]);

    survives('a nested list', [
        { _type: 'block', _key: 'i1', style: 'normal', listItem: 'bullet', level: 1, children: [span('Top')] },
        { _type: 'block', _key: 'i2', style: 'normal', listItem: 'bullet', level: 2, children: [span('Nested')] },
        { _type: 'block', _key: 'i3', style: 'normal', listItem: 'bullet', level: 1, children: [span('Back out')] },
    ]);

    survives('an image', [
        { _type: 'image', _key: 'm1', url: '/storage/1/a.jpg', alt: 'A dog', ratio: '16/9', caption: 'A caption', width: 1200, height: 800 },
    ]);

    survives('a video', [
        { _type: 'video', _key: 'v1', url: 'https://example.com/v.mp4', caption: null, poster: null, width: 1920, height: 1080 },
    ]);

    survives('a code block', [
        { _type: 'code', _key: 'c1', code: "echo 'hi';", language: 'php', filename: 'a.php', lineNumbers: true },
    ]);

    survives('a divider', [{ _type: 'divider', _key: 'd1' }]);

    survives('a callout with nested blocks', [
        {
            _type: 'callout',
            _key: 'co1',
            variant: 'warning',
            children: [{ _type: 'block', _key: 'cb1', style: 'normal', children: [span('Careful.')] }],
        },
    ]);

    survives('a whole document of mixed content', [
        { _type: 'block', _key: 'b1', style: 'h2', children: [span('Heading')] },
        { _type: 'block', _key: 'b2', style: 'normal', children: [span('Intro '), span('bold', ['strong'])] },
        { _type: 'block', _key: 'i1', style: 'normal', listItem: 'bullet', level: 1, children: [span('Point')] },
        { _type: 'divider', _key: 'd1' },
        { _type: 'code', _key: 'c1', code: 'true', language: 'js', filename: null, lineNumbers: null },
        { _type: 'block', _key: 'b3', style: 'blockquote', children: [span('Closing.')] },
    ]);

    it('drops a mention left in stored content', () => {
        // Picking an entry inserts an ordinary link now, and the schema has no
        // mention node to convert one into. Dropping it beats emitting a node
        // ProseMirror would reject, which would take the whole document with it.
        const blocks = [{
            _type: 'block',
            _key: 'b1',
            style: 'normal',
            children: [
                span('As covered in '),
                { _type: 'mention', _key: 'm1', kind: 'article', id: 42 },
                span(' last week.'),
            ],
        }];

        const children = roundTrip(blocks)[0].children;

        assert.equal(children.every((child) => child._type === 'span'), true);
    });

    it('preserves block keys, so a save is not a whole-document rewrite', () => {
        const blocks = [{ _type: 'block', _key: 'stable-key', style: 'normal', children: [span('Text')] }];

        assert.equal(roundTrip(blocks)[0]._key, 'stable-key');
    });

    it('preserves a markDef key, so links keep pointing at the same span', () => {
        const blocks = [{
            _type: 'block',
            _key: 'b1',
            style: 'normal',
            markDefs: [{ _key: 'link-key', _type: 'link', href: 'https://example.com' }],
            children: [span('a link', ['link-key'])],
        }];

        assert.equal(roundTrip(blocks)[0].markDefs[0]._key, 'link-key');
    });

    it('is idempotent, so repeated saves never drift', () => {
        const blocks = [
            { _type: 'block', _key: 'b1', style: 'normal', children: [span('Text', ['em', 'strong'])] },
            { _type: 'image', _key: 'm1', url: '/a.jpg', caption: null, width: null, height: null },
        ];

        const once = stripSpanKeys(roundTrip(blocks));
        const twice = stripSpanKeys(roundTrip(roundTrip(blocks)));

        assert.deepEqual(twice, once);
    });

    it('normalises decorator order rather than preserving the input order', () => {
        // ['em','strong'] and ['strong','em'] mean the same thing; picking one
        // ordering is what makes the idempotence above hold.
        const blocks = [{ _type: 'block', _key: 'b1', style: 'normal', children: [span('Text', ['em', 'strong'])] }];

        assert.deepEqual(roundTrip(blocks)[0].children[0].marks, ['strong', 'em']);
    });

    it('drops an unknown node rather than guessing at it', () => {
        const blocks = [
            { _type: 'block', _key: 'b1', style: 'normal', children: [span('Kept')] },
            { _type: 'somethingNew', _key: 'x1' },
        ];

        assert.equal(roundTrip(blocks).length, 1);
    });
});

describe('empty documents', () => {
    it('gives an empty document a paragraph to type in', () => {
        // Without one there is no caret position and no node for the
        // placeholder to hang off, so a new entry opens blank and unlabelled.
        assert.deepEqual(toProseMirror([]), { type: 'doc', content: [{ type: 'paragraph' }] });
    });

    it('stores nothing for an untouched editor', () => {
        resetKeyCounter();

        assert.deepEqual(fromProseMirror(toProseMirror([])), []);
    });

    it('keeps a document that has real content alongside an empty block', () => {
        resetKeyCounter();

        const blocks = fromProseMirror({
            type: 'doc',
            content: [
                { type: 'paragraph', attrs: { _key: 'b1' }, content: [{ type: 'text', text: 'Real.' }] },
                { type: 'paragraph', attrs: { _key: 'b2' } },
            ],
        });

        assert.equal(blocks.length, 1);
        assert.equal(blocks[0]._key, 'b1');
    });
});

describe('dynamic tags', () => {
    // A stored tag becomes its own atom node, not literal token text: see
    // dynamic-tag-node.test.js for the node's own round-trip coverage.
    it('converts a dynamicTag child into a dynamicTag node, rather than dropping it', () => {
        const blocks = [{
            _type: 'block',
            _key: 'b1',
            style: 'normal',
            children: [
                span('Logged '),
                { _type: 'dynamicTag', _key: 't1', tag: 'entries.count', options: { type: 'note', period: '2026' } },
                span('.'),
            ],
        }];

        const content = toProseMirror(blocks).content[0].content;

        assert.equal(content[1].type, 'dynamicTag');
        assert.deepEqual(content[1].attrs, { tag: 'entries.count', options: { type: 'note', period: '2026' }, _key: 't1' });
    });

    it('keeps a tag with no options as an empty options object', () => {
        const blocks = [{
            _type: 'block',
            _key: 'b1',
            style: 'normal',
            children: [{ _type: 'dynamicTag', _key: 't1', tag: 'streak.current', options: {} }],
        }];

        assert.deepEqual(toProseMirror(blocks).content[0].content[0].attrs, { tag: 'streak.current', options: {}, _key: 't1' });
    });
});
