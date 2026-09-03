<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';

/**
 * Positioned subject labels over a photograph. Wraps the slotted `<img>` in
 * an element that shrink-wraps to its rendered box (see Lightbox.vue for
 * why), so a tag's stored x/y percentage lines up with the picture directly,
 * no measurement needed.
 *
 * A tag is a point, not a box, and the name itself marks it: the label sits
 * centred on the point and is revealed on hovering or focusing anywhere on
 * the photograph (or focusing any one tag). It scales with the rendered
 * picture, so a thumbnail and a full-screen view read the same.
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
    // Labels as plain spans rather than links. Required wherever the layer
    // sits inside a <button> (a grid tile, a card carousel), since an anchor
    // inside a button is invalid and the browser reparents it.
    static: { type: Boolean, default: false },
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
        class="tag-layer group relative inline-flex overflow-hidden"
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

        <component
            :is="static ? 'span' : 'a'"
            v-for="tag in positionTags"
            :key="tag.subjectId"
            :href="static ? undefined : tag.url"
            class="absolute -translate-x-1/2 -translate-y-1/2 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
            :class="static ? 'pointer-events-none' : ''"
            :style="{ left: `${tag.x}%`, top: `${tag.y}%` }"
            @mouseenter="static || emit('hover', tag.subjectId)"
            @mouseleave="static || emit('unhover')"
            @focus="static || emit('hover', tag.subjectId)"
            @blur="static || emit('unhover')"
        >
            <span
                class="tag-label whitespace-nowrap rounded-md bg-black/80 text-white opacity-0 transition-opacity duration-150 group-hover:opacity-100 group-focus-within:opacity-100"
                :class="{ 'opacity-100': hoveredId === tag.subjectId }"
            >{{ tag.name }}</span>
        </component>
    </div>
</template>

<style scoped>
/* The label is the marker, so it sizes against the picture rather than the
   page: the same tag reads correctly on a grid thumbnail and full screen. */
/* The radius belongs to whatever wraps the picture: a grid tile rounds its own
   box, the lightbox rounds the image itself. */
.tag-layer {
    container-type: inline-size;
    border-radius: inherit;
}

.tag-label {
    display: block;
    font-size: clamp(0.65rem, 2.4cqw, 1rem);
    padding: 0.25em 0.6em;
    line-height: 1.35;
}
</style>
