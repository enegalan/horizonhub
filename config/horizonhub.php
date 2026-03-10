<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Horizon HTTP API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for talking directly to each service's existing Horizon
    | HTTP API. The Hub will proxy retry actions through these endpoints.
    |
    | - api_base_path: the base path where Horizon's API is exposed on the
    |   service (for example, "/horizon/api").
    | - retry_path: the relative path for retrying a failed job; the "{id}"
    |   placeholder will be replaced with the job UUID.
    | - ping_path: the relative path used to test connectivity with the
    |   Horizon API for a given service.
    | - workload_path: the relative path used to read queue workload from
    |   the Horizon API.
    | - failed_jobs_path: the relative path to list failed jobs.
    | - completed_jobs_path: the relative path to list completed jobs.
    | - pending_jobs_path: the relative path to list pending/processing jobs.
    | - headers: optional headers to send to the Horizon API on each request
    |   (for example, authentication headers).
    |
    */
    'horizon' => [
        'api_base_path' => \env('HORIZON_HUB_HORIZON_API_BASE_PATH', '/horizon/api'),
        'dashboard_path' => \env('HORIZON_HUB_HORIZON_DASHBOARD_PATH', '/horizon'),
        'retry_path' => '/jobs/retry/{id}',
        'ping_path' => '/stats',
        'workload_path' => '/workload',
        'failed_jobs_path' => '/jobs/failed',
        'completed_jobs_path' => '/jobs/completed',
        'pending_jobs_path' => '/jobs/pending',
        'headers' => [],
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

];
