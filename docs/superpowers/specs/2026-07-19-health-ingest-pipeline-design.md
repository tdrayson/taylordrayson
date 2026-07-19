# Health ingest pipeline (GitHub #64)

Date: 2026-07-19
Status: Approved, ready for implementation planning

## Problem

`POST /api/health/ingest` (`HealthExportController@store`) currently captures each raw Health Auto Export (HAE) payload plus a summary to `storage/app/private/health/incoming`, writing synchronously in the request. Processing into models happens later in separate commands (`health:sleep`, `health:heart_rate`) that re-read those raw files from disk.

We want one metric-routed endpoint that processes on a queue and discards raw, so the request returns fast and the database is the single source of truth.

## Decisions (locked)

- **One endpoint, route by metric name.** HAE payloads are self-describing (`metric.name` = `sleep_analysis`, `heart_rate`, and later `activity_rings`, `steps`). A single handler dispatches a job; the job routes each metric to its processor.
- **Process-and-discard, log a summary.** The raw body is never persisted. On every ingest a compact structural summary (metric names, counts, first sample) is written to the log for debugging. There is no raw replay for successful ingests. The DB-only maintenance modes still work off stored rows.
- **Return fast.** The endpoint authenticates, does minimal validation, logs the summary, dispatches `ProcessHealthExport`, and returns 200. No heavy work inline (HAE has request timeouts).
- **This PR migrates sleep and HR onto the pipeline.** Both existing processors move from disk-reading to payload-passing, proving the pipeline end-to-end and leaving the app on a single ingest path. Vitals (#67) later plugs a new processor into the finished router.

## Architecture

Processing logic is extracted into standalone processor classes that both the queued job and the artisan commands call. This matches the existing `app/Support/Health/` pattern (`SleepAggregator`, `SleepScore`) and keeps the job and commands thin. (Rejected: job calling `Artisan::call`, which couples the queue to the CLI and cannot cleanly hand over a decoded payload; and a fat job with inline processing, which orphans the commands' maintenance modes.)

### Components

| Piece | Responsibility |
|---|---|
| `HealthExportController@store` | Auth, decode JSON, minimal validation, log the compact summary, dispatch `ProcessHealthExport`, return 200. No disk write. |
| `HealthExportController@ping` | Simplified reachability check (drops the capture count). |
| `ProcessHealthExport` (new `app/Jobs/`, `ShouldQueue`) | Receives the decoded payload, iterates `data.metrics`, routes each metric by name to a processor. Unknown metric names are logged and skipped (forward-compatible). |
| `SleepProcessor` (`app/Support/Health/`) | The DB-upsert + CSV-mirror logic lifted out of `ImportHealthSleep`. Reuses `SleepAggregator` and `SleepScore`. |
| `HeartRateProcessor` (`app/Support/Health/`) | The DB-upsert + CSV-mirror logic lifted out of `ImportHealthHeartRate`. Reuses the existing HR window matcher. |
| `HealthPayloadSummary` (small helper) | The existing `summarise()` logic relocated, used for the ingest log line. |
| `health:sleep` / `health:heart_rate` | Thin wrappers: a `--file=<path>` dev affordance to run a processor on a supplied payload, plus sleep's DB-only `--resplit`/`--redate`/`--score`. Disk-reading (`payloads()`/`collectSegments()`) removed. Bare invocation with no `--file` and no maintenance flag prints guidance. |

The queue connection is already `database`; no infra change. Assumes a `queue:work`/`queue:listen` worker runs (dev: `composer run dev` already runs `queue:listen`; tests: `phpunit.xml` sets `QUEUE_CONNECTION=sync` so jobs run inline; prod: xCloud worker).

## Data flow

```
HAE POST /api/health/ingest
  -> auth (existing token check)
  -> decode JSON, minimal validation
  -> Log::info(HealthPayloadSummary)      # metric names, counts, first sample
  -> dispatch ProcessHealthExport($payload)
  -> 200 (fast)
        |  (queue: database)
        v
  worker runs ProcessHealthExport
  -> iterate data.metrics
  -> route each by name -> SleepProcessor / HeartRateProcessor
  -> processor upserts typed rows (+ CSV mirror, dev-only, removed in #84)
  -> unknown metric names: log + skip
```

## Error handling

- **Auth fail** -> 401 (unchanged). **Malformed/empty JSON** -> 422 + log, no dispatch. **Valid but no recognised metrics** -> 200 (accept) and log "nothing to process" (HAE sends partial payloads).
- **The queue is the failure safety net.** The decoded payload rides in the `jobs` table until the job succeeds. A transient processor error retries from the queue with the payload intact; a permanent failure lands in `failed_jobs` with its full payload, which is the inspect-and-replay path. Successful ingests discard raw as chosen; failed ones are retained by the queue mechanism itself, giving debuggability exactly where it is needed.
- **Per-metric isolation.** Each processor call is wrapped so one bad metric cannot abort the others. Failures are collected; if any metric failed, the job throws at the end so the queue retries the whole payload. Idempotent upserts (sleep keyed on the night's date, HR on the activity + window) make redoing already-succeeded metrics safe. `tries=3` with a short backoff.
- **Concurrency.** The single worker serialises simultaneous midnight payloads; idempotency makes any overlap or retry harmless.

## Testing (high-value only)

- **Endpoint feature tests:** valid payload + token -> 200 and `Queue::fake` asserts `ProcessHealthExport` dispatched; no token -> 401; malformed JSON -> 422.
- **Job test:** a trimmed real HAE payload fixture (`tests/Fixtures`) with `sleep_analysis` + `heart_rate` metrics -> run job (inline under `sync`) -> assert `Sleep` rows created and a matching `Activity`'s HR filled.
- **Processor tests:** `SleepProcessor` upserts nights from segments (thin, `SleepAggregator` is already covered); `HeartRateProcessor` fills missing HR but does not clobber existing Strava HR.
- **Idempotency:** run the job twice on the same payload -> identical row counts.
- Skip: CSV-mirror internals, exact log strings, framework internals.

## Removed / changed in this PR

- **Deleted:** `health:inspect` (`InspectHealthExport`) and any test tied to it.
- **Controller:** `health/incoming` raw + summary writes gone; `summarise()` relocated to `HealthPayloadSummary`; `ping()` simplified.
- **Commands:** disk-reading removed; bare invocation prints guidance.
- **No migration** (sleep/HR tables already exist).

## Out of scope (their own issues)

- HAE trigger config, scheduling, rolling windows: #71 (sleep), #69 (HR).
- Historical HR backfill: #69.
- Vitals rings/steps processor: #67 (plugs a new processor into the finished router).
- Settings + today's rings: #65 / #68.
- CSV mirroring removal: #84 (processors keep the mirror for now).
