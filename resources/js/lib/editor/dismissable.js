import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * One popup open at a time, closing on outside click and on Escape. Shared by
 * every field that opens something, which would otherwise stack panels with no
 * way to tell which one the keyboard is talking to.
 */
let openId = ref(null);
let nextId = 0;

export function useDismissable() {
    const id = ++nextId;
    const isOpen = ref(false);
    const root = ref(null);

    // Opening anything closes whatever was open before it.
    watch(openId, (current) => {
        if (current !== id) {
            isOpen.value = false;
        }
    });

    function open() {
        openId.value = id;
        isOpen.value = true;
    }

    function close() {
        isOpen.value = false;

        if (openId.value === id) {
            openId.value = null;
        }
    }

    function toggle() {
        isOpen.value ? close() : open();
    }

    function onPointerDown(event) {
        if (isOpen.value && root.value && ! root.value.contains(event.target)) {
            close();
        }
    }

    function onKeydown(event) {
        if (event.key === 'Escape' && isOpen.value) {
            // Claimed, so one Escape does not also cancel the form behind it.
            event.stopPropagation();
            close();
        }
    }

    onMounted(() => {
        document.addEventListener('pointerdown', onPointerDown, true);
        document.addEventListener('keydown', onKeydown, true);
    });

    onBeforeUnmount(() => {
        document.removeEventListener('pointerdown', onPointerDown, true);
        document.removeEventListener('keydown', onKeydown, true);
    });

    return { isOpen, root, open, close, toggle };
}
