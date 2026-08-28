<script setup>
import { ref, useId, watch } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    title: { type: String, required: true },
    open: { type: Boolean, default: false },
    // Off where accordions are stacked as bare toggles and a rule per row
    // would read as a stack of dividers rather than a list of choices.
    bordered: { type: Boolean, default: true },
});

const expanded = ref(props.open);

// Followed rather than read once, so a caller can open the panel in response
// to something else on the page (Reply on a comment opens the comment form).
watch(() => props.open, (open) => {
    expanded.value = open;
});
const headerId = useId();
const contentId = useId();

function toggle() {
    expanded.value = !expanded.value;
}
</script>

<template>
    <div :class="bordered && 'border-t border-neutral-50'">
        <h2 class="m-0">
            <button
                :id="headerId"
                type="button"
                class="flex w-full items-center justify-between gap-4 py-4 text-left font-display text-section"
                :aria-expanded="expanded"
                :aria-controls="contentId"
                @click="toggle"
            >
                {{ title }}
                <Icon
                    name="ArrowDown01Icon"
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
            <!-- `expanded` is exposed so a caller can v-if a lazily imported
                 component: `hidden` keeps the slot mounted, which would fetch
                 the chunk on page load and undo the point of deferring it. -->
            <slot :expanded="expanded" />
        </section>
    </div>
</template>
