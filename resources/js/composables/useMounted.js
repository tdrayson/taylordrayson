import { ref, onMounted } from 'vue';

/**
 * False until the component has mounted in the browser.
 *
 * Gate `<Teleport>` on this. Vue's server renderer collects teleported content
 * into a `teleports` buffer that Inertia never injects, so the markup is lost
 * and hydration instead matches the teleport against whatever real children
 * `<body>` happens to have.
 */
export function useMounted() {
    const mounted = ref(false);

    onMounted(() => {
        mounted.value = true;
    });

    return mounted;
}
