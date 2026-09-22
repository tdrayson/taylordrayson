import { computed, watch } from 'vue';
import { defineSetting } from '../useSettings';
import { createNumeronymMode } from '../lib/numeronym';

const numeronymDef = defineSetting('numeronym', 'off', ['off', 'on']);

/**
 * Keep the page in step with the setting. Client only, called once after mount.
 * @param {HTMLElement} root
 * @returns {void}
 */
export function watchNumeronymMode(root) {
    const mode = createNumeronymMode(root);

    watch(numeronymDef.value, (value) => (value === 'on' ? mode.start() : mode.stop()), { immediate: true });
}

/**
 * The numeronym mode switch as a boolean, for the settings panel.
 * @returns {{numeronymMode: import('vue').WritableComputedRef<boolean>}}
 */
export function useNumeronym() {
    const numeronymMode = computed({
        get: () => numeronymDef.value.value === 'on',
        set: (on) => numeronymDef.set(on ? 'on' : 'off'),
    });

    return { numeronymMode };
}
