<script setup>
import { computed, ref, onBeforeUnmount } from 'vue';
import { Copy01Icon, Tick02Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from './Icon.vue';
import hljs from 'highlight.js/lib/core';
import bash from 'highlight.js/lib/languages/bash';
import css from 'highlight.js/lib/languages/css';
import javascript from 'highlight.js/lib/languages/javascript';
import json from 'highlight.js/lib/languages/json';
import php from 'highlight.js/lib/languages/php';
import sql from 'highlight.js/lib/languages/sql';
import typescript from 'highlight.js/lib/languages/typescript';
import xml from 'highlight.js/lib/languages/xml';
import yaml from 'highlight.js/lib/languages/yaml';

// Curated language set keeps the bundle small; anything else renders as
// plain escaped text rather than pulling in every hljs grammar.
hljs.registerLanguage('bash', bash);
hljs.registerLanguage('css', css);
hljs.registerLanguage('javascript', javascript);
hljs.registerLanguage('json', json);
hljs.registerLanguage('php', php);
hljs.registerLanguage('sql', sql);
hljs.registerLanguage('typescript', typescript);
hljs.registerLanguage('xml', xml);
hljs.registerLanguage('yaml', yaml);

const props = defineProps({
    code: { type: String, default: '' },
    language: { type: String, default: null },
    filename: { type: String, default: null },
    lineNumbers: { type: Boolean, default: false },
});

// hljs escapes the source before wrapping tokens in spans, so its output is
// safe to inject; this is the one sanctioned innerHTML in the PT renderer.
const highlighted = computed(() => {
    if (props.language && hljs.getLanguage(props.language)) {
        return hljs.highlight(props.code, { language: props.language }).value;
    }

    return null;
});

const hasHeader = computed(() => Boolean(props.filename || props.language));

// Gutter numbers count raw source lines; the highlighted HTML is never split,
// so hljs spans that cross line boundaries stay intact.
const lineCount = computed(() => props.code.replace(/\n$/, '').split('\n').length);

const copied = ref(false);
let timer = null;

async function copy() {
    try {
        await navigator.clipboard.writeText(props.code);
    } catch {
        // Non-secure context (e.g. http://*.test): fall back to a transient textarea.
        const scratch = document.createElement('textarea');
        scratch.value = props.code;
        document.body.appendChild(scratch);
        scratch.select();
        document.execCommand('copy');
        scratch.remove();
    }

    copied.value = true;
    clearTimeout(timer);
    timer = setTimeout(() => (copied.value = false), 2000);
}

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <div class="group/code max-w-media overflow-hidden rounded-lg border border-neutral-50 bg-neutral-25">
        <!-- Header bar only when there is something to say; the copy button
             floats over the code instead when the header is absent. -->
        <div v-if="hasHeader" class="flex items-center gap-3 border-b border-neutral-50 px-4 py-2">
            <span v-if="filename" class="min-w-0 truncate font-mono text-caption text-neutral-700">{{ filename }}</span>
            <span v-if="language" class="text-caption font-medium uppercase text-neutral-400">{{ language }}</span>
            <button
                type="button"
                class="ml-auto flex items-center gap-1.5 text-caption font-medium text-neutral-500 transition-colors hover:text-neutral-900 focus-visible:text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                :aria-label="copied ? 'Copied' : 'Copy code'"
                @click="copy"
            >
                <Icon :icon="copied ? Tick02Icon : Copy01Icon" class="size-4" :class="copied ? 'text-accent-500' : ''" />
                {{ copied ? 'Copied' : 'Copy' }}
            </button>
        </div>

        <div class="relative flex">
            <button
                v-if="!hasHeader"
                type="button"
                class="absolute right-2 top-2 flex items-center gap-1.5 rounded-md bg-neutral-0/80 px-2 py-1 text-caption font-medium text-neutral-500 opacity-0 backdrop-blur transition-opacity hover:text-neutral-900 focus-visible:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 group-hover/code:opacity-100"
                :aria-label="copied ? 'Copied' : 'Copy code'"
                @click="copy"
            >
                <Icon :icon="copied ? Tick02Icon : Copy01Icon" class="size-4" :class="copied ? 'text-accent-500' : ''" />
                {{ copied ? 'Copied' : 'Copy' }}
            </button>

            <!-- Both columns share text-meta + leading-6 so gutter rows line up
                 with code lines without splitting the highlighted HTML. -->
            <div v-if="lineNumbers" aria-hidden="true" class="select-none py-4 pl-4 pr-3 text-right text-meta leading-6 text-neutral-300 tnum">
                <div v-for="line in lineCount" :key="line">{{ line }}</div>
            </div>

            <pre class="flex-1 overflow-x-auto py-4 text-meta leading-6" :class="lineNumbers ? 'pr-4' : 'px-4'"><code v-if="highlighted" class="code-highlight" v-html="highlighted"></code><code v-else>{{ code }}</code></pre>
        </div>
    </div>
</template>

<style scoped>
/* Restrained light theme built from the design tokens rather than a stock
   hljs stylesheet, so code blocks read as part of the site. */
.code-highlight :deep(.hljs-comment),
.code-highlight :deep(.hljs-quote) {
    color: var(--color-neutral-400);
    font-style: italic;
}

.code-highlight :deep(.hljs-keyword),
.code-highlight :deep(.hljs-selector-tag),
.code-highlight :deep(.hljs-meta) {
    color: var(--color-accent-700);
}

.code-highlight :deep(.hljs-string),
.code-highlight :deep(.hljs-addition),
.code-highlight :deep(.hljs-attr) {
    color: hsl(150 45% 32%);
}

.code-highlight :deep(.hljs-number),
.code-highlight :deep(.hljs-literal),
.code-highlight :deep(.hljs-built_in) {
    color: hsl(28 75% 40%);
}

.code-highlight :deep(.hljs-title),
.code-highlight :deep(.hljs-name),
.code-highlight :deep(.hljs-function) {
    color: var(--color-neutral-900);
    font-weight: 600;
}

.code-highlight :deep(.hljs-variable),
.code-highlight :deep(.hljs-template-variable),
.code-highlight :deep(.hljs-subst) {
    color: hsl(340 55% 42%);
}
</style>
