<script setup>
import { computed, h } from 'vue';

/**
 * Portable Text written by somebody else.
 *
 * Deliberately not PortableTextBlocks: that renderer draws images, callouts,
 * embeds and code blocks, and gives links the author's own rel. Here the set of
 * things that can be drawn IS the safety property, so a node with no case below
 * has no way to render at all, whatever the stored document claims.
 */
const props = defineProps({
    // Portable Text blocks, already validated server-side by ContributedDocument.
    blocks: { type: Array, default: () => [] },
});

const DECORATORS = {
    strong: 'strong',
    em: 'em',
    underline: 'u',
    code: 'code',
};

const CODE_CLASS = 'rounded bg-neutral-25 px-1 py-0.5 font-mono text-[0.9em]';

/** http(s) only, mirroring the server rule, so a stored oddity still cannot link out. */
function isSafeHref(href) {
    try {
        return ['http:', 'https:'].includes(new URL(href).protocol);
    } catch {
        return false;
    }
}

function renderSpan(span, markDefs) {
    let node = span.text ?? '';

    for (const mark of span.marks ?? []) {
        const tag = DECORATORS[mark];

        if (tag) {
            node = h(tag, tag === 'code' ? { class: CODE_CLASS } : null, [node]);
            continue;
        }

        const def = markDefs.find((candidate) => candidate._key === mark);

        // ugc and nofollow on every one of them: nothing an author wrote
        // reaches this renderer, so there is no case where they do not apply.
        node = def?._type === 'link' && isSafeHref(def.href)
            ? h('a', {
                href: def.href,
                target: '_blank',
                rel: 'ugc nofollow noopener noreferrer',
                class: 'underline decoration-neutral-100 underline-offset-2 transition-colors hover:text-accent-500',
            }, [node])
            // A link we will not follow still keeps its words.
            : node;
    }

    return node;
}

const paragraphs = computed(() => props.blocks.filter((block) => block?._type === 'block'));

/** A block's own spans, whatever wrapper it ends up in. */
function childrenOf(block) {
    return (block.children ?? [])
        .filter((span) => span?._type === 'span' || span?.text !== undefined)
        .map((span) => renderSpan(span, block.markDefs ?? []));
}

/**
 * Blocks in order, with consecutive list items gathered into one list. Portable
 * Text has no list node: an item is a block carrying `listItem`, so the run has
 * to be found here rather than read off the document.
 */
const Rendered = () => {
    const out = [];

    for (let i = 0; i < paragraphs.value.length; i++) {
        const block = paragraphs.value[i];

        if (block.listItem) {
            const kind = block.listItem;
            const items = [];

            while (i < paragraphs.value.length && paragraphs.value[i].listItem === kind) {
                items.push(h('li', childrenOf(paragraphs.value[i])));
                i++;
            }

            i--;
            out.push(h(kind === 'number' ? 'ol' : 'ul', {
                class: kind === 'number'
                    ? 'mt-1 list-decimal space-y-1 pl-5 first:mt-0'
                    : 'mt-1 list-disc space-y-1 pl-5 first:mt-0',
            }, items));

            continue;
        }

        out.push(block.style === 'blockquote'
            ? h('blockquote', { class: 'mt-1 border-l-2 border-neutral-100 pl-3 text-neutral-700 first:mt-0' }, [
                h('p', childrenOf(block)),
            ])
            : h('p', { class: 'mt-1 whitespace-pre-line first:mt-0' }, childrenOf(block)));
    }

    return out;
};
</script>

<template>
    <div class="e-content text-body text-neutral-900">
        <Rendered />
    </div>
</template>
