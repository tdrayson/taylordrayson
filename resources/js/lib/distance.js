const METRES_PER_MILE = 1609.344;

// Metres to miles for display. Whole miles by default; pass a precision
// for short distances where tenths matter (e.g. a 5k run is 3.1 mi).
export function metresToMiles(metres, precision = 0) {
    if (metres === null || metres === undefined) return null;
    const factor = 10 ** precision;
    return Math.round((metres / METRES_PER_MILE) * factor) / factor;
}

// Metres to kilometres for display. Whole km by default; pass a precision for
// short distances where tenths matter. Mirrors metresToMiles so the settings
// layer can swap units without changing call sites.
export function metresToKm(metres, precision = 0) {
    if (metres === null || metres === undefined) return null;
    const factor = 10 ** precision;
    return Math.round((metres / 1000) * factor) / factor;
}

// Miles to kilometres, for call sites that already hold a mile value (e.g. a
// flight's route.distance) rather than raw metres.
export function milesToKm(miles, precision = 0) {
    if (miles === null || miles === undefined) return null;
    const factor = 10 ** precision;
    return Math.round((miles * 1.609344) * factor) / factor;
}
