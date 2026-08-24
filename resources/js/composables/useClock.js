import { ref, onMounted, onUnmounted, toValue, watch } from 'vue';
import { DEFAULT_TIMEZONE, formatDate, formatTime } from '../lib/time';

/**
 * Reactive wall-clock time and date in a named timezone, refreshed on an
 * interval.
 *
 * @param timezone An IANA zone, or a ref/getter of one, since the ambient
 *                 reading it comes from can change while the page is open.
 * @param intervalMs How often to re-read the clock.
 */
export function useClock(timezone = DEFAULT_TIMEZONE, intervalMs = 15000) {
    const time = ref('');
    const date = ref('');
    let timer = null;

    function tick() {
        const zone = toValue(timezone) || DEFAULT_TIMEZONE;
        const now = new Date();

        time.value = formatTime(zone, now);
        date.value = formatDate(zone, now);
    }

    // Re-read straight away rather than waiting out the interval, so a zone
    // arriving late does not leave the wrong time on screen.
    watch(() => toValue(timezone), tick);

    onMounted(() => {
        tick();
        timer = setInterval(tick, intervalMs);
    });

    onUnmounted(() => {
        if (timer) {
            clearInterval(timer);
        }
    });

    return { time, date };
}
