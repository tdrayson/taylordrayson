import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { mediaIds, withMediaIds } from '../../resources/js/lib/editor/media.js';

describe('media field values', () => {
    it('reduces picker items to the ids the server stores', () => {
        // The upload endpoint answers with name and url so the picker can draw
        // a thumbnail; posting that object back fails validation as an object
        // where a string is expected, which is what stopped covers saving.
        assert.deepEqual(
            mediaIds([
                { id: 'pending:abc123', name: 'hero.jpg', url: '/media/pending/abc123' },
                { id: '9f1c-uuid', name: 'old.jpg', url: '/storage/1/old.jpg' },
            ]),
            ['pending:abc123', '9f1c-uuid'],
        );
    });

    it('passes ids through untouched', () => {
        assert.deepEqual(mediaIds(['pending:abc123', '9f1c-uuid']), ['pending:abc123', '9f1c-uuid']);
    });

    it('drops anything with no id, and copes with an unset field', () => {
        assert.deepEqual(mediaIds([{ name: 'no id' }, null, '', 'kept']), ['kept']);
        assert.deepEqual(mediaIds(undefined), []);
    });

    it('touches only the media fields', () => {
        const fields = [
            { name: 'title', type: 'title' },
            { name: 'cover', type: 'image' },
            { name: 'photos', type: 'gallery' },
        ];

        assert.deepEqual(
            withMediaIds(fields, {
                title: 'A page',
                cover: [{ id: 'pending:one', name: 'a.jpg', url: '/x' }],
                photos: [{ id: 'two', name: 'b.jpg', url: '/y' }],
                tags: ['keep', 'these'],
            }),
            {
                title: 'A page',
                cover: ['pending:one'],
                photos: ['two'],
                tags: ['keep', 'these'],
            },
        );
    });
});
