<script setup>
import { computed } from 'vue';
import { NodeViewContent, NodeViewWrapper } from '@tiptap/vue-3';
import { lowlight } from '../../lib/editor/lowlight';

/**
 * A code block while writing, carrying the same chrome the published page shows:
 * its filename, its language, and a line-number gutter when one is asked for.
 *
 * Highlighting is not done here. CodeBlockLowlight decorates the text directly,
 * which is the only way to colour content that is still editable; the token
 * colours come from the `code-highlight` rules the published block also uses.
 */
const props = defineProps({
    node: { type: Object, required: true },
});

const filename = computed(() => props.node.attrs.filename);
/**
 * The chosen language, or the one the highlighter guessed. The extension already
 * falls back to auto-detection when none is set; this only says which it landed
 * on, so the header is not blank while the code is plainly being coloured.
 */
const language = computed(() => {
    if (props.node.attrs.language) {
        return props.node.attrs.language;
    }

    const text = props.node.textContent;

    if (text.trim() === '') {
        return null;
    }

    try {
        return lowlight.highlightAuto(text).data?.language ?? null;
    } catch {
        return null;
    }
});

const detected = computed(() => ! props.node.attrs.language && language.value !== null);
const lineNumbers = computed(() => Boolean(props.node.attrs.lineNumbers));

// Counted from the text so the gutter tracks what is typed, rather than needing
// the editor to tell it.
const lines = computed(() => Math.max(1, props.node.textContent.split('\n').length));
</script>

<template>
    <NodeViewWrapper class="not-prose my-6 max-w-media overflow-hidden rounded-lg border border-neutral-50 bg-neutral-25">
        <div
            v-if="filename || language"
            contenteditable="false"
            class="flex items-center justify-between gap-3 border-b border-neutral-50 px-4 py-2 text-caption"
        >
            <span class="min-w-0 truncate text-neutral-700">{{ filename }}</span>
            <span class="shrink-0 uppercase tracking-wide text-neutral-500">
                {{ language }}<span v-if="detected" class="normal-case tracking-normal"> (auto)</span>
            </span>
        </div>

        <div class="flex">
            <!-- Deliberately not an `ol`: the editor styles lists, and those
                 rules outrank any utility class here, so a real list puts
                 markers beside the numbers and spaces them off their lines.
                 aria-hidden because a reader announcing every number before
                 its line is worse than no numbers at all. -->
            <div
                v-if="lineNumbers"
                contenteditable="false"
                aria-hidden="true"
                class="code-gutter shrink-0 select-none border-r border-neutral-50 px-3 text-right font-mono text-neutral-400"
            >
                <span v-for="line in lines" :key="line" class="block">{{ line }}</span>
            </div>

            <pre class="code-body min-w-0 flex-1"><NodeViewContent as="code" class="code-highlight" /></pre>
        </div>
    </NodeViewWrapper>
</template>
