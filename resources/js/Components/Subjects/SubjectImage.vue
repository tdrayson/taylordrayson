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
});

const FALLBACK_ICONS = {
    Person: UserIcon,
    Pet: FootprintsIcon,
    Spot: Location01Icon,
    Thing: CubeIcon,
};

const fallbackIcon = computed(() => FALLBACK_ICONS[props.kind] ?? UserIcon);
</script>

<template>
    <div
        class="overflow-hidden rounded-lg border border-neutral-50 bg-neutral-25"
        :class="wide ? 'aspect-video' : 'aspect-square'"
    >
        <img
            v-if="cover"
            :src="cover.full"
            :srcset="cover.srcset || undefined"
            :alt="name"
            class="size-full object-cover"
        >
        <div v-else class="flex size-full items-center justify-center text-neutral-400">
            <Icon :icon="fallbackIcon" class="size-10" />
        </div>
    </div>
</template>
