# Portable Text Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace Editor.js as the rich-content format for articles and pages with the Portable Text standard; delete all Editor.js code.

**Architecture:** `content` JSON columns on `articles`/`pages` store a bare Portable Text array (the canonical spec shape: `block` nodes with `children` spans, `marks`, `markDefs`, plus custom `image`/`code`/`divider` nodes). A new `App\Support\PortableText` class owns plain-text extraction and block construction. `BlockContent.vue` becomes a hand-rolled Portable Text renderer (no new npm dependency). A migration converts stored Editor.js documents (dev DB holds exactly one lorem sample article; pages are empty).

**Tech Stack:** Laravel 13, PHP 8.4, Pest 4, Vue 3. NO new composer or npm dependencies.

## Global Constraints

- Portable Text shape (per portabletext.org spec):
  - Text block: `{"_type": "block", "_key": "<random>", "style": "normal|h2|h3|blockquote", "markDefs": [{"_key": "<k>", "_type": "link", "href": "..."}], "children": [{"_type": "span", "_key": "<k>", "text": "...", "marks": ["strong"|"em"|"code"|"<markDefKey>", ...]}]}`
  - List items are flat sibling text blocks carrying `"listItem": "bullet"|"number"` and `"level": 1..n` — the RENDERER groups consecutive list blocks into `<ul>/<ol>` trees.
  - Custom nodes: `{"_type": "image", "_key", "url", "caption"?}`, `{"_type": "code", "_key", "code", "language"?}`, `{"_type": "divider", "_key"}`.
  - The stored document is the BARE ARRAY of nodes (no wrapper object).
- Checklist blocks are dropped (no real data exists); every other current BlockContent feature keeps parity: headings, paragraphs, bullet/numbered lists with nesting, blockquote, code, divider, images with the shared lightbox, link/bold/italic/inline-code marks.
- After this plan: `grep -rn "EditorJs\|editorjs" app/ resources/ tests/ database/` returns nothing (migrations under database/migrations referencing the old format in comments are acceptable; code references are not).
- v-html is FORBIDDEN in the new renderer: spans render as text nodes; marks become real elements. (The Editor.js renderer needed v-html for inline HTML; Portable Text does not.)
- Notes are plaintext and out of scope. Search LIKE queries against the content column keep working (they match words inside JSON either way).
- Project rules: explicit return types, PHPDoc over inline comments, `vendor/bin/pint --dirty --format agent` before commits, conventional commits, no attribution footer, stage by path (NO `git add -A` — unrelated untracked plan doc in repo), Vue non-obvious logic gets brief comments, no arbitrary Tailwind bracket values.

---

### Task 1: PHP side — PortableText support, migration, factories, EditorJs removal

**Files:**
- Create: `app/Support/PortableText.php`
- Create: `database/migrations/<timestamp>_convert_content_to_portable_text.php`
- Modify: `app/Models/Article.php` (card subtitle uses PortableText::plainText)
- Modify: `database/factories/ArticleFactory.php`, `database/factories/PageFactory.php`
- Delete: `app/Support/EditorJs.php`
- Modify/rename: `tests/Feature/EditorContentTest.php` → the PHP-side expectations move to `tests/Feature/PortableTextTest.php` (Task 2 rewrites the render-side tests; this task ports the plainText/model-facing ones)
- Test: `tests/Unit/PortableTextTest.php` (new, plainText datasets)

**Interfaces:**
- Produces `App\Support\PortableText`:

