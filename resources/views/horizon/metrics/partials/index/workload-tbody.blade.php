@include('horizon.partials.workload-rows-tbody', [
    'workloadRows' => $workloadRows ?? [],
    'emptyId' => 'metrics-workload-empty',
    'rowIdPrefix' => 'wl',
    'emptyTitle' => 'No queues yet',
    'emptyDescription' => 'Queues will appear here once jobs are dispatched to your services.',
])
