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

// Miles to metres, for converting a visitor-typed value into integer-metres
// storage (e.g. advanced search's activity/flight distance fields).
export function milesToMetres(miles) {
    return miles == null ? null : miles * METRES_PER_MILE;
}

// Kilometres to metres, the km counterpart of milesToMetres.
export function kmToMetres(km) {
    return km == null ? null : km * 1000;
}

// Kilometres to miles, the inverse of milesToKm, for call sites that hold a
// km value and need to convert to a mile-stored column (e.g. fuel odometer).
export function kmToMiles(km, precision = 2) {
    if (km == null) return null;
    const factor = 10 ** precision;
    return Math.round((km / 1.609344) * factor) / factor;
}
