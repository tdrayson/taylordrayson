<script setup>
import { ref, useId } from 'vue';
import { ArrowDown01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from './Icon.vue';

const props = defineProps({
    title: { type: String, required: true },
    open: { type: Boolean, default: false },
});

const expanded = ref(props.open);
const headerId = useId();
const contentId = useId();

function toggle() {
    expanded.value = !expanded.value;
}
</script>

<template>
    <div class="border-t border-neutral-50">
        <h2 class="m-0">
            <button
                :id="headerId"
                type="button"
                class="flex w-full cursor-pointer items-center justify-between gap-4 py-4 text-left font-display text-section"
                :aria-expanded="expanded"
                :aria-controls="contentId"
                @click="toggle"
            >
                {{ title }}
                <Icon
                    :icon="ArrowDown01Icon"
                    class="size-5 shrink-0 text-neutral-500 transition-transform"
                    :class="{ 'rotate-180': expanded }"
                    aria-hidden="true"
                />
            </button>
        </h2>
        <section
            :id="contentId"
            :aria-labelledby="headerId"
            :hidden="!expanded"
            class="mt-2 pb-6"
        >
            <slot />
        </section>
    </div>
</template>