```php
<?php

namespace App\Support;

use Illuminate\Support\Str;

class PortableText
{
    /**
     * Flatten a Portable Text document into readable plain text for card
     * excerpts and previews. Only text blocks and code nodes carry text.
     *
     * @param  array<int, array<string, mixed>>|string|null  $document
     */
    public static function plainText(array|string|null $document): string
    {
        $parts = [];

        foreach (self::nodes($document) as $node) {
            if (($node['_type'] ?? null) === 'block') {
                $parts[] = implode('', array_map(
                    fn (array $child): string => $child['text'] ?? '',
                    $node['children'] ?? [],
                ));
            }

            if (($node['_type'] ?? null) === 'code') {
                $parts[] = $node['code'] ?? '';
            }
        }

        return trim((string) preg_replace('/\s+/', ' ', implode(' ', $parts)));
    }

    /**
     * Normalise a stored document (array or raw JSON) to the bare node list.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function nodes(array|string|null $document): array
    {
        if (is_string($document)) {
            $document = json_decode($document, true);
        }

        return is_array($document) && array_is_list($document) ? $document : [];
    }

    /**
     * Build a simple text block, used by factories and conversions.
     */
    public static function block(string $text, string $style = 'normal', ?string $listItem = null, int $level = 1): array
    {
        $block = [
            '_type' => 'block',
            '_key' => self::key(),
            'style' => $style,
            'markDefs' => [],
            'children' => [self::span($text)],
        ];

        if ($listItem !== null) {
            $block['listItem'] = $listItem;
            $block['level'] = $level;
        }

        return $block;
    }

    /**
     * @param  list<string>  $marks
     */
    public static function span(string $text, array $marks = []): array
    {
        return ['_type' => 'span', '_key' => self::key(), 'text' => $text, 'marks' => $marks];
    }

    public static function key(): string
    {
        return Str::lower(Str::random(12));
    }

    /**
     * One-way conversion of a stored Editor.js document. Inline HTML is
     * stripped to plain spans (the only stored document is factory lorem).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function fromEditorJs(array|string|null $document): array
    {
        if (is_string($document)) {
            $document = json_decode($document, true);
        }

        $blocks = is_array($document) ? ($document['blocks'] ?? []) : [];
        $nodes = [];

        foreach ($blocks as $block) {
            $type = $block['type'] ?? null;
            $data = $block['data'] ?? [];

            $nodes = [...$nodes, ...match ($type) {
                'paragraph' => [self::block(strip_tags($data['text'] ?? ''))],
                'header' => [self::block(strip_tags($data['text'] ?? ''), ((int) ($data['level'] ?? 2)) <= 2 ? 'h2' : 'h3')],
                'quote' => [self::block(strip_tags(trim(($data['text'] ?? '').' '.($data['caption'] ?? ''))), 'blockquote')],
                'list', 'nestedlist' => self::listBlocks($data),
                'code' => [['_type' => 'code', '_key' => self::key(), 'code' => $data['code'] ?? '']],
                'delimiter' => [['_type' => 'divider', '_key' => self::key()]],
                'image' => array_filter([self::imageNode($data)]),
                default => [],
            }];
        }

        return $nodes;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function listBlocks(array $data, int $level = 1): array
    {
        $listItem = ($data['style'] ?? 'unordered') === 'ordered' ? 'number' : 'bullet';
        $nodes = [];

        foreach ($data['items'] ?? [] as $item) {
            $text = is_array($item) ? ($item['content'] ?? '') : $item;
            $nodes[] = self::block(strip_tags($text), 'normal', $listItem, $level);

            if (is_array($item) && ! empty($item['items'])) {
                $nodes = [...$nodes, ...self::listBlocks(['style' => $data['style'] ?? 'unordered', 'items' => $item['items']], $level + 1)];
            }
        }

        return $nodes;
    }

    private static function imageNode(array $data): ?array
    {
        $url = $data['file']['url'] ?? $data['url'] ?? null;

        if (! $url) {
            return null;
        }

        return array_filter([
            '_type' => 'image',
            '_key' => self::key(),
            'url' => $url,
            'caption' => isset($data['caption']) ? strip_tags($data['caption']) : null,
        ], fn ($value) => $value !== null);
    }
}
```

