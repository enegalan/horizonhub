@include('horizon.partials.workload-rows-tbody', [
    'workloadRows' => $workloadRows ?? [],
    'emptyId' => 'dashboard-workload-empty',
    'rowIdPrefix' => 'wl-top',
    'emptyTitle' => 'No queue workload',
    'emptyDescription' => 'Queues will show here once work is pending across your services.',
])
