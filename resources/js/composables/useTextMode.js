import { watch } from 'vue';
import { defineSetting } from '../useSettings';
import { deleteCookie, readCookie } from '../lib/cookies';
import { createTextMode } from '../lib/textMode';
import { WORD_TRANSFORMS } from '../lib/wordTransforms';

/** The modes in the settings dropdown, in order. */
export const TEXT_MODES = [
    { value: 'off', label: 'Off' },
    { value: 'numeronym', label: 'Numeronym' },
    { value: 'pirate', label: 'Pirate' },
    { value: 'reversed', label: 'Reversed' },
    { value: 'emoji', label: 'Emoji' },
    { value: 'pig-latin', label: 'Pig Latin' },
];

const textModeDef = defineSetting('textMode', 'off', TEXT_MODES.map((mode) => mode.value));

/** Numeronym mode used to be its own on/off switch; carry that choice over once. */
function migrateNumeronymCookie() {
    const legacy = readCookie('pref_numeronym');

    if (legacy === null) {
        return;
    }

    if (legacy === 'on' && readCookie('pref_textMode') === null) {
        textModeDef.set('numeronym');
    }

    deleteCookie('pref_numeronym');
}

/**
 * Keep the page in step with the setting. Client only, called once after mount.
 * @param {HTMLElement} root
 * @returns {void}
 */
export function watchTextMode(root) {
    migrateNumeronymCookie();

    let active = null;

    watch(textModeDef.value, (value) => {
        active?.stop();
        active = WORD_TRANSFORMS[value] ? createTextMode(root, WORD_TRANSFORMS[value]) : null;
        active?.start();
    }, { immediate: true });
}

/**
 * The text mode setting, for the settings panel.
 * @returns {{textMode: import('vue').Ref<string>, setTextMode: (value: string) => void}}
 */
export function useTextMode() {
    return { textMode: textModeDef.value, setTextMode: textModeDef.set };
}
