<script setup>
import { computed } from 'vue';
import { UserIcon, FootprintsIcon, Location01Icon, CubeIcon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';

const props = defineProps({
    // {src, srcset, full} or null.
    cover: { type: Object, default: null },
    name: { type: String, required: true },
    // The subject's kind label ('Person', 'Pet', 'Spot', 'Thing'), for the
    // no-cover fallback icon.
    kind: { type: String, default: 'Person' },
    // A thing's illustration reads as a wide picture rather than a portrait.
    wide: { type: Boolean, default: false },
    // A subject page's establishing image: shown whole, at its own aspect
    // ratio. Only the small square uses (shelves, companions, chips) crop, so
    // a cover is composed to survive a centre crop, not to fit a band.
    hero: { type: Boolean, default: false },
});

const FALLBACK_ICONS = {
    Person: UserIcon,
    Pet: FootprintsIcon,
    Spot: Location01Icon,
    Thing: CubeIcon,
};

const fallbackIcon = computed(() => FALLBACK_ICONS[props.kind] ?? UserIcon);

// A hero sets no aspect-ratio on the box at all: the picture supplies it.
const shape = computed(() => {
    if (props.hero) {
        return '';
    }

    return props.wide ? 'aspect-video' : 'aspect-square';
});
</script>

<template>
    <div
        class="overflow-hidden rounded-lg border border-neutral-50 bg-neutral-25"
        :class="shape"
    >
        <img
            v-if="cover"
            :src="cover.full"
            :srcset="cover.srcset || undefined"
            :alt="name"
            :class="hero ? 'block h-auto w-full' : 'size-full object-cover'"
        >
        <div v-else class="flex size-full items-center justify-center text-neutral-400" :class="hero ? 'aspect-video' : ''">
            <Icon :icon="fallbackIcon" class="size-10" />
        </div>
    </div>
</template>
