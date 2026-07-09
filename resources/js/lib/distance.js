const METRES_PER_MILE = 1609.344;

// Metres to miles for display. Whole miles by default; pass a precision
// for short distances where tenths matter (e.g. a 5k run is 3.1 mi).
export function metresToMiles(metres, precision = 0) {
    if (metres === null || metres === undefined) return null;
    const factor = 10 ** precision;
    return Math.round((metres / METRES_PER_MILE) * factor) / factor;
}
