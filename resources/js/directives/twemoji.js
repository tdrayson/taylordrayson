import twemoji from '@twemoji/api';

// Pin the asset host to the same jdecked release as `@twemoji/api` so the
// parser and image pack stay on one Unicode line. jsDelivr serves the repo's
// `assets/` tree; we prefer SVG over the default 72×72 PNG.
const options = {
    base: 'https://cdn.jsdelivr.net/gh/jdecked/twemoji@17.0.3/',
    folder: 'assets/svg',
    ext: '.svg',
    className: 'emoji',
};

// Replace native emoji glyphs in the element's subtree with Twemoji <img>.
// parse() is safe to re-run on update: it skips text already inside its
// generated <img>, so reactive/deferred content re-parses cleanly.
function apply(el) {
    twemoji.parse(el, options);
}

export const twemojiDirective = {
    mounted: apply,
    updated: apply,
};
