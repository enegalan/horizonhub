# ADR: Horizon API hot-reload path cache

- ID: ADR-0002
- Status: accepted
- Date: 2026-05-21

## Context

Horizon Hub refreshes dashboard and metrics views through Server-Sent Events on an interval configured in `horizonhub.hot_reload_interval`. A single user-visible refresh can trigger many identical Horizon HTTP GET calls to the same relative path (for example `/stats` or paginated job lists) because several calculators read the same endpoint independently.

IDEA-0003 rejected open-ended caching of Horizon service data because unbounded or opaque TTLs make freshness hard to reason about and increase incident risk.

## Decision

Cache successful Horizon HTTP GET responses in the application cache, keyed by service and relative API path (including query string), with a reasonable TTL equal to `horizonhub.hot_reload_interval` (seconds, fractional values allowed) measured from the last upstream request that populated the entry. High TTL values ​​are not welcome; real-time data is always preferred.

Within that window, repeated GETs to the same path for the same service return the cached payload without calling Horizon again. After the interval elapses, the next GET performs a live request, replaces the cache entry, and starts a new TTL window.

The cache applies only to:

- GET calls through `HorizonClientService`
- Successful responses
- Calls without dashboard-session bootstrap (`withDashboardSession = false`)
- Calls that do not bypass service enablement (`allowWhenDisabled = false`)

POST, DELETE, session-bootstrap flows, and failed responses are never cached.

## Rationale

- Maximum staleness is explicitly bounded by the same interval operators already use for UI refresh, so freshness expectations stay aligned with hot reload.
- Duplicate reads during one SSE tick (or overlapping ticks inside the interval) avoid redundant upstream load without introducing a separate TTL configuration.
- Per-path keys keep invalidation predictable: each path refreshes on its own last-request clock.
- This satisfies the bounded-staleness reopen condition documented in IDEA-0003 without reintroducing general-purpose Horizon response caching.

## Consequences

- Metrics and dashboard data may be up to `hot_reload_interval` seconds behind live Horizon state for a given path; that lag is intentional and configurable.
- Log output in debug mode distinguishes live calls from cache hits (`hot-reload path cache`).
- Tests must cover cache hit within the interval and refresh after expiry.
- Broader caching (cross-user, long TTL, or write operations) remains out of scope unless a new ADR is accepted.

## Reopen triggers

This decision can be revisited only if at least one condition is met:

- Product requires sub-interval freshness for specific endpoints while keeping cache for others.
- Hot reload is removed or its interval is no longer the authoritative freshness budget for the UI.
- Horizon provides first-class cache semantics (ETag, push invalidation) that replace application-level path caching.
- Operational incidents show the interval-bound cache still produces unacceptable drift for agreed SLOs.
