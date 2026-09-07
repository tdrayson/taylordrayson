<script setup>
import { ref, useId, watch } from 'vue';
import { cn } from '../../lib/cn.js';
import Icon from './Icon.vue';

const props = defineProps({
    title: { type: String, required: true },
    open: { type: Boolean, default: false },
    /**
     * 'section' is a named part of the page and carries a heading to match.
     * 'quiet' is a side door (send a link, cite this page) that sits under the
     * content without competing with it, so it is neither a heading nor
     * display-sized, and its marker leads the label instead of being flung to
     * the far edge of the container.
     */
    variant: { type: String, default: 'section' },
    // Off where accordions are stacked as bare toggles and a rule per row
    // would read as a stack of dividers rather than a list of choices.
    bordered: { type: Boolean, default: true },
});

const quiet = props.variant === 'quiet';
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
    <div :class="bordered && ! quiet && 'border-t border-neutral-50'">
        <component :is="quiet ? 'div' : 'h2'" class="m-0">
            <button
                :id="headerId"
                type="button"
                :class="cn(
                    'flex w-full items-center text-left transition-colors',
                    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500',
                    quiet
                        ? 'gap-1.5 rounded-sm py-2 text-body text-neutral-700 hover:text-accent-700'
                        : 'justify-between gap-4 py-4 font-display text-section',
                )"
                :aria-expanded="expanded"
                :aria-controls="contentId"
                @click="toggle"
            >
                <!-- Leading in the quiet variant so the marker stays against
                     the words: pushed to the end of a narrow column it reads as
                     a stray glyph rather than as the control's own arrow. -->
                <Icon
                    v-if="quiet"
                    name="ArrowRight01Icon"
                    class="size-4 shrink-0 transition-transform"
                    :class="{ 'rotate-90': expanded }"
                    aria-hidden="true"
                />
                {{ title }}
                <Icon
                    v-if="! quiet"
                    name="ArrowDown01Icon"
                    class="size-5 shrink-0 text-neutral-500 transition-transform"
                    :class="{ 'rotate-180': expanded }"
                    aria-hidden="true"
                />
            </button>
        </component>
        <!-- Animated by grid rows rather than height: 0fr to 1fr transitions
             without anyone measuring the content, which a height in pixels
             would need and would get wrong the moment the panel reflowed.
             `inert` does what `hidden` used to, keeping a closed panel out of
             the tab order and the accessibility tree, while leaving it in the
             layout so it has something to grow from. -->
        <div class="accordion-panel" :class="{ 'is-open': expanded }">
            <section
                :id="contentId"
                :aria-labelledby="headerId"
                :inert="! expanded || undefined"
            >
                <!-- `expanded` is exposed so a caller can v-if a lazily
                     imported component: the slot stays mounted otherwise,
                     which would fetch the chunk on page load and undo the
                     point of deferring it. -->
                <div :class="quiet ? 'pb-4 pl-5.5' : 'mt-2 pb-6'">
                    <slot :expanded="expanded" />
                </div>
            </section>
        </div>
    </div>
</template>

<style scoped>
.accordion-panel {
    display: grid;
    grid-template-rows: 0fr;
    transition: grid-template-rows 220ms cubic-bezier(0.16, 1, 0.3, 1);
}

.accordion-panel.is-open {
    grid-template-rows: 1fr;
}

/* The row is the thing being sized, so the content has to be clipped by it or
   it spills out of a collapsed panel at full height. */
.accordion-panel > section {
    overflow: hidden;
}

@media (prefers-reduced-motion: reduce) {
    .accordion-panel {
        transition: none;
    }
}
</style>
