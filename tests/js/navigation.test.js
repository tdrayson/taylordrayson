import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { archiveCommands, pageCommands } from '../../resources/js/navigation.js';

describe('palette destinations', () => {
    it('lists only timeline archives', () => {
        const labels = archiveCommands.map((command) => command.label);

        assert.ok(labels.includes('Activity'));
        // Link-preview types share the entry-type registry but are not archives.
        for (const label of ['Now', 'Story', 'Archive', 'Tag']) {
            assert.ok(! labels.includes(label), `${label} is not an archive`);
        }
    });

    it('reaches every destination by a distinct href', () => {
        const hrefs = [...pageCommands, ...archiveCommands].map((command) => command.href);

        assert.equal(new Set(hrefs).size, hrefs.length);
    });
});
