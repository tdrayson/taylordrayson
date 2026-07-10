// Mapbox Static Images helper for generated route maps.
//
// INTERIM: this renders live from the Mapbox API (token from VITE_MAPBOX_TOKEN).
// The plan is to generate each route image once and host it on Cloudflare (R2),
// then point callers at the stored URL instead of building a Mapbox URL on the fly.
import { greatCircle } from "./maplibre.js";
import { decodePolyline, encodePolyline } from "./geo.js";

const MAPBOX_TOKEN = import.meta.env.VITE_MAPBOX_TOKEN;

// Mapbox base styles for the generated route maps; pass one as the `style`
// option so a caller can render a light or dark variant of the same route.
export const MAPBOX_LIGHT = "mapbox/light-v11";
export const MAPBOX_DARK = "mapbox/dark-v11";

const TILE = 512; // Web Mercator tile size, the basis for zoom -> pixel scale.

// Cap how far a route map zooms in, so short, tightly-clustered activities
// (e.g. padel) keep surrounding context instead of filling the frame.
const MAX_ROUTE_ZOOM = 17;

/**
 * A `path` overlay for an encoded polyline. The polyline charset is all >= '?'
 * (63), so the overlay's own delimiters (parens, commas) never collide;
 * encodeURIComponent is enough to escape it.
 */
function pathOverlay(polyline, width, color, opacity) {
    return `path-${width}+${color}-${opacity}(${encodeURIComponent(polyline)})`;
}

/**
 * Static map for an encoded polyline route (e.g. an activity GPS trace). The
 * zoom is computed from the route bounds and capped at MAX_ROUTE_ZOOM (rather
 * than left to Mapbox `auto`), so tiny activities keep surrounding context.
 *
 * @param {string|null|undefined} polyline Google-encoded polyline string.
 * @param {{ color?: string, padding?: number, width?: number, height?: number }} [options]
 * @returns {string|null} The image URL, or null when there is no polyline.
 */
export function staticRouteMap(
    polyline,
    { color = "2e9e6a", padding = 64, width = 1280, height = 720, style = MAPBOX_LIGHT } = {},
) {
    if (!polyline) {
        return null;
    }

    const world = decodePolyline(polyline).map(([lng, lat]) =>
        lngLatToWorld(lng, lat),
    );

    if (world.length === 0) {
        return null;
    }

    const xs = world.map((point) => point[0]);
    const ys = world.map((point) => point[1]);
    const minX = Math.min(...xs);
    const maxX = Math.max(...xs);
    const minY = Math.min(...ys);
    const maxY = Math.max(...ys);

    const spanX = Math.max(maxX - minX, 1e-9);
    const spanY = Math.max(maxY - minY, 1e-9);
    const usableW = Math.max(width - 2 * padding, 1);
    const usableH = Math.max(height - 2 * padding, 1);
    const zoom = Math.min(
        MAX_ROUTE_ZOOM,
        Math.max(
            0,
            Math.min(
                Math.log2(usableW / (spanX * TILE)),
                Math.log2(usableH / (spanY * TILE)),
            ),
        ),
    );

    const [centerLng, centerLat] = worldToLngLat(
        (minX + maxX) / 2,
        (minY + maxY) / 2,
    );

    return (
        `https://api.mapbox.com/styles/v1/${style}/static/${pathOverlay(polyline, 5, color, "0.85")}` +
        `/${centerLng.toFixed(5)},${centerLat.toFixed(5)},${zoom.toFixed(2)}/${width}x${height}@2x` +
        `?attribution=false&logo=false&access_token=${MAPBOX_TOKEN}`
    );
}

/** Project [lng, lat] to Web Mercator world coordinates in the unit square. */
function lngLatToWorld(lng, lat) {
    const sinLat = Math.sin((lat * Math.PI) / 180);

    return [
        (lng + 180) / 360,
        0.5 - Math.log((1 + sinLat) / (1 - sinLat)) / (4 * Math.PI),
    ];
}

/** Inverse of lngLatToWorld: world coordinates back to [lng, lat]. */
function worldToLngLat(x, y) {
    return [
        x * 360 - 180,
        (Math.atan(Math.sinh(Math.PI * (1 - 2 * y))) * 180) / Math.PI,
    ];
}

/**
 * A closed circle of a given world-unit radius around a world point, encoded as
 * a [lat, lng] polyline. Mercator is conformal, so a world circle renders as a
 * true on-screen circle.
 */
function worldCircle([cx, cy], radius, segments = 28) {
    const points = [];

    for (let index = 0; index <= segments; index += 1) {
        const theta = (2 * Math.PI * index) / segments;
        const [lng, lat] = worldToLngLat(
            cx + radius * Math.cos(theta),
            cy + radius * Math.sin(theta),
        );
        points.push([lat, lng]);
    }

    return encodePolyline(points);
}

/** A 2-point dash between two world points, encoded as a [lat, lng] polyline. */
function worldDash(a, b) {
    const start = worldToLngLat(a[0], a[1]);
    const end = worldToLngLat(b[0], b[1]);

    return encodePolyline([
        [start[1], start[0]],
        [end[1], end[0]],
    ]);
}

