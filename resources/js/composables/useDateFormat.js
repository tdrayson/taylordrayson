import { router } from '@inertiajs/vue3';
import { defineSetting } from '../useSettings';
import { formatDate, useDisplayFormats } from '../lib/dateFormat.js';
import { DEFAULT_TIMEZONE } from '../lib/time.js';

const timeFormatDef = defineSetting('timeFormat', '12h', ['12h', '24h']);
const dateFormatDef = defineSetting('dateFormat', 'short', ['short', 'long', 'dmy', 'mdy', 'iso']);

useDisplayFormats({ time: () => timeFormatDef.value.value, date: () => dateFormatDef.value.value });

/**
 * Save a format and refetch the page, since the server writes many of its strings.
 * @param {{set: (value: string) => void}} setting
 * @param {string} value
 * @returns {void}
 */
function choose(setting, value) {
    setting.set(value);
    router.reload();
}

/**
 * The time and date format controls for the settings panel.
 * @returns {object}
 */
export function useDateFormat() {
    // Read in the site's zone, so the server render and hydration agree on the day.
    const today = new Intl.DateTimeFormat('en-CA', { timeZone: DEFAULT_TIMEZONE }).format(new Date());

    const dateOptions = ['short', 'long', 'dmy', 'mdy', 'iso'].map((value) => ({ value, label: formatDate(today, {}, value) }));

    return {
        timeFormat: timeFormatDef.value,
        setTimeFormat: (value) => choose(timeFormatDef, value),
        dateFormat: dateFormatDef.value,
        setDateFormat: (value) => choose(dateFormatDef, value),
        dateOptions,
    };
}
