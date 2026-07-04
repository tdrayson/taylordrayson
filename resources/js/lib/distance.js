const METRES_PER_MILE = 1609.344;

// Metres to kilometres for display, one decimal place.
export function metresToKm(metres) {
    if (metres === null || metres === undefined) return null;
    return Math.round(metres / 100) / 10;
}

// Metres to whole miles for display.
export function metresToMiles(metres) {
    if (metres === null || metres === undefined) return null;
    return Math.round(metres / METRES_PER_MILE);
}
