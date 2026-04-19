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
    | - job: the relative path to read a single job
    | - failed_jobs: the relative path to list failed jobs.
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
        'job' => '/jobs/{id}',
        'failed_jobs' => '/jobs/failed',
        'completed_jobs' => '/jobs/completed',
        'pending_jobs' => '/jobs/pending',
        'masters' => '/masters',
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
    | Stale Service Minutes
    |--------------------------------------------------------------------------
    |
    | After this many minutes without events, a service is considered stand-by
    | and a supervisor is considered stale. Supervisors older than
    | `dead_service_minutes` are removed; services become offline.
    |
    */
    'stale_service_minutes' => (int) env('HORIZON_HUB_STALE_SERVICE_MINUTES', 5),

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
    | SSE stream tick interval
    |--------------------------------------------------------------------------
    |
    | Seconds to wait between emitting consecutive Server-Sent Events on the
    | refresh stream (sleep inside the long-lived SSE response).
    | This is not a full browser reload interval.
    |
    */
    'hot_reload_interval' => (int) env('HORIZON_HUB_HOT_RELOAD_INTERVAL', 0.5),

    /*
    |--------------------------------------------------------------------------
    | Horizon API: job list page size
    |--------------------------------------------------------------------------
    |
    | How many jobs each paginated response returns when Hub calls Horizon’s
    | completed / failed / pending list HTTP endpoints (maps to query `limit`).
    | Typical Horizon default is 50.
    |
    */
    'horizon_api_job_list_page_size' => (int) env('HORIZON_HUB_API_JOB_LIST_PAGE_SIZE', 50),

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
    | Max Horizon API pages (pagination loops)
    |--------------------------------------------------------------------------
    |
    | Upper bound for how many paginated HTTP responses Hub reads from a Horizon
    | jobs list endpoint per service (completed/failed/pending in the job UI,
    | and the scrolling fetch used for rolling 24h metrics). Each response is at
    | most ~50 jobs (Horizon default chunk size).
    |
    */
    'max_horizon_pages' => (int) env('HORIZON_HUB_MAX_HORIZON_PAGES', 40),

    /*
    |--------------------------------------------------------------------------
    | Alerts configuration
    |--------------------------------------------------------------------------
    |
    | Configuration related to Horizon Hub alerts, including batching and
    | email interval defaults.
    |
    | pending_ttl_minutes: Minutes to batch pending alert events before one notification.
    |
    | delivery_log_max_distinct_jobs: Max distinct job UUID rows shown in the alert
    | delivery log modal (not related to the jobs table page size).
    |
    */
    'alerts' => [
        'default_count' => (int) env('HORIZON_HUB_ALERT_DEFAULT_COUNT', 5),
        'default_seconds' => (int) env('HORIZON_HUB_ALERT_DEFAULT_SECONDS', 60),
        'default_minutes' => (int) env('HORIZON_HUB_ALERT_DEFAULT_MINUTES', 15),
        'pending_ttl_minutes' => (int) env('HORIZON_HUB_ALERT_PENDING_TTL_MINUTES', 60),
        'delivery_log_max_distinct_jobs' => (int) env('HORIZON_HUB_ALERT_DELIVERY_LOG_MAX_DISTINCT_JOBS', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Job Exception Preview Lines
    |--------------------------------------------------------------------------
    |
    | The number of lines to preview in the exception of a failed job.
    |
    */
    'failed_job_exception_preview_lines' => (int) env('HORIZON_HUB_FAILED_JOB_EXCEPTION_PREVIEW_LINES', 5),
];
