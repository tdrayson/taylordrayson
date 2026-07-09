# Codebase Review - 2026-07-08

A snapshot health check: standards compliance, non-standard folders, and a map of
our commands/scripts. Companion doc: the parked
[Checkins/Events/Trips thinking](2026-07-08-checkins-events-trips-parking.md).

**Overall: healthy.** Pint is clean, all validation goes through Form Requests, no
`env()` leaks outside config, Eloquent used almost everywhere. Issues below are small
and mostly housekeeping.

---

## 1. Standards compliance

### High
- **Stale doc** - `project/project-brief.md:157` still describes articles/notes as
  Editor.js block content (`app/Support/EditorJs.php`). That was removed; we use
  Portable Text now (`app/Support/PortableText.php`). Update or delete the brief.
- **Raw DB query** - `app/Http/Controllers/TagController.php:56` uses
  `DB::table('taggables')` (the only `DB::` in `app/`). CLAUDE.md says prefer
  `Model::query()`; route it through the morph/relationship instead.

### Medium
- **Em-dashes in CLI output** - real user-facing `—` in 5 commands
  (`FetchAirlineLogos`, `EnrichFlights`, `StravaPolylines`, `StravaPhotos`,
  `FoursquareImport`). This breaks our no-em-dash rule; swap for `-`.
- **7 untested commands** (of 22) - `og:clear`, `health:inspect`, `strava:polylines`,
  `events:maps`, `appearances:thumbnails`, `export:all`, `import:all`. Mostly thin
  wrappers/dev helpers; add tests or consciously accept.

### Low
- **Middot `·` separators** - used pervasively as a design separator (page titles,
  card subtitles, OG images, DesignSystem). You've said we don't use `·`, but it's
  currently a de-facto design token and there's **no rule for it in CLAUDE.md**.
  Decision needed: codify "no `·` / no `—`" in CLAUDE.md and sweep, or keep `·` as
  the separator. (See action list.)
- **No CHANGELOG.md** - the global rule suggests one; not required by CLAUDE.md.

---

## 2. Non-standard folders & stray files

| Folder | What's in it | Notes |
|---|---|---|
| `data/` | 12 source CSVs (import/seed data) | **~24 MB of binaries in git** - activities 10 MB, podcasts 5.4 MB, sleep 4.3 MB, calories 2.7 MB. Consider git-lfs or move to storage. |
| `docs/` | `data-types.md`, `portable-text.schema.json`, `superpowers/{plans,specs}` | Legit. 7 new plans/specs are **untracked**. |
| `project/` | `project-brief.md`, `design-summary.md` | Ad-hoc stash that **overlaps `docs/`**; `design-summary.md` has odd `0600` perms. Fold into `docs/` or remove. |
| `.claude/` `.cursor/` `.vscode/` | Editor/agent config | Fine (`.vscode` ignored). |

**Stray / suspect:**
- **`CLAUDE.md` and `AGENTS.md` are byte-identical duplicates** - keep one, symlink or delete the other.
- **Design notes live in 3 places** - root `design.md`, `project/design-summary.md`, `docs/superpowers/specs/*`. Consolidate.
- **Dirty working tree** - lots uncommitted (this session's a11y + event work, plus `events.csv`, `flights.csv`, `podcasts.csv`, `portable-text.schema.json`). Wants a commit checkpoint.
- `.DS_Store` on disk (ignored, harmless).

---

## 3. Commands & scripts

22 commands, attribute-based signatures. **Only one is scheduled:** `rovi:sync-food`
(every 15 min). Everything else is run manually. No dead/superseded commands; events
are an active feature, and there's no Statamic / control-panel / Editor.js code left.

**Recurring syncs** (safe to re-run)
`strava:sync` · `rovi:sync-food` · `podcast:sync` · `foursquare:import` ·
`health:heart_rate` · `health:sleep`

**One-time backfills** (historical/backdate - done their job)
`strava:backfill-descriptions` · `strava:backfill-timezones` · `strava:photos` ·
`strava:polylines` · `import:activity-descriptions` · `flights:enrich`

**Utility / maintenance**
`import:csv` · `import:all` · `export:csv` · `export:all` · `podcast:listens` ·
`appearances:thumbnails` · `airlines:logos` · `events:maps` · `health:inspect` · `og:clear`

**External API clients** (`app/Services/`): Strava, Foursquare, PocketCasts,
ThisWeekWith, Rovi, LogoStream, TimeApi, YouTube, StaticMap, Health/*.

**`data/` CSVs:** `import:all` covers 10 canonical files. `checkins.csv` and
`listens.csv` are managed by their own fetch commands. **Inconsistency:** `listens.csv`
is produced but has no `import:csv` type and isn't in `import:all`, so it can't be
re-imported through the standard path.

---

## 4. Suggested actions (quick punch list)

1. Commit the current working tree as a checkpoint (big uncommitted surface).
2. Decide the `·` / `—` rule: codify in CLAUDE.md + sweep, or accept `·`. Either way, fix the 5 CLI em-dashes.
3. De-duplicate docs: pick one home for design notes; resolve `CLAUDE.md`==`AGENTS.md`; fold `project/` into `docs/`.
4. Fix `project/project-brief.md` Editor.js → Portable Text.
5. Replace the raw `DB::table('taggables')` in `TagController`.
6. Move the big `data/*.csv` out of git history (lfs or storage) if repo size matters.
7. Give `listens.csv` a standard import path, or document why it's fetch-only.
8. Add tests for the 7 untested commands (or accept them as thin wrappers).
