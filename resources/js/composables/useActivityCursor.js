import { ref } from 'vue';

// One shared cursor index across the profile charts and the route map.
// null means "not hovering" — the crosshair line, dot, and map dot all hide.
export function useActivityCursor() {
    const index = ref(null);

    // Set the active point index (callers clamp to their series length).
    function set(value) {
        index.value = value;
    }

    // Clear on pointer leave so line and dot disappear together.
    function clear() {
        index.value = null;
    }

    return { index, set, clear };
}
