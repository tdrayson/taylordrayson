/**
 * Park a file with the server and get back the URL that stands in for it until
 * the entry is saved. Shared by the gallery field and the in-document image, so
 * both speak to the same endpoint the same way.
 */

import { csrf } from '../csrf.js';

/**
 * @param {File} file
 * @returns {Promise<{id: string, name: string, url: string}>}
 */
export async function uploadPending(file) {
    const body = new FormData();
    body.append('file', file);

    const response = await fetch('/media/pending', {
        method: 'POST',
        body,
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-XSRF-TOKEN': csrf() },
    });

    if (! response.ok) {
        throw new Error('upload failed');
    }

    return (await response.json()).data;
}
