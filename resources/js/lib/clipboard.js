/**
 * Copy text to the clipboard. On a secure context this uses the async Clipboard
 * API; otherwise (e.g. plain http) it falls back to the legacy execCommand path
 * via a hidden textarea, restoring focus to whatever was focused before (the
 * copy button) so the fallback doesn't bounce focus to the top of the page.
 *
 * @param {string} text
 * @returns {Promise<void>}
 */
export async function copyText(text) {
    try {
        await navigator.clipboard.writeText(text);

        return;
    } catch {
        // Secure-context clipboard unavailable: legacy fallback. Selecting and
        // then removing a scratch textarea would drop focus to <body>, so we
        // capture the active element and restore it afterwards.
        const active = document.activeElement;
        const scratch = document.createElement('textarea');
        scratch.value = text;
        scratch.setAttribute('readonly', '');
        // Keep it out of view and out of layout so it can't flash or scroll.
        scratch.style.position = 'fixed';
        scratch.style.top = '-9999px';
        document.body.appendChild(scratch);
        scratch.select();
        document.execCommand('copy');
        scratch.remove();

        if (active instanceof HTMLElement) {
            active.focus();
        }
    }
}
