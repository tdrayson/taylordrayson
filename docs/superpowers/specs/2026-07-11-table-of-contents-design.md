# Merge ContentToc + StoryToc into one TableOfContents — design

**Date:** 2026-07-11
**Branch:** `feature/table-of-contents` (off master)
**Status:** Approved, pending implementation plan

## Context

Two near-identical components render a table of contents with a desktop rail + a mobile pill/sheet and scroll-spy:

- `resources/js/Components/Ui/ContentToc.vue` (256 lines) — generic; collects headings via a configurable `selector`/`labelAttr` into `{ id, label }`. Used by `ArticleDetail.vue` (`<ContentToc v-if="headingCount >= 2" />`).
- `resources/js/Components/Story/StoryToc.vue` (235 lines) — story-specific; hardcoded `[data-story-chapter]`, collects `{ id, number, kicker }` from `data-number`/`data-kicker` (stamped by `StoryChapter.vue`), and renders numbered chapters. Used by the three story pages (`Flights.vue`, `Food.vue`, `Fuel.vue`) as `<StoryToc />`.

They share all mechanics (IntersectionObserver scroll-spy marking the section in the upper third as active, `useDialog` for the mobile sheet's focus-trap/Esc/scroll-lock, the pill trigger gated on scroll, the rail, scroll-to-and-close). They differ only in item shape (label vs number+kicker) and the active-item tint (`bg-accent-50` vs a hardcoded `bg-fuel/10`).

## Decision

One merged `resources/js/Components/Ui/TableOfContents.vue` owning the shared mechanics + chrome, with the item difference handled by **config props** (not slots). The active-item highlight uses a **single neutral surface colour** for every TOC (articles and stories) — no accent/data-type tint — which also removes StoryToc's existing quirk of tinting every story's TOC fuel-amber.

### Props

| Prop | Default | Purpose |
| --- | --- | --- |
| `selector` | `'[data-toc]'` | Elements to collect into the TOC |
| `labelAttr` | `'data-toc-label'` | Attribute holding each item's display label |
| `numberAttr` | `null` | When set, the sheet shows a leading number (story chapters); omitted for articles |

Items collected as `{ id, label, number }` (`number` present only when `numberAttr` is set and the attribute exists).

### Rendering (parity with the two originals, minus the accent)

- **Desktop rail:** the label, with the active item using the existing neutral treatment (`border-neutral-900 font-medium text-neutral-900`; inactive `border-transparent text-neutral-400 hover:text-neutral-700`).
- **Mobile sheet:** each row is `label`, or `[number] label` when `numberAttr` is set (number in a `text-label tnum text-neutral-400` span, as StoryToc does today). Active row background: a neutral surface (`bg-neutral-50`); hover `bg-neutral-25` (unchanged). No accent tint.
- Pill trigger, sheet header ("Contents" + close), transitions, `useDialog` wiring, and scroll-spy are lifted verbatim (they are already identical between the two).

## Migration

- `ArticleDetail.vue`: `import ContentToc` → `import TableOfContents`; `<ContentToc v-if="headingCount >= 2" />` → `<TableOfContents v-if="headingCount >= 2" />` (defaults match ContentToc's).
- `Flights.vue` / `Food.vue` / `Fuel.vue`: `import StoryToc` → `import TableOfContents`; `<StoryToc />` → `<TableOfContents selector="[data-story-chapter]" label-attr="data-kicker" number-attr="data-number" />`.
- Delete `resources/js/Components/Ui/ContentToc.vue` and `resources/js/Components/Story/StoryToc.vue` once no references remain.
- `StoryChapter.vue` is unchanged (still stamps `data-story-chapter`/`data-number`/`data-kicker`).

## Testing

- Behaviour parity is the bar: articles and stories must render the same rail + sheet, open the sheet from the pill, trap focus, Escape-close, scroll-spy the active item, and scroll-to-and-close on click. The only intended visual change is the active-item tint (now neutral, not fuel).
- `tests/Browser/ContentTocDialogTest.php` already opens the mobile sheet on `/stories/fuel` (now via `TableOfContents`) and asserts the rendered dialog + Escape close; keep it green (update the component reference if needed). Add/confirm an article-side case if practical (an article with >= 2 `data-toc` headings renders the rail).

## Out of scope

- Changing how headings/chapters are stamped (`StoryChapter.vue`, the article PT renderer's `data-toc` anchors).
- Any redesign of the rail/sheet beyond dropping the accent tint.

## Notes

- Config-props over scoped slots: only two consumers with a small, enumerable difference; props keep the callers terse and the component self-contained.
- Neutral active tint chosen for consistency across every TOC and to fix StoryToc's hardcoded fuel highlight.
