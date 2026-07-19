# Twemoji — design

**Date:** 2026-07-11
**Branch:** `feature/twemoji` (off `master`)
**Status:** Approved, pending implementation plan

## Goal

Render emojis in user-authored content as consistent flat Twemoji SVGs across every platform, instead of relying on the OS's native emoji font (which differs on macOS / Windows / Android and gives no design control).

## Where emojis occur

User-authored text: check-in, food/calorie, and note descriptions; project/event descriptions; and article Portable Text (rendered as HTML by `BlockContent.vue`). Confirmed present in the seed data (e.g. `👍`, `😂`, `⛸` in `data/checkins.csv` / `data/calories.csv`). Not in UI chrome (the earlier "emoji" hits there were the `→` arrow glyph, U+2192).

## Decisions

| Decision | Choice |
| --- | --- |
| Parser | `@twemoji/api` (v17) — the current official Twemoji package; provides `parse()`, ships NO assets |
| Assets | `@twemoji/svg` (v15) — the 3,720 optimized SVG files |
| Format | SVG (crisp, scalable, one asset per emoji) |
| Asset hosting | **Self-hosted** — `@twemoji/svg` files copied into `public/twemoji/svg/` at build time via `vite-plugin-static-copy`, gitignored |
| Apply mechanism | A global Vue directive `v-twemoji`, applied at content-container level |
| Server-rendered Blade fallback pages | Out of scope (keep native emoji) |

## Architecture

### 1. Dependencies & assets

- Add three dev dependencies: `@twemoji/api` (the `parse()` function), `@twemoji/svg` (the SVG asset files), and `vite-plugin-static-copy` (build-time asset copy).
- `@twemoji/svg` publishes its SVGs at the package root (e.g. `node_modules/@twemoji/svg/1f44d.svg`). Configure `vite-plugin-static-copy` in `vite.config.js` to copy `node_modules/@twemoji/svg/*.svg` → `public/twemoji/svg/`, so the app serves them from its own origin at `/twemoji/svg/<codepoint>.svg`.
- Verify the copy runs on `npm run build` / `npm run dev` and lands files at `public/twemoji/svg/`; the plan pins the exact plugin `targets` config.
- `public/twemoji/` is added to `.gitignore` so the 3,720 SVGs are not committed; they are regenerated from the package on build.
- `parse()` from `@twemoji/api` is configured with `{ base: '/twemoji/', folder: 'svg', ext: '.svg', className: 'emoji' }` so it resolves `/twemoji/svg/<codepoint>.svg` from our own origin (not the default jsDelivr CDN).

### 2. Apply mechanism — global directive `v-twemoji`

- `resources/js/directives/twemoji.js` exports a Vue directive that, on `mounted` and `updated`, runs `@twemoji/api`'s `parse(el, options)` over the element's subtree, replacing native emoji codepoints with `<img class="emoji" draggable="false" alt="<emoji>">`. (The plan confirms the exact import shape — default vs named `parse` — from `@twemoji/api`'s ESM build.)
- Registered globally in `resources/js/app.js` via `app.directive('twemoji', ...)`.
- `twemoji.parse` is idempotent enough to re-run on `updated` (it skips text already inside its generated `<img>`), which matters for reactive/deferred content (paginated timeline tails, deferred props).
- Applied at the **content-container** level (one directive per content region, covering nested HTML), specifically:
  - The article body container in `BlockContent.vue`.
  - The note / check-in / food (calorie) / project / event description text elements in their detail components.
  - The title/body/description text in `FeedItem.vue` (the timeline card), so emojis in feed text are converted too.

### 3. Styling

Add to `resources/css/app.css`:
```css
img.emoji {
    display: inline-block;
    height: 1em;
    width: 1em;
    margin: 0 0.05em 0 0.1em;
    vertical-align: -0.1em;
}
```
So each emoji sizes to the surrounding text and sits on the baseline. Twemoji SVGs are full-colour and theme-independent, so no dark-mode handling is needed.

### 4. Testing

- A Pest browser test (the browser plugin is available): visit a page whose content contains a known emoji (e.g. a check-in seeded with `👍`) and assert an `img.emoji` element is rendered (the native glyph was replaced). Assert its `src` resolves under `/twemoji/`.
- If a page-render assertion is impractical, fall back to asserting the directive is registered and that `twemoji.parse` produces an `img.emoji` for a sample string in a small harness.

## Out of scope

- Server-rendered Blade fallback pages (`resources/views/pages/home.blade.php`, `day.blade.php`) keep native emoji — they are no-JS crawler fallbacks; matching them would need a separate PHP Twemoji port.
- Emoji input/picker UI (content is authored elsewhere, not in-app).
- Overlap note: this branch and `feature/dark-mode` (open PR #5) both touch `FeedItem.vue` / `BlockContent.vue`; a small merge reconciliation is expected and accepted.
