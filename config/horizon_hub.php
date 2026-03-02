<?php

return [
    'agent' => [
        'retry_path' => '/horizon-hub/jobs/{id}/retry',
        'delete_path' => '/horizon-hub/jobs/{id}/delete',
        'pause_path' => '/horizon-hub/queues/{name}/pause',
        'resume_path' => '/horizon-hub/queues/{name}/resume',
    ],

    'stale_minutes' => (int) env('HORIZON_HUB_STALE_MINUTES', 5),

    'events_rate_limit' => (int) env('HORIZON_HUB_EVENTS_RATE_LIMIT', 2000),

    'hot_reload_interval' => (int) env('HORIZON_HUB_HOT_RELOAD_INTERVAL', 5),

    'alert_email_interval_minutes' => (int) env('HORIZON_HUB_ALERT_EMAIL_INTERVAL_MINUTES', 5),
];
