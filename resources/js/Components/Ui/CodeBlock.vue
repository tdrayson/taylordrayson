<script setup>
import { computed, ref, onBeforeUnmount } from 'vue';
import { Copy01Icon, Tick02Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from './Icon.vue';
import { copyText } from '../../lib/clipboard';
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
    await copyText(props.code);

    copied.value = true;
    clearTimeout(timer);
    timer = setTimeout(() => (copied.value = false), 2000);
}

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <!-- not-prose: the code block is a self-contained component; typography
         plugin defaults must not leak into it. -->
    <div class="code-block not-prose max-w-media overflow-hidden rounded-lg border border-neutral-50 bg-neutral-25">
        <div v-if="hasHeader" class="flex items-center border-b border-neutral-50 text-caption">
            <span v-if="language" class="bg-neutral-50 px-4 py-2.5 font-medium uppercase tracking-wide text-neutral-500">{{ language }}</span>
            <span v-if="filename" class="min-w-0 truncate px-4 font-mono text-neutral-700">{{ filename }}</span>
            <button
                type="button"
                class="ml-auto flex items-center gap-1.5 px-4 py-2.5 font-medium text-neutral-500 transition-colors hover:text-neutral-900 focus-visible:text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
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

            <!-- Both columns share leading-6 so gutter rows line up with code lines
                 without splitting the highlighted HTML. -->
            <div v-if="lineNumbers" aria-hidden="true" class="select-none py-4 pl-4 pr-3 text-right text-meta leading-6 text-neutral-300 tnum">
                <div v-for="line in lineCount" :key="line">{{ line }}</div>
            </div>

            <pre class="flex-1 overflow-x-auto py-4 text-meta leading-6" :class="lineNumbers ? 'pr-4' : 'px-4'"><code v-if="highlighted" class="code-highlight" v-html="highlighted"></code><code v-else>{{ code }}</code></pre>
        </div>
    </div>
</template>
