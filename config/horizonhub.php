<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Agent Configuration
    |--------------------------------------------------------------------------
    |
    | This is used to configure the agent endpoints.
    |
    */
    'agent' => [
        'retry_path' => '/horizon-hub/jobs/{id}/retry',
        'delete_path' => '/horizon-hub/jobs/{id}/delete',
        'pause_path' => '/horizon-hub/queues/{name}/pause',
        'resume_path' => '/horizon-hub/queues/{name}/resume',
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
    | Alert Email Interval Minutes
    |--------------------------------------------------------------------------
    |
    | This is the number of minutes after which an alert will be sent.
    |
    */
    'alert_email_interval_minutes' => (int) env('HORIZON_HUB_ALERT_EMAIL_INTERVAL_MINUTES', 5),
];
