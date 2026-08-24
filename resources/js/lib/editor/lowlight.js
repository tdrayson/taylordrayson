import { createLowlight } from 'lowlight';
import bash from 'highlight.js/lib/languages/bash';
import css from 'highlight.js/lib/languages/css';
import javascript from 'highlight.js/lib/languages/javascript';
import json from 'highlight.js/lib/languages/json';
import php from 'highlight.js/lib/languages/php';
import sql from 'highlight.js/lib/languages/sql';
import typescript from 'highlight.js/lib/languages/typescript';
import xml from 'highlight.js/lib/languages/xml';
import yaml from 'highlight.js/lib/languages/yaml';

/**
 * The same curated grammar set CodeBlock.vue registers, so a block is
 * highlighted identically while it is being written and once it is published.
 * Anything outside the set renders as plain text rather than pulling in every
 * highlight.js grammar.
 */
export const lowlight = createLowlight();

lowlight.register({ bash, css, javascript, json, php, sql, typescript, xml, yaml });
