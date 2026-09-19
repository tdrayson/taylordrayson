<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import Icon from './Icon.vue';
import { cn } from '../../lib/cn.js';
import { useDismissable } from '../../composables/useDismissable.js';
import { useListboxNavigation } from '../../composables/useListboxNavigation.js';

/**
 * A menu of links behind a trigger. The panel, its dismissal and its keyboard
 * handling only; the trigger is the caller's, because a split button and an
 * icon button share nothing but the popover.
 */
const props = defineProps({
    // [{ label, href, icon?, description?, external? }]
    items: { type: Array, required: true },
    // Which edge the panel is pinned to.
    align: { type: String, default: 'right' },
    // Accessible name for the panel itself.
    label: { type: String, default: 'Menu' },
    widthClass: { type: String, default: 'w-44' },
    // Where the panel sits relative to the trigger, e.g. 'bottom-full mb-3' for a trigger it must open above.
    panelClass: { type: String, default: 'mt-2' },
    // Merged over the default row classes via cn(), so a consumer can override colour, size, or spacing.
    itemClass: { type: String, default: '' },
    iconClass: { type: String, default: 'size-4' },
});

// cn() (tailwind-merge) resolves conflicting utilities so a consumer's override
// genuinely wins rather than depending on stylesheet order.
const panelClasses = computed(() =>
    cn(
        'absolute z-50 overflow-hidden rounded-md border border-neutral-100 bg-neutral-0 py-1 shadow-card',
        props.widthClass,
        props.align === 'right' ? 'right-0' : 'left-0',
        props.panelClass,
    ),
);

const itemClasses = computed(() =>
    cn(
        'flex items-center gap-3 px-4 py-2 text-sm text-neutral-700 transition-colors hover:bg-neutral-25 hover:text-neutral-900 focus-visible:bg-neutral-25 focus-visible:text-neutral-900 focus-visible:outline-none data-[active=true]:bg-neutral-25',
        props.itemClass,
    ),
);

const iconClasses = computed(() => cn('text-neutral-500', props.iconClass));

const { isOpen, root, close, toggle } = useDismissable();
const listEl = ref(null);

// The navigable items, only while open: an index into a hidden list means the
// first arrow key after opening lands on a stale row.
const navigable = computed(() => (isOpen.value ? props.items : []));

// Enter on the highlighted row follows it, the same as clicking it would.
const { activeIndex, onKeydown } = useListboxNavigation(navigable, {
    listEl,
    onSelect: (item) => {
        close();
        if (item.external) {
            window.open(item.href, '_blank', 'noopener');
        } else {
            router.visit(item.href);
        }
    },
});

// An external row opens a file rather than a page, so its name has to say so.
function accessibleName(item) {
    return item.external ? `${item.label}, opens in a new tab` : item.label;
}
</script>

<template>
    <div ref="root" class="relative">
        <slot name="trigger" :open="isOpen" :toggle="toggle" />

        <div
            v-if="isOpen"
            ref="listEl"
            role="menu"
            :aria-label="label"
            :class="panelClasses"
            @keydown="onKeydown"
        >
            <component
                :is="item.external ? 'a' : Link"
                v-for="(item, index) in items"
                :key="item.href"
                role="menuitem"
                :href="item.href"
                :target="item.external ? '_blank' : undefined"
                :rel="item.external ? 'noopener' : undefined"
                :aria-label="accessibleName(item)"
                :data-active="index === activeIndex"
                :class="itemClasses"
                @click="close"
            >
                <Icon v-if="item.icon" :icon="item.icon" :class="iconClasses" />
                <span class="min-w-0 flex-1">{{ item.label }}</span>
                <span v-if="item.description" class="shrink-0 text-xs text-neutral-500">{{ item.description }}</span>
            </component>
        </div>
    </div>
</template>