/**
 * Static map for a flight: a dashed great-circle arc with a direction arrowhead
 * and white airport markers. The zoom is computed here (rather than left to
 * Mapbox's `auto`) so dashes, markers, and the arrowhead can be sized in fixed
 * pixels and stay identical across every route, short hop or long haul.
 *
 * @param {{ lat: number|string, lng: number|string }} origin
 * @param {{ lat: number|string, lng: number|string }} destination
 * @param {{ color?: string, padding?: number, width?: number, height?: number }} [options]
 * @returns {string|null} The image URL, or null when either coordinate is missing/invalid.
 */
export function staticArcMap(
    origin,
    destination,
    { color = "209fdf", padding = 60, width = 1280, height = 720, style = MAPBOX_LIGHT } = {},
) {
    const from = { lat: Number(origin?.lat), lng: Number(origin?.lng) };
    const to = { lat: Number(destination?.lat), lng: Number(destination?.lng) };

    if ([from.lat, from.lng, to.lat, to.lng].some(Number.isNaN)) {
        return null;
    }

    const world = greatCircle(from, to, 128).map(([lng, lat]) =>
        lngLatToWorld(lng, lat),
    );
    const xs = world.map((point) => point[0]);
    const ys = world.map((point) => point[1]);
    const minX = Math.min(...xs);
    const maxX = Math.max(...xs);
    const minY = Math.min(...ys);
    const maxY = Math.max(...ys);

    // Fit the arc's bounding box into the padded frame, then derive the
    // world-units-per-pixel scale at that zoom.
    const spanX = Math.max(maxX - minX, 1e-9);
    const spanY = Math.max(maxY - minY, 1e-9);
    const usableW = Math.max(width - 2 * padding, 1);
    const usableH = Math.max(height - 2 * padding, 1);
    const zoom = Math.max(
        0,
        Math.min(
            Math.log2(usableW / (spanX * TILE)),
            Math.log2(usableH / (spanY * TILE)),
            16,
        ),
    );
    const scale = TILE * 2 ** zoom; // logical pixels per world unit
    const [centerLng, centerLat] = worldToLngLat(
        (minX + maxX) / 2,
        (minY + maxY) / 2,
    );

    // Cumulative pixel length along the arc, so dashes land at fixed intervals.
    const lengths = [0];
    for (let index = 1; index < world.length; index += 1) {
        const dx = (world[index][0] - world[index - 1][0]) * scale;
        const dy = (world[index][1] - world[index - 1][1]) * scale;
        lengths[index] = lengths[index - 1] + Math.hypot(dx, dy);
    }
    const total = lengths[lengths.length - 1];

    // World coordinate at a given pixel distance along the arc.
    const at = (distance) => {
        for (let index = 1; index < lengths.length; index += 1) {
            if (lengths[index] >= distance) {
                const segment = lengths[index] - lengths[index - 1] || 1;
                const fraction = (distance - lengths[index - 1]) / segment;

                return [
                    world[index - 1][0] +
                        (world[index][0] - world[index - 1][0]) * fraction,
                    world[index - 1][1] +
                        (world[index][1] - world[index - 1][1]) * fraction,
                ];
            }
        }

        return world[world.length - 1];
    };

    const DASH_PX = 13;
    const GAP_PX = 11;
    const overlays = [];

    for (let distance = 0; distance < total; distance += DASH_PX + GAP_PX) {
        const dash = worldDash(
            at(distance),
            at(Math.min(distance + DASH_PX, total)),
        );
        overlays.push(`path-5+${color}-1(${encodeURIComponent(dash)})`);
    }

    // Direction-of-travel arrowhead at the midpoint, sized in fixed pixels.
    const mid = at(total / 2);
    const ahead = at(Math.min(total / 2 + 1, total));
    let dirX = ahead[0] - mid[0];
    let dirY = ahead[1] - mid[1];
    const length = Math.hypot(dirX, dirY) || 1;
    dirX /= length;
    dirY /= length;
    const perpX = -dirY;
    const perpY = dirX;
    const tip = 16 / scale;
    const back = 5 / scale;
    const half = 10 / scale;
    const tipPoint = worldToLngLat(mid[0] + dirX * tip, mid[1] + dirY * tip);
    const leftPoint = worldToLngLat(
        mid[0] - dirX * back + perpX * half,
        mid[1] - dirY * back + perpY * half,
    );
    const rightPoint = worldToLngLat(
        mid[0] - dirX * back - perpX * half,
        mid[1] - dirY * back - perpY * half,
    );
    const arrow = encodePolyline([
        [tipPoint[1], tipPoint[0]],
        [leftPoint[1], leftPoint[0]],
        [rightPoint[1], rightPoint[0]],
        [tipPoint[1], tipPoint[0]],
    ]);
    overlays.push(`path-1+${color}-1+${color}-1(${encodeURIComponent(arrow)})`);

    // Airport markers (white fill, coloured ring) at a fixed pixel radius, on top.
    const markerRadius = 9 / scale;
    overlays.push(
        `path-6+${color}-1+ffffff-1(${encodeURIComponent(worldCircle(world[0], markerRadius))})`,
    );
    overlays.push(
        `path-6+${color}-1+ffffff-1(${encodeURIComponent(worldCircle(world[world.length - 1], markerRadius))})`,
    );

    return (
        `https://api.mapbox.com/styles/v1/${style}/static/${overlays.join(",")}` +
        `/${centerLng.toFixed(5)},${centerLat.toFixed(5)},${zoom.toFixed(2)}/${width}x${height}@2x` +
        `?attribution=false&logo=false&access_token=${MAPBOX_TOKEN}`
    );
}