- [ ] **Step 1: Failing unit tests.** Create `tests/Unit/PortableTextTest.php` with dataset cases for `plainText` (text blocks joined, code included, marks ignored, string input parsed, garbage/null → ''), `nodes` (bare array passes, JSON string parses, envelope object → []), and `fromEditorJs` (paragraph→normal block, header level 3→h3, list→flat listItem blocks with level, delimiter→divider, image with file.url, inline `<b>` HTML stripped). Write concrete expected arrays (ignore `_key` values via targeted assertions on `_type`/`style`/`text`/`listItem`/`level`, not whole-array equality). Run, verify FAIL (class missing).
- [ ] **Step 2: Implement the class** exactly as above; unit tests pass.
- [ ] **Step 3: Migration.** Convert `articles.content` and `pages.content` in place: select rows, `PortableText::fromEditorJs($row->content)`, update. Only convert rows whose decoded content is an object with a `blocks` key (already-converted bare arrays are skipped, making the migration re-runnable). `down()` is a no-op with a PHPDoc note (one-way conversion; pre-conversion data is factory lorem). Run `php artisan migrate --no-interaction`, then verify: `php artisan tinker --execute "dump(App\Models\Article::first()?->content[0]['_type'] ?? 'none');"` prints `block` or `none`.
- [ ] **Step 4: Factories + model.** `ArticleFactory`/`PageFactory` build content as `[PortableText::block($title, 'h2'), PortableText::block(fake()->paragraph()), ...]` style arrays (keep roughly the current block variety: a heading + 2-3 paragraphs). `Article::card()` subtitle switches `EditorJs::plainText` → `PortableText::plainText`. Search for any other `EditorJs` references (`grep -rn "EditorJs" app/ tests/ database/`) and port them (the `EditorContentTest` render tests belong to Task 2 — for this task, convert their content SEEDS to Portable Text via the factory/PortableText helpers and keep assertions that are about plain text/excerpts; move/rename the file as fits).
- [ ] **Step 5: Delete `app/Support/EditorJs.php`.** Grep gate: `grep -rn "EditorJs" app/ resources/js tests/ database/factories` → empty. Full suite green (`php artisan test --compact`). NOTE: the Vue renderer still renders the OLD shape until Task 2 lands, so a Task-1-only checkout renders article bodies empty — acceptable intermediate state on this branch, do NOT try to fix the Vue side in this task.
- [ ] **Step 6: Commit** `feat: Portable Text content format - support class, conversion migration, factories` (stage by path).

---

### Task 2: Vue renderer — BlockContent renders Portable Text

**Files:**
- Rewrite: `resources/js/Components/Ui/BlockContent.vue`
- Create: `resources/js/Components/Ui/PortableTextBlocks.js` (render-function component for nodes/spans/marks/list grouping)
- Delete: `resources/js/Components/Ui/EditorList.vue`
- Test: `tests/Feature/PortableTextRenderTest.php` (new; Inertia feature tests asserting article/page props carry the PT array and pages render 200 — DOM-level render assertions are out of reach for feature tests, so cover the payload contract + a smoke visit of an article entry page and a content page)

**Interfaces:**
- Consumes: Task 1's stored format (bare PT array on `entry.content` / page `content` prop). `ArticleDetail.vue` and `Page.vue` keep passing `:document="..."` to `BlockContent`; prop name unchanged.
- Produces: `BlockContent.vue` accepting `document` = bare PT array (or JSON string), rendering with feature parity minus checklists, keeping the existing lightbox behaviour for images and the existing scoped styles for links/inline code.

