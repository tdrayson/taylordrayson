# Brief: macOS authoring app (Glaze)

**Date:** 2026-07-04

## The idea

A native macOS app for writing and managing the hand-authored content of a personal
lifelog. The lifelog already collects a lot automatically (workouts, sleep, flights,
places, listening, and so on), but the *written* parts — notes, articles, projects,
pages, events — deserve a calm, focused place to be created and edited. This app is
that place: a small, native, first-party CMS client for the human-written content.

It exists so authoring doesn't happen through a web admin or database, but through a
dedicated Mac app that feels quick to open, pleasant to write in, and gets out of the
way. Capture a passing thought in seconds; sit down and write a proper article when
you want to.

## What it's for

The app manages five kinds of content:

- **Notes** — short, plaintext, quick-capture thoughts. The fastest thing in the app;
  ideally a global hotkey away.
- **Articles** — long-form rich writing with a title, summary, tags, and a draft →
  published lifecycle.
- **Pages** — standalone rich pages addressed by a URL slug (e.g. an "about" page),
  not part of the timeline.
- **Projects** — things being built, with a short blurb, a longer rich write-up,
  links (site / GitHub), status, and tags.
- **Events** — things attended, with a place, date, and details.

Auto-synced data (workouts, sleep, flights, etc.) is deliberately **out of scope** —
that content isn't hand-written, so it doesn't belong in an authoring tool.

## How it connects

The app is a **thin client** over the lifelog's existing API. It doesn't own any data
or logic of its own — it reads and writes content over that API, and the server
stays the source of truth. That keeps the app simple and means the same content is
consistent everywhere it appears (the public site, this app, anything else that talks
to the API).

## Writing experience

The heart of the app is a good rich-text editor for articles, pages, and project
write-ups. Content is stored in an **open, editor-agnostic JSON format (Portable
Text)** rather than tied to one editor's output. That's a deliberate choice: it means
the writing surface can be anything (a native Mac editor, a Markdown mode, or an
embedded web editor) and the content stays portable and future-proof. The format is
the contract; the editor is a free choice.

Supported formatting is intentionally modest and blog-like: headings, bold/italic/
strikethrough, inline code, links, bullet and numbered lists, quotes, code blocks,
images, and dividers. Enough to write well, not a kitchen sink.

## The feel

- **Fast to capture, calm to write.** Opening the app and jotting a note should be
  instant; writing an article should feel focused and uncluttered.
- **Native and first-party.** It should feel like a Mac app, not a web page in a
  window.
- **Trustworthy.** Saving is reliable, drafts are safe, and nothing is lost.

## Out of scope (for now)

- Managing auto-synced data types.
- Offline-first sync.
- Anything the public website's rendering already handles.

## Companion document

Full data-model, API contract, and server prerequisites live in the design spec:
`docs/superpowers/specs/2026-07-04-macos-authoring-app-design.md`.
