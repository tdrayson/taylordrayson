<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';

/**
 * Positioned subject labels over a photograph. Wraps the slotted `<img>` in
 * an element that shrink-wraps to its rendered box (see Lightbox.vue for
 * why), so a tag's stored x/y percentage lines up with the picture directly,
 * no measurement needed.
 *
 * A tag is a point, not a box: a small dot marks it, with the name in a
 * caret-tipped bubble just below, revealed together on hovering or focusing
 * anywhere on the photograph (or focusing any one tag).
 */
const props = defineProps({
    photo: { type: Object, required: true }, // { id, tags: [{subjectId, name, url, role, x, y}] }
    // Crosshair mode: the photo becomes clickable/focusable to drop a new
    // point. A click supplies it; Enter/Space (keyboard, no pointer) defaults
    // to centre.
    placing: { type: Boolean, default: false },
    hoveredId: { type: [Number, String, null], default: null },
    // {x, y} | null: a point already chosen while placing, previewed until saved.
    pendingPosition: { type: Object, default: null },
});

const emit = defineEmits(['place', 'hover', 'unhover']);

const wrapperEl = ref(null);

// Camera credits carry no point, so only subject tags render on the image.
const positionTags = computed(() => props.photo.tags.filter((tag) => tag.role === 'subject'));

// The intrinsic image ratio, read once when it loads. Without it a wrapper
// sized purely by `max-height`/`max-width` percentages can't tell whether the
// picture is width- or height-bound and ends up too tall for a portrait
// photo; this gives it the same aspect ratio so both axes cap correctly.
const aspectRatio = ref(null);

function setAspectRatio(img) {
    if (img.naturalWidth && img.naturalHeight) {
        aspectRatio.value = `${img.naturalWidth} / ${img.naturalHeight}`;
    }
}

// The slotted <img> isn't a component we can bind a listener to directly, so
// it's read straight off the DOM: already loaded (cache) reads immediately,
// otherwise one 'load' listener picks it up.
function watchSlottedImage() {
    const img = wrapperEl.value?.querySelector('img');

    if (!img) {
        return;
    }

    if (img.complete && img.naturalWidth) {
        setAspectRatio(img);
    } else {
        img.addEventListener('load', () => setAspectRatio(img), { once: true });
    }
}

onMounted(watchSlottedImage);

watch(() => props.photo.id, () => {
    aspectRatio.value = null;
    nextTick(watchSlottedImage);
});

const wrapperStyle = computed(() => (aspectRatio.value ? { aspectRatio: aspectRatio.value } : {}));

function percentFromEvent(event) {
    const target = event.target;
    const x = (event.offsetX / target.clientWidth) * 100;
    const y = (event.offsetY / target.clientHeight) * 100;

    return {
        x: Math.min(100, Math.max(0, x)),
        y: Math.min(100, Math.max(0, y)),
    };
}

function onClick(event) {
    if (!props.placing || event.target.closest('a')) {
        return;
    }

    emit('place', percentFromEvent(event));
}

function onKeyPlace(event) {
    if (!props.placing) {
        return;
    }

    event.preventDefault();
    emit('place', { x: 50, y: 50 });
}

// Entering crosshair mode moves focus onto the photo itself, so a keyboard
// user lands somewhere they can press Enter without first hunting for it.
watch(() => props.placing, (placing) => {
    if (placing) {
        nextTick(() => wrapperEl.value?.focus());
    }
});
</script>

<template>
    <div
        ref="wrapperEl"
        class="group relative inline-flex overflow-hidden rounded-lg"
        :class="placing ? 'cursor-crosshair' : ''"
        :style="wrapperStyle"
        :tabindex="placing ? 0 : undefined"
        :role="placing ? 'button' : undefined"
        :aria-label="placing ? 'Tag someone at this point' : undefined"
        @click="onClick"
        @keydown.enter="onKeyPlace"
        @keydown.space="onKeyPlace"
    >
        <slot />

        <span
            v-if="pendingPosition"
            class="pointer-events-none absolute size-3 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow"
            :style="{ left: `${pendingPosition.x}%`, top: `${pendingPosition.y}%` }"
        />

        <a
            v-for="tag in positionTags"
            :key="tag.subjectId"
            :href="tag.url"
            class="absolute -translate-x-1/2 -translate-y-1/2 rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-black/60"
            :style="{ left: `${tag.x}%`, top: `${tag.y}%` }"
            @mouseenter="emit('hover', tag.subjectId)"
            @mouseleave="emit('unhover')"
            @focus="emit('hover', tag.subjectId)"
            @blur="emit('unhover')"
        >
            <span class="block size-2.5 rounded-full bg-white shadow ring-2 ring-black/50" />
            <span
                class="absolute left-1/2 top-full flex -translate-x-1/2 flex-col items-center pt-1 opacity-0 transition-opacity duration-150 group-hover:opacity-100 group-focus-within:opacity-100"
                :class="{ 'opacity-100': hoveredId === tag.subjectId }"
            >
                <span class="size-0 border-x-4 border-b-4 border-x-transparent border-b-black/80" />
                <span class="-mt-px whitespace-nowrap rounded bg-black/80 px-2 py-0.5 text-caption text-white">{{ tag.name }}</span>
            </span>
        </a>
    </div>
</template>