**Renderer contract (implement in `PortableTextBlocks.js` with Vue `h()` render functions — templates get unwieldy for recursive marks/lists):**
- Group consecutive nodes where `listItem` is set into list trees: same `level` + same `listItem` → siblings; higher `level` → nested list inside the previous item; `number` → `<ol>`, `bullet` → `<ul>` (mirror the old `EditorList` classes: `list-disc`/`list-decimal`, `space-y-1.5`, `pl-5`).
- Text block styles: `normal` → `<p>`; `h2` → `<h2 class="font-display text-item-title text-neutral-900">`; `h3` → `<h3 class="text-lg font-semibold font-display text-neutral-900">`; `blockquote` → `<blockquote class="border-l-2 border-accent-200 pl-4 text-neutral-700">`.
- Spans: plain text nodes. Marks nest outward-in: decorator `strong` → `<strong>`, `em` → `<em>`, `code` → `<code>`; any other mark key is looked up in the block's `markDefs` — `_type: 'link'` renders `<a :href="def.href" rel="noopener">` (external links may add `target="_blank"` only if the current site convention does; check `ExternalLink.vue` usage and match).
- `image` node → the existing figure + ZoomButton + shared Lightbox gallery pattern (all images in the document form one gallery, as today). `code` node → the existing `<pre><code>` styling. `divider` → `<hr class="border-neutral-50">`.
- No `v-html`/`innerHTML` anywhere.
- **Long-read width system** (design tokens already exist in resources/css/app.css: "Long-read column widths: prose reads at max-w-reading; charts, images and other blocks sit a touch wider at max-w-media. Reused by stories/articles"): text blocks (`normal`, headings, lists) get `max-w-reading`; `blockquote`, `code`, and `image` nodes get `max-w-media`. The renderer owns these classes so every consumer (articles, pages) reads identically.
- **Heading anchors for the TOC** (consumed by Task 4): every `h2`/`h3` block renders with `id` = slugified span text (dedupe repeats with `-2` suffixes within the document) plus `data-toc` and `data-toc-label="<plain text>"` attributes, and `scroll-mt-24` for anchored scrolling.

- [ ] **Step 1: Failing feature tests.** `PortableTextRenderTest`: (a) an article entry page returns the PT array in `entry.content` (assert `entry.content.0._type === 'block'` via Inertia `where`); (b) a published page at its slug 200s with `content.0._type === 'block'`; (c) article card excerpt on the timeline equals the plainText of its content (ties Task 1 + display together). Verify they fail only where expected (they may partially pass after Task 1 — that is fine; keep the assertions that add render-side value).
- [ ] **Step 2: Implement** `PortableTextBlocks.js` + rewritten `BlockContent.vue` per the contract. Delete `EditorList.vue` and remove its imports.
- [ ] **Step 3: Verify visually-adjacent behaviour by payload + build**: `php artisan test --compact` full suite green; `npm run build` clean; `grep -rn "EditorList\|editorjs\|EditorJs" resources/js app/ tests/` → empty.
- [ ] **Step 4: Commit** `feat: Portable Text renderer replaces Editor.js BlockContent` (stage by path).

---

### Task 3: Entry reading layout + universal title measure

**Files:**
- Modify: `resources/js/Pages/Entry.vue`, `resources/js/Pages/Page.vue`
- Modify: `resources/js/Components/Entry/ArticleDetail.vue`, `resources/js/Components/Entry/NoteDetail.vue` (width application only)
- Test: extend `tests/Feature/PortableTextRenderTest.php` only if payloads change (they should not; this task is presentational)

**Interfaces:**
- Consumes: Task 2's renderer (which already applies per-block widths). This task fixes the CONTAINERS so those widths can breathe, and the headline measure.

**Requirements:**
- The single-entry `<h1>` in `Entry.vue` and the page title in `Page.vue` get a universal headline measure: `max-w-2xl` (the same measure StoryChapter headings use; at display size this is roughly the 30-40ch the timeline cards got via `max-w-md` at their smaller size). Applies to ALL entry types.
- Article and Page bodies must not be constrained by a narrower ancestor than `max-w-media`: check `Page.vue`'s `<article class="max-w-2xl">` wrapper and `ArticleDetail.vue`'s container — the wrapper widens (or drops its cap) so the renderer's `max-w-reading` prose / `max-w-media` breakout blocks render at their intended widths, mirroring how the story pages lay out (see `resources/js/Pages/Stories/Food.vue` for the reference structure).
- `NoteDetail.vue` content gets `max-w-reading` for consistency.
- Verify with `npm run build` + full suite; commit `feat: story-style reading widths on entry pages + universal headline measure`.

