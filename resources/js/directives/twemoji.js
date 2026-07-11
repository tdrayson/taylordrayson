import twemoji from '@twemoji/api';

// Twemoji options: resolve each emoji to our self-hosted SVG (see Task 1)
// rather than the default jsDelivr CDN.
const options = {
    base: '/twemoji/',
    folder: 'svg',
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
