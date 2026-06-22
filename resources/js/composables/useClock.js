import { ref, onMounted, onUnmounted } from 'vue';

/**
 * Reactive wall-clock time, formatted HH:MM (en-GB), refreshed on an interval.
 */
export function useClock(intervalMs = 15000) {
    const time = ref('');
    const date = ref('');
    let timer = null;

    function tick() {
        const current = new Date();

        const formatted = current.toLocaleTimeString('en-GB', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        });

        time.value = formatted.replace(/\s+/g, '').toLowerCase();
        date.value = current.toLocaleDateString('en-GB', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        });
    }

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