---

### Task 4: Reusable table of contents for articles

**Files:**
- Create: `resources/js/Components/Ui/ContentToc.vue` (generalised from `resources/js/Components/Story/StoryToc.vue`)
- Modify: `resources/js/Components/Entry/ArticleDetail.vue` (mount the TOC when the document has 2+ headings)
- Reference (do not break): `resources/js/Components/Story/StoryToc.vue` and the story pages stay as they are in this task; refactoring stories onto ContentToc is optional follow-up, NOT required here.

**Interfaces:**
- Consumes: Task 2's `data-toc` / `data-toc-label` / `id` attributes on rendered h2/h3 blocks.
- Produces: `<ContentToc selector="[data-toc]" />` — a component that on mount collects matching elements into `[{ id, label }]`, runs the same IntersectionObserver scroll-spy as StoryToc (`rootMargin: '-15% 0px -70% 0px'`), renders the same desktop rail / mobile sheet interaction pattern, and smooth-scrolls on click. Copy StoryToc's accessibility behaviour (body scroll lock on mobile sheet, focus handling) faithfully; strip the story-specific number/kicker fields in favour of plain labels.

**Requirements:**
- Articles with fewer than 2 headings render no TOC.
- Keep the component generic: props `selector` (default `[data-toc]`) and `labelAttr` (default `data-toc-label`), so stories can adopt it later.
- Feature test: an article whose content has two h2 blocks serves its entry page 200 (payload-level; TOC behaviour is client-side). Visual check is the user's.
- Commit `feat: reusable content table of contents on article entries`.

---

### Task 5: Cross-type tag pages at /tags/{slug}

**Files:**
- Create: `app/Http/Controllers/TagController.php`, `resources/js/Pages/Tag.vue`
- Modify: `routes/web.php` (route BEFORE the catch-all `/{slug}` page route), `app/Http/Controllers/EntryController.php`/`ArticleDetail.vue`/`NoteDetail.vue` (tag chips become links to `/tags/{slug}`)
- Test: `tests/Feature/TagPageTest.php`

**Interfaces:**
- Consumes: the `tags`/`taggables` tables + `HasTags` (Article, Note, Project).
- Produces: `GET /tags/{slug}` → 404 for unknown slugs; otherwise an Inertia `Tag` page with the tag name and a cross-type feed of everything carrying that tag (articles + notes + projects), built by resolving each tagged model's timeline entry and reusing `BuildTimelineFeed::groupByDay` / `cardItem` so cards render identically to the timeline. Unpublished articles must respect the existing guest gate (their timeline entries do not exist, so resolving through `timeline_entries` handles it — verify with a test).

**Requirements:**
- Route named `tags.show`, placed above the page catch-all so `/tags/x` never hits `PageController`.
- Entry pages: article and note tag chips render as `<Link :href="`/tags/${tag.slug}`">` — the entry payload must therefore carry tags as `{name, slug}` objects (adjust `EntryController::entryPayload` mapping and the two Vue components; the notes API resource keeps returning plain names).
- Tests: tagged article + tagged note + tagged project all appear on the tag page; unknown slug 404s; a tag only on an unpublished article 404s for guests (no entries resolve) but renders for the authed user; tag chips on an article entry page link to /tags/{slug}.
- Commit `feat: cross-type tag pages at /tags/{slug} with linkable tag chips`.

---

## Self-Review Notes

- Format decision (user, 2026-07-05): Portable Text standard, supersedes both Editor.js (removed here) and the earlier TipTap recommendation (never built). No new dependencies: renderer is hand-rolled; PT is a small spec and the site controls its own content.
- Checklist support dropped deliberately (no stored data uses it); everything else keeps parity.
- Task 1 leaves rendering broken until Task 2 (documented intermediate state, same branch, same session).
- `_key` values are required by the spec for editors/patching; we generate them now so future authoring tools (Plan 3 compose UI, MCP writes) can rely on them.
