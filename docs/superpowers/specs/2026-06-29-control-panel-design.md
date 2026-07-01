# Control Panel (`/cp`) Design

**Status:** Design / spec. Implementation will be planned and built phase by phase. This document records the full vision; **the first implementation plan is scoped to Phase 1 only**.

## Goal

A single-user, session-authenticated control panel at `/cp` for managing every timeline data type and the reference data behind them, embedded into the existing site shell so editing feels like part of the site rather than a separate backend.

## Guiding decisions (locked)

- **Database is the single source of truth.** The `data/*.csv` files remain the historical bootstrap/seed; production is populated from a database dump. The control panel reads and writes the database, not the CSVs.
- **Full CRUD** (list, create, edit, delete) for all timeline types plus reference data (airlines, airports, fuel stations).
- **Bespoke**, in the project's own Tailwind v4 + `cn()` design system. No Filament/Nova/Statamic.
- **Embedded shell:** the control panel reuses the existing `AppLayout`, made context-aware, rather than a wholly separate admin layout. The logged-out public site stays visually identical, guarded by tests.
- **Edit reached by navigation in Phase 1:** an Edit affordance on each entry page navigates to a dedicated edit view inside the same shell. The inline preview/edit toggle is deferred to Phase 3.
- **Shared, dual-mode field components:** each field is one component that can render in `display` mode (read-only) or `edit` mode (input), so the same components serve public entry pages and control-panel forms.

## Architecture overview

Four pillars:

1. **Auth** , Laravel session auth (the `web` guard, already the default), a single admin user, `auth` middleware on all `/cp/*` routes and the entry-edit routes, and the authenticated user shared to the front end via Inertia so the UI can reveal edit affordances.
2. **Config-driven resources** , a `CpResource` abstraction (one class per managed model) declaring label, icon, group, table columns, searchable columns, default sort, and field definitions (type, label, options, validation, provenance flag). A registry exposes them by slug. A single generic controller drives all CRUD.
3. **Field component system** , `resources/js/Components/Fields/` holding dual-mode field components dispatched by a `FieldRenderer`, wrapping the existing `Ui/` primitives.
4. **Context-aware shell** , `AppLayout` and its sidebar/topbar gain a control-panel mode: collections navigation in the sidebar, an account menu in the topbar. Public mode is the default and unchanged.

### Anchoring on existing code (no duplication)

- `app/Timeline/TypeRegistry.php` already maps each timeline `type` to its model, URL slug, and label. The control-panel resource layer **reuses these slugs** for `/cp` URLs (`/cp/activities`, `/cp/flights`, `/cp/food`, ...) and reads model/label/slug from the registry where possible, adding only the control-panel-specific concerns (columns, fields, validation). Reference resources (airlines, airports, fuel stations) are new entries the registry does not cover.
- The entry detail dispatcher `DETAIL_COMPONENTS` in `resources/js/Pages/Entry.vue` is the template for the field dispatcher (`FieldRenderer` keyed by field type).
- `resources/js/lib/cn.js` (clsx + tailwind-merge, font-size tokens) is the styling pattern every new component follows. Existing `Ui/` primitives (`Input`, `Textarea`, `Switch`, `Checkbox`, `Button`, `Card`, `Alert`, `Pill`) and `Search/StyledSelect.vue`, `Search/MultiSelect.vue` are wrapped, not reinvented.
- `app/Support/EditorJs.php` (plain-text extraction) and `Ui/BlockContent.vue` (read-only block renderer) are the display half of the eventual `EditorField`; the editor half (Phase 2) adds the `@editorjs/editorjs` dependency.

## Data model: synced vs manual

The field configuration and provenance behaviour differ by how a model is populated.

**Synced / imported (have provenance columns, written by a command):**

| Model | Provenance | Command |
|-------|-----------|---------|
| Activity | `platform_type='strava'`, `platform_id` | `strava:sync` |
| Checkin | `platform_type='swarm'`, `platform_id` | `foursquare:import` |
| Podcast | (This Week With API) | `podcast:sync` |
| Media | `platform_type`, `platform_id` | CSV import |
| Sleep | `source` | CSV import / shortcuts |

**Manual (no provenance columns, richly created):** Calorie, Flight, Article, Note, Event, Project, Fuel, Appearance.

**Reference:** Airline, Airport, FuelStation.

Synced models get **provenance locking** (Phase 3): auto-generated fields render read-only with a lock badge and an unlock toggle, plus a per-record re-sync action. Manual models are freely editable.

## Field-type taxonomy

Each `CpResource` field declares a `type`. The `FieldRenderer` dispatches to a component; every field component accepts `mode` (`display` | `edit`), `modelValue`, and the field definition.

