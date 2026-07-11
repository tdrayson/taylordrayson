# Rich link previews — design

**Date:** 2026-07-11
**Branch:** `feature/link-previews` (off `master`)
**Status:** Approved, pending implementation plan

## Goal

When hovering (or keyboard-focusing) an **internal** link inside **content**, show a floating rich-preview card of the target — cover, title, excerpt, type, date — like a Wikipedia/Notion hovercard. Only for internal links inside Portable Text content, not every link on the site.

## Where the links are

Internal content links live in **Portable Text bodies** (articles and CMS pages — both render through the same PT renderer, `resources/js/Components/Ui/PortableTextBlocks.js` / `BlockContent.vue`). A `link` markDef whose `href` does not start with `http` is rendered as a plain internal `<a href="/…">` (external links get `target="_blank"`). Notes are plaintext (no links); listing/tag pages are out of scope.

## Decisions

| Decision | Choice |
| --- | --- |
| Card content | Cover image (if any) + title + excerpt + type chip + date |
| Data delivery | Server resolves previews into Inertia **page props** (no API fetch, no inline-per-link bloat), deduped by href |
| Card markup | A hidden **reservoir** rendered once at the end of the article/page; hover reveals + positions the matching card |
| Trigger | Hover **and** keyboard focus (desktop). Touch: link just navigates, no preview |
| Positioning | Hand-rolled Teleport + bounding-rect placement with viewport-edge flip (same approach as `Tooltip.vue`; no Floating UI dependency) |

## Architecture

### 1. Backend — resolve previews into page props

- A new action `app/Actions/BuildLinkPreviews.php` (invokable) takes Portable Text `content` (the article/page body) and returns a deduped map:
  ```
  [ '/articles/my-post' => [
        'url' => '/articles/my-post',
        'title' => '…',
        'excerpt' => '…',      // from the model's excerpt / PortableText::plainText() truncation
        'type' => 'article',   // data-type key for the colour chip
        'date' => '2026-05-01',
        'cover' => '…url…'|null,
    ], … ]
  ```
- It walks the PT blocks' `markDefs` for `_type === 'link'` with an internal `href` (not starting with `http`), collects the unique hrefs, resolves each to its target model, and builds the entry from the model's existing preview data (`Article::excerpt` / `coverPhoto()` / `card()`-style metadata; the equivalent for other Timelineable types and `Page`). Unresolvable hrefs (paths with no previewable model — e.g. `/now`, `/photos`, a tag listing) are omitted.
- **href → model resolution:** map the internal path to its target via the app's existing routing/slug conventions (article slug, entry permalink `/{type}/{slug}`, page slug). Reuse existing resolution helpers where they exist rather than re-parsing routes.
- Wired into the controllers that render Portable Text content: the article branch of `EntryController@show` and the page controller (`PageController@show`), adding a `linkPreviews` prop alongside the existing entry/page payload.

### 2. Frontend — reservoir + floating popover

- **`resources/js/Components/Ui/LinkPreviewCard.vue`** — the card: cover `<img>` (lazy), title, excerpt (line-clamped), and a type-coloured chip + date. Themed by the data-type colour token.
- **`resources/js/Components/Ui/LinkPreviewLayer.vue`** — rendered once at the end of the article/page body. Props: the `linkPreviews` map and a ref/selector for the content container. It:
  - Renders a hidden reservoir — one `LinkPreviewCard` per unique preview — as the last child of the article/page body (never inline at each link). Hover reveals and positions the matching card.
  - Attaches `mouseenter`/`mouseleave` + `focus`/`blur` listeners to the content's internal `<a>` elements whose `href` is a key in the map.
  - On trigger, positions the matching card near the link via `getBoundingClientRect()` + a Teleport to `<body>`, flipping above/below and clamping horizontally to stay in the viewport.
- **Timing:** ~350 ms hover-intent delay before open; a short grace delay on close so the pointer can travel from the link onto the card (hovering the card keeps it open). Keyboard focus opens immediately; blur closes.
- **Touch:** detected via pointer type / no-hover media query — on touch, no listeners are attached and the link navigates normally.
- **a11y:** the card is supplementary and decorative to the link (the link's own text is the accessible name); the card is not focusable/no focus trap; `prefers-reduced-motion` disables the open/close transition.
- Wired into `BlockContent.vue` (and/or `ArticleDetail.vue` / the page view) so the layer receives the content container ref and the `linkPreviews` prop.

## Testing

- **Feature test (primary):** create an Article whose Portable Text content contains (a) an internal link to another previewable entry, (b) an external `http` link, and (c) an internal link to a non-previewable path. Assert the article page's Inertia props `linkPreviews` contains ONLY (a), with the correct `title`/`excerpt`/`type`/`date`/`cover`/`url`; (b) and (c) are absent.
- **Browser test (interaction):** hover an internal content link and assert the preview card appears with the target's title; move away and it hides. Requires `pestphp/pest-plugin-browser` — add it as a dev dependency on this branch (as the Twemoji branch did), since it is not present on `master`.
- Unit-level: `BuildLinkPreviews` dedupes repeated hrefs and skips external/unresolvable ones (covered by the feature test's assertions, or a focused action test).

## Out of scope

- External-link previews (unfurling third-party URLs).
- Previews outside Portable Text content (nav, sidebars, tag chips, timeline cards).
- Tap-to-preview on touch devices.
- A visible "linked references" section (the reservoir is hidden; hover-only).

## Notes

- Branches off `master`; overlaps `BlockContent.vue` with the open dark-mode (PR #5) and Twemoji (PR #6) branches — a small merge reconciliation is expected and accepted.
- Adds one dev dependency (`pestphp/pest-plugin-browser`) for the interaction test.
