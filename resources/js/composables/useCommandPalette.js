import { ref } from 'vue';

// Module-level singleton so the trigger (search bar) and the palette share state.
const isOpen = ref(false);

export function useCommandPalette() {
    return {
        isOpen,
        open: () => {
            isOpen.value = true;
        },
        close: () => {
            isOpen.value = false;
        },
        toggle: () => {
            isOpen.value = !isOpen.value;
        },
    };
}
