<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Horizon HTTP API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for talking directly to each service's existing Horizon
    | HTTP API.
    |
    | Base paths:
    | - dashboard: the base path where Horizon's dashboard is exposed.
    | - api: the base path where Horizon's API is exposed.
    |
    | Relative paths:
    | - retry: the relative path for retrying a failed job; the "{id}"
    |   placeholder will be replaced with the job UUID.
    | - ping: the relative path used to test connectivity with Horizon
    |   API for a given service.
    | - workload: the relative path used to read queue workload from
    |   Horizon API.
    | - failed_jobs: the relative path to list failed jobs.
    | - failed_job: the relative path to read a single failed job
    |   by UUID; the "{id}" placeholder will be replaced with the job UUID.
    | - completed_jobs: the relative path to list completed jobs.
    | - pending_jobs: the relative path to list pending/processing jobs.
    | - masters: the relative path to list Horizon masters.
    */
    'horizon_paths' => [
        'dashboard' => '/horizon',
        'api' => '/horizon/api',
        'retry' => '/jobs/retry/{id}',
        'ping' => '/stats',
        'workload' => '/workload',
        'failed_jobs' => '/jobs/failed',
        'failed_job' => '/jobs/failed/{id}',
        'completed_jobs' => '/jobs/completed',
        'pending_jobs' => '/jobs/pending',
        'masters' => '/masters',
        'metrics_queues' => '/metrics/queues',
    ],

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | The timeout in seconds for the Horizon HTTP API.
    |
    */
    'api_timeout' => (int) env('HORIZON_HUB_API_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Horizon HTTP connect timeout
    |--------------------------------------------------------------------------
    |
    | Optional connect timeout in seconds for outbound Horizon HTTP calls.
    | Null or zero disables an explicit connect timeout (Guzzle default applies).
    |
    */
    'horizon_http_connect_timeout' => \is_numeric(env('HORIZON_HUB_HTTP_CONNECT_TIMEOUT', 0))
        ? (float) env('HORIZON_HUB_HTTP_CONNECT_TIMEOUT', 0)
        : 0,

    /*
    |--------------------------------------------------------------------------
    | Horizon HTTP retry (GET only)
    |--------------------------------------------------------------------------
    |
    | Retries apply only to safe GET requests (API reads and dashboard bootstrap).
    | POST/DELETE (e.g. job retry) are not retried here; session flow still handles 419.
    |
    | times: total attempts (1 = no retry). sleep_ms: base backoff in milliseconds (exponential).
    | retry_on_status: HTTP statuses to retry after a response is received.
    |
    */
    'horizon_http_retry' => [
        'times' => (int) env('HORIZON_HUB_HTTP_RETRY_TIMES', 3),
        'sleep_ms' => (int) env('HORIZON_HUB_HTTP_RETRY_SLEEP_MS', 100),
        'retry_on_status' => [429, 502, 503, 504],
    ],

    /*
    |--------------------------------------------------------------------------
    | Stale Minutes
    |--------------------------------------------------------------------------
    |
    | After this many minutes without events, a service is considered stand-by
    | and a supervisor is considered stale. Supervisors older than
    | `dead_service_minutes` are removed; services become offline.
    |
    */
    'stale_minutes' => (int) env('HORIZON_HUB_STALE_MINUTES', 5),

    /*
    |--------------------------------------------------------------------------
    | Dead Service Minutes
    |--------------------------------------------------------------------------
    |
    | After this many minutes without any signal, supervisors are deleted and
    | services are marked offline (services are never deleted).
    |
    */
    'dead_service_minutes' => (int) env('HORIZON_HUB_DEAD_SERVICE_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Events Rate Limit
    |--------------------------------------------------------------------------
    |
    | This is the number of events per second that are allowed to be processed.
    |
    */
    'events_rate_limit' => (int) env('HORIZON_HUB_EVENTS_RATE_LIMIT', 2000),

    /*
    |--------------------------------------------------------------------------
    | Hot Reload Interval
    |--------------------------------------------------------------------------
    |
    | This is the number of seconds after which the dashboard will reload.
    |
    */
    'hot_reload_interval' => (int) env('HORIZON_HUB_HOT_RELOAD_INTERVAL', 5),

    /*
    |--------------------------------------------------------------------------
    | Stream Connections Rate Limit
    |--------------------------------------------------------------------------
    |
    | Maximum number of SSE stream connections per minute
    | per user or IP. Each new connection or reconnect counts.
    |
    */
    'stream_rate_limit' => (int) env('HORIZON_HUB_STREAM_RATE_LIMIT', 20),

    /*
    |--------------------------------------------------------------------------
    | Default Jobs Per Page
    |--------------------------------------------------------------------------
    |
    | Default page size for paginated job listings in the Horizon Hub UI.
    |
    */
    'jobs_per_page' => (int) env('HORIZON_HUB_JOBS_PER_PAGE', 20),

    /*
    |--------------------------------------------------------------------------
    | Job resolver cache TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | How long to cache the mapping job_uuid => service_id so that resolving
    | a job does not require iterating all services on every request, just for internal optimization.
    |
    */
    'job_resolver_cache_ttl' => (int) env('HORIZON_HUB_JOB_RESOLVER_CACHE_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Alerts configuration
    |--------------------------------------------------------------------------
    |
    | Configuration related to Horizon Hub alerts, including batching and
    | email interval defaults.
    |
    | pending_ttl_minutes: The TTL minutes for pending alert events to be batched into one notification, just for internal optimization.
    |
    */
    'alerts' => [
        'pending_ttl_minutes' => (int) env('HORIZON_HUB_ALERT_PENDING_TTL_MINUTES', 60),
    ],

];
