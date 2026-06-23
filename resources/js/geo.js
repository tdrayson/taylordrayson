/**
 * Decode a Google-encoded polyline into [lng, lat] pairs.
 */
export function decodePolyline(encoded) {
    if (!encoded) {
        return [];
    }

    const points = [];
    let index = 0;
    let lat = 0;
    let lng = 0;

    while (index < encoded.length) {
        let result = 1;
        let shift = 0;
        let byte;

        do {
            byte = encoded.charCodeAt(index++) - 63 - 1;
            result += byte << shift;
            shift += 5;
        } while (byte >= 0x1f);

        lat += result & 1 ? ~(result >> 1) : result >> 1;

        result = 1;
        shift = 0;

        do {
            byte = encoded.charCodeAt(index++) - 63 - 1;
            result += byte << shift;
            shift += 5;
        } while (byte >= 0x1f);

        lng += result & 1 ? ~(result >> 1) : result >> 1;

        points.push([lng * 1e-5, lat * 1e-5]);
    }

    return points;
}

/**
 * Project [lng, lat] points into SVG coordinates that fit a width×height box,
 * preserving aspect ratio (with a rough longitude correction) and centring the
 * shape. Returns [x, y] pairs with north pointing up.
 */
export function projectPoints(points, width, height, padding = 14) {
    if (!points.length || width <= 0 || height <= 0) {
        return [];
    }

    let minLat = Infinity;
    let maxLat = -Infinity;

    for (const [, lat] of points) {
        minLat = Math.min(minLat, lat);
        maxLat = Math.max(maxLat, lat);
    }

    const correction = Math.cos((((minLat + maxLat) / 2) * Math.PI) / 180) || 1;
    const planar = points.map(([lng, lat]) => [lng * correction, lat]);

    let minX = Infinity;
    let maxX = -Infinity;
    let minY = Infinity;
    let maxY = -Infinity;

    for (const [x, y] of planar) {
        minX = Math.min(minX, x);
        maxX = Math.max(maxX, x);
        minY = Math.min(minY, y);
        maxY = Math.max(maxY, y);
    }

    const spanX = maxX - minX || 1e-6;
    const spanY = maxY - minY || 1e-6;
    const innerW = Math.max(1, width - padding * 2);
    const innerH = Math.max(1, height - padding * 2);
    const scale = Math.min(innerW / spanX, innerH / spanY);
    const offsetX = padding + (innerW - spanX * scale) / 2;
    const offsetY = padding + (innerH - spanY * scale) / 2;

    return planar.map(([x, y]) => [
        offsetX + (x - minX) * scale,
        offsetY + (maxY - y) * scale,
    ]);
}
