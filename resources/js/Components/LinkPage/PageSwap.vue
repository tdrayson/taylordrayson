<template>
    <!-- The old page fades out, then the new one rises in a beat behind the banner. -->
    <Transition
        mode="out-in"
        leave-active-class="transition-opacity duration-150"
        leave-to-class="opacity-0"
        enter-active-class="transition delay-75 duration-500 ease-out-expo"
        enter-from-class="translate-y-6 opacity-0 motion-reduce:translate-y-0"
    >
        <slot />
    </Transition>
</template>
