<?php

return [
    'hub_url' => env('HORIZON_HUB_URL', ''),
    'api_key' => env('HORIZON_HUB_API_KEY', ''),
    'service_name' => env('HORIZON_HUB_SERVICE_NAME', env('APP_NAME', 'default')),

    'events_path' => '/api/v1/events',

    'http' => [
        'retry_times' => 3,
        'retry_sleep_ms' => 500,
    ],
];
