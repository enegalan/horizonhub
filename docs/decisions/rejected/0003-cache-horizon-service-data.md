# IDEA-0003: Cache data from Horizon services

- Status: rejected
- Date: 2026-03-20

## Proposal

Introduce caching (HTTP, application, or shared store) for responses and aggregates read from Horizon services so Horizon Hub reduces load and latency by reusing previously fetched payloads.

## Reason for rejection

- Stale cached snapshots diverge from live Horizon state and are hard for operators to reason about; misleading UI or metrics increase incident risk and debugging time.
- Horizon Hub prioritizes correctness and clarity of “what is true now” over marginal latency or upstream load savings from cached reads.
- Defining safe TTLs, invalidation, and per-entity cache keys across Horizon APIs adds complexity without a documented requirement that upstream cost or latency is the limiting factor.

## Reopen conditions

- Measured evidence shows Horizon or network latency is a hard bottleneck for agreed SLOs and fresh-enough data can be defined with an explicit maximum staleness budget.
- Horizon exposes supported cache semantics (for example ETag/If-None-Match, documented invalidation hooks, or contractually stable freshness rules) that make correctness testable.
- A compliance or operational policy explicitly allows bounded staleness for a named subset of reads.

## Alternatives kept

- Fetch from Horizon services on demand; optimize with connection pooling, pagination, and scoped queries rather than cross-request response caches.
- If performance work is required later, prefer pushing aggregation or indexing into Horizon or a dedicated downstream with clear consistency semantics instead of opaque caches in Horizon Hub.
