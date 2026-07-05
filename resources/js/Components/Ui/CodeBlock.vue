<script setup>
import { computed } from 'vue';
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
});

// hljs escapes the source before wrapping tokens in spans, so its output is
// safe to inject; this is the one sanctioned innerHTML in the PT renderer.
const highlighted = computed(() => {
    if (props.language && hljs.getLanguage(props.language)) {
        return hljs.highlight(props.code, { language: props.language }).value;
    }

    return null;
});
</script>

<template>
    <pre class="max-w-media overflow-x-auto rounded-lg bg-neutral-25 p-4 text-meta"><code v-if="highlighted" class="code-highlight" v-html="highlighted"></code><code v-else>{{ code }}</code></pre>
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
