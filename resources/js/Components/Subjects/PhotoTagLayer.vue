<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';

/**
 * Positioned subject labels over a photograph. Wraps the slotted `<img>` in
 * an element that shrink-wraps to its rendered box (see Lightbox.vue for
 * why), so a tag's stored x/y percentage lines up with the picture directly,
 * no measurement needed.
 *
 * A tag is a point, not a box. The name sits clear of it in a caret-tipped
 * bubble, revealed on hovering or focusing anywhere on the photograph (or
 * focusing any one tag), and scales with the rendered picture so a thumbnail
 * and a full-screen view read the same.
 *
 * The bubble flips to whichever side has room: below a point in the top half,
 * above one in the bottom half, and pulled in at the left and right edges, so
 * it never covers the face it is naming or gets clipped by the frame.
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

// Which way the bubble opens, from where the point sits in the frame. A point
// in the top half is labelled below it, and vice versa; a point near either
// edge has the bubble anchored to that edge rather than centred on it.
function calloutClass(tag) {
    const vertical = (tag.y ?? 50) < 50 ? 'is-below' : 'is-above';
    const horizontal = (tag.x ?? 50) < 15 ? 'is-start' : (tag.x ?? 50) > 85 ? 'is-end' : 'is-centre';

    return `${vertical} ${horizontal}`;
}

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
            class="tag-anchor absolute focus:outline-none"
            :class="static ? 'pointer-events-none' : ''"
            :style="{ left: `${tag.x}%`, top: `${tag.y}%` }"
            @mouseenter="static || emit('hover', tag.subjectId)"
            @mouseleave="static || emit('unhover')"
            @focus="static || emit('hover', tag.subjectId)"
            @blur="static || emit('unhover')"
        >
            <span
                class="tag-callout opacity-0 transition-opacity duration-150 group-hover:opacity-100 group-focus-within:opacity-100"
                :class="[calloutClass(tag), { 'opacity-100': hoveredId === tag.subjectId }]"
            >
                <span class="tag-caret" />
                <span class="tag-label rounded-md bg-black/80 text-white">{{ tag.name }}</span>
            </span>
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

/* Zero-size, so the anchor IS the point: the bubble hangs off it and the
   caret tip is what marks the spot. */
.tag-anchor {
    width: 0;
    height: 0;
}

.tag-callout {
    position: absolute;
    display: flex;
    align-items: center;
    font-size: clamp(0.65rem, 2.4cqw, 1rem);
}

.tag-callout.is-below {
    top: 0.35em;
    flex-direction: column;
}

.tag-callout.is-above {
    bottom: 0.35em;
    flex-direction: column-reverse;
}

/* Anchored to the edge it is near, so a tag on the far left or right keeps
   its whole name inside the frame. */
.tag-callout.is-centre {
    left: 50%;
    transform: translateX(-50%);
}

.tag-callout.is-start {
    left: 0;
    align-items: flex-start;
}

.tag-callout.is-end {
    right: 0;
    align-items: flex-end;
}

.tag-caret {
    width: 0;
    height: 0;
    border-left: 0.35em solid transparent;
    border-right: 0.35em solid transparent;
}

/* The caret points back at the point, so it sits on whichever face is nearer. */
.is-below .tag-caret {
    border-bottom: 0.35em solid rgb(0 0 0 / 0.8);
}

.is-above .tag-caret {
    border-top: 0.35em solid rgb(0 0 0 / 0.8);
}

.is-start .tag-caret {
    margin-left: 0.5em;
}

.is-end .tag-caret {
    margin-right: 0.5em;
}

.tag-label {
    display: block;
    padding: 0.25em 0.6em;
    line-height: 1.35;
    white-space: nowrap;
}
</style>