- **Phase 1 types:** `text`, `number`, `textarea`, `date`, `datetime`, `boolean` (Switch or Checkbox), `select` (StyledSelect).
- **Phase 2 types:** `editor` (BlockContent display, EditorJS edit), `combobox` (async search, e.g. flight airport/airline pickers built on the `/search/suggest` pattern), `tags`, `json`.

Field components wrap the existing `Ui/` primitives so visual style is shared automatically. Display mode is built alongside edit mode in Phase 1 (it is cheap), but **public entry pages keep their bespoke layouts in Phase 1**; converting them to compose display-mode field components is incremental and lands with the toggle in Phase 3.

## Routing & URLs

All under an `auth`-protected group:

- `GET /cp` , dashboard (collection counts, recent edits).
- `GET /cp/login`, `POST /cp/login`, `POST /cp/logout` , session auth (login page is the only public `/cp` route).
- `GET /cp/{resource}` , index table.
- `GET /cp/{resource}/create`, `POST /cp/{resource}` , create.
- `GET /cp/{resource}/{id}/edit`, `PUT /cp/{resource}/{id}` , edit/update.
- `DELETE /cp/{resource}/{id}` , delete.

`{resource}` is the slug from the resource registry (timeline slugs reused from `TypeRegistry`, plus `airlines`, `airports`, `fuel-stations`).

Entry pages gain an Edit affordance (visible only when authenticated) linking to `/cp/{resource}/{id}/edit`.

## Shell integration

`AppLayout` gains a `mode` prop (`public` default, `cp`). In `cp` mode:

- The sidebar navigation renders the **collections list** grouped Timeline / Reference (driven by the resource registry shared via Inertia) instead of the public links.
- The topbar's right cluster becomes an **account menu** (name + logout) instead of search/status/time-jump.

`SidebarNav` and `AppTopbar` are refactored to accept their content (nav items / right cluster) so the same shell renders both modes. The default props reproduce the current public behaviour exactly; a test asserts the logged-out public site is unchanged.

## Authentication

- One admin user. Created/updated by an artisan command (using Laravel Prompts for the password), not by hardcoding credentials. No public registration.
- Login page (`Cp/Login.vue`) reuses the shell and `Ui/` primitives.
- `auth` middleware protects `/cp/*` (except the login routes) and the entry-edit routes.
- `HandleInertiaRequests::share()` adds a minimal `auth.user` (name, email) so the front end can conditionally show edit affordances and the account menu. When logged out this is `null` and nothing changes for public visitors.

## Validation

A single generic `app/Http/Requests/Cp/ResourceRequest` resolves its rules from the active resource definition (the field definitions own their validation rules and messages), so one source drives both the form UI and server validation. No per-resource request classes.

## Phasing

**Phase 1 , Foundation + generic CRUD (first implementation plan):**

1. Auth: login/logout, single-admin artisan command, `auth` middleware, shared `auth.user`.
2. Context-aware shell: `AppLayout`/sidebar/topbar `cp` mode; public unchanged (test-guarded).
3. Resource config layer: `CpResource` + registry for the 13 timeline types and 3 reference types (columns, searchable, sort, fields, validation, group), reusing `TypeRegistry` slugs.
4. Generic `ResourceController` (index/create/store/edit/update/destroy) + routes.
5. Field foundation: `FieldRenderer` + Phase-1 field components (text/number/textarea/date/datetime/boolean/select), display + edit modes, wrapping `Ui/` primitives.
6. Generic pages: `Cp/Dashboard`, `Cp/Resource/Index` (table, search, sort, pagination, delete), `Cp/Resource/Form` (create/edit).
7. Entry edit affordance: Edit button on entry pages when authenticated, linking to the CP edit form.
8. Tests: auth gating, representative CRUD + validation, public-site-unchanged-when-logged-out.

**Phase 2 , Rich fields:** `editor` (EditorJS, adds dependency) and `combobox` (async airport/airline pickers) field components; richer create/edit for Flight, Article, Note, and the other manual types.

**Phase 3 , Provenance & inline editing:** locking + unlock for synced fields; per-record re-sync/regenerate actions; dual-mode display rendering on entry pages and the inline preview/edit toggle (incrementally per type).

## Testing strategy

- Feature tests (Pest) for auth gating, each generic CRUD action against representative resources (a manual one, a synced one), and validation failures.
- A test asserting the logged-out public navigation and shell render identically after the shell refactor.
- Front-end field components covered by the existing smoke-test approach plus targeted browser tests for the form flow if warranted.

## Out of scope

- Multi-user, roles, or permissions (single user only).
- Editing the `data/*.csv` seeds through the panel (database is the source of truth).
- Bulk import/export UI (handled by existing artisan commands).
