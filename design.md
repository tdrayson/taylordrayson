# Design System — Taylor Drayson

## Overview

A personal **lifelog** built as a flat, borderless, photography-and-data canvas. The base is **pure white** (`{colors.canvas}` — #ffffff) with deep near-black ink (`{colors.ink}` — #222222) for headlines and body, and a single voltage of **Rausch** (`{colors.accent}` — #ff385c) carrying every emphasis moment — new PBs, active nav icons, chart lines, the streak flame, inline links. There is no secondary brand colour; the palette is otherwise white canvas, a three-tier ink scale, and two hairline greys.

Type is a two-family pairing: **Bricolage Grotesque** (a variable grotesque) for every display moment, and **Inter** for body, meta, and labels. Unlike a photography-led marketplace, this system trusts **type and data** for visual weight — display headings run loud and tightly tracked (the day title is 44px / 800 at -0.035em tracking; the year title scales to 84px), while body sits calm at 15px Inter. Numbers render in **tabular figures** everywhere (`.tnum`) so columns of stats align.

The shape language is **soft but restrained**. Cards and the avatar clip at 14px (`{rounded.lg}`), calendar cells and the zoom switcher at 11px (`{rounded.md}`), nav items at 9px, timeline nodes and the avatar are circles, heatmap cells are 3px. Depth comes from **one shadow tier and corner clipping — never borders on cards**; the only 1px rules are hairline dividers between rows and sections.

The signature layout is an **app shell**: a fixed 264px left sidebar (avatar, name, bio, social, nav, streak) beside a scrolling main column that caps at ~860px, fronted by a thin topbar (breadcrumb + live clock / weather / location). The main content reflows through three **zoom levels** — Day, Month, Year — switched by a segmented control.

**Key Characteristics:**
- Single accent colour: `{colors.accent}` (#ff385c — "Rausch") used scarcely — most surfaces are white + ink, with accent reserved for PBs, active icons, chart strokes, and the streak flame.
- Two-family type: `Bricolage Grotesque` for display (weights 700–800, tight tracking), `Inter` for body/meta/labels (400–600).
- Flat & borderless: cards never carry borders — depth is one shadow tier (`{elevation.card}`) plus 14px corner clipping. 1px rules appear only as row/section dividers.
- App-shell layout: sticky 264px sidebar + scrolling ~860px content column + thin topbar with a live clock.
- Three zoom levels: Day (timeline feed), Month (calendar grid), Year (heatmap + rich stats), toggled by a segmented `{component.zoom}` control.
- Data-first: number strips, bar charts, line/area SVG charts, donut macros, a GitHub-style heatmap, and ranked tables — all flat, borderless, tabular-figure.
- 13 data-type accent colours: a per-type palette (activity, sleep, food, media, event, appearance, podcast, flight, checkin, fuel, project, article, note) used for type identification in feeds and calendars.

## Colors

### Brand & Accent
- **Rausch** (`{colors.accent}` — #ff385c): The single brand colour. New-PB highlights, active nav icons, chart strokes/markers, the streak flame, heatmap peak, inline link hover.
- **Rausch Active** (`{colors.accent-active}` — #e00b41): Pressed state.
- **Rausch Soft** (`{colors.accent-soft}` — #ffd1da): Disabled / tint.

### Surface
- **Canvas** (`{colors.canvas}` — #ffffff): The default page floor for every view. No dark mode.
- **Surface** (`{colors.surface}` — #f7f7f7): The lightest fill — sidebar nav hover, zoom track, calendar cells, bar tracks, timeline node circles, photo placeholders.

### Hairlines
- **Line** (`{colors.line}` — #dddddd): Default 1px tone — breadcrumb separators, the heaviest dividers.
- **Line 2** (`{colors.line-2}` — #ebebeb): Lighter divider — the timeline spine, row borders, section rules, chart gridlines.

### Text (Ink scale)
- **Ink** (`{colors.ink}` — #222222): Dominant text — display headings, primary body, active nav, stat values. Never pure black.
- **Ink 2** (`{colors.ink-2}` — #3f3f3f): Item meta and secondary running text.
- **Ink 3** (`{colors.ink-3}` — #6a6a6a): Labels, eyebrows, sub-text, inactive nav, axis text.

### Heatmap Ramp
A four-step Rausch ramp over the surface base, for the year activity heatmap (quiet → busy):
- `{colors.surface}` #f7f7f7 → `{colors.heat-1}` #ffd7df → `{colors.heat-2}` #ff9db1 → `{colors.heat-3}` #ff6383 → `{colors.heat-4}` #ff385c.

### Data-Type Accents (13)
A per-type colour used to identify entry types in feeds, calendars, and legends. Stored as HSL:
- **activity** 152 55% 40% · **sleep** 245 50% 58% · **food** 25 90% 52% · **media** 340 60% 52% · **event** 270 55% 52% · **appearance** 320 55% 50% · **podcast** 210 65% 48% · **flight** 200 75% 50% · **checkin** 170 55% 40% · **fuel** 45 85% 48% · **project** 185 60% 42% · **article** 215 18% 48% · **note** 36 40% 50%.

### Sleep Stage Colours
For hypnogram / sleep breakdowns: **awake** 0 70% 72% · **rem** 210 60% 58% · **light** 210 50% 75% · **deep** 245 50% 52%.

## Typography

### Font Family
- **Display:** `Bricolage Grotesque` (variable, opsz 12–96, weight 500–800), falling back to `Inter, sans-serif`. Carries every heading, stat value, and title.
- **Body:** `Inter` (400 / 500 / 600), falling back to `-apple-system, system-ui, Segoe UI, Roboto, sans-serif`. Body, meta, labels, captions.

### Hierarchy

| Token | Size | Weight | Tracking | Family | Use |
|---|---|---|---|---|---|
| `{typography.year-title}` | clamp(58–84px) | 800 | -0.035em | Display | Year view h1 ("2026") |
| `{typography.view-title}` | 44px | 800 | -0.035em | Display | Day / Month view h1 |
| `{typography.num-big}` | 46px | 800 | -0.03em | Display | Year hero number strip |
| `{typography.num}` | 31px | 800 | -0.03em | Display | Day/Month number strip values |
| `{typography.profile-name}` | 24px | 800 | -0.03em | Display | Sidebar name |
| `{typography.item-title}` | 23px | 700 | -0.025em | Display | Timeline feed entry title |
| `{typography.streak}` | 23px | 800 | -0.02em | Display | Streak counter |
| `{typography.section-h}` | 18px | 700 | -0.02em | Display | Section heading ("Movement") |
| `{typography.body}` | 15px | 400 | 0 | Body | Default running text |
| `{typography.nav}` | 14.5px | 500–600 | 0 | Body | Sidebar nav label |
| `{typography.meta}` | 14px | 400 | 0 | Body | Item meta, sub-text |
| `{typography.bar}` | 12.5px | 400 | 0 | Body | Bar labels / values (tnum) |
| `{typography.label}` | 11px | 600 | 0.07em | Body | Number-strip labels (uppercase) |
| `{typography.eyebrow}` | 10.5px | 700 | 0.1em | Body | Eyebrows / section meta (uppercase) |
| `{typography.item-type}` | 11px | 600 | 0.1em | Body | Timeline type label (uppercase) |

### Principles
Display weights stay loud and tight — the opposite of a photography-led system. Headings carry hierarchy through size + 700/800 weight + negative tracking. The single quietest family is Inter at 15px for body. Every numeric value uses `font-variant-numeric: tabular-nums` so stat columns and clocks stay aligned.

### Note on Font Substitutes
If Bricolage Grotesque is unavailable, any tightly-tracked grotesque (e.g. Space Grotesk) substitutes; keep the -0.02 to -0.035em display tracking. Inter is already the open body default.

## Layout

### Spacing System
- **Base unit:** 4px (with occasional 2px micro-step in component internals).
- **Tokens:** 4 · 8 · 12 · 16 · 24 · 30 · 40 · 54px.
- **Sidebar:** 264px fixed width, 30px / 24px padding, sticky full-height.
- **Content column:** max-width 860px, centered, 30px / 40px padding with 120px bottom gutter.
- **Section rhythm:** section headings at ~42–48px top margin; feed entries 38px apart; number strips 26px / 46px gap.

### Grid & Container
- **App shell:** flex row — `{component.sidebar}` (264px, sticky) + `{component.main}` (fluid, min-width 0).
- **Day:** vertical timeline feed with a 54px left gutter for the node spine.
- **Month:** 7-column calendar grid, 7px gap, ~96px min cell height.
- **Year:** GitHub-style heatmap (7-row column-flow grid of 12px cells) + two-column (`grid2`) stat blocks.
- **Sub-grids:** `grid2` is a 2-column 1fr/1fr at 34px gap; photo grids run 4-up; film posters 6-up; city/place tables single-column ranked rows.

### Whitespace Philosophy
Generous vertical rhythm between sections (42–48px) but dense, borderless data blocks within them. The page reads as "calm column of stacked data cards" — no boxes, no shadows competing for attention, just type, hairline rules, and the occasional Rausch highlight.

## Elevation

Essentially **one shadow tier** plus the flat baseline.
- **Flat (no shadow):** 95% of surfaces — sidebar, feed, stat blocks, tables. Depth comes from corner clipping and hairline dividers.
- **Card float** (`{elevation.card}`): `rgba(0,0,0,0.02) 0 0 0 1px, rgba(0,0,0,0.05) 0 4px 14px 0` — applied to calendar cells on hover and the zoom-active button. The single shadow definition in the system.
- There are no progressive elevation tiers.

## Components

### Sidebar (`sidebar`)
264px sticky column: circular 54px avatar, 24px / 800 display name, 13px ink-3 bio, social icon row, vertical nav, and a pinned streak block at the foot.

**`nav-item`** — icon + label, 9px radius, 14.5px medium. Inactive: ink-2 label, ink-3 icon. Active: surface fill, ink label at 600, **accent** icon. Hover: surface fill.

**`streak`** — eyebrow label above a flame icon + 23px / 800 tabular number + unit.

### Topbar (`topbar`)
Thin bar: left breadcrumb (`Home / 2026 / June / 21`, ink-3 with line separators) and a right status cluster — live clock, weather, location — each a 13px ink-2 item with a 15px ink-3 icon.

### Zoom Switcher (`zoom`)
Segmented control on a surface track, 11px radius, 3px padding. Active button: canvas fill, ink label, subtle 1px-3 shadow. Inactive: ink-3 label. Toggles Day / Month / Year views.

### View Header (`view-header`)
Title block (44px display title + ink-3 sub-line) with a right-aligned prev/next nav pair (13px / 600 ink-3, chevron icons, accent on hover). The Year variant adds an eyebrow and scales the title to clamp(58–84px).

### Number Strip (`number-strip`)
Horizontal wrap of stat cells: 31px / 800 display value (optional smaller unit suffix in ink-3) over an 11px uppercase ink-3 label. The Year hero uses the 46px `numbers--big` variant.

### Timeline Feed (`feed`) — Day view
Vertical list with a 2px line-2 spine in a 54px gutter. Each `item`:
- a 36px circular `item__node` (surface fill, type icon) pinned in the gutter,
- an uppercase 11px ink-3 type label + right-floated time,
- a 23px / 700 display title,
- a 14px ink-2 meta line (bold ink for key figures, **accent** for PBs),
- optional media (hypnogram bars, run-route SVG, photo strip).

### Calendar (`calendar`) — Month view
7-column grid of `cal__cell`: surface fill, 11px radius, day number + sleep hours + a wrap of small type icons. `today` gets a 2px accent outline; cells lift with `{elevation.card}` on hover.

### Heatmap (`heatmap`) — Year view
Column-flow 7-row grid of 12px / 3px-radius cells over the Rausch ramp, with a month axis and a quiet→busy legend.

### Bars (`bars`)
Borderless horizontal bars: `[label | track | value]` grid. Track is surface, fill is ink (or **accent** to highlight one row), 4px radius, 13px tall.

### Line / Area Charts (`chart`)
Inline SVG: line-2 gridlines, ink polyline/path stroke, a dashed **accent** average line, 9px ink-3 axis text. Used for sleep-hours and run-route trends.

### Donut (`macro`)
A stroke-dasharray donut (ink + accent + grey segments) with a centred display number and unit, paired with a legend list of swatch · label · value · percent rows.

### Ranked Table (`ptable`)
Borderless rows divided by line-2: `[rank | name + sub | value]`. Name 14.5px / 600, sub 12px ink-3 (italic locale), value 16px display. Used for most-visited places, coffee logs, etc.

### Poster Gallery (`gallery`)
6-up grid of 5:6 posters with a bottom gradient scrim and white title + rating overlay. Used for films.

### Highlights (`highlights`)
Key/value rows divided by line-2: icon + label on the left, display value + ink-3 sub-detail on the right.

## Responsive Behavior

| Name | Width | Key Changes |
|---|---|---|
| Mobile | < 880px | App shell stacks: sidebar becomes a static top strip (streak hidden); content padding tightens to 20/18px; view title drops to 32px; calendar gap/cell shrink and per-cell sleep hours hide; `grid2` / photo / poster grids drop columns. |
| Desktop | ≥ 880px | Full app shell: sticky 264px sidebar beside the scrolling 860px content column; full Day/Month/Year layouts with multi-column stat blocks. |

### Touch Targets
- Sidebar nav items and zoom buttons are generous tap targets (≥36px tall).
- Calendar cells are ~96px tall blocks.
- Timeline nodes are 36px circles.

### Collapsing Strategy
- The sidebar switches from sticky side-rail to a static top block below 880px.
- Multi-column stat blocks (`grid2`, photo grids, poster gallery) cleanly reduce column counts — never reflow within a row.
- The Year heatmap scrolls horizontally rather than wrapping.

## Known Gaps

- **Dark mode:** none — the system is light-only by design.
- **Hover states:** documented for nav, calendar cells, and links (accent); other hover treatments are intentionally minimal.
- **Loading / empty states:** not yet specified — to be defined as the Inertia/Vue pages gain live data.
- **Form controls:** the reference views are read-only dashboards; input, select, and validation styling are not yet part of the system.
- **Real charts:** the reference uses hand-built static SVG; a charting approach (library vs. bespoke SVG components) is still open.
