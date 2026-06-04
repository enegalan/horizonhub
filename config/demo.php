<?php

use App\Support\HorizonHub\Mock\MockDataset;

if (\strtolower(\trim((string) env('API_ENVIRONMENT', ''))) !== 'mock') {
    return [
        'catalog' => [],
        'horizon' => [],
        'job_service_index' => [],
    ];
}

return MockDataset::config();
