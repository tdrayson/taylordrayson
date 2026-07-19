import twemoji from '@twemoji/api';

// Replace native emoji glyphs in the element's subtree with Twemoji <img>.
// parse() is safe to re-run on update: it skips text already inside its
// generated <img>, so reactive/deferred content re-parses cleanly. Asset
// URLs use the package defaults (pinned jdecked CDN, 72×72 PNG).
function apply(el) {
    twemoji.parse(el);
}

export const twemojiDirective = {
    mounted: apply,
    updated: apply,
};
