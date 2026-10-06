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
2. **Bound the scan of a search.** When a search term is active, the pagination loop for each service and job status stops as soon as `horizonhub.job_search_match_cap` matches (default 500) have been collected. The remaining pages of that window are not read.
3. **Report truncated totals honestly.** When the loop stopped early, `SearchResultsPaginator::resultsMayBeTruncated()` is set and the pagination component renders the total with a trailing `+` (`Showing 1–20 of 500+`).
4. **Never truncate actions.** The failed jobs batch retry modal ignores the cap and always reads the whole window, because `selection=all` must act on every matching job.

## Rationale

- Filtering at the payload boundary is the earliest point available without an upstream predicate, and it keeps behaviour identical: the haystack (queue, name, UUID) and the `stripos()` semantics are unchanged.
- The scan window was already bounded by `max_horizon_pages`, so a bounded search total is consistent with existing behaviour rather than a new class of approximation; the `+` marker makes the bound visible instead of silently misreporting it.
- The cap is per service and per job status, so aggregated totals stay proportional to the number of services being searched, and deep pagination keeps working within the collected window.
- Searches with no active term are untouched: no filter closure, no cap, same totals and same requests as before.

## Consequences

- A search denser than `1 / job_search_match_cap` reads fewer upstream pages; a very selective search still reads the whole window because the cap cannot be reached. `job_search_match_cap` set to `0` disables the bound entirely.
- Reported search totals are lower bounds when truncated; the UI marks them and operators can raise or lower the cap without a code change.
- Tests must cover the cap boundary (truncated vs exact totals), the retry-modal exemption, and large-search request counts (`tests/Unit/JobListServiceLargeSearchBenchmarkTest`).
