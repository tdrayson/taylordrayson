import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { urlLabel } from '../../resources/js/lib/urlLabel.js';

/**
 * Only ever consulted for a pasted URL, where the alternative is the bare
 * domain. A handler is right or absent: returning null is a correct answer and
 * falls back to the host.
 */

describe('github', () => {
    it('reads the shapes that carry their own meaning', () => {
        const cases = {
            'https://github.com/tdrayson/taylordrayson/pull/191': 'PR #191',
            'https://github.com/tdrayson/taylordrayson/issues/99': 'issue #99',
            'https://github.com/tdrayson/taylordrayson/releases/tag/v2.1.0': 'v2.1.0',
            'https://github.com/tdrayson/taylordrayson/blob/master/app/Models/Note.php': 'Note.php',
            'https://github.com/tdrayson/taylordrayson': 'tdrayson/taylordrayson',
            'https://github.com/tdrayson': '@tdrayson',
        };

        for (const [url, expected] of Object.entries(cases)) {
            assert.equal(urlLabel(url), expected, url);
        }
    });

    it('abbreviates a commit the way github does', () => {
        assert.equal(urlLabel('https://github.com/tdrayson/taylordrayson/commit/2b4d6e5e1234567890'), '@2b4d6e5');
    });

    // The home repo is most of the links here, so repeating its name says
    // nothing; another repo has to name itself to be understood.
    it('keeps the repo name only when it is not the home one', () => {
        assert.equal(urlLabel('https://github.com/laravel/framework/pull/50000'), 'framework PR #50000');
        assert.equal(urlLabel('https://github.com/tdrayson/taylordrayson/pull/50000'), 'PR #50000');
    });

    it('falls back to owner/repo for a shape it does not know', () => {
        assert.equal(urlLabel('https://github.com/tdrayson/taylordrayson/actions/runs/123'), 'tdrayson/taylordrayson');
    });
});

describe('the other handlers', () => {
    it('reads a wikipedia article, in any language edition', () => {
        assert.equal(urlLabel('https://en.wikipedia.org/wiki/Portable_Text'), 'Portable Text');
        assert.equal(urlLabel('https://fr.wikipedia.org/wiki/Jeu_de_paume'), 'Jeu de paume');
    });

    it('decodes an escaped title', () => {
        assert.equal(urlLabel('https://en.wikipedia.org/wiki/Caf%C3%A9_wall_illusion'), 'Café wall illusion');
    });

    it('reads a packagist and an npm package', () => {
        assert.equal(urlLabel('https://packagist.org/packages/spatie/browsershot'), 'spatie/browsershot');
        assert.equal(urlLabel('https://www.npmjs.com/package/vue'), 'vue');
        assert.equal(urlLabel('https://www.npmjs.com/package/@vue/reactivity'), '@vue/reactivity');
    });
});

describe('everything else', () => {
    it('says nothing rather than guessing', () => {
        // Opaque ids in the path: there is nothing here to derive.
        assert.equal(urlLabel('https://youtube.com/watch?v=dQw4w9WgXcQ'), null);
        assert.equal(urlLabel('https://example.com/p/12345'), null);
        assert.equal(urlLabel('https://github.com'), null);
        assert.equal(urlLabel('not a url'), null);
    });
});
