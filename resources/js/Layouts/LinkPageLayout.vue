<script setup>
import PageSwap from '../Components/LinkPage/PageSwap.vue';
</script>

<template>
    <div class="min-h-dvh bg-neutral-0">
        <main>
            <PageSwap>
                <slot />
            </PageSwap>
        </main>
    </div>
</template>
