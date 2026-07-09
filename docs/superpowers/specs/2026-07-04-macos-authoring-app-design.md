# macOS authoring app (Glaze) — design

**Date:** 2026-07-04
**Status:** Approved (brainstorm) — ready for implementation plan

## Summary

A native macOS app (built with Glaze) for authoring the hand-written content types
of the lifelog. It is a thin client over the existing `/api/v1` surface: it never
touches the database or Eloquent directly, it makes authenticated HTTP calls
against the shared action layer that already backs the web frontend and MCP server.

Scope is deliberately **authoring only**. Auto-synced data types (activity, sleep,
calorie, flight, checkin, fuel, media, podcast) are out of scope — they arrive from
Strava / Foursquare / Health export / PocketCasts and are not hand-edited here.

## Goals

- Author, edit, and delete the five hand-written content types from a native Mac app.
- A rich-text editing experience for long-form content, stored in an open,
  editor-agnostic JSON format so the editing surface is a free choice.
- Reuse the established `/api/v1` vertical pattern — no bespoke backend for the app.

## Non-goals

- No manual creation/editing of auto-synced types.
- No offline-first sync engine. The app is online, request/response against the API.
- No SSR or Node dependency (consistent with the site's client-only Inertia/Vue model).

## Architecture

The Mac app is another client of `/api/v1`, exactly like the web frontend and the
MCP server. The proven vertical pattern (reference implementation: Notes) is:

```
Model → Actions (Create/Update/Delete) → FormRequest (validation)
      → Api/V1/Controller (apiResource) → V1/Resource (JSON shape)
      behind middleware('api.token')
```

Every app action maps 1:1 to `POST` / `PUT` / `DELETE` on a resource. The API is the
contract; the app holds no business logic.

**Auth:** the app stores an API token in the macOS Keychain (never plaintext) and
sends `Authorization: Bearer <token>` on every request, satisfying the `api.token`
middleware. A Settings screen manages the token and the base URL.

## Data types

Fields below are the real columns. `created_at` / `updated_at` are Laravel
timestamps; `occurred_at` is the author-controlled published/display date.

### Note (API already exists)
- `content` — plaintext, required
- `occurred_at` — defaults to now

Quick-capture surface. Should be the fastest thing in the app (ideally a global
hotkey → type → save). No title, slug, or draft.

### Article
- `title` — string, required
- `slug` — string, auto from title, editable, unique
- `excerpt` — text, nullable (summary / social preview)
- `content` — **Portable Text** (see Rich text)
- `tags` — string[] (JSON array, autocomplete from existing)
- `draft` — boolean (draft ↔ published toggle)
- `occurred_at` — datetime (publish/display date on the timeline)

### Page (CMS, not timeline)
- `title` — string
- `slug` — string, **unique** (this is the route, e.g. `/about`)
- `excerpt` — text, nullable
- `content` — **Portable Text**, nullable
- `draft` — boolean

No `occurred_at` — pages are slug-routed, not part of the feed. Present them in a
separate sidebar section from the timeline authoring types.

### Project
- `title` / `slug` — string
- `description` — text, plain one-liner (card blurb) — stays plain
- `long_description` — **Portable Text** (migrated from `text`; reuses the article editor)
- `url` / `github_url` — string
- `status` — string enum on the app side (e.g. active / shipped / archived)
- `featured` — boolean (pin to top)
- `tags` — string[]
- `started_at` — datetime
- `occurred_at` — datetime

### Event
- `type` — string (category: gig, conference…)
- `name` — string
- `venue_name` / `address` / `city` / `country` — string
- `latitude` / `longitude` — decimal (map pin)
- `ticket_price` — decimal
- `notes` — text, plain
- `occurred_at` — datetime

## Rich text: Portable Text

**Storage format is Portable Text** — an open, editor-agnostic spec (an array of
block objects with marks and custom block types). It is the contract; any editing
surface (native macOS, markdown-to-PT, or an embedded web editor) is acceptable as
long as it emits valid Portable Text conforming to the node whitelist below.

Used by: Article `content`, Page `content`, Project `long_description`.

### Node whitelist (validated server-side at the API boundary)

- **Blocks:** `paragraph`, `heading` (h2–h4), `blockquote`, `code` (code block),
  `image` (references an Attachment), horizontal rule
- **Lists:** bullet, numbered
- **Marks:** `strong`, `em`, `code`, `strike`, `link`

The whitelist is the linchpin that makes "the source editor doesn't matter" true and
safe: a server-side validation rule (reused across article/page/project FormRequests)
rejects any document containing off-spec blocks or marks, so no editor can corrupt
another editor's documents.

### Editor options (Mac app, free choice)

- **Native macOS editor** — serialize editor state to Portable Text on save, parse
  back on load.
- **Markdown typing surface** — parse markdown → Portable Text on save, serialize
  Portable Text → markdown on load. Fast to type; lossy for custom blocks (an image
  with attachment metadata does not round-trip through plain markdown). Acceptable
  if articles are mostly prose.
- **Embedded web editor** — a WKWebView hosting a Portable Text-emitting editor.
  Guarantees schema fidelity if the same component is shared with the web frontend.

The choice can be deferred; the storage format and validation are what matter.

## Cross-cutting concerns

- **Media / image upload** — `image` blocks reference the R2 + Spatie `attachments`
  library. Requires a `/api/v1` upload endpoint returning a URL the editor inserts.
  Prerequisite for the rich editor.
- **Tags** — free-form JSON arrays on Article/Project. Expose a "distinct tags per
  type" endpoint for autocomplete (`TypeRegistry` already computes these).
- **Slugs** — generated client-side from the title, but the server is the source of
  truth for uniqueness (page slugs are `unique`). The app handles the 422 collision
  gracefully.
- **Drafts** — Article and Page have a `draft` flag; the app needs a clear
  draft/publish control and should list drafts distinctly.
- **Timezone** — per the site convention, `occurred_at` is stored as local wall-clock
  plus a per-entry timezone (server-computed, no client `new Date()` tz math). The
  date/time picker sends wall-clock + tz string, not a UTC instant. **Verify the
  current column shape before wiring** (the base migration stored a bare timestamp).
- **Response envelope** — every vertical returns a Laravel Resource (`{ data: … }`,
  paginated collections), matching Notes, so the app has a single decoder.

## Timestamps

All three already exist; the work is exposing them in the Resources (currently
`NoteResource` only returns created/updated):

- `created_at` → created
- `updated_at` → last updated (bumps on every save)
- `occurred_at` → published / display date (author-controlled)

No separate `published_at` (YAGNI — "published" == `occurred_at`).

## Current state (verified 2026-07-04)

- **Nothing to remove.** TipTap was never installed (absent from `package.json` and
  `composer.json`). Editor.js *is* in `package.json` but is entirely unused (zero
  references in `resources/js`) — dead dependencies, an optional `npm uninstall`, and
  a frontend concern with no bearing on the app.
- **Zero migration cost.** Articles and Pages have 0 rows, so adopting Portable Text
  is a clean greenfield decision — no content to convert.
- **Only `notes` is live.** `notes` + `/api/v1/ping` exist behind `api.token`. The
  other four verticals, upload, and tags endpoints are all still to build.
- **Token is env-based** (single user). The Mac app just uses that token. Revisit only
  if per-device revocable tokens are ever wanted.

## Server prerequisites (the bulk of the work)

The Mac app is thin once the API exists. Server-side, copying the Notes vertical:

1. **Article / Page / Project / Event verticals** — Model tweak + Actions
   (Create/Update/Delete) + FormRequest + Api/V1 Controller (apiResource) + V1 Resource.
2. **Portable Text validation rule** — enforces the node whitelist; reused across
   article/page/project FormRequests.
3. **`long_description` migration** — Project `long_description` from `text` → JSON
   (Portable Text); cast to `array` on the model. Trivial (0 rows to convert).
4. **Media upload endpoint** — R2 + Attachment; returns a URL for `image` blocks.
5. **Tags-list endpoint** — distinct tags per type for autocomplete.
6. **Resource timestamp exposure** — created/updated/occurred on every authoring Resource.

## Build order

1. Server: build the article / project / page / event verticals.
2. Server: Portable Text validation rule + `long_description` migration.
3. Server: media-upload endpoint + tags-list endpoint.
4. Mac app: token/settings + auth, then Notes (proves the pipe), then the rich
   editor, then the remaining types.

## Risks

- **Rich-text rendering is not yet built** — no Portable Text tooling exists on
  either side. The public Vue frontend must gain a Portable Text renderer (e.g. a
  `@portabletext`-style renderer) so authored content actually displays. This is
  frontend work, separate from the Mac app, but it closes the loop. (There is no
  legacy format to migrate away from — see Current state.)
- **Timezone column assumption** — confirm the stored shape of `occurred_at` before
  building the date/time picker.
- **Glaze rendering model unknown** — decides whether the editor is native, embedded
  web, or markdown. Deferred; does not block the server work.
