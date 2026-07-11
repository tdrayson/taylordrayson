# Rich Link Previews Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Hovering (or focusing) an internal link inside Portable Text content shows a floating rich-preview card of the target (cover, title, excerpt, type, date), with the data delivered in the page props (no API) and the cards rendered in a hidden reservoir at page end.

**Architecture:** A backend action `BuildLinkPreviews` walks the article/page Portable Text content, resolves each unique internal link href to its target model, and returns a deduped `{ href: preview }` map added to the Inertia page props. On the client, a `LinkPreviewLayer` renders a hidden reservoir of `LinkPreviewCard`s at the end of the content and, on hover/focus of a matching internal `<a>`, positions the card as a floating popover (Teleport + rect math). Desktop-only; touch just navigates.

**Tech Stack:** Laravel 13 + Inertia v3 + Vue 3 (client-only, no SSR), Tailwind v4, Pest 4.

## Global Constraints

- **Commit policy:** per-task commits on `feature/link-previews` only; no push/PR without asking. NO "Claude-Session" trailer or attribution. Never stage `todo.md`, `.superpowers/`, `public/twemoji/`, or any `.svg`.
- **Approved dependency:** `pestphp/pest-plugin-browser` (dev) for the hover test. No others without asking.
- **Routing facts:** entry permalinks are `/{year}/{month}/{day}/{slug}` (resolved via `TimelineEntry` `whereDate('occurred_at')` + `where('url_slug', $slug)` → `timelineable`); pages are `/{slug}` (single letter-first segment) via `Page::where('slug')`. Only these two shapes are previewable; tags/stories/archives/multi-segment paths are skipped.
- **Only internal, previewable, published targets** get a preview. External (`http…`) links and unresolvable/unpublished targets are omitted.
- PHP: explicit return types, constructor promotion, curly braces, PHPDoc over inline; run `vendor/bin/pint --dirty --format agent` before committing PHP. No arbitrary Tailwind bracket values; comment non-obvious Vue/JS. Single root element per component.
- This branch overlaps `BlockContent.vue` with the open dark-mode (PR #5) and Twemoji (PR #6) branches — expected; do not pull their changes in.

---

### Task 1: `BuildLinkPreviews` action + backend wiring + feature test

**Files:**
- Create: `app/Actions/BuildLinkPreviews.php`
- Modify: `app/Http/Controllers/EntryController.php` (article branch of `show`)
- Modify: `app/Http/Controllers/PageController.php` (`show`)
- Test: `tests/Feature/LinkPreviewsTest.php`

**Interfaces:**
- Produces: `BuildLinkPreviews::__invoke(?array $blocks): array` — a `{ href => ['url','title','excerpt','type','accent','date','cover'] }` map. Added to the `Entry` and `Page` Inertia props as `linkPreviews`.

- [ ] **Step 1: Write the failing feature test**

Create `tests/Feature/LinkPreviewsTest.php`:
```php
<?php

use App\Models\Article;
use function Pest\Laravel\actingAs;

it('exposes previews only for internal, previewable content links', function () {
    // Target article the link points to.
    $target = Article::factory()->create([
        'title' => 'Target Post',
        'excerpt' => 'A short summary.',
        'occurred_at' => '2026-05-01 10:00:00',
        'slug' => 'target-post',
        'published' => true,
    ]);
    $targetHref = '/2026/05/01/target-post';

    // Source article whose content links to the target, plus an external link
    // and an internal link to a non-previewable path.
    $source = Article::factory()->create([
        'occurred_at' => '2026-05-02 10:00:00',
        'slug' => 'source-post',
        'published' => true,
        'content' => [[
            '_type' => 'block',
            'markDefs' => [
                ['_key' => 'a', '_type' => 'link', 'href' => $targetHref],
                ['_key' => 'b', '_type' => 'link', 'href' => 'https://example.com'],
                ['_key' => 'c', '_type' => 'link', 'href' => '/photos'],
            ],
            'children' => [
                ['_type' => 'span', 'marks' => ['a'], 'text' => 'target'],
                ['_type' => 'span', 'marks' => ['b'], 'text' => 'external'],
                ['_type' => 'span', 'marks' => ['c'], 'text' => 'photos'],
            ],
        ]],
    ]);

    get('/2026/05/02/source-post')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('linkPreviews', 1)
            ->where("linkPreviews.$targetHref.title", 'Target Post')
            ->where("linkPreviews.$targetHref.excerpt", 'A short summary.')
            ->where("linkPreviews.$targetHref.type", 'article')
            ->where("linkPreviews.$targetHref.url", $targetHref)
        );
});
```
> Confirm the Article factory produces a resolvable `TimelineEntry` with `url_slug` = the article slug (the `TimelineEntryObserver` creates the spine row; verify `url_slug` matches). If the factory's slug/url_slug differ, align the test's href to the actual generated `url_slug` (read an existing entry/article feature test for the pattern). Import `get` via `use function Pest\Laravel\get;` if not auto-available.

- [ ] **Step 2: Run it — expect failure**

Run: `php artisan test tests/Feature/LinkPreviewsTest.php --compact`
Expected: FAIL (`linkPreviews` prop missing).

- [ ] **Step 3: Write the action**

Create `app/Actions/BuildLinkPreviews.php`:
```php
<?php

namespace App\Actions;

use App\Models\Article;
use App\Models\Page;
use App\Models\TimelineEntry;
use Illuminate\Database\Eloquent\Model;

class BuildLinkPreviews
{
    /**
     * Build a deduped map of internal-link hrefs to preview cards from Portable
     * Text content. External, unresolvable, and unpublished targets are omitted.
     *
     * @param  array<int, array<string, mixed>>|null  $blocks
     * @return array<string, array<string, mixed>>
     */
    public function __invoke(?array $blocks): array
    {
        if ($blocks === null) {
            return [];
        }

        $hrefs = [];
        foreach ($blocks as $block) {
            foreach ($block['markDefs'] ?? [] as $def) {
                $href = $def['href'] ?? null;
                if (($def['_type'] ?? null) === 'link' && is_string($href) && ! str_starts_with($href, 'http')) {
                    $hrefs[$href] = true;
                }
            }
        }

        $previews = [];
        foreach (array_keys($hrefs) as $href) {
            $preview = $this->resolve($href);
            if ($preview !== null) {
                $previews[$href] = $preview;
            }
        }

        return $previews;
    }

    /**
     * Resolve an internal href to its preview data, or null when not previewable.
     *
     * @return array<string, mixed>|null
     */
    private function resolve(string $href): ?array
    {
        // Entry permalink: /YYYY/MM/DD/slug
        if (preg_match('#^/(\d{4})/(\d{2})/(\d{2})/([a-z0-9-]+)$#', $href, $m) === 1) {
            $entry = TimelineEntry::query()
                ->with('timelineable')
                ->whereDate('occurred_at', "{$m[1]}-{$m[2]}-{$m[3]}")
                ->where('url_slug', $m[4])
                ->first();

            $model = $entry?->timelineable;

            if ($model === null) {
                return null;
            }

            if ($model instanceof Article && ! $model->published) {
                return null;
            }

            return $this->fromCard($model, $href);
        }

        // Content page: /slug
        if (preg_match('#^/([a-z][a-z0-9-]*)$#', $href, $m) === 1) {
            $page = Page::query()->where('slug', $m[1])->where('published', true)->first();

            if ($page === null) {
                return null;
            }

            return [
                'url' => $href,
                'title' => $page->title,
                'excerpt' => $page->excerpt,
                'type' => 'page',
                'accent' => 'page',
                'date' => null,
                'cover' => null,
            ];
        }

        return null;
    }

    /**
     * Preview data from a timelineable model's card() metadata.
     *
     * @return array<string, mixed>
     */
    private function fromCard(Model $model, string $href): array
    {
        $card = $model->card();

        return [
            'url' => $href,
            'title' => $card['title'] ?? null,
            'excerpt' => data_get($card, 'meta.subtitle'),
            'type' => $card['type'] ?? null,
            'accent' => $card['accent'] ?? $card['type'] ?? null,
            'date' => $model->occurredAtForDisplay()?->toDateString(),
            'cover' => data_get($card, 'meta.photos.0.src'),
        ];
    }
}
```
> Verify `card()` for an Article actually exposes `meta.subtitle` (the excerpt) and `meta.photos.0.src` (the cover) — confirmed for `Article::card()` in `app/Models/Article.php`. If a given type nests these differently, `data_get` returns null (graceful — excerpt/cover just absent).

- [ ] **Step 4: Wire into the controllers**

In `EntryController@show`, before the `Inertia::render('Entry', [...])`, build previews from article content and add the prop:
```php
        $linkPreviews = $model instanceof Article
            ? (new \App\Actions\BuildLinkPreviews)($model->content)
            : [];
```
Add `'linkPreviews' => $linkPreviews,` to the `Inertia::render('Entry', [...])` array.

In `PageController@show`, add:
```php
        'linkPreviews' => (new \App\Actions\BuildLinkPreviews)($page->content),
```
to the `Inertia::render('Page', [...])` array.

- [ ] **Step 5: Run the test — expect pass**

Run: `php artisan test tests/Feature/LinkPreviewsTest.php --compact`
Expected: PASS.

- [ ] **Step 6: Pint + checkpoint**

Run: `vendor/bin/pint --dirty --format agent`. Commit to `feature/link-previews` (`BuildLinkPreviews.php`, both controllers, the test).

---

### Task 2: Frontend — card, layer, wiring + browser hover test

**Files:**
- Create: `resources/js/Components/Ui/LinkPreviewCard.vue`
- Create: `resources/js/Components/Ui/LinkPreviewLayer.vue`
- Modify: `resources/js/Components/Ui/BlockContent.vue` (accept `linkPreviews`, mount the layer)
- Modify: `resources/js/Components/Entry/ArticleDetail.vue` and `resources/js/Pages/Page.vue` (thread `linkPreviews` from page props into `BlockContent`)
- Modify: `composer.json` (add pest-plugin-browser), `package.json` (playwright)
- Test: `tests/Browser/LinkPreviewTest.php`

**Interfaces:**
- Consumes: the `linkPreviews` prop from Task 1 (`Entry`/`Page` page props).

- [ ] **Step 1: Write `LinkPreviewCard.vue`**

```vue
<script setup>
defineProps({
    // One preview: { url, title, excerpt, type, accent, date, cover }.
    preview: { type: Object, required: true },
});
</script>

<template>
    <article
        class="overflow-hidden rounded-lg border border-neutral-100 bg-neutral-0 shadow-card"
        :style="{ '--type-color': `var(--color-${preview.accent ?? preview.type})` }"
    >
        <img
            v-if="preview.cover"
            :src="preview.cover"
            alt=""
            loading="lazy"
            class="aspect-[16/9] w-full object-cover"
        >
        <div class="space-y-1.5 p-3">
            <div class="flex items-center gap-2 text-label uppercase tracking-wide">
                <span class="inline-block size-2 rounded-full" :style="{ backgroundColor: 'var(--type-color)' }" />
                <span class="text-neutral-500">{{ preview.type }}</span>
                <span v-if="preview.date" class="text-neutral-400">{{ preview.date }}</span>
            </div>
            <p class="text-section font-bold leading-tight text-neutral-900">{{ preview.title }}</p>
            <p v-if="preview.excerpt" class="line-clamp-2 text-caption text-neutral-600">{{ preview.excerpt }}</p>
        </div>
    </article>
</template>
```

- [ ] **Step 2: Write `LinkPreviewLayer.vue`**

```vue
<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import LinkPreviewCard from './LinkPreviewCard.vue';

const props = defineProps({
    // Map of href -> preview data (from server page props).
    previews: { type: Object, default: () => ({}) },
    // The content element whose internal <a> links get previews.
    container: { type: [Object, null], default: null },
});

const active = ref(null);
const pos = ref({ top: 0, left: 0, placement: 'top' });
const CARD_W = 320;
const GAP = 8;
const OPEN_DELAY = 350;
const CLOSE_DELAY = 150;

let openTimer = null;
let closeTimer = null;

// Touch devices have no hover; skip previews there entirely.
const canHover = typeof window !== 'undefined' && window.matchMedia('(hover: hover)').matches;

// Place the card centered over the link, clamped to the viewport, flipping
// below the link when there isn't room above.
function placeFor(el) {
    const rect = el.getBoundingClientRect();
    let left = rect.left + rect.width / 2 - CARD_W / 2;
    left = Math.max(GAP, Math.min(left, window.innerWidth - CARD_W - GAP));
    const placement = rect.top > 280 ? 'top' : 'bottom';
    const top = placement === 'top' ? rect.top - GAP : rect.bottom + GAP;
    return { top, left, placement };
}

function open(el, preview, immediate = false) {
    clearTimeout(closeTimer);
    const run = () => {
        pos.value = placeFor(el);
        active.value = preview;
    };
    if (immediate) {
        run();
    } else {
        openTimer = setTimeout(run, OPEN_DELAY);
    }
}

function scheduleClose() {
    clearTimeout(openTimer);
    closeTimer = setTimeout(() => {
        active.value = null;
    }, CLOSE_DELAY);
}

function keepOpen() {
    clearTimeout(closeTimer);
}

// Attach listeners to each internal <a> whose href has a preview.
function bind() {
    if (!canHover || !props.container) {
        return;
    }
    props.container.querySelectorAll('a[href]').forEach((link) => {
        const preview = props.previews[link.getAttribute('href')];
        if (!preview) {
            return;
        }
        link.addEventListener('mouseenter', () => open(link, preview));
        link.addEventListener('mouseleave', scheduleClose);
        link.addEventListener('focus', () => open(link, preview, true));
        link.addEventListener('blur', scheduleClose);
    });
}

onMounted(bind);
onBeforeUnmount(() => {
    clearTimeout(openTimer);
    clearTimeout(closeTimer);
});
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div
                v-if="active"
                class="fixed z-50 w-80 motion-reduce:transition-none"
                :style="{
                    top: `${pos.top}px`,
                    left: `${pos.left}px`,
                    transform: pos.placement === 'top' ? 'translateY(-100%)' : 'none',
                }"
                @mouseenter="keepOpen"
                @mouseleave="scheduleClose"
            >
                <LinkPreviewCard :preview="active" />
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.12s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
```
> `w-80` = 320px, matching `CARD_W`. If you change one, change both.

- [ ] **Step 3: Wire into `BlockContent.vue`**

Add a `linkPreviews` prop, put a template ref on the `.block-content` div, and render the layer as its last child:
```vue
// in <script setup>: add to defineProps
    linkPreviews: { type: Object, default: () => ({}) },
// add a ref
const contentEl = ref(null);
```
```vue
<template>
    <div v-if="nodes.length" ref="contentEl" class="block-content prose max-w-none text-body text-neutral-900">
        <PortableTextBlocks :nodes="nodes" @image-click="openImage" />
        <LinkPreviewLayer :previews="linkPreviews" :container="contentEl" />
    </div>
    ...
```
Import `LinkPreviewLayer` and ensure `ref` is imported (it already is). The layer binds on mount, after `PortableTextBlocks` has rendered the `<a>` links into `contentEl`.

- [ ] **Step 4: Thread `linkPreviews` from the pages**

- `resources/js/Pages/Entry.vue` passes the page's `linkPreviews` prop into the detail component; `resources/js/Components/Entry/ArticleDetail.vue` forwards it to `<BlockContent :link-previews="linkPreviews" ... />`. Add a `linkPreviews` prop to ArticleDetail (default `{}`) and to whatever passes content to BlockContent.
- `resources/js/Pages/Page.vue` passes its `linkPreviews` prop into its `<BlockContent :link-previews="linkPreviews" ... />`.
> Open each file to match the existing prop-passing style; add only the `linkPreviews` plumbing. If Page.vue renders content through a different component than BlockContent, thread the prop to wherever BlockContent is used.

- [ ] **Step 5: Build**

Run: `npm run build`
Expected: succeeds.

- [ ] **Step 6: Add the browser test dependency**

Run:
```bash
composer require --dev pestphp/pest-plugin-browser --no-interaction
npm install -D playwright && npx playwright install chromium
```

- [ ] **Step 7: Write the browser hover test**

Create `tests/Browser/LinkPreviewTest.php`. Bind Browser to `TestCase` + `RefreshDatabase` locally in the file (Pest.php only binds Feature). Seed the same source→target article pair as the feature test, visit the source article, hover the internal link, and assert the preview card appears with the target title:
```php
<?php

use App\Models\Article;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

it('shows a preview card when hovering an internal content link', function () {
    $target = Article::factory()->create([
        'title' => 'Target Post', 'excerpt' => 'A short summary.',
        'occurred_at' => '2026-05-01 10:00:00', 'slug' => 'target-post', 'published' => true,
    ]);
    $source = Article::factory()->create([
        'occurred_at' => '2026-05-02 10:00:00', 'slug' => 'source-post', 'published' => true,
        'content' => [[
            '_type' => 'block',
            'markDefs' => [['_key' => 'a', '_type' => 'link', 'href' => '/2026/05/01/target-post']],
            'children' => [['_type' => 'span', 'marks' => ['a'], 'text' => 'see the target post']],
        ]],
    ]);

    $page = visit('/2026/05/02/source-post');
    $page->assertSee('see the target post');
    // Hover the internal link and assert the teleported card shows the target title.
    // Use the plugin's hover API; confirm the exact method from the installed
    // pest-plugin-browser (see any existing Browser test / the plugin docs).
    $page->hover('a[href="/2026/05/01/target-post"]')
        ->assertSee('Target Post');
});
```
> The plugin's hover/assert API names may differ — confirm against the installed `pest-plugin-browser` version and adjust (e.g. the method to trigger a hover, and whether an explicit wait for the ~350ms open delay is needed). The test MUST actually pass, not be skipped. Align the seeded href with the factory's real `url_slug` (as in Task 1).

- [ ] **Step 8: Run the browser test**

Run: `php artisan test tests/Browser/LinkPreviewTest.php --compact`
Expected: PASS.

- [ ] **Step 9: Checkpoint** — `vendor/bin/pint --dirty --format agent` if any PHP changed; commit to `feature/link-previews` (the two new components, BlockContent, ArticleDetail, Page.vue, composer/package files, the browser test). Do NOT stage `public/twemoji/` or `.svg`.

---

## Self-Review

**Spec coverage:**
- Server-resolved previews in page props, deduped by href → Task 1 (`BuildLinkPreviews` + controller wiring). ✓
- Only internal + previewable + published; external/unresolvable omitted → `resolve()` + feature test asserting exactly that. ✓
- Card content (cover/title/excerpt/type chip/date) → `LinkPreviewCard.vue`. ✓
- Hidden reservoir at page end + hover/focus floating popover, Teleport + rect positioning + edge flip → `LinkPreviewLayer.vue`. ✓
- ~350ms open delay + grace close; keyboard focus opens; hovering card keeps open → timing logic in the layer. ✓
- Touch just navigates → `matchMedia('(hover: hover)')` guard. ✓
- reduced-motion → `motion-reduce:transition-none` + short fade. ✓
- Scope articles + pages → BlockContent wiring threaded from Entry/ArticleDetail and Page. ✓
- Feature test (props) + browser test (hover) + pest-plugin-browser dep → Tasks 1 & 2. ✓

**Placeholder scan:** Test seeding delegates the exact `url_slug` alignment to the implementer (repo-specific factory behaviour) with a concrete assertion target — genuine, not deferred logic. The browser hover API is confirmed against the installed plugin (version-specific) — a real verification step.

**Type/name consistency:** `BuildLinkPreviews::__invoke(?array): array` used identically in both controllers. Preview shape `{ url,title,excerpt,type,accent,date,cover }` produced by the action and consumed by `LinkPreviewCard`. `linkPreviews` prop name consistent across controllers → pages → ArticleDetail/Page → BlockContent → LinkPreviewLayer. `CARD_W`/`w-80` both 320px (noted).
