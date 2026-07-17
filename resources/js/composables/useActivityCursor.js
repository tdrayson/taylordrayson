import { ref } from 'vue';

// One shared cursor across the profile charts and the route map, held as a
// FRACTION along the activity (0..1) rather than a raw index, so series of
// different lengths (e.g. an Apple-Health heart-rate trace alongside a
// 240-point Strava track) all map to the same position. null means "not
// hovering" — the crosshair line, dot, and map dot all hide.
export function useActivityCursor() {
    const fraction = ref(null);

    // Set the active position as a 0..1 fraction along the activity.
    function set(value) {
        fraction.value = value;
    }

    // Clear on pointer leave so the line and dot disappear together.
    function clear() {
        fraction.value = null;
    }

    return { fraction, set, clear };
}
