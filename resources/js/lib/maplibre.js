export const OPENFREEMAP_LIGHT = 'https://tiles.openfreemap.org/styles/positron';
export const OPENFREEMAP_DARK = 'https://tiles.openfreemap.org/styles/dark';

// Pick the basemap style that matches the active colour scheme.
export function mapStyleForTheme(resolved) {
    return resolved === 'dark' ? OPENFREEMAP_DARK : OPENFREEMAP_LIGHT;
}

/**
 * Swap the basemap style while preserving the given custom source + layer ids.
 * A plain `setStyle` drops every custom source/layer, and its post-swap event
 * (`style.load`) does not reliably fire when maplibre diffs the two styles, so
 * a re-add-on-style.load approach leaves routes/arcs missing until reload.
 * `transformStyle` instead carries our layers into the new style atomically, so
 * they stay drawn across a theme toggle with no re-add timing dance.
 */
export function swapBasemapStyle(map, styleUrl, ids) {
    map.setStyle(styleUrl, {
        transformStyle: (previous, next) => {
            if (!previous) {
                return next;
            }

            const sources = { ...next.sources };
            for (const id of ids) {
                if (previous.sources[id]) {
                    sources[id] = previous.sources[id];
                }
            }

            const carried = previous.layers.filter((layer) => ids.includes(layer.id));

            return { ...next, sources, layers: [...next.layers, ...carried] };
        },
    });
}

/**
 * Lazily load MapLibre GL and its stylesheet, resolving with the module (or
 * null if the chunk failed to load).
 *
 * Imported dynamically rather than at the top of the file so the ~1MB library
 * is a chunk of its own, fetched when a map is actually created instead of by
 * every visitor who loads a page that happens to import this module for
 * `greatCircle` or `mapStyleForTheme`.
 *
 * The version comes from package.json. It used to be a hardcoded CDN URL,
 * which had drifted to loading v4 from unpkg while the bundled dependency was
 * v5, so two different majors were in play depending on which map you opened.
 */
export async function loadMaplibre() {
    try {
        const [module] = await Promise.all([
            import('maplibre-gl'),
            import('maplibre-gl/dist/maplibre-gl.css'),
        ]);

        return module.default ?? module;
    } catch (error) {
        console.error('[maplibre] failed to load', error);

        return null;
    }
}

/** Resolve a CSS custom property reference like `var(--color-flight)` to its value. */
export function resolveColor(value, fallback = '#3858e9') {
    const match = /^var\((--[\w-]+)\)$/.exec(value);

    if (!match) {
        return value;
    }

    return getComputedStyle(document.documentElement).getPropertyValue(match[1]).trim() || fallback;
}

/**
 * Interpolate points along the great-circle (shortest sphere path) between two
 * coordinates, unwrapping longitude so the line stays continuous across the
 * antimeridian rather than wrapping flat across the map.
 */
export function greatCircle(a, b, segments = 128) {
    const toRad = (degrees) => (degrees * Math.PI) / 180;
    const toDeg = (radians) => (radians * 180) / Math.PI;

    const lat1 = toRad(a.lat);
    const lng1 = toRad(a.lng);
    const lat2 = toRad(b.lat);
    const lng2 = toRad(b.lng);

    const delta = 2 * Math.asin(Math.sqrt(
        Math.sin((lat2 - lat1) / 2) ** 2
        + Math.cos(lat1) * Math.cos(lat2) * Math.sin((lng2 - lng1) / 2) ** 2,
    ));

    if (delta === 0) {
        return [[a.lng, a.lat], [b.lng, b.lat]];
    }

    const points = [];
    let previousLng = null;

    for (let index = 0; index <= segments; index += 1) {
        const fraction = index / segments;
        const scaleA = Math.sin((1 - fraction) * delta) / Math.sin(delta);
        const scaleB = Math.sin(fraction * delta) / Math.sin(delta);

        const x = scaleA * Math.cos(lat1) * Math.cos(lng1) + scaleB * Math.cos(lat2) * Math.cos(lng2);
        const y = scaleA * Math.cos(lat1) * Math.sin(lng1) + scaleB * Math.cos(lat2) * Math.sin(lng2);
        const z = scaleA * Math.sin(lat1) + scaleB * Math.sin(lat2);

        const latitude = toDeg(Math.atan2(z, Math.sqrt(x * x + y * y)));
        let longitude = toDeg(Math.atan2(y, x));

        if (previousLng !== null) {
            while (longitude - previousLng > 180) {
                longitude -= 360;
            }
            while (longitude - previousLng < -180) {
                longitude += 360;
            }
        }

        previousLng = longitude;
        points.push([longitude, latitude]);
    }

    return points;
}

/** Build a small pill label marker (e.g. an airport code or a venue name) that floats above its pin. */
export function placeLabel(maplibregl, point, text) {
    const element = document.createElement('div');
    element.textContent = text;
    element.className = 'pointer-events-none max-w-48 truncate rounded-md border border-neutral-100 bg-neutral-0 px-1.5 py-0.5 text-label font-bold text-neutral-700 shadow-card';

    return new maplibregl.Marker({ element, anchor: 'bottom', offset: [0, -9] }).setLngLat([point.lng, point.lat]);
}

/** Build an IATA-code label marker element styled like a small pill. */
export function iataLabel(maplibregl, point) {
    return placeLabel(maplibregl, point, point.iata);
}
