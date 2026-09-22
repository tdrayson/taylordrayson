import { watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { defineSetting } from '../useSettings';
import { formatDate, useDisplayFormats } from '../lib/dateFormat.js';
import { DEFAULT_TIMEZONE } from '../lib/time.js';

const timeFormatDef = defineSetting('timeFormat', '12h', ['12h', '24h']);
const dateFormatDef = defineSetting('dateFormat', 'short', ['short', 'long', 'dmy', 'mdy', 'iso']);

useDisplayFormats({ time: () => timeFormatDef.value.value, date: () => dateFormatDef.value.value });

// The server writes many of these strings, so a change, reset included, refetches the page.
if (typeof window !== 'undefined') {
    watch([timeFormatDef.value, dateFormatDef.value], () => router.reload());
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
        setTimeFormat: timeFormatDef.set,
        dateFormat: dateFormatDef.value,
        setDateFormat: dateFormatDef.set,
        dateOptions,
    };
}
