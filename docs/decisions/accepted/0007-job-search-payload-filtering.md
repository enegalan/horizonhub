# ADR: Payload-level job search filtering with a bounded scan

- ID: ADR-0007
- Status: accepted
- Date: 2026-10-05

## Context

Job searches on the Jobs page and on a service detail page used to download every job page for each service and job status, map each raw Horizon payload into a list row (payload decoding plus timestamp parsing), and only then run the search predicate over the mapped rows. On large backlogs the discarded work dominated the request: the whole window was materialized, and rows that could never match were mapped and sorted anyway.

The obvious fix — push the filter into Horizon — was evaluated first. Horizon's job list endpoints (`/horizon/api/jobs/pending`, `/jobs/completed`, `/jobs/failed`) are thin wrappers over sorted sets and accept only:

- `starting_at` (an index cursor into the sorted set) and
- `tag` for the failed list.

`limit` is not honoured (the chunk size is fixed at 50 in `RedisJobRepository::getJobsByType()`), and there is no queue, job name or UUID predicate on those endpoints. A single job can still be read by UUID through `GET /horizon/api/jobs/{id}`, which is not a list filter either. So the search predicate cannot be pushed upstream with the current Horizon contract.

## Decision

1. **Match on the raw payload, as early as possible.** `App\Support\Jobs\JobSearchFilter` evaluates the search term against the raw Horizon payload (`queue`, `name`, `id`), and `JobsPaginator::fetchFiltered()` applies it to each job as soon as its page arrives. Only matching jobs are mapped into rows, sorted, and accumulated, so non-matching jobs cost one `stripos()` and no payload/timestamp work, and never inflate memory.
2. **Bound every list scan in jobs.** Hub reads at most `horizonhub.job_list_scan_cap` jobs per service and section (default 2000). Horizon's list chunk is treated as a fixed ~50 because many installs ignore `limit`. The same window applies to filtered and unfiltered lists (including the retry modal).
3. **Report truncated totals honestly.** When the scan window ends before the source is exhausted, `SearchResultsPaginator::resultsMayBeTruncated()` is set and the UI renders a trailing `+` with an explanation (`Showing 1–20 of 500+`).

## Rationale

- Filtering at the payload boundary is the earliest point available without an upstream predicate, and it keeps behaviour identical: the haystack (queue, name, UUID) and the `stripos()` semantics are unchanged.
- The scan window is bounded by `job_list_scan_cap` in jobs (not opaque page counts); the `+` marker makes the bound visible instead of silently misreporting it.
- The cap is per service and per job status, so aggregated totals stay proportional to the number of services being searched, and deep pagination keeps working within the collected window.

## Consequences

- Dense and selective searches share one budget: raise or lower `job_list_scan_cap` to trade completeness for upstream load.
- Reported totals are lower bounds when truncated; the UI marks them.
- Tests cover the scan-cap boundary (truncated vs exact totals) and large-search request counts (`tests/Unit/JobListServiceLargeSearchBenchmarkTest`).
