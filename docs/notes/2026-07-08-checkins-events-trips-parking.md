# Checkins / Events / Trips - Parked Thinking

> Date: 2026-07-08. A brain-dump so we can set this aside and pick it up later.
> **Nothing here is built.** Supersedes the full-merge spec
> `docs/superpowers/specs/2026-07-07-events-into-checkins-design.md` (that spec
> assumed events fold entirely into checkins; we've since moved to a 3-type model).

## The question we were chewing on

Are my "events" actually just checkins? IndieWeb events (h-event) are things you
publish for *others* to RSVP to. Mine are a private log of things I attended, no
RSVP, so they don't fit h-event. That made merging events into checkins tempting.
But two reference sites showed the boundary depends on whether you keep checkins:
gregorlove logs everything as "events" (no checkins); calumryan's events are only
conferences (he *has* checkins). So the split is a personal-taxonomy choice.

## Where it landed: three complementary types

- **Checkin** - a single point in time. "Where are you / what are you up to."
  Ambient presence *plus* the private-feeling outings: theatre, shows, matches,
  festivals. Grouped by `category` (not a multi-day span). ~2,381 exist in Swarm
  to import. Pull the Swarm checkin URL when there is one.
- **Event** - a public, participatory gathering: conferences, conventions,
  trade shows, WordCamps. Single OR multi-day (Parecki's IBC Amsterdam is a 4-day
  event). This is the *existing* event type; keep it and its multi-day work.
- **Trip** - a multi-day container that groups flights + checkins + events, e.g.
  the ~3-week America trip with multiple flights. Parecki-style. Future.

Nesting: a Trip contains Events and Checkins; an Event and a Checkin can stand
alone. Checkin = point, Event = public occasion, Trip = span.

## Reclassification (current `event` rows)

- **Stay events:** conference, convention, exhibition, talk.
- **Become checkins:** theatre, musical, show, sport, comedy, festival, immersive.

## Recommended sequencing

1. **Checkins first** - Swarm import + dedupe, reclassify shows/matches/festivals
   over from events. Rename the mislabelled `places`/`Places` taxonomy to
   `checkins`/`Checkins`. Use the event purple accent for checkins.
2. **Keep Events** - no teardown; retain the multi-day `ends_at` work on
   `TimelineEntry` (this is the key reversal from the full-merge spec, which said
   to strip `ends_at`/mayor and undo the multi-day timeline changes).
3. **Trips later** - its own type + a grouping UI; note as a future phase.

## Swarm import + dedupe (unchanged from the merge spec, still valid)

- Fetch all checkins via `foursquare:import` (API is the real source; the WP CSV
  export is partial/stale). Keep venue, address, lat-lon, occurred_at,
  description, source_id (→ Swarm permalink), one photo. Drop score/coins.
- Dedupe against reclassified event-checkins: same calendar day + same venue
  (name or <~150 m). On a match the **rich record wins**; graft the Swarm URL and
  any missing photo. Uncertain matches go to a manual review list, not auto-merge.

## Open questions to resolve when we resume

- Do reclassified shows/matches keep their rich detail (map, gallery, note) as
  checkins? (Yes in intent; means CheckinDetail must absorb the event view.)
- Trip data model: does a Trip own its members by date range, or by explicit
  membership? Parecki uses date-range membership.
- Naming for the checkin category taxonomy values (headline-cased categories).
- Whether "notable" occasions get a curated/featured list later (out of scope now).
